<?php

namespace App\Http\Controllers;

use App\Helper\Reply;
use App\Models\Role;
use App\Models\SidebarMenu;
use Illuminate\Http\Request;

/**
 * Settings > Assign Menus — which roles may see which sidebar entries.
 *
 * A grant here only makes a menu *eligible*. Whether a given person actually
 * sees it is still decided by their own permissions, in
 * User::menuPermissionAllows(). Each entry shows the permission it needs and
 * whether the role holds it; ticking one the role cannot open grants that
 * permission (MenuPermissions), so the two screens never disagree.
 *
 * Role is company-scoped by its global scope, so every lookup here is already
 * limited to the signed-in company.
 */
class AssignMenuController extends AccountBaseController
{

    public function __construct()
    {
        parent::__construct();
        $this->pageTitle = 'Menu Access';
        $this->activeSettingMenu = 'role_management';
    }

    /**
     * Who may use this page: whoever holds Roles & Permissions (the Super Admin), and the Admin role
     * (manager_admin, added 2026-11-23), who gives other roles the menus their own role has.
     */
    public static function canManage(): bool
    {
        return user() && (user()->permission('manage_role_permission_setting') === 'all'
            || in_array('manager_admin', user_roles(), true));
    }

    /** The Super Admin (or whoever holds Roles & Permissions) has no limits here. */
    private function unlimited(): bool
    {
        return user()->permission('manage_role_permission_setting') === 'all';
    }

    /**
     * For the Admin: the menus they may give (those their own roles have) - null when unlimited.
     * @return array<int, int>|null
     */
    private function givable(): ?array
    {
        if ($this->unlimited()) {
            return null;
        }

        $roles = Role::whereIn('name', user_roles())->pluck('id');

        return \Illuminate\Support\Facades\DB::table('role_menus')->whereIn('role_id', $roles)->pluck('menu_id')->map(fn ($id) => (int) $id)->unique()->values()->all();
    }

    /**
     * The Admin cannot change the Super Admin role or their own Admin role. Employee is the base role
     * everybody holds (the Admin too), so the Admin may change it like any other.
     */
    private function mayEdit(Role $role): bool
    {
        return $this->unlimited() || ($role->name !== 'admin' && ($role->name === 'employee' || !in_array($role->name, user_roles(), true)));
    }

    public function index()
    {
        abort_403(!self::canManage());

        // by seniority; any other role after the named ones, Employee and Client last
        $order = ['admin', 'manager_admin', 'ceo', 'cfo', 'hr', 'manager', 'team_lead', 'developer', 'gis_engineer'];
        $last = ['employee', 'client'];
        $this->roles = Role::withCount('menus')->orderBy('id', 'asc')->get()
            ->sortBy(fn ($r) => in_array($r->name, $order, true) ? array_search($r->name, $order, true)
                : (in_array($r->name, $last, true) ? 1000 + array_search($r->name, $last, true) : 500 + $r->id))
            ->values();
        $this->unlimited = $this->unlimited();
        $this->editable = $this->roles->filter(fn ($r) => $this->mayEdit($r))->pluck('id')->all();
        $this->menuParents = $this->menuTree();
        $this->comingSoonReady = \Illuminate\Support\Facades\Schema::hasColumn('sidebar_menus', 'coming_soon');

        return view('assign-menu.assign-menus', $this->data);
    }

    /**
     * The menu tree plus this role's current grants, for the modal.
     */
    public function getRoleAccess($roleId)
    {
        abort_403(!self::canManage());

        $this->role = Role::with('menus')->findOrFail($roleId);
        abort_403(!$this->mayEdit($this->role));
        $this->menus = $this->menuTree();
        // entries the Admin cannot give (their own roles do not have them): shown, not changeable
        $givable = $this->givable();
        $this->lockedMenus = $givable === null ? [] : SidebarMenu::forCompany(company()?->id)->where('is_active', 1)
            ->whereNotIn('id', $givable)->pluck('id')->map(fn ($id) => (int) $id)->all();
        $this->assignedMenus = $this->role->menus->pluck('id')->toArray();
        $this->isAdminRole = $this->role->name === 'admin';
        // Each linked entry's permission and whether this role holds it.
        $this->menuStatus = \App\Services\Access\MenuPermissions::statusFor($this->role,
            SidebarMenu::forCompany(company()?->id)->where('is_active', 1)->get(['id', 'permission_key', 'permission_rule']));

        $html = view('assign-menu.ajax.assign-menus-modal', $this->data)->render();

        return Reply::dataOnly(['status' => 'success', 'html' => $html]);
    }

