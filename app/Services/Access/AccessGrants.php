<?php

namespace App\Services\Access;

use App\Models\Role;
use App\Models\User;
use App\Models\UserPermission;
use Illuminate\Support\Facades\DB;

/**
 * Changing a role's access from Settings -> Access: a page on or off, an action
 * to a level. The same effect as Assign Menus and Roles & Permissions - the
 * role's people follow (unless their permissions were customised one by one),
 * a page switched on brings the permission it needs, a permission changed
 * brings or removes the pages that need it - and each change is logged.
 */
class AccessGrants
{
    /** @return array{granted: array<string, string>} permissions given so the page opens */
    public function setPage(Role $role, int $menuId, bool $on): array
    {
        $before = DB::table('role_menus')->where('role_id', $role->id)->pluck('menu_id')->map(fn ($id) => (int) $id)->all();
        $ids = $on ? array_values(array_unique(array_merge($before, [$menuId]))) : array_values(array_diff($before, [$menuId]));

        $role->menus()->sync($ids);
        $granted = $on ? MenuPermissions::grantForMenus($role, [$menuId]) : [];
        $this->forget($role);

        $menu = DB::table('sidebar_menus')->where('id', $menuId)->value('name');
        $this->log($role, 'page', (string) $menu, in_array($menuId, $before, true) ? 'on' : 'off', $on ? 'on' : 'off');

        foreach ($granted as $name => $level) {
            $this->log($role, 'permission', $name, null, $level . ' (so the page opens)');
        }

        return ['granted' => $granted];
    }

    public function setPermission(Role $role, int $permissionId, int $typeId): void
    {
        $before = DB::table('permission_role')->where('role_id', $role->id)->where('permission_id', $permissionId)->value('permission_type_id');

        DB::table('permission_role')->updateOrInsert(['permission_id' => $permissionId, 'role_id' => $role->id], ['permission_type_id' => $typeId]);

        // The role's people follow, as on Roles & Permissions.
        $rows = [];

        foreach ($role->users()->with('role')->get() as $user) {
            if (($role->name !== 'employee' || count($user->role) === 1) && !$user->customised_permissions) {
                $rows[] = ['permission_id' => $permissionId, 'user_id' => $user->id, 'permission_type_id' => $typeId];
            }
        }

        if ($rows) {
            UserPermission::upsert($rows, ['permission_id', 'user_id'], ['permission_type_id']);
        }

        MenuPermissions::permissionChanged((int) $role->id, $permissionId, $typeId);
        $this->forget($role);

        $types = DB::table('permission_types')->pluck('name', 'id');
        $this->log($role, 'permission', (string) DB::table('permissions')->where('id', $permissionId)->value('name'),
            $before ? ($types[$before] ?? null) : 'none', $types[$typeId] ?? (string) $typeId);
    }

    /** The latest changes made to a role. */
    public function history(int $roleId, int $limit = 15)
    {
        return DB::table('access_change_logs as l')->leftJoin('users as u', 'u.id', '=', 'l.changed_by')
            ->where('l.role_id', $roleId)->orderByDesc('l.id')->limit($limit)
            ->get(['l.kind', 'l.subject', 'l.from_value', 'l.to_value', 'l.created_at', 'u.name as by']);
    }

    protected function forget(Role $role): void
    {
        foreach ($role->users()->pluck('users.id') as $userId) {
            cache()->forget('sidebar_user_perms_' . $userId);
            cache()->forget('sidebar_menus_' . $userId);
            cache()->forget('user_modules_' . $userId);
        }

        MenuPermissions::forget($role);
        User::forgetPermissionMemo();
    }

    protected function log(Role $role, string $kind, string $subject, ?string $from, ?string $to): void
    {
        DB::table('access_change_logs')->insert([
            'role_id' => $role->id, 'kind' => $kind, 'subject' => mb_substr($subject, 0, 191),
            'from_value' => $from, 'to_value' => $to, 'changed_by' => user()?->id, 'created_at' => now(),
        ]);
    }
}
