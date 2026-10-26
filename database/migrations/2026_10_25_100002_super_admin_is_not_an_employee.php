<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The Super Admin login is the system's owner account, not a member of staff.
 *
 * It also held the Employee role, so it was counted as one: in the employee
 * list, attendance, leave, the payroll run (with a CTC of its own), OT,
 * salary revisions and the nightly draft payslips. The Employee role comes
 * off; the account keeps the Super Admin role and every permission. Its
 * employee record stays (the application reads it for every signed-in user),
 * and the screens that list people by that record leave it out
 * (App\Services\Access\SystemAccounts). Day-to-day access is the Admin role's.
 */
return new class extends Migration
{

    public function up(): void
    {
        $admin = DB::table('roles')->where('name', 'admin')->pluck('id');
        $employee = DB::table('roles')->where('name', 'employee')->pluck('id');
        $accounts = DB::table('role_user')->whereIn('role_id', $admin)->pluck('user_id')->unique();

        DB::table('role_user')->whereIn('user_id', $accounts)->whereIn('role_id', $employee)->delete();

        foreach ($accounts as $userId) {
            cache()->forget('sidebar_user_perms_' . $userId);
            cache()->forget('sidebar_menus_' . $userId);
        }
    }

    public function down(): void
    {
        $employee = DB::table('roles')->where('name', 'employee')->value('id');

        foreach (DB::table('role_user as ru')->join('roles as r', 'r.id', '=', 'ru.role_id')->where('r.name', 'admin')->pluck('ru.user_id')->unique() as $userId) {
            DB::table('role_user')->insertOrIgnore(['user_id' => $userId, 'role_id' => $employee]);
        }
    }

};
