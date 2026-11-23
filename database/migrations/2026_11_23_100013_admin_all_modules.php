<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The Admin role (manager_admin) gets every module's pages (asked for 2026-11-23: "for admin all the
 * modules with pages need to have access"). A check of every menu entry found Admin without:
 * My Calendar, Offer Letter, Timesheet, Weekly Timesheets, Task Board, the Weekly Timesheet report,
 * all of Recruitment (and its settings), Attendance Requests, Employee Import and Attendance Import.
 *
 * Each entry is granted to the role with the permission it needs (as ticking it on Settings ->
 * Assign Menus does, MenuPermissions). Recruitment gets view / manage / settings in full. The two
 * import pages also check the role in code (EmployeeImportController, AttendanceImportController).
 * The rest of Settings (roles, menus, backups, company...) stays with the Super Admin.
 *
 * Safe to run more than once; down() takes these entries and the Recruitment permissions off again.
 */
return new class extends Migration {

    private const ROUTES = [
        'my-calendar.index', 'payslip-entry.index', 'timelogs.index', 'weekly-timesheets.index', 'taskboards.index',
        'time-log-weekly-report.index', 'recruitment.dashboard', 'job-positions.index', 'job-applications.index',
        'candidates.index', 'recruit-interviews.index', 'recruitment-settings.index', 'attendances.by_request',
        'employee-import.index', 'attendance-import.index',
    ];

    private const PERMISSIONS = ['view_recruitment', 'manage_recruitment', 'manage_recruitment_settings'];

    public function up(): void
    {
        foreach ($this->roles() as $role) {
            $ids = $this->menuIds();

            foreach ($ids as $menuId) {
                if (!DB::table('role_menus')->where('role_id', $role->id)->where('menu_id', $menuId)->exists()) {
                    DB::table('role_menus')->insert(['role_id' => $role->id, 'menu_id' => $menuId, 'created_at' => now(), 'updated_at' => now()]);
                }
            }

            if (class_exists(\App\Services\Access\MenuPermissions::class)) {
                \App\Services\Access\MenuPermissions::grantForMenus($role, $ids);

                foreach (self::PERMISSIONS as $permission) {
                    \App\Services\Access\MenuPermissions::grant($role, $permission, 'all');
                }
            }

            $this->forget($role);
        }
    }

    public function down(): void
    {
        foreach ($this->roles() as $role) {
            DB::table('role_menus')->where('role_id', $role->id)->whereIn('menu_id', $this->menuIds())->delete();

            if (class_exists(\App\Services\Access\MenuPermissions::class)) {
                foreach (self::PERMISSIONS as $permission) {
                    \App\Services\Access\MenuPermissions::grant($role, $permission, 'none');
                }
            }

            $this->forget($role);
        }
    }

    private function roles()
    {
        if (!Schema::hasTable('role_menus') || !Schema::hasTable('sidebar_menus')) {
            return collect();
        }

        return \App\Models\Role::withoutGlobalScopes()->where('name', 'manager_admin')->get();
    }

    /** The entries, and the headings they sit under (Recruitment, Reports, My Team). */
    private function menuIds(): array
    {
        $menus = DB::table('sidebar_menus')->where('is_active', 1)->whereIn('route', self::ROUTES)->get(['id', 'parent_id']);
        $parents = $menus->pluck('parent_id')->filter()->unique()
            ->reject(fn ($id) => DB::table('sidebar_menus')->where('id', $id)->value('slug') === 'settings');

        return $menus->pluck('id')->merge($parents)->unique()->values()->all();
    }

    private function forget($role): void
    {
        foreach (DB::table('role_user')->where('role_id', $role->id)->pluck('user_id') as $userId) {
            cache()->forget('sidebar_user_perms_' . $userId);
            cache()->forget('sidebar_menus_' . $userId);
        }
    }

};
