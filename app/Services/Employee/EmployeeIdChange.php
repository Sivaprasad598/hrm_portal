<?php

namespace App\Services\Employee;

use Illuminate\Support\Facades\DB;

/**
 * When an employee's Employee ID changes (employee import, or the employee's edit form), everywhere
 * that holds the ID as text:
 *
 *  - HR records follow the new ID: employment history (employment_periods: code and card number)
 *    and payslips (payslips.emp_id).
 *  - The biometric link stays on its device code. The code is what the machine sends with every
 *    punch (device code list, punches, day records, attendance links all carry it), so renaming it
 *    here would stop the person's punches matching until the machine is re-enrolled. The link is
 *    marked "id_changed" so Biometric -> Sync all does not hand the code to whoever now has the old
 *    Employee ID (it becomes an exception instead).
 */
class EmployeeIdChange
{

    /** What changes, for the import review. */
    public static function impact(int $userId, string $old, string $new): array
    {
        $device = trim((string) DB::table('employee_details')->where('user_id', $userId)->value('iapp_emp_code'));

        return [
            'from' => $old,
            'to' => $new,
            'periods' => $old === '' ? 0 : DB::table('employment_periods')->where('user_id', $userId)
                ->where(fn ($q) => $q->where('employee_code', $old)->orWhere('employee_card_id', $old))->count(),
            'payslips' => $old === '' ? 0 : DB::table('payslips')->where('user_id', $userId)->where('emp_id', $old)->count(),
            'device_code' => $device !== '' ? $device : null,
            'device_punches' => $device !== '' ? DB::table('iapp_device_transactions')->where('emp_code', $device)->count() : 0,
            // someone else holds the new ID as their device code
            'new_id_is_device_of' => DB::table('employee_details as ed')->join('users as u', 'u.id', '=', 'ed.user_id')
                ->where('ed.iapp_emp_code', $new)->where('ed.user_id', '!=', $userId)->value('u.name'),
        ];
    }

    /** After the change is saved: the records that carry the ID follow it. */
    public static function follow(int $userId, ?string $old, ?string $new): void
    {
        $old = trim((string) $old);
        $new = trim((string) $new);

        if ($old === '' || $new === '' || $old === $new) {
            return;
        }

        DB::table('employment_periods')->where('user_id', $userId)->where('employee_code', $old)->update(['employee_code' => $new]);
        DB::table('employment_periods')->where('user_id', $userId)->where('employee_card_id', $old)->update(['employee_card_id' => $new]);
        DB::table('payslips')->where('user_id', $userId)->where('emp_id', $old)->update(['emp_id' => $new]);

        $device = trim((string) DB::table('employee_details')->where('user_id', $userId)->value('iapp_emp_code'));

        if ($device !== '') {
            DB::table('iapp_employees')->where('emp_code', $device)->where('user_id', $userId)
                ->update(['matched_on' => 'id_changed', 'updated_at' => now()]);
        }
    }

}
