<?php

namespace App\Http\Controllers;

use App\Helper\Reply;
use App\Models\Role;
use App\Models\SidebarMenu;
use Illuminate\Http\Request;

class AssignMenuController extends AccountBaseController
{
    /**
     * Constructor
     */
    public function __construct()
    {
        parent::__construct();
        $this->pageTitle         = 'Assign Menus';
        $this->activeSettingMenu = 'role_management';
    }

    /**
     * Show the Assign Menus page (inside Settings).
     */
    public function index()
    {
        abort_403(user()->permission('manage_role_permission_setting') != 'all');

        $this->roles       = Role::withCount('menus')->orderBy('id', 'asc')->get();
        $this->menuParents = SidebarMenu::with('children')
            ->whereNull('parent_id')
            ->where('is_active', 1)
            ->orderBy('sort_order')
            ->get();

        return view('assign-menu.assign-menus', $this->data);
    }

    /**
     * Fetch the menu tree and role's current access (AJAX for the modal).
     */
    public function getRoleAccess($roleId)
    {
        abort_403(user()->permission('manage_role_permission_setting') != 'all');

        $this->role = Role::with('menus')->findOrFail($roleId);

        // Load only active menus, with children
        $this->menus = SidebarMenu::with('children.children')
            ->whereNull('parent_id')
            ->where('is_active', 1)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        // Load only active child menus
        $this->menus->each(function ($mainMenu) {
            $mainMenu->setRelation(
                'children',
                $mainMenu->children->where('is_active', 1)->values()
            );

            $mainMenu->children->each(function ($subMenu) {
                $subMenu->setRelation(
                    'children',
                    $subMenu->children->where('is_active', 1)->values()
                );
            });
        });

        $this->assignedMenus = $this->role->menus->pluck('id')->toArray();

        $html = view('assign-menu.ajax.assign-menus-modal', $this->data)->render();

        return Reply::dataOnly(['status' => 'success', 'html' => $html]);
    }

    /**
     * Save the menu access (Sync role_menus pivot table).
     */
    public function updateRoleAccess(Request $request, $roleId)
    {
        abort_403(user()->permission('manage_role_permission_setting') != 'all');

        $role = Role::findOrFail($roleId);

        // Admin role (ID 1) → force ALL active menus
        if ($role->id == 1) {
            $allMenuIds = SidebarMenu::where('is_active', 1)->pluck('id')->toArray();
            $role->menus()->sync($allMenuIds);
        } else {
            $role->menus()->sync($request->menu_ids ?? []);
        }

        // Clear caches for all users of this role → sidebar updates instantly
        foreach ($role->users as $user) {
            cache()->forget('sidebar_user_perms_' . $user->id);
            cache()->forget('sidebar_menus_' . $user->id);
            cache()->forget('sidebar_menu_slugs_' . $user->id);
        }

        return Reply::success(__('messages.updateSuccess'));
    }

    /**
     * Bulk-assign ALL active menus to a role.
     */
    public function assignAllMenus($roleId)
    {
        abort_403(user()->permission('manage_role_permission_setting') != 'all');

        $role = Role::findOrFail($roleId);
        $allMenuIds = SidebarMenu::where('is_active', 1)->pluck('id')->toArray();
        $role->menus()->sync($allMenuIds);

        foreach ($role->users as $user) {
            cache()->forget('sidebar_user_perms_' . $user->id);
            cache()->forget('sidebar_menus_' . $user->id);
            cache()->forget('sidebar_menu_slugs_' . $user->id);
        }

        return Reply::success('All menus assigned to this role.');
    }

    /**
     * Revoke ALL menus from a role.
     */
    public function revokeAllMenus($roleId)
    {
        abort_403(user()->permission('manage_role_permission_setting') != 'all');

        $role = Role::findOrFail($roleId);
        $role->menus()->sync([]);

        foreach ($role->users as $user) {
            cache()->forget('sidebar_user_perms_' . $user->id);
            cache()->forget('sidebar_menus_' . $user->id);
            cache()->forget('sidebar_menu_slugs_' . $user->id);
        }

        return Reply::success('All menus revoked from this role.');
    }
}
