<?php

namespace App\Services\Iapp\Sync;

use App\Models\IappEmployee;
use App\Services\Iapp\IappConfig;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * The biometric machine's own employee list ("Report Listing For: Employee",
 * exported from the biometric software) as the device register.
 *
 * The API's employee list is not open to this account, so without this the
 * register only knows the codes that have punched. With it, every enrolled
 * person is compared with the employees here, whether they have punched or not.
 *
 * Only the chosen locations are read, and (with "only existing employees") only
 * codes that belong to an employee here. A code whose name on the machine is
 * plainly somebody else than the employee holding that code is set aside as
 * Ignored, with a note, instead of being linked: its punches would otherwise be
 * paid to the wrong person.
 */
class MachineListImport
{

    public function __construct(protected ?IappConfig $config = null)
    {
        $this->config ??= app(IappConfig::class);
    }

    /**
     * @param  array<int, string>  $locations  empty = every location
     * @return array<string, mixed>
     */
    public function load(string $path, array $locations, bool $onlyKnown, ?int $actorId = null): array
    {
        $rows = $this->read($path);

        if ($rows === null) {
            return ['ok' => false, 'message' => 'This is not the biometric Employee report: no sheet has the Code, Name and Location columns.'];
        }

        $companyId = $this->config->companyId();
        $wanted = array_values(array_filter(array_map(fn ($l) => self::locKey($l), $locations)));
        $erp = DB::table('employee_details as ed')->join('users as u', 'u.id', '=', 'ed.user_id')
            ->when($companyId, fn ($q) => $q->where('u.company_id', $companyId))
            ->get(['u.id', 'u.name', 'ed.employee_id', 'ed.employee_card_id', 'ed.iapp_emp_code']);
        $byCode = [];

        foreach ($erp as $e) {
            foreach ([$e->employee_id, $e->employee_card_id, $e->iapp_emp_code] as $code) {
                if (($k = KnownCodes::key($code)) !== '') {
                    $byCode[$k] = $e;
                }
            }
        }

        $stats = ['read' => count($rows), 'other_location' => 0, 'not_employee' => 0, 'loaded' => 0, 'name_conflicts' => [], 'kept_manual' => 0];
        $now = now();

        foreach ($rows as $row) {
            if ($wanted && !$this->inLocations($row['location'], $wanted)) {
                $stats['other_location']++;
                continue;
            }

            $owner = $byCode[KnownCodes::key($row['code'])] ?? null;

            if (!$owner && $onlyKnown) {
                $stats['not_employee']++;
                continue;
            }

            $existing = IappEmployee::withoutGlobalScopes()->where('company_id', $companyId)->where('emp_code', $row['code'])->first();

            // A link or decision made by hand on Employee Mapping is never undone here.
            if ($existing && $existing->map_method === IappEmployee::METHOD_MANUAL) {
                $stats['kept_manual']++;
                continue;
            }

            $values = [
                'employee_name' => $row['name'],
                'card_id' => $row['card'] !== '' ? $row['card'] : ($existing->card_id ?? null),
                'location_name' => $row['location'],
                'department' => $row['department'] ?: ($existing->department ?? null),
                'is_active' => 1,
                'synced_at' => $now,
                'updated_at' => $now,
            ];

            $conflict = $owner && !self::sameName($owner->name, $row['name']);

            if ($conflict) {
                $values += [
                    'user_id' => null,
                    'map_status' => IappEmployee::STATUS_IGNORED,
                    'map_note' => "On the machine {$row['code']} is '{$row['name']}', but here {$row['code']} is {$owner->name}. "
                        . 'Not linked, and its punches are not taken. Correct the Employee ID (or the machine), then link it on this page.',
                ];
                $stats['name_conflicts'][] = "{$row['code']}: machine '{$row['name']}', here {$owner->name}";

                if ($existing && $existing->user_id) {
                    app(EmployeeSync::class)->unlinkFromEmployee($existing, $actorId);
                }
            }

            if ($existing) {
                IappEmployee::withoutGlobalScopes()->where('id', $existing->id)->update($values);
            } else {
                IappEmployee::withoutGlobalScopes()->insert($values + [
                    'company_id' => $companyId,
                    'emp_code' => $row['code'],
                    'source' => IappEmployee::SOURCE_LIST,
                    'map_status' => $values['map_status'] ?? IappEmployee::STATUS_UNMAPPED,
                    'punch_count' => 0,
                    'created_at' => $now,
                ]);
            }

            $stats['loaded']++;
        }

        // Link everything loaded to the employee whose ID it is.
        $stats['sync'] = (new MappingSync($this->config))->run($actorId);
        $stats['ok'] = true;

        return $stats;
    }

    /**
     * The report's rows: the header row is found by its Code / Name / Location columns.
     *
     * @return array<int, array{code: string, name: string, card: string, location: string, department: string}>|null
     */
    public function read(string $path): ?array
    {
        foreach (IOFactory::load($path)->getWorksheetIterator() as $sheet) {
            $rows = $sheet->toArray(null, true, true, false);

            foreach (array_slice($rows, 0, 10, true) as $i => $header) {
                $h = array_map(fn ($v) => strtolower(trim((string) $v)), $header);
                $col = array_flip($h);

                if (!isset($col['code'], $col['name'], $col['location'])) {
                    continue;
                }

                $out = [];

                foreach (array_slice($rows, $i + 1) as $r) {
                    $code = trim((string) ($r[$col['code']] ?? ''));

                    if ($code === '') {
                        continue;
                    }

                    $out[] = [
                        'code' => $code,
                        'name' => trim((string) ($r[$col['name']] ?? '')),
                        'card' => trim((string) ($r[$col['card id']] ?? '')),
                        'location' => trim((string) ($r[$col['location']] ?? '')),
                        'department' => trim((string) ($r[$col['department']] ?? '')),
                    ];
                }

                return $out;
            }
        }

        return null;
    }

    protected function inLocations(string $location, array $wanted): bool
    {
        $key = self::locKey($location);

        foreach ($wanted as $w) {
            if ($key === $w || str_contains($key, $w)) {
                return true;
            }
        }

        return false;
    }

    public static function locKey(?string $location): string
    {
        return strtolower(preg_replace('/[^a-z0-9]/i', '', (string) $location));
    }

    /**
     * Do two spellings name the same person? Lenient: one shared word (or a
     * near spelling of one), or one name written together inside the other, is
     * enough. Only names with nothing in common are a conflict.
     */
    public static function sameName(string $a, string $b): bool
    {
        $words = function ($s) {
            $s = preg_replace(['/\(.*?\)/', '/[^a-z]/i'], [' ', ' '], strtolower($s));

            return array_values(array_filter(explode(' ', $s), fn ($w) => strlen($w) >= 3));
        };
        $wa = $words($a);
        $wb = $words($b);

        if (!$wa || !$wb) {
            return true;   // nothing to compare: not evidence of another person
        }

        foreach ($wa as $x) {
            foreach ($wb as $y) {
                if ($x === $y || (strlen($x) >= 4 && strlen($y) >= 4 && (levenshtein($x, $y) <= 1 || str_starts_with($x, $y) || str_starts_with($y, $x)))) {
                    return true;
                }
            }
        }

        $ja = implode('', $wa);
        $jb = implode('', $wb);

        return (strlen($jb) >= 6 && str_contains($ja, $jb)) || (strlen($ja) >= 6 && str_contains($jb, $ja));
    }

}
