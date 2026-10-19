<?php

namespace App\Services\Employee;

use App\Models\Role;
use App\Models\User;
use App\Services\Attendance\ShiftAssignments;
use App\Services\Payroll\PayPeriod;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * What every employee gets without anybody choosing it:
 *
 *   - an access role from their designation (Settings -> Designations -> Role),
 *     on top of the base Employee role. A designation with no role set gets one
 *     from its name (RULES); nothing fitting means Employee only;
 *   - the company's default shift (General Shift) when they have none.
 *
 * Used by the Excel import, by Settings -> Designations when a designation's
 * role changes, and once for the employees already on file.
 */
class EmployeeDefaults
{

    /** role name => words in the designation, most specific first */
    public const RULES = [
        'ceo' => ['chief executive', 'chairman', 'managing director'],
        'cfo' => ['chief financial', 'accountant'],
        'hr' => ['hr '],
        'manager_admin' => ['admin', 'system admin', 'it head', 'system administrator'],
        'team_lead' => ['team lead', 'project lead', 'project manager', 'manager', 'head of', 'dgm', 'agm', 'general man',
            'vice president', 'chief technology', 'strategist'],
        'developer' => ['developer'],
        'gis_engineer' => ['gis', 'lidar'],
    ];

    /** How a role made from the rules is shown. */
    public const ROLE_LABELS = [
        'ceo' => 'CEO', 'cfo' => 'CFO', 'hr' => 'HR', 'manager_admin' => 'Admin', 'team_lead' => 'Team Lead',
        'developer' => 'Developer', 'gis_engineer' => 'GIS Engineer',
    ];

    /** The role a designation's name suggests; null means Employee only. */
    public static function guessRoleId(string $designation): ?int
    {
        // Punctuation as spaces: "Hr- Executive", "Sr. System Admin".
        $name = ' ' . strtolower(trim(preg_replace('/[^a-z0-9]+/i', ' ', $designation))) . ' ';

        foreach (self::RULES as $role => $words) {
            foreach ($words as $word) {
                if (str_contains($name, $word)) {
                    return self::ensureRole($role);
                }
            }
        }

        return null;
    }

    /**
     * The role, created when it does not exist (for instance after Data Clean
     * removed the custom roles). A role made here starts with exactly the base
     * Employee role's permissions and menu: nobody gains access by it until it
     * is widened in Settings -> Roles & Permissions.
     */
    public static function ensureRole(string $name): ?int
    {
        $existing = Role::where('name', $name)->value('id');

        if ($existing) {
            return (int) $existing;
        }

        $employee = Role::where('name', 'employee')->first();

        if (!$employee) {
            return null;
        }

        // Straight into the table: the model rewrites the name (team_lead -> team-lead),
        // and the role is looked up by the name the rules use.
        $role = (object) ['id' => DB::table('roles')->insertGetId([
            'name' => $name,
            'display_name' => self::ROLE_LABELS[$name] ?? ucwords(str_replace('_', ' ', $name)),
            'company_id' => $employee->company_id,
            'created_at' => now(), 'updated_at' => now(),
        ])];

        // Leave types list the roles they apply to.
        \App\Services\Leaves\LeaveTypeScope::include('role', [$role->id]);

        foreach (DB::table('permission_role')->where('role_id', $employee->id)->get() as $permission) {
            DB::table('permission_role')->insert(['permission_id' => $permission->permission_id, 'role_id' => $role->id,
                'permission_type_id' => $permission->permission_type_id]);
        }

        if (\Illuminate\Support\Facades\Schema::hasTable('role_menus')) {
            $columns = \Illuminate\Support\Facades\Schema::getColumnListing('role_menus');

            foreach (DB::table('role_menus')->where('role_id', $employee->id)->get() as $menu) {
                $row = array_intersect_key((array) $menu, array_flip($columns));
                unset($row['id']);
                $row['role_id'] = $role->id;
                DB::table('role_menus')->insert($row);
            }
        }

        if (in_array($name, RoleAccess::ROLES, true)) {
            RoleAccess::apply((int) $role->id);
        }

        return (int) $role->id;
    }

    /** The designation's role: the one set on it, else the one its name suggests. */
    public static function roleIdFor(?int $designationId): ?int
    {
        if (!$designationId) {
            return null;
        }

        $designation = DB::table('designations')->where('id', $designationId)->first(['name', 'role_id']);

        if (!$designation) {
            return null;
        }

        return $designation->role_id ? (int) $designation->role_id : self::guessRoleId((string) $designation->name);
    }

    /**
     * Base Employee role plus the designation's role, with that role's
     * permissions. Admins (Super Admin) are never touched.
     */
    public static function applyRole(int $userId, ?int $designationId = null): void
    {
        $user = User::withoutGlobalScopes()->find($userId);

        if (!$user || $user->roles()->where('name', 'admin')->exists()) {
            return;
        }

        $designationId ??= DB::table('employee_details')->where('user_id', $userId)->value('designation_id');
        $employeeRole = Role::where('name', 'employee')->first();
        $roleId = self::roleIdFor($designationId) ?: $employeeRole?->id;

        if (!$employeeRole || !$roleId) {
            return;
        }

        $current = DB::table('role_user')->where('user_id', $userId)->pluck('role_id')->map(fn ($id) => (int) $id)->sort()->values()->all();
        $wanted = collect([$employeeRole->id, $roleId])->unique()->map(fn ($id) => (int) $id)->sort()->values()->all();

        if ($current !== $wanted) {
            DB::table('role_user')->where('user_id', $userId)->delete();

            foreach ($wanted as $id) {
                DB::table('role_user')->insert(['user_id' => $userId, 'role_id' => $id]);
            }
        }

        // Permissions follow the extra role (the base one when there is none).
        if ($current !== $wanted || !DB::table('user_permissions')->where('user_id', $userId)->exists()) {
            $user->assignUserRolePermission($roleId);
        }

        cache()->forget('sidebar_user_perms_' . $userId);
    }

    /**
     * The company's default shift for somebody who has none of their own. It
     * starts on the later of the joining date and the start of the current
     * salary period: earlier days already fall back to the default shift, and
     * writing years of roster for them would add nothing.
     */
    public static function applyDefaultShift(int $userId, ?int $by = null): void
    {
        $shiftId = (int) DB::table('attendance_settings')->value('default_employee_shift');

        if (!$shiftId || DB::table('employee_shift_assignments')->where('user_id', $userId)->exists()) {
            return;
        }

        $joined = DB::table('employee_details')->where('user_id', $userId)->value('joining_date');
        $periodStart = PayPeriod::containing(Carbon::today())->start->toDateString();
        $from = $joined ? max(substr((string) $joined, 0, 10), $periodStart) : $periodStart;

        app(ShiftAssignments::class)->assign($userId, $shiftId, $from, $by, 'Default shift');
    }

}
