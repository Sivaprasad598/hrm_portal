<?php

namespace App\Services\Iapp\Sync;

use App\Models\IappEmployee;
use App\Services\Access\SystemAccounts;
use App\Services\Iapp\IappConfig;
use Illuminate\Support\Facades\DB;

/**
 * Linking people to their device codes before any punch of theirs has been pulled.
 *
 * The register only learns a code when it punches (iApp offers no list of the
 * people enrolled), so someone on leave, new, or simply not in the pulled dates
 * shows "Not linked". Two ways to link them anyway:
 *
 *  - byEmployeeId(): every active employee not linked yet gets the code equal to
 *    their Employee ID (as the devices are enrolled). Their punches already match
 *    that way; this makes it a link, shown as Linked, and kept.
 *  - typed(): one person, a code HR reads off the device.
 *
 * Never over a code that is linked to someone else, set aside (Ignored), or held
 * in an open conflict; never two employees on one Employee ID.
 */
class Prelink
{

    public function __construct(protected ?IappConfig $config = null)
    {
        $this->config ??= new IappConfig();
    }

    /**
     * Who would be linked by Employee ID, and who not and why.
     *
     * @return array{link: array<int, array>, skip: array<int, array>}
     */
    public function plan(?array $onlyUserIds = null): array
    {
        $companyId = $this->config->companyId();

        $employees = DB::table('users as u')->join('employee_details as ed', 'ed.user_id', '=', 'u.id')
            ->when($companyId, fn ($q) => $q->where('u.company_id', $companyId))
            ->when($onlyUserIds !== null, fn ($q) => $q->whereIn('u.id', $onlyUserIds ?: [0]))
            ->where('u.status', 'active')
            ->whereNotIn('u.id', SystemAccounts::notCounted())
            ->get(['u.id', 'u.name', 'ed.employee_id', 'ed.iapp_emp_code']);

        // An Employee ID shared by two employees settles nothing.
        $idCount = $employees->filter(fn ($e) => trim((string) $e->employee_id) !== '')
            ->countBy(fn ($e) => strtolower(trim($e->employee_id)));

        $register = IappEmployee::withoutGlobalScopes()->when($companyId, fn ($q) => $q->where('company_id', $companyId))
            ->get(['emp_code', 'user_id', 'map_status', 'employee_name'])
            ->keyBy(fn ($r) => strtolower(trim((string) $r->emp_code)));
        $held = DB::table('employee_details')->whereNotNull('iapp_emp_code')->where('iapp_emp_code', '!=', '')
            ->pluck('user_id', 'iapp_emp_code')->mapWithKeys(fn ($u, $c) => [strtolower(trim($c)) => $u]);
        $conflicts = array_change_key_case(MappingSync::openConflicts());
        $names = $employees->pluck('name', 'id');

        $plan = ['link' => [], 'skip' => []];

        foreach ($employees as $e) {
            if (trim((string) $e->iapp_emp_code) !== '') {
                continue;   // linked already
            }

            $code = trim((string) $e->employee_id);
            $key = strtolower($code);
            $row = ['user_id' => (int) $e->id, 'name' => $e->name, 'code' => $code];

            $reason = match (true) {
                $code === '' => 'No Employee ID',
                $idCount[$key] > 1 => 'Employee ID ' . $code . ' is also another employee\'s',
                isset($held[$key]) => 'Code ' . $code . ' is linked to ' . ($names[$held[$key]] ?? 'someone else'),
                ($register[$key]->map_status ?? null) === IappEmployee::STATUS_IGNORED => 'Code ' . $code . ' was set aside (Ignored)'
                    . (($register[$key]->employee_name ?? '') !== '' ? ': on the device it is "' . $register[$key]->employee_name . '"' : ''),
                isset($conflicts[$key]) => 'Code ' . $code . ' has an open conflict (Exceptions)',
                default => null,
            };

            $reason ? $plan['skip'][] = $row + ['reason' => $reason] : $plan['link'][] = $row;
        }

        return $plan;
    }

    /** Link everyone the plan can link. @return array{linked:int, failed:array<int,string>, skipped:int} */
    public function byEmployeeId(?int $actorId, ?array $onlyUserIds = null): array
    {
        $plan = $this->plan($onlyUserIds);
        $result = ['linked' => 0, 'failed' => [], 'skipped' => count($plan['skip'])];

        foreach ($plan['link'] as $p) {
            $link = $this->link($p['code'], $p['user_id'], $p['name'], IappEmployee::SOURCE_EMPLOYEE_ID, IappEmployee::METHOD_AUTO, 'employee_id', $actorId);
            $link['ok'] ? $result['linked']++ : $result['failed'][] = $p['name'] . ': ' . $link['message'];
        }

        if ($result['linked']) {
            (new EmployeeSync($this->config))->applyMappings($this->config->companyId());
        }

        return $result;
    }

    /** One person, a code typed by HR (read off the device). */
    public function typed(int $userId, string $code, ?int $actorId): array
    {
        $code = trim($code);

        if ($code === '' || mb_strlen($code) > 50) {
            return ['ok' => false, 'message' => 'Type the device code (as on the device, up to 50 characters).'];
        }

        $row = IappEmployee::withoutGlobalScopes()->where('company_id', $this->config->companyId())->where('emp_code', $code)->first();

        if ($row && $row->map_status === IappEmployee::STATUS_IGNORED) {
            return ['ok' => false, 'message' => "Code {$code} was set aside (Ignored)"
                . ($row->employee_name ? " - on the device it is \"{$row->employee_name}\"" : '') . '. Check the code on the device.'];
        }

        $name = (string) DB::table('users')->where('id', $userId)->value('name');
        $link = $this->link($code, $userId, $name, IappEmployee::SOURCE_TYPED, IappEmployee::METHOD_MANUAL, 'typed', $actorId);

        if ($link['ok']) {
            (new EmployeeSync($this->config))->applyMappings($this->config->companyId());
        }

        return $link;
    }

    /** The code on the register (added when it has not punched yet), then linked both ways. */
    protected function link(string $code, int $userId, string $name, string $source, string $method, string $on, ?int $actorId): array
    {
        $companyId = $this->config->companyId();

        return DB::transaction(function () use ($code, $userId, $name, $source, $method, $on, $actorId, $companyId) {
            $device = IappEmployee::withoutGlobalScopes()->where('company_id', $companyId)->where('emp_code', $code)->lockForUpdate()->first();

            if ($device && $device->user_id && (int) $device->user_id !== $userId) {
                $other = DB::table('users')->where('id', $device->user_id)->value('name');

                return ['ok' => false, 'message' => "Code {$code} is already linked to {$other}."];
            }

            if (!$device) {
                $device = new IappEmployee();
                $device->forceFill([
                    'company_id' => $companyId, 'emp_code' => $code, 'employee_name' => $name, 'source' => $source,
                    'map_status' => IappEmployee::STATUS_UNMAPPED, 'punch_count' => 0, 'synced_at' => now(),
                ])->save();
            }

            return (new EmployeeSync($this->config))->linkToEmployee($device, $userId, $method, $on, $actorId);
        });
    }

}
