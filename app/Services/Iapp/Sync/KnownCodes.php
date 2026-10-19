<?php

namespace App\Services\Iapp\Sync;

use App\Models\IappEmployee;
use Illuminate\Support\Facades\DB;

/**
 * The biometric codes that belong to employees in this system.
 *
 * With "only existing employees" on (Biometric Settings), punches and device
 * list entries are kept only for these codes: temporary staff, leavers and
 * device-only entries on the machine never come in.
 *
 * A code counts when it is an employee's Employee ID, card ID or linked device
 * code, or a device code linked to an employee on Employee Mapping. A code set
 * aside as Ignored (for example because the machine's name for it is somebody
 * else) does not count, even when it equals an Employee ID.
 *
 * Codes are compared exactly, ignoring case and spaces: "2301694" and
 * "02301694" are different enrolments.
 */
class KnownCodes
{

    /** @return array<string, int> normalised code => user id */
    public static function map(?int $companyId = null): array
    {
        $codes = [];

        $rows = DB::table('employee_details')
            ->when($companyId, fn ($q) => $q->where('company_id', $companyId))
            ->get(['user_id', 'employee_id', 'employee_card_id', 'iapp_emp_code']);

        foreach ($rows as $row) {
            foreach ([$row->employee_id, $row->employee_card_id, $row->iapp_emp_code] as $code) {
                if (($key = self::key($code)) !== '') {
                    $codes[$key] = (int) $row->user_id;
                }
            }
        }

        $register = IappEmployee::withoutGlobalScopes()
            ->when($companyId, fn ($q) => $q->where('company_id', $companyId))
            ->get(['emp_code', 'user_id', 'map_status']);

        foreach ($register as $entry) {
            $key = self::key($entry->emp_code);

            if ($entry->user_id) {
                $codes[$key] = (int) $entry->user_id;
            } elseif ($entry->map_status === IappEmployee::STATUS_IGNORED) {
                unset($codes[$key]);
            }
        }

        return $codes;
    }

    public static function key($code): string
    {
        return strtolower(preg_replace('/\s+/', '', (string) $code));
    }

}
