<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

/**
 * Test sign-ins for the CEO, CFO and Vice President roles (asked for 2026-11-24), beside the
 * Test HR / Test Admin / Test Employee / Test Manager ones already on file:
 *
 *   Test CEO             ceo@arowanasoft.com   Test@1234
 *   Test CFO             cfo@arowanasoft.com   Test@1234
 *   Test Vice President  vp@arowanasoft.com    Test@1234
 *
 * Each takes the designation and Division of a real holder of the role (so editing or importing
 * them later keeps the role), the Employee role plus that role, its permissions, and the default
 * shift. An address already on file is left exactly as it is (its password too).
 * Change these passwords on the live site once testing is done.
 *
 * Safe to run more than once; down() removes the three test accounts.
 */
return new class extends Migration {

    /** email => [name, employee id, role] */
    private const ACCOUNTS = [
        'ceo@arowanasoft.com' => ['Test CEO', 'TEST-CEO', 'ceo'],
        'cfo@arowanasoft.com' => ['Test CFO', 'TEST-CFO', 'cfo'],
        'vp@arowanasoft.com' => ['Test Vice President', 'TEST-VP', 'vice_president'],
    ];

    private const PASSWORD = 'Test@1234';

    public function up(): void
    {
        if (!Schema::hasTable('users') || !Schema::hasTable('employee_details')) {
            return;
        }

        $employee = DB::table('roles')->where('name', 'employee')->first(['id', 'company_id']);

        if (!$employee) {
            return;
        }

        foreach (self::ACCOUNTS as $email => [$name, $code, $roleName]) {
            if (DB::table('users')->where('email', $email)->exists()) {
                continue;
            }

            $role = DB::table('roles')->where('name', $roleName)->where('company_id', $employee->company_id)->value('id');

            if (!$role) {
                continue;
            }

            // a real holder of the role: their designation and Division
            $model = DB::table('employee_details as e')->join('role_user as ru', 'ru.user_id', '=', 'e.user_id')
                ->where('ru.role_id', $role)->whereNotNull('e.designation_id')
                ->orderBy('e.id')->first(['e.designation_id', 'e.department_id']);

            $userId = DB::table('users')->insertGetId([
                'company_id' => $employee->company_id, 'name' => $name, 'email' => $email,
                'password' => Hash::make(self::PASSWORD),
                'gender' => 'male', 'locale' => 'en', 'status' => 'active', 'login' => 'enable',
                'email_notifications' => 0, 'admin_approval' => 1, 'country_id' => 99, 'country_phonecode' => 91,
                'created_at' => now(), 'updated_at' => now(),
            ]);

            DB::table('employee_details')->insert([
                'company_id' => $employee->company_id, 'user_id' => $userId,
                'employee_id' => $code, 'employee_card_id' => $code,
                'designation_id' => $model->designation_id ?? null, 'department_id' => $model->department_id ?? null,
                'joining_date' => now()->startOfDay(), 'employment_type' => 'full_time', 'employment_status' => 'confirmed',
                'marital_status' => 'single', 'created_at' => now(), 'updated_at' => now(),
            ]);

            foreach ([$employee->id, $role] as $roleId) {
                DB::table('role_user')->insert(['user_id' => $userId, 'role_id' => $roleId]);
            }

            // the role's permissions, then the company's default shift
            if ($user = \App\Models\User::withoutGlobalScopes()->find($userId)) {
                $user->assignUserRolePermission($role);
            }

            if (class_exists(\App\Services\Employee\EmployeeDefaults::class)) {
                try {
                    \App\Services\Employee\EmployeeDefaults::applyDefaultShift($userId);
                } catch (\Throwable $e) {
                    // no default shift set up yet: the attendance settings give one later
                }
            }
        }

        if (method_exists(\App\Models\User::class, 'forgetAllMenuCaches')) {
            \App\Models\User::forgetAllMenuCaches();
        }
    }

    public function down(): void
    {
        $ids = DB::table('users')->whereIn('email', array_keys(self::ACCOUNTS))
            ->where('name', 'like', 'Test %')->pluck('id');

        foreach (['user_permissions', 'role_user', 'employee_details'] as $table) {
            if (Schema::hasTable($table)) {
                DB::table($table)->whereIn('user_id', $ids)->delete();
            }
        }

        if (Schema::hasTable('employee_shift_schedules')) {
            DB::table('employee_shift_schedules')->whereIn('user_id', $ids)->delete();
        }

        DB::table('users')->whereIn('id', $ids)->delete();
    }

};
