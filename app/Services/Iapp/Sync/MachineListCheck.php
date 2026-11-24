<?php

namespace App\Services\Iapp\Sync;

use App\Models\IappEmployee;
use App\Services\Access\SystemAccounts;
use App\Services\Iapp\IappConfig;
use Illuminate\Support\Facades\DB;

/**
 * The cross-check of the biometric machine's own employee list (Employee.xlsx)
 * against the employees here, before anything is linked.
 *
 * Read-only: it sorts every entry and every active employee into one of these,
 * and Employee Mapping shows them in a side panel for HR to look over first.
 *
 *   link          code = an Employee ID here and the names agree (also written
 *                 differently: initials, word order, a letter off) -> linked on Apply
 *   conflict      code = an Employee ID here, but the machine names somebody
 *                 else -> set aside, never linked; the ID or the machine is wrong
 *   suggest       no code match, but the full name matches one employee and
 *                 only one -> linked on Apply only when ticked
 *   missing       active employees found neither by code nor by name -> to be
 *                 enrolled on the machine
 *   device_only   machine entries that are nobody here (people who left, or not
 *                 on the payroll), and shared cards (temp, guest, security...)
 *
 * The rule is the one agreed for linking: code and name together. A code alone
 * can be a mistyped ID, and a name alone is often another person with the same
 * first names, so neither links on its own.
 */
class MachineListCheck
{

    /** Shared cards and doors rather than a person. */
    protected const SHARED = '/\b(temp|temporary|guest|visitor|visitors|security|test|reception)\b|^(temp|guest|security|visitor)/i';

    public function __construct(protected ?IappConfig $config = null)
    {
        $this->config ??= app(IappConfig::class);
    }

    /**
     * @param  array<int, array{code:string,name:string,card:string,location:string,department:string}>  $rows
     * @param  array<int, string>  $locations  empty = every location
     */
    public function check(array $rows, array $locations): array
    {
        $companyId = $this->config->companyId();
        $wanted = array_values(array_filter(array_map(fn ($l) => MachineListImport::locKey($l), $locations)));

        $employees = DB::table('users as u')->join('employee_details as ed', 'ed.user_id', '=', 'u.id')
            ->leftJoin('teams as t', 't.id', '=', 'ed.department_id')
            ->when($companyId, fn ($q) => $q->where('u.company_id', $companyId))
            ->where('u.status', 'active')
            ->whereNotIn('u.id', SystemAccounts::notCounted())   // never the super admin or test accounts
            ->orderBy('u.name')
            ->get(['u.id', 'u.name', 'ed.employee_id', 'ed.employee_card_id', 'ed.iapp_emp_code', 't.team_name as division']);

        // A code is first somebody's Employee ID; only then a card number or a code
        // linked earlier (so a code wrongly linked to another person still names its owner).
        $byCode = [];

        foreach (['employee_id', 'employee_card_id', 'iapp_emp_code'] as $field) {
            foreach ($employees as $e) {
                if (($k = KnownCodes::key($e->{$field})) !== '') {
                    $byCode[$k] ??= $e;
                }
            }
        }

        // Codes set aside earlier by hand (Ignored) stay out of the suggestions.
        $ignored = IappEmployee::withoutGlobalScopes()->when($companyId, fn ($q) => $q->where('company_id', $companyId))
            ->where('map_status', IappEmployee::STATUS_IGNORED)->where('map_method', IappEmployee::METHOD_MANUAL)
            ->pluck('emp_code')->map(fn ($c) => KnownCodes::key($c))->flip();

        $out = ['link' => [], 'conflict' => [], 'suggest' => [], 'missing' => [], 'device_only' => [], 'shared' => []];
        $otherLocation = 0;
        $found = [];       // employee id => true (found on the machine by code)
        $loose = [];       // machine rows that are no employee's code

        foreach ($rows as $row) {
            if ($wanted && !$this->inLocations($row['location'], $wanted)) {
                $otherLocation++;
                continue;
            }

            $owner = $byCode[KnownCodes::key($row['code'])] ?? null;

            if (!$owner) {
                $loose[] = $row;
                continue;
            }

            $found[$owner->id] = true;
            $entry = $this->entry($row, $owner);

            if (MachineListImport::sameName($owner->name, $row['name'])) {
                $entry['already'] = KnownCodes::key($owner->iapp_emp_code) === KnownCodes::key($row['code']);
                $out['link'][] = $entry;
            } else {
                $out['conflict'][] = $entry;
            }
        }

        // Name suggestions: employees not found by code and not linked yet, against the
        // machine entries that are nobody's code. Kept only when the match is one-to-one.
        $open = $employees->filter(fn ($e) => !isset($found[$e->id]) && KnownCodes::key($e->iapp_emp_code) === '')->values();
        $pairs = [];
        $perRow = [];
        $perEmployee = [];

        foreach ($loose as $i => $row) {
            if ($this->isShared($row) || isset($ignored[KnownCodes::key($row['code'])])) {
                continue;
            }

            foreach ($open as $e) {
                if (self::fullNameMatch($e->name, $row['name'])) {
                    $pairs[] = [$i, $e];
                    $perRow[$i] = ($perRow[$i] ?? 0) + 1;
                    $perEmployee[$e->id] = ($perEmployee[$e->id] ?? 0) + 1;
                }
            }
        }

        $suggested = [];

        foreach ($pairs as [$i, $e]) {
            if ($perRow[$i] === 1 && $perEmployee[$e->id] === 1) {
                $out['suggest'][] = $this->entry($loose[$i], $e);
                $suggested[$i] = true;
                $found[$e->id] = true;
            }
        }

        foreach ($loose as $i => $row) {
            if (isset($suggested[$i])) {
                continue;
            }

            $out[$this->isShared($row) ? 'shared' : 'device_only'][] = [
                'code' => $row['code'], 'device_name' => $row['name'], 'location' => $row['location'], 'department' => $row['department'],
            ];
        }

        // Not on the machine: neither found by code nor suggested by name. Someone
        // already linked through their punches is enrolled, so not listed.
        foreach ($employees as $e) {
            if (!isset($found[$e->id]) && KnownCodes::key($e->iapp_emp_code) === '') {
                $out['missing'][] = ['user_id' => $e->id, 'name' => $e->name, 'employee_id' => $e->employee_id, 'division' => $e->division];
            }
        }

        $linkedElsewhere = $employees->filter(fn ($e) => !isset($found[$e->id]) && KnownCodes::key($e->iapp_emp_code) !== '')->count();

        return $out + [
            'counts' => [
                'read' => count($rows),
                'other_location' => $otherLocation,
                'link' => count($out['link']),
                'link_new' => count(array_filter($out['link'], fn ($r) => !$r['already'])),
                'conflict' => count($out['conflict']),
                'suggest' => count($out['suggest']),
                'missing' => count($out['missing']),
                'device_only' => count($out['device_only']),
                'shared' => count($out['shared']),
                'employees' => $employees->count(),
                'linked_elsewhere' => $linkedElsewhere,
            ],
        ];
    }

