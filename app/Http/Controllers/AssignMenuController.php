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
        $this->pageTitle = 'Assign Menus';
        $this->activeSettingMenu = 'role_management';
    }

    public function index()
    {
        abort_403(user()->permission('manage_role_permission_setting') != 'all');

        $this->roles = Role::withCount('menus')->orderBy('id', 'asc')->get();
        $this->menuParents = $this->menuTree();

        return view('assign-menu.assign-menus', $this->data);
    }

    /**
     * The menu tree plus this role's current grants, for the modal.
     */
    public function getRoleAccess($roleId)
    {
        abort_403(user()->permission('manage_role_permission_setting') != 'all');

        $this->role = Role::with('menus')->findOrFail($roleId);
        $this->menus = $this->menuTree();
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
        abort_403(user()->permission('manage_role_permission_setting') != 'all');

        $role = Role::findOrFail($roleId);

        // The administrator role always holds every menu. Matched by name, not
        // by id: id 1 is only the admin role on a single-company install, and
        // each company has an admin role of its own.
        if ($role->name === 'admin') {
            $role->menus()->sync($this->activeMenuIds());
        } else {
            $ids = $this->sanitiseMenuIds($request->menu_ids);
            $role->menus()->sync($ids);
            // A ticked entry the role could not open gets the permission it needs.
            $granted = \App\Services\Access\MenuPermissions::grantForMenus($role, $ids);
        }

        $this->forgetRoleCaches($role);

        if (!empty($granted)) {
            $names = \Illuminate\Support\Facades\DB::table('permissions')->whereIn('name', array_keys($granted))->pluck('display_name')->implode(', ');

            return Reply::success('Saved. So the ticked menus open, ' . $role->display_name . ' was also given: ' . $names . '.');
        }

        return Reply::success(__('messages.updateSuccess'));
    }

    public function assignAllMenus($roleId)
    {
        abort_403(user()->permission('manage_role_permission_setting') != 'all');

        $role = Role::findOrFail($roleId);
        $role->menus()->sync($this->activeMenuIds());
        $this->forgetRoleCaches($role);

        return Reply::success('All menus assigned to this role.');
    }

    public function revokeAllMenus($roleId)
    {
        abort_403(user()->permission('manage_role_permission_setting') != 'all');

        $role = Role::findOrFail($roleId);

        // Revoking everything from the administrator role would leave the
        // people who manage this screen unable to reach it.
        if ($role->name === 'admin') {
            return Reply::error('The administrator role keeps every menu and cannot be emptied.');
        }

        $role->menus()->sync([]);
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
