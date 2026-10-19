<?php

namespace App\Services\Iapp\Sync;

use App\Models\BiometricException;
use App\Models\IappEmployee;
use App\Services\Iapp\IappConfig;
use Illuminate\Support\Facades\DB;

/**
 * "Sync all employees" on Employee Mapping: every device code is linked to the
 * employee whose Employee ID it is. The Employee ID decides; names are not
 * compared (the devices were re-enrolled onto Employee IDs on 17 Oct 2026).
 *
 * An employee linked to some other code moves to the code that is their
 * Employee ID, and a code linked to somebody else moves to the employee whose
 * ID it is. A code that is nobody's Employee ID is left as it is: a manual link
 * stays, and an unlinked one is listed on Employee Comparison.
 *
 * The only exceptions are the cases the ID cannot settle: two employees with
 * the same Employee ID.
 */
class MappingSync
{

    public const TYPE = 'mapping_conflict';

    public function __construct(protected ?IappConfig $config = null)
    {
        $this->config ??= new IappConfig();
    }

    /**
     * @return array{linked:int, moved:int, conflicts:int, device_only:int, already:int, ignored:int, employees_without_device:int, punches:int}
     */
    public function run(?int $actorId = null): array
    {
        $companyId = $this->config->companyId();
        $employeeSync = new EmployeeSync($this->config);

        // The register of device codes, refreshed from the punches.
        $employeeSync->sync(false, $actorId);

        $erp = DB::table('employee_details as ed')->join('users as u', 'u.id', '=', 'ed.user_id')
            ->when($companyId, fn ($q) => $q->where('u.company_id', $companyId))
            ->whereNotNull('ed.employee_id')->where('ed.employee_id', '<>', '')
            ->get(['u.id', 'u.name', 'ed.employee_id']);

        // Employee ID → employees, case-insensitive.
        $byId = [];

        foreach ($erp as $e) {
            $byId[strtolower(trim((string) $e->employee_id))][] = $e;
        }

        $result = ['linked' => 0, 'moved' => 0, 'conflicts' => 0, 'device_only' => 0, 'already' => 0, 'ignored' => 0,
            'employees_without_device' => 0, 'punches' => 0];
        $seen = [];

        $devices = IappEmployee::withoutGlobalScopes()->when($companyId, fn ($q) => $q->where('company_id', $companyId))->get();

        foreach ($devices as $device) {
            if ($device->map_status === IappEmployee::STATUS_IGNORED) {
                $result['ignored']++;
                continue;
            }

            $code = trim((string) $device->emp_code);
            $owners = $byId[strtolower($code)] ?? [];

            if ($owners === []) {
                // Nobody's Employee ID: a manual link stays; otherwise device-only.
                $device->user_id ? $result['already']++ : $result['device_only']++;
                continue;
            }

            if (count($owners) > 1) {
                $this->conflict($device, $seen, $result, sprintf('Device code %s: %d employees have this Employee ID (%s). Correct the Employee IDs, then sync again.',
                    $code, count($owners), implode(', ', array_map(fn ($o) => $o->name, $owners))));
                continue;
            }

            $owner = $owners[0];

            if ((int) $device->user_id === (int) $owner->id) {
                $result['already']++;
                continue;
            }

            // Final rule: the code goes to the employee whose ID it is. Whatever
            // either side was linked to before is released first.
            $moved = false;

            if ($device->user_id) {
                $employeeSync->unlinkFromEmployee($device, $actorId);
                $device->refresh();
                $moved = true;
            }

            $previous = DB::table('employee_details')->where('user_id', $owner->id)->value('iapp_emp_code');

            if ($previous !== null && $previous !== '' && (string) $previous !== $code) {
                if ($old = IappEmployee::withoutGlobalScopes()->where('emp_code', $previous)->first()) {
                    $employeeSync->unlinkFromEmployee($old, $actorId);
                } else {
                    DB::table('employee_details')->where('user_id', $owner->id)->update(['iapp_emp_code' => null, 'iapp_linked_at' => null]);
                }

                $moved = true;
            }

            $link = $employeeSync->linkToEmployee($device, (int) $owner->id, IappEmployee::METHOD_AUTO, 'employee_id', $actorId);

            if ($link['ok']) {
                $result[$moved ? 'moved' : 'linked']++;
            } else {
                $this->conflict($device, $seen, $result, sprintf('Device code %s could not be linked to %s: %s', $code, $owner->name, $link['message']));
            }
        }

        $applied = $employeeSync->applyMappings($companyId);

        // Conflicts that are gone close by themselves.
        BiometricException::where('type', self::TYPE)->where('status', BiometricException::OPEN)
            ->whereNotIn('fingerprint', $seen ?: ['-'])
            ->get()
            ->each(fn ($e) => $e->forceFill(['status' => BiometricException::RESOLVED, 'resolution' => 'auto', 'resolved_at' => now(),
                'note' => 'No longer a conflict at the last Sync all employees.'])->save());

        $result['employees_without_device'] = DB::table('employee_details as ed')->join('users as u', 'u.id', '=', 'ed.user_id')
            ->where('u.status', 'active')->where(fn ($q) => $q->whereNull('ed.iapp_emp_code')->orWhere('ed.iapp_emp_code', ''))
            ->when($companyId, fn ($q) => $q->where('u.company_id', $companyId))->count();
        $result['punches'] = $applied['punches'] ?? 0;

        return $result;
    }

    /** Codes with an open conflict: code => what is wrong. */
    public static function openConflicts(): array
    {
        return BiometricException::where('type', self::TYPE)->where('status', BiometricException::OPEN)
            ->pluck('title', 'emp_code')->all();
    }

    protected function conflict(IappEmployee $device, array &$seen, array &$result, string $title): void
    {
        $fingerprint = self::TYPE . '|' . $device->emp_code;
        $seen[] = $fingerprint;
        $result['conflicts']++;

        $values = ['company_id' => $this->config->companyId(), 'date' => now()->toDateString(), 'category' => BiometricException::SYNC,
            'type' => self::TYPE, 'emp_code' => (string) $device->emp_code, 'title' => mb_substr($title, 0, 255),
            'details' => ['device_name' => $device->employee_name], 'last_seen_at' => now()];

        $existing = BiometricException::where('fingerprint', $fingerprint)->first();

        if (!$existing) {
            BiometricException::create($values + ['fingerprint' => $fingerprint, 'status' => BiometricException::OPEN, 'first_seen_at' => now()]);

            return;
        }

        $existing->forceFill($values + ($existing->status === BiometricException::IGNORED ? []
            : ['status' => BiometricException::OPEN, 'resolution' => null, 'resolved_at' => null, 'resolved_by' => null]))->save();
    }

}
