<?php

namespace App\Http\Controllers;

use App\Helper\Reply;
use App\Models\Role;
use App\Services\Access\AccessCatalog;
use App\Services\Access\AccessGrants;
use Illuminate\Http\Request;

/**
 * Settings -> Access: for one role, which pages it has (its sidebar) and what
 * it may do on each, in one place. Replaces Assign Menus and Roles &
 * Permissions for everyday use; those stay under "Advanced" (role create and
 * delete, per-person permissions, the non-HRM modules' finer detail).
 */
class AccessSettingsController extends AccountBaseController
{
    public function __construct()
    {
        parent::__construct();
        $this->pageTitle = 'Access';
        $this->activeSettingMenu = 'access_settings';

        $this->middleware(function ($request, $next) {
            abort_403(user()->permission('manage_role_permission_setting') !== 'all');

            return $next($request);
        });
    }

    public function index(Request $request)
    {
        $this->roles = Role::withCount('users')->where('name', '!=', 'client')->orderBy('id')->get();
        $this->role = $this->roles->firstWhere('id', (int) $request->role)
            ?? $this->roles->firstWhere('name', 'hr') ?? $this->roles->first();

        return view('access-settings.index', $this->data + $this->panelData($this->role));
    }

    /** Switch a page on or off for the role. */
    public function page(Request $request, AccessGrants $grants)
    {
        $request->validate(['role_id' => 'required|integer', 'menu_id' => 'required|integer|exists:sidebar_menus,id', 'on' => 'required|boolean']);
        $role = $this->editableRole((int) $request->role_id);
        $result = $grants->setPage($role, (int) $request->menu_id, $request->boolean('on'));

        $message = $request->boolean('on') ? 'Page switched on.' : 'Page switched off.';

        if ($result['granted']) {
            $message .= ' So it opens, ' . $role->display_name . ' was also given: ' . implode(', ', array_keys($result['granted'])) . '.';
        }

        return $this->panel($role, $message);
    }

    /** Set an action (permission) to a level for the role. */
    public function permission(Request $request, AccessGrants $grants)
    {
        $request->validate(['role_id' => 'required|integer', 'permission_id' => 'required|integer|exists:permissions,id',
            'level' => 'required|integer|exists:permission_types,id']);
        $role = $this->editableRole((int) $request->role_id);
        $grants->setPermission($role, (int) $request->permission_id, (int) $request->level);

        return $this->panel($role, 'Saved.');
    }

    /** The Super Admin role always has everything; it is shown, not changed. */
    protected function editableRole(int $roleId): Role
    {
        $role = Role::findOrFail($roleId);
        abort_if($role->name === 'admin', 422, 'The Super Admin always has full access.');

        return $role;
    }

    protected function panel(Role $role, string $message)
    {
        $html = view('access-settings.panel', $this->panelData($role))->render();

        return Reply::successWithData($message, ['html' => $html]);
    }

    protected function panelData(Role $role): array
    {
        return [
            'role' => $role,
            'areas' => AccessCatalog::forRole((int) $role->id),
            'history' => app(AccessGrants::class)->history((int) $role->id),
            'locked' => $role->name === 'admin',
        ];
    }
}
