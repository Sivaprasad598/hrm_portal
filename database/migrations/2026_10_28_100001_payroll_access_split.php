<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Payroll in four switches instead of one (App\Services\Access\PayrollAccess):
 *
 *   payroll_prepare  - Run Payroll (generate payslips, OT bank, TDS), the calculator, one payslip, offer letters
 *   payroll_approve  - approve, mark paid, send payslips
 *   payroll_revoke   - revoke and delete payslips
 *   payroll_settings - pay elements, OT hours, income tax, statutory returns, settlements, salary revisions, payslip design
 *
 * Nobody gains or loses anything: whoever holds manage_payroll (the old single
 * switch) gets all four, everyone else none. The payroll pages in the sidebar
 * are keyed to the switch that opens them.
 *
 * Also the log of access changes made on Settings -> Access.
 */
return new class extends Migration
{
    const SWITCHES = [
        'payroll_prepare' => 'Payroll: prepare (run payroll, generate payslips)',
        'payroll_approve' => 'Payroll: approve, mark paid, send payslips',
        'payroll_revoke' => 'Payroll: revoke and delete payslips',
        'payroll_settings' => 'Payroll: pay elements, OT, tax, settlements, salary revisions',
    ];

    /** Sidebar page => the switch(es) that open it ("a,b" = any of them). */
    const MENUS = [
        'payroll-run-index' => 'payroll_prepare',
        'payslips-index' => 'payroll_prepare',
        'payslip-entry-index' => 'payroll_prepare',
        'payslips-generated' => 'payroll_prepare,payroll_approve,payroll_revoke,payroll_settings',
        'payroll-register-index' => 'payroll_prepare,payroll_approve,payroll_revoke,payroll_settings',
        'payroll-dashboard-index' => 'payroll_prepare,payroll_approve,payroll_revoke,payroll_settings',
        'pay-elements-index' => 'payroll_settings',
        'ot-hours' => 'payroll_settings',
        'income-tax-index' => 'payroll_settings',
        'statutory-returns-index' => 'payroll_settings',
        'settlements-index' => 'payroll_settings',
        'salary-revisions' => 'payroll_settings',
    ];

    public function up(): void
    {
        $old = DB::table('permissions')->where('name', 'manage_payroll')->first();

        if (!$old) {
            return;
        }

        foreach (self::SWITCHES as $name => $label) {
            if (!DB::table('permissions')->where('name', $name)->exists()) {
                DB::table('permissions')->insert([
                    'name' => $name, 'display_name' => $label, 'module_id' => $old->module_id, 'is_custom' => 1,
                    'allowed_permissions' => '{"all":4, "none":5}', 'created_at' => now(), 'updated_at' => now(),
                ]);
            }

            $id = DB::table('permissions')->where('name', $name)->value('id');

            // The same holders as the old switch, roles and people alike.
            foreach (DB::table('permission_role')->where('permission_id', $old->id)->get() as $row) {
                DB::table('permission_role')->updateOrInsert(
                    ['permission_id' => $id, 'role_id' => $row->role_id], ['permission_type_id' => $row->permission_type_id]);
            }

            foreach (DB::table('user_permissions')->where('permission_id', $old->id)->get() as $row) {
                DB::table('user_permissions')->updateOrInsert(
                    ['permission_id' => $id, 'user_id' => $row->user_id], ['permission_type_id' => $row->permission_type_id]);
            }
        }

        foreach (self::MENUS as $slug => $keys) {
            DB::table('sidebar_menus')->where('slug', $slug)->update(['permission_key' => $keys, 'permission_rule' => 'setting_all']);
        }

        if (!Schema::hasTable('access_change_logs')) {
            Schema::create('access_change_logs', function (Blueprint $table) {
                $table->id();
                $table->unsignedInteger('role_id')->index();
                $table->string('kind', 20);           // page | permission
                $table->string('subject', 191);       // the page or permission
                $table->string('from_value', 60)->nullable();
                $table->string('to_value', 60)->nullable();
                $table->unsignedInteger('changed_by')->nullable();
                $table->timestamp('created_at')->useCurrent();
            });
        }

        foreach (DB::table('users')->pluck('id') as $userId) {
            cache()->forget('sidebar_menus_' . $userId);
            cache()->forget('sidebar_user_perms_' . $userId);
        }
    }

    public function down(): void
    {
        foreach (array_keys(self::MENUS) as $slug) {
            DB::table('sidebar_menus')->where('slug', $slug)->update(['permission_key' => 'manage_payroll', 'permission_rule' => 'setting_all']);
        }

        $ids = DB::table('permissions')->whereIn('name', array_keys(self::SWITCHES))->pluck('id');
        DB::table('permission_role')->whereIn('permission_id', $ids)->delete();
        DB::table('user_permissions')->whereIn('permission_id', $ids)->delete();
        DB::table('permissions')->whereIn('id', $ids)->delete();
        Schema::dropIfExists('access_change_logs');
    }
};
