<?php

use App\Services\Employee\RoleAccess;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * HR, Admin and CEO: dashboards, HR, Biometric, Payroll and their settings pages
 * (see RoleAccess). The sidebar entries that were Super Admin only by role
 * (Identity Card, Income Tax, Statutory Returns, Full & Final Settlement) now
 * follow the permission instead, like the rest of HR and Payroll.
 */
return new class extends Migration {

    public function up(): void
    {
        $rules = [
            'identity.index' => 'edit_employees',
            'income-tax.index' => 'manage_payroll',
            'statutory-returns.index' => 'manage_payroll',
            'settlements.index' => 'manage_payroll',
        ];

        foreach ($rules as $route => $permission) {
            DB::table('sidebar_menus')->where('route', $route)->update([
                'role_rule' => null, 'permission_key' => $permission, 'permission_rule' => 'setting_all', 'updated_at' => now(),
            ]);
        }

        RoleAccess::applyAll();
    }

    public function down(): void
    {
        // Access only: set it back under Settings -> Roles & Permissions if needed.
    }

};