    public function updateRoleAccess(Request $request, $roleId)
    {
        abort_403(!self::canManage());

        $role = Role::findOrFail($roleId);
        abort_403(!$this->mayEdit($role));

        // The administrator role always holds every menu. Matched by name, not
        // by id: id 1 is only the admin role on a single-company install, and
        // each company has an admin role of its own.
        if ($role->name === 'admin') {
            $role->menus()->sync($this->activeMenuIds());
        } else {
            $ids = $this->limitToGivable($role, $this->sanitiseMenuIds($request->menu_ids));
            $role->menus()->sync($ids);
            // A ticked entry the role could not open gets the permission it needs (for the Admin,
            // only on the menus they may give: the others are kept as the Super Admin left them).
            $givable = $this->givable();
            $granted = \App\Services\Access\MenuPermissions::grantForMenus($role, $givable === null ? $ids : array_values(array_intersect($ids, $givable)));
        }

        $this->forgetRoleCaches($role);

        if (!empty($granted)) {
            $names = \Illuminate\Support\Facades\DB::table('permissions')->whereIn('name', array_keys($granted))->pluck('display_name')->implode(', ');

            return Reply::success('Saved. So the ticked menus open, ' . $role->display_name . ' was also given: ' . $names . '.');
        }

        return Reply::success(__('messages.updateSuccess'));
    }

    /**
     * Modules tab: mark a module (a top-level menu) Coming soon, or open it again. Everyone but the
     * Super Admin then sees a Coming soon page on its pages (App\Services\Access\ComingSoon).
     */
    public function toggleComingSoon(Request $request, $menuId)
    {
        abort_403(user()->permission('manage_role_permission_setting') != 'all');

        $menu = SidebarMenu::forCompany(company()?->id)->whereNull('parent_id')->findOrFail($menuId);

        if ($menu->slug === 'settings') {
            return Reply::error('Settings cannot be marked Coming soon: this page lives there.');
        }

        $menu->coming_soon = $request->boolean('coming_soon');
        $menu->save();

        \App\Services\Access\ComingSoon::forget();
        \App\Models\User::forgetAllMenuCaches();

        return Reply::success($menu->coming_soon
            ? $menu->name . ' now shows Coming soon to everyone except the Super Admin.'
            : $menu->name . ' is open again.');
    }

    public function assignAllMenus($roleId)
    {
        abort_403(!self::canManage());

        $role = Role::findOrFail($roleId);
        abort_403(!$this->mayEdit($role));
        $role->menus()->sync($this->limitToGivable($role, $this->activeMenuIds()));
        $this->forgetRoleCaches($role);

        return Reply::success($this->unlimited() ? 'All menus assigned to this role.' : 'Every menu you can give was assigned to this role.');
    }

    /**
     * For the Admin: of what was asked, keep only the menus they may give; and leave the role's
     * other menus (given by the Super Admin) exactly as they are.
     */
    private function limitToGivable(Role $role, array $ids): array
    {
        $givable = $this->givable();

        if ($givable === null) {
            return $ids;
        }

        $kept = $role->menus()->pluck('sidebar_menus.id')->map(fn ($id) => (int) $id)->diff($givable);

        return collect($ids)->map(fn ($id) => (int) $id)->intersect($givable)->merge($kept)->unique()->values()->all();
    }

    public function revokeAllMenus($roleId)
    {
        abort_403(!self::canManage());

        $role = Role::findOrFail($roleId);

        // Revoking everything from the administrator role would leave the
        // people who manage this screen unable to reach it.
        if ($role->name === 'admin') {
            return Reply::error('The administrator role keeps every menu and cannot be emptied.');
        }

        abort_403(!$this->mayEdit($role));
        $role->menus()->sync($this->limitToGivable($role, []));
        $this->forgetRoleCaches($role);

        return Reply::success('All menus revoked from this role.');
    }

    /**
     * Active menus for this company, three levels deep.
     *
     * The management screen offers three levels, so three are loaded; the
     * shipped version loaded two and fetched the third one row at a time.
     */
    private function menuTree()
    {
        return SidebarMenu::with(SidebarMenu::treeRelations())
            ->forCompany(company()?->id)
            ->whereNull('parent_id')
            ->where('is_active', 1)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }

    private function activeMenuIds(): array
    {
        return SidebarMenu::forCompany(company()?->id)
            ->where('is_active', 1)
            ->pluck('id')
            ->all();
    }

    /**
     * Only ids that are real, active and belong to this company get stored —
     * the form posts whatever the browser sends.
     */
    private function sanitiseMenuIds($ids): array
    {
        $ids = array_filter(array_map('intval', (array) ($ids ?? [])));

        if ($ids === []) {
            return [];
        }

        return SidebarMenu::forCompany(company()?->id)
            ->where('is_active', 1)
            ->whereIn('id', $ids)
            ->pluck('id')
            ->all();
    }

    /**
     * Everyone holding this role sees a different sidebar from now on.
     */
    private function forgetRoleCaches(Role $role): void
    {
        foreach ($role->users as $user) {
            cache()->forget('sidebar_user_perms_' . $user->id);
            cache()->forget('sidebar_menus_' . $user->id);
        }
    }

}
