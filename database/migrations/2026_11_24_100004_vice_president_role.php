<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A Vice President role with the CEO's access (asked for 2026-11-24): the CEO's menus and
 * permissions, the Executive Dashboard as their Dashboard, the project money and invoices
 * (ExecutiveDashboardController::ROLES, RoleAccess::ROLES and the other lists name it beside the CEO).
 *
 * The designations "Vice President" and "Sr. Vice President" pointed at Team Lead; they now point
 * at Vice President, and the people holding them (R Ganesh, Naman Kabra) are moved over. A new
 * designation with "vice president" in its name suggests this role too (EmployeeDefaults::RULES).
 * Not changed: the CEO's own steps in approvals (leave, salary revisions) stay with the CEO.
 *
 * Safe to run more than once. down() puts those designations and people back on Team Lead and
 * removes the role.
 */
return new class extends Migration {

    private const ROLE = 'vice_president';

    public function up(): void
    {
        if (!Schema::hasTable('roles') || !Schema::hasTable('permission_role')) {
            return;
        }

        foreach (DB::table('roles')->where('name', 'ceo')->get(['id', 'company_id']) as $ceo) {
            $vpId = DB::table('roles')->where('name', self::ROLE)->where('company_id', $ceo->company_id)->value('id');

            if (!$vpId) {
                $vpId = DB::table('roles')->insertGetId([
                    'name' => self::ROLE, 'display_name' => 'Vice President',
                    'description' => 'Same access as the CEO: the Executive Dashboard and every module the CEO sees.',
                    'company_id' => $ceo->company_id, 'created_at' => now(), 'updated_at' => now(),
                ]);

                // leave types list the roles they apply to
                if (class_exists(\App\Services\Leaves\LeaveTypeScope::class)) {
                    \App\Services\Leaves\LeaveTypeScope::include('role', [$vpId]);
                }
            }

            // the CEO's permissions, at the CEO's level
            foreach (DB::table('permission_role')->where('role_id', $ceo->id)->get(['permission_id', 'permission_type_id']) as $p) {
                DB::table('permission_role')->updateOrInsert(['role_id' => $vpId, 'permission_id' => $p->permission_id],
                    ['permission_type_id' => $p->permission_type_id]);
            }

            // the CEO's menu entries (the Executive Dashboard among them)
            if (Schema::hasTable('role_menus')) {
                foreach (DB::table('role_menus')->where('role_id', $ceo->id)->pluck('menu_id') as $menuId) {
                    if (!DB::table('role_menus')->where('role_id', $vpId)->where('menu_id', $menuId)->exists()) {
                        DB::table('role_menus')->insert(['role_id' => $vpId, 'menu_id' => $menuId, 'created_at' => now(), 'updated_at' => now()]);
                    }
                }
            }

            // the standard access the CEO has (RoleAccess lists the Vice President beside the CEO)
            if (class_exists(\App\Services\Employee\RoleAccess::class)) {
                \App\Services\Employee\RoleAccess::apply((int) $vpId);
            }

            // "Vice President" / "Sr. Vice President" designations, and the people holding them
            $teamLead = DB::table('roles')->where('name', 'team_lead')->where('company_id', $ceo->company_id)->value('id');
            $designations = DB::table('designations')->where('company_id', $ceo->company_id)
                ->whereRaw("LOWER(name) LIKE '%vice president%'")
                ->where(fn ($q) => $q->whereNull('role_id')->orWhere('role_id', $teamLead)->orWhere('role_id', $vpId))
                ->pluck('id');

            DB::table('designations')->whereIn('id', $designations)->update(['role_id' => $vpId]);
            $this->reapply($designations);
        }

        $this->forget();
    }

    public function down(): void
    {
        foreach (DB::table('roles')->where('name', self::ROLE)->get(['id', 'company_id']) as $vp) {
            $teamLead = DB::table('roles')->where('name', 'team_lead')->where('company_id', $vp->company_id)->value('id');
            $designations = DB::table('designations')->where('role_id', $vp->id)->pluck('id');

            DB::table('designations')->whereIn('id', $designations)->update(['role_id' => $teamLead]);
            $people = DB::table('role_user')->where('role_id', $vp->id)->pluck('user_id');

            DB::table('role_menus')->where('role_id', $vp->id)->delete();
            DB::table('permission_role')->where('role_id', $vp->id)->delete();
            DB::table('role_user')->where('role_id', $vp->id)->delete();
            DB::table('roles')->where('id', $vp->id)->delete();

            // back on the designation's role (Team Lead)
            foreach ($people as $userId) {
                if (class_exists(\App\Services\Employee\EmployeeDefaults::class)) {
                    \App\Services\Employee\EmployeeDefaults::applyRole((int) $userId);
                }
            }
        }

        $this->forget();
    }

    /** Everyone holding these designations: the Employee role plus the designation's role, with its permissions. */
    private function reapply($designationIds): void
    {
        if (!class_exists(\App\Services\Employee\EmployeeDefaults::class)) {
            return;
        }

        foreach (DB::table('employee_details')->whereIn('designation_id', $designationIds)->pluck('user_id') as $userId) {
            \App\Services\Employee\EmployeeDefaults::applyRole((int) $userId);
            cache()->forget('sidebar_menus_' . $userId);
        }
    }

    private function forget(): void
    {
        if (method_exists(\App\Models\User::class, 'forgetAllMenuCaches')) {
            \App\Models\User::forgetAllMenuCaches();
        }
    }

};