    /**
     * The same person by the full name? Stricter than MachineListImport::sameName
     * (which only checks a code's two names are not plainly different people):
     * here the name is all there is, so every word of the shorter name has to be
     * in the longer one - a full word, a near spelling, or its initial - and at
     * least two of them as words. "T SAI MANOJ" = "SAI MANOJ TIRUMANI";
     * "Tangeti Durga Prasad" is not "Lanka Durga Prasad".
     */
    public static function fullNameMatch(string $a, string $b): bool
    {
        $words = function ($s) {
            $s = preg_replace(['/\(.*?\)/', '/[^a-z]/i'], [' ', ' '], strtolower($s));

            return array_values(array_filter(explode(' ', $s), fn ($w) => $w !== ''));
        };
        $wa = $words($a);
        $wb = $words($b);

        if (count($wa) > count($wb)) {
            [$wa, $wb] = [$wb, $wa];
        }

        if (count(array_filter($wa, fn ($w) => strlen($w) >= 3)) < 2) {
            return false;
        }

        $full = 0;

        foreach ($wa as $x) {
            $hit = null;

            foreach ($wb as $j => $y) {
                $same = $x === $y
                    || (strlen($x) >= 4 && strlen($y) >= 4 && levenshtein($x, $y) <= 1)
                    || (strlen($x) <= 2 && str_starts_with($y, $x))
                    || (strlen($y) <= 2 && str_starts_with($x, $y));

                if ($same) {
                    $hit = $j;
                    break;
                }
            }

            if ($hit === null) {
                return false;
            }

            if (strlen($x) >= 3 && strlen($wb[$hit]) >= 3) {
                $full++;
            }

            unset($wb[$hit]);
        }

        return $full >= 2;
    }

    protected function entry(array $row, object $employee): array
    {
        return [
            'code' => $row['code'], 'device_name' => $row['name'], 'location' => $row['location'],
            'user_id' => (int) $employee->id, 'name' => $employee->name, 'employee_id' => $employee->employee_id,
            'division' => $employee->division, 'already' => false,
        ];
    }

    protected function isShared(array $row): bool
    {
        return (bool) preg_match(self::SHARED, $row['name'] . ' ' . $row['code']) || stripos($row['code'], 'gis') !== false;
    }

    protected function inLocations(string $location, array $wanted): bool
    {
        $key = MachineListImport::locKey($location);

        foreach ($wanted as $w) {
            if ($key === $w || str_contains($key, $w)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Apply the ticked name suggestions (after MachineListImport::load has linked
     * the code matches): each code is put on the register and linked by hand.
     *
     * @param  array<int, string>  $picks  "code|user_id"
     * @return array{linked:int, failed:array<int,string>}
     */
    public function linkSuggestions(array $rows, array $locations, array $picks, ?int $actorId): array
    {
        $allowed = [];

        foreach ($this->check($rows, $locations)['suggest'] as $s) {
            $allowed[KnownCodes::key($s['code']) . '|' . $s['user_id']] = $s;
        }

        $companyId = $this->config->companyId();
        $sync = new EmployeeSync($this->config);
        $result = ['linked' => 0, 'failed' => []];

        foreach (array_unique($picks) as $pick) {
            [$code, $userId] = array_pad(explode('|', (string) $pick, 2), 2, '');
            $s = $allowed[KnownCodes::key($code) . '|' . (int) $userId] ?? null;

            if (!$s) {
                continue;   // no longer a suggestion (linked meanwhile, or the file changed)
            }

            $device = IappEmployee::withoutGlobalScopes()->where('company_id', $companyId)->where('emp_code', $s['code'])->first();

            if (!$device) {
                $device = new IappEmployee();
                $device->forceFill([
                    'company_id' => $companyId, 'emp_code' => $s['code'], 'employee_name' => $s['device_name'],
                    'location_name' => $s['location'], 'source' => IappEmployee::SOURCE_LIST,
                    'map_status' => IappEmployee::STATUS_UNMAPPED, 'punch_count' => 0, 'is_active' => 1, 'synced_at' => now(),
                ])->save();
            }

            $link = $sync->linkToEmployee($device, (int) $s['user_id'], IappEmployee::METHOD_MANUAL, 'name', $actorId);
            $link['ok'] ? $result['linked']++ : $result['failed'][] = $s['code'] . ': ' . $link['message'];
        }

        if ($result['linked']) {
            $sync->applyMappings($companyId);
        }

        return $result;
    }

}
