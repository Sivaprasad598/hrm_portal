<?php

namespace App\Services\Access;

use App\Models\Role;
use App\Models\SidebarMenu;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Sidebar menus and Roles & Permissions, kept in step.
 *
 * A menu entry names the permission it needs (sidebar_menus.permission_key,
 * with a rule: all / any / setting_all). Two screens decide access:
 *
 *   Roles & Permissions  a permission given to a role puts the menus that need
 *                        it in that role's sidebar; set to None, they go.
 *   Assign Menus         each entry shows the permission it needs and whether
 *                        the role holds it; ticking an entry the role cannot
 *                        open grants that permission.
 *
 * Entries gated on several permissions at once, or on none, are left as they
 * are - only an entry with exactly one permission is linked.
 */
class MenuPermissions
{

    /** permission_types ids, as this application stores them. */
    public const LEVELS = ['added' => 1, 'owned' => 2, 'both' => 3, 'all' => 4, 'none' => 5];

    /** The one permission an entry needs, and its rule; null when it needs none or several. */
    public static function required(object $menu): ?array
    {
        $key = trim((string) ($menu->permission_key ?? ''));

        if ($key === '' || str_contains($key, ',')) {
            return null;
        }

        return [$key, trim((string) ($menu->permission_rule ?? 'any')) ?: 'any'];
    }

    /** Whether a level (all / owned / none ...) opens an entry with this rule. */
    public static function levelPasses(string $level, string $rule): bool
    {
        return match ($rule) {
            'all', 'setting_all' => $level === 'all',
            'own' => $level === 'none',   // the legacy "own" rule: shown to those without the wider permission
            default => $level !== 'none' && $level !== '',
        };
    }

    /** The level to grant so an entry with this rule opens. */
    public static function levelFor(string $permission, string $rule): string
    {
        if (in_array($rule, ['all', 'setting_all'], true)) {
            return 'all';
        }

        $allowed = json_decode((string) DB::table('permissions')->where('name', $permission)->value('allowed_permissions'), true) ?: [];

        // "Some access": their own where the permission has that level, otherwise everything.
        return isset($allowed['owned']) ? 'owned' : 'all';
    }

    /** permission name => level name, for a role. */
    public static function roleLevels(int $roleId): array
    {
        return DB::table('permission_role as pr')
            ->join('permissions as p', 'p.id', '=', 'pr.permission_id')
            ->join('permission_types as t', 't.id', '=', 'pr.permission_type_id')
            ->where('pr.role_id', $roleId)
            // Aliased: both columns are "name", and pluck would read the same one twice.
            ->select('p.name as permission', 't.name as level')
            ->pluck('level', 'permission')->all();
    }

    /**
     * Roles & Permissions changed one permission of a role: the menus that need
     * exactly that permission follow it into, or out of, the role's sidebar.
     */
    public static function permissionChanged(int $roleId, int $permissionId, int $levelId): void
    {
        $role = Role::find($roleId);
        $name = DB::table('permissions')->where('id', $permissionId)->value('name');

        if (!$role || $role->name === 'admin' || !$name) {
            return;
        }

        $level = array_search($levelId, self::LEVELS, true) ?: 'none';
        $menus = SidebarMenu::where('permission_key', $name)->get(['id', 'parent_id', 'permission_key', 'permission_rule']);

        if ($menus->isEmpty()) {
            return;
        }

        $open = $menus->filter(fn ($m) => self::levelPasses($level, self::required($m)[1] ?? 'any'));
        $closed = $menus->diff($open);

        if ($open->isNotEmpty()) {
            // The headings above them, so the entry has somewhere to sit.
            $ids = $open->pluck('id')->merge(self::ancestors($open))->unique()->values()->all();
            $role->menus()->syncWithoutDetaching($ids);
        }

        if ($closed->isNotEmpty()) {
            $role->menus()->detach($closed->pluck('id')->all());
        }

        self::forget($role);
    }

    /**
     * Assign Menus saved: an entry ticked for a role that cannot open it gets
     * the permission it needs. Returns the permissions granted, by name.
     *
     * @return array<string, string> permission => level granted
     */
    public static function grantForMenus(Role $role, array $menuIds): array
    {
        if ($role->name === 'admin' || !$menuIds) {
            return [];
        }

        $levels = self::roleLevels((int) $role->id);
        $granted = [];

        foreach (SidebarMenu::whereIn('id', $menuIds)->get(['id', 'permission_key', 'permission_rule']) as $menu) {
            $need = self::required($menu);

            if (!$need || isset($granted[$need[0]])) {
                continue;
            }

            [$permission, $rule] = $need;

            if (!self::levelPasses($levels[$permission] ?? 'none', $rule)) {
                $granted[$permission] = self::levelFor($permission, $rule);
                self::grant($role, $permission, $granted[$permission]);
            }
        }

        return $granted;
    }

    /**
     * Give a role a permission at a level, and the people holding the role -
     * as the Roles & Permissions screen does (people whose permissions were
     * customised one by one keep theirs).
     */
    public static function grant(Role $role, string $permission, string $level): void
    {
        $permissionId = DB::table('permissions')->where('name', $permission)->value('id');
        $levelId = self::LEVELS[$level] ?? null;

        if (!$permissionId || !$levelId) {
            return;
        }

        DB::table('permission_role')->updateOrInsert(['role_id' => $role->id, 'permission_id' => $permissionId], ['permission_type_id' => $levelId]);

        foreach ($role->users()->with('role')->get() as $user) {
            $onlyEmployee = $role->name === 'employee' && count($user->role) > 1;

            if (!$onlyEmployee && !$user->customised_permissions) {
                DB::table('user_permissions')->updateOrInsert(['user_id' => $user->id, 'permission_id' => $permissionId], ['permission_type_id' => $levelId]);
            }
        }

        self::forget($role);
    }

    /**
     * For Assign Menus: each linked entry's permission, its readable name and
     * whether the role holds enough of it.
     *
     * @return array<int, array{permission: string, label: string, holds: bool}>
     */
    public static function statusFor(Role $role, Collection $menus): array
    {
        $levels = self::roleLevels((int) $role->id);
        $labels = DB::table('permissions')->pluck('display_name', 'name')->all();
        $out = [];

        foreach ($menus as $menu) {
            $need = self::required($menu);

            if ($need) {
                $out[$menu->id] = [
                    'permission' => $need[0],
                    'label' => $labels[$need[0]] ?? ucwords(str_replace('_', ' ', $need[0])),
                    'holds' => $role->name === 'admin' || self::levelPasses($levels[$need[0]] ?? 'none', $need[1]),
                ];
            }
        }

        return $out;
    }

    /** Everybody holding the role sees a different sidebar from now on. */
    public static function forget(Role $role): void
    {
        \App\Models\User::forgetPermissionMemo();

        foreach ($role->users()->pluck('users.id') as $userId) {
            cache()->forget('sidebar_user_perms_' . $userId);
            cache()->forget('sidebar_menus_' . $userId);
        }
    }

    protected static function ancestors(Collection $menus): array
    {
        $ids = [];
        $parents = $menus->pluck('parent_id')->filter()->unique()->all();

        while ($parents) {
            $ids = array_merge($ids, $parents);
            $parents = SidebarMenu::whereIn('id', $parents)->whereNotNull('parent_id')->pluck('parent_id')->unique()->diff($ids)->all();
        }

        return $ids;
    }

}
