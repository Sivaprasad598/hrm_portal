<?php

namespace App\Http\Controllers;

use App\Helper\Reply;
use App\Models\SidebarMenu;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class MenuController extends AccountBaseController
{
    public function __construct()
    {
        parent::__construct();
        $this->pageTitle         = 'Menu Management';
        $this->activeSettingMenu = 'menu_management';
    }

    /**
     * Show the Menu Management page — hierarchical tree view.
     */
    public function index()
    {
        abort_403(user()->permission('manage_role_permission_setting') != 'all');

        $this->menus = SidebarMenu::with('children.children')
            ->whereNull('parent_id')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return view('menu-management.index', $this->data);
    }

    /**
     * Show create modal (for "+ Add Menu Item" or child-add buttons).
     */
    public function create(Request $request)
    {
        abort_403(user()->permission('manage_role_permission_setting') != 'all');

        $this->menuType  = $request->get('menu_type', 'main');
        $this->parentId  = $request->get('parent_id');
        $this->mainMenus = SidebarMenu::whereNull('parent_id')->orderBy('sort_order')->get();
        $this->subMenus  = SidebarMenu::where('level', 1)->orderBy('sort_order')->get();
        $this->preselectedMainId = null;
        $this->preselectedSubmenuId = null;

        if ($this->parentId) {
            $selectedParent = SidebarMenu::find($this->parentId);

            if ($selectedParent && $selectedParent->level === 1) {
                $this->preselectedSubmenuId = $selectedParent->id;
                $this->preselectedMainId = $selectedParent->parent_id;
            }
        }

        // Auto-suggest next priority
        if ($this->parentId) {
            $this->nextOrder = (SidebarMenu::where('parent_id', $this->parentId)->max('sort_order') ?? 0) + 1;
        } else {
            $this->nextOrder = (SidebarMenu::whereNull('parent_id')->max('sort_order') ?? 0) + 1;
        }

        $html = view('menu-management.ajax.create', $this->data)->render();

        return Reply::dataOnly(['status' => 'success', 'html' => $html]);
    }

    /**
     * Store a menu and make room in its sibling list for its priority.
     */
    public function store(Request $request)
    {
        abort_403(user()->permission('manage_role_permission_setting') != 'all');

        $request->validate([
            'menu_type'  => 'required|in:main,submenu,child',
            'name'       => ['required', 'string', 'max:191', Rule::unique('sidebar_menus', 'name'), function ($attribute, $value, $fail) {
                if (preg_match('/[\x{1F000}-\x{1FAFF}\x{2300}-\x{23FF}\x{2600}-\x{27BF}\x{2B00}-\x{2BFF}\x{FE0F}\x{200D}\x{20E3}]/u', $value)) {
                    $fail('Emojis are not allowed.');
                }
            }],
            'icon'       => 'nullable|string|max:191',
            'sort_order' => 'required|integer|min:1',
            'route'      => 'nullable|string|max:191',
        ], [
            'name.unique' => 'Menu name already exists',
        ]);

        $parentId = null;
        $level    = 0;

        if ($request->menu_type === 'submenu') {
            $request->validate(['parent_id' => 'required|exists:sidebar_menus,id']);
            $parentId = $request->parent_id;
            $level    = 1;
        }

        if ($request->menu_type === 'child') {
            $request->validate([
                'main_id'    => 'required|exists:sidebar_menus,id',
                'submenu_id' => 'required|exists:sidebar_menus,id',
            ]);
            $parentId = $request->submenu_id;
            $level    = 2;
        }

        // Generate unique slug
        $slug = Str::slug($request->name);
        $base = $slug;
        $i    = 1;
        while (SidebarMenu::where('slug', $slug)->exists()) {
            $slug = $base . '-' . $i++;
        }

        DB::transaction(function () use ($parentId, $level, $request, $slug) {
            $this->siblingMenusQuery($parentId, $level)
                ->where('sort_order', '>=', $request->sort_order)
                ->update(['sort_order' => DB::raw('sort_order + 1')]);

            SidebarMenu::create([
                'company_id' => company()?->id,
                'parent_id'  => $parentId,
                'name'       => $request->name,
                'slug'       => $slug,
                'route'      => $request->route,
                'icon'       => $request->icon,
                'level'      => $level,
                'sort_order' => $request->sort_order,
                'is_active'  => 1,
            ]);
        });

        $this->clearMenuCache();

        return Reply::success('Menu item added successfully.');
    }

    /**
     * Edit modal.
     */
    public function edit($id)
    {
        abort_403(user()->permission('manage_role_permission_setting') != 'all');

        $this->menu      = SidebarMenu::findOrFail($id);
        $excludedIds     = array_merge([$id], $this->getDescendantIds($this->menu));
        $this->mainMenus = SidebarMenu::whereNull('parent_id')
            ->whereNotIn('id', $excludedIds)
            ->orderBy('sort_order')
            ->get();
        $this->subMenus  = SidebarMenu::where('level', 1)
            ->whereNotIn('id', $excludedIds)
            ->orderBy('sort_order')
            ->get();
        $this->parentId  = $this->menu->parent_id;

        // Determine current top-level main menu of this row (for child cascading)
        $this->mainMenuId = null;
        if ($this->menu->level === 1) {
            $this->mainMenuId = $this->menu->id;
        } elseif ($this->menu->level === 2) {
            $parent = SidebarMenu::find($this->menu->parent_id);
            $this->mainMenuId = $parent ? $parent->parent_id : null;
        }

        $html = view('menu-management.ajax.edit', $this->data)->render();

        return Reply::dataOnly(['status' => 'success', 'html' => $html]);
    }

    /**
     * Update a menu and reorder its old and new sibling lists as needed.
     */
    public function update(Request $request, $id)
    {
        abort_403(user()->permission('manage_role_permission_setting') != 'all');

        $menu = SidebarMenu::findOrFail($id);

        $request->validate([
            'menu_type'  => 'required|in:main,submenu,child',
            'name'       => ['required', 'string', 'max:191', Rule::unique('sidebar_menus', 'name')->ignore($menu->id), function ($attribute, $value, $fail) {
                if (preg_match('/[\x{1F000}-\x{1FAFF}\x{2300}-\x{23FF}\x{2600}-\x{27BF}\x{2B00}-\x{2BFF}\x{FE0F}\x{200D}\x{20E3}]/u', $value)) {
                    $fail('Emojis are not allowed.');
                }
            }],
            'icon'       => 'nullable|string|max:191',
            'sort_order' => 'required|integer|min:1',
            'route'      => 'nullable|string|max:191',
        ], [
            'name.unique' => 'Menu name already exists',
        ]);

        $parentId = null;
        $level    = 0;

        if ($request->menu_type === 'submenu') {
            $request->validate(['parent_id' => 'required|exists:sidebar_menus,id']);
            $parentId = $request->parent_id;
            $level    = 1;
        }

        if ($request->menu_type === 'child') {
            $request->validate([
                'main_id'    => 'required|exists:sidebar_menus,id',
                'submenu_id' => 'required|exists:sidebar_menus,id',
            ]);
            $parentId = $request->submenu_id;
            $level    = 2;
        }

        $oldParentId = $menu->parent_id;
        $oldLevel = (int) $menu->level;
        $oldSort = (int) $menu->sort_order;
        $newSort = (int) $request->sort_order;

        DB::transaction(function () use ($menu, $id, $oldParentId, $oldLevel, $oldSort, $parentId, $level, $newSort, $request) {
            $sameSiblingList = (int) $level === $oldLevel
                && (int) $parentId === (int) $oldParentId;

            if ($sameSiblingList && $newSort < $oldSort) {
                $this->siblingMenusQuery($parentId, $level)
                    ->where('id', '!=', $id)
                    ->where('sort_order', '>=', $newSort)
                    ->where('sort_order', '<', $oldSort)
                    ->update(['sort_order' => DB::raw('sort_order + 1')]);
            } elseif ($sameSiblingList && $newSort > $oldSort) {
                $this->siblingMenusQuery($parentId, $level)
                    ->where('id', '!=', $id)
                    ->where('sort_order', '>', $oldSort)
                    ->where('sort_order', '<=', $newSort)
                    ->update(['sort_order' => DB::raw('sort_order - 1')]);
            } elseif (!$sameSiblingList) {
                $this->siblingMenusQuery($oldParentId, $oldLevel)
                    ->where('id', '!=', $id)
                    ->where('sort_order', '>', $oldSort)
                    ->update(['sort_order' => DB::raw('sort_order - 1')]);

                $this->siblingMenusQuery($parentId, $level)
                    ->where('sort_order', '>=', $newSort)
                    ->update(['sort_order' => DB::raw('sort_order + 1')]);
            }

            $menu->update([
                'parent_id'  => $parentId,
                'name'       => $request->name,
                'route'      => $request->route,
                'icon'       => $request->icon,
                'level'      => $level,
                'sort_order' => $newSort,
            ]);
        });

        $this->clearMenuCache();

        return Reply::success('Menu updated successfully.');
    }

    /**
     * Delete menu + descendants.
     */
    public function destroy($id)
    {
        abort_403(user()->permission('manage_role_permission_setting') != 'all');

        $menu = SidebarMenu::findOrFail($id);

        $protected = ['dashboard', 'hr', 'work', 'finance', 'settings', 'clients', 'leads', 'deals'];
        if (in_array($menu->slug, $protected)) {
            return Reply::error('This is a protected system menu and cannot be deleted.');
        }

        $this->deleteDescendants($menu);
        $menu->delete();

        $this->clearMenuCache();

        return Reply::success('Menu deleted successfully.');
    }

    /**
     * Get submenus of a given main menu (for cascading dropdown).
     */
    public function getSubmenus(Request $request)
    {
        $mainId = $request->get('main_id');

        $submenus = SidebarMenu::where('parent_id', $mainId)
            ->where('level', 1)
            ->orderBy('sort_order')
            ->get(['id', 'name']);

        return response()->json(['submenus' => $submenus]);
    }

    public function getNextPriority(Request $request)
    {
        abort_403(user()->permission('manage_role_permission_setting') != 'all');

        $validated = $request->validate([
            'menu_type' => 'required|in:main,submenu,child',
            'parent_id' => 'nullable|integer|exists:sidebar_menus,id',
        ]);

        $level = ['main' => 0, 'submenu' => 1, 'child' => 2][$validated['menu_type']];
        $parentId = $validated['parent_id'] ?? null;

        if ($level === 0) {
            $parentId = null;
        } else {
            $expectedParentLevel = $level - 1;
            abort_unless(
                SidebarMenu::where('id', $parentId)->where('level', $expectedParentLevel)->exists(),
                422,
                'Select a valid parent menu.'
            );
        }

        $nextOrder = ($this->siblingMenusQuery($parentId, $level)->max('sort_order') ?? 0) + 1;

        return response()->json(['next_order' => $nextOrder]);
    }

    // ==================== Helpers ====================

    private function siblingMenusQuery($parentId, int $level)
    {
        $query = SidebarMenu::where('level', $level);

        return $parentId
            ? $query->where('parent_id', $parentId)
            : $query->whereNull('parent_id');
    }

    private function deleteDescendants($menu)
    {
        foreach ($menu->children as $child) {
            $this->deleteDescendants($child);
            $child->delete();
        }
    }

    private function getDescendantIds($menu)
    {
        $ids = [];
        foreach ($menu->children as $child) {
            $ids[] = $child->id;
            $ids   = array_merge($ids, $this->getDescendantIds($child));
        }
        return $ids;
    }

    /**
     * Editing a menu changes what an unknown number of people see, so every
     * built sidebar is thrown away. The walk lives on the model, beside the
     * cache key it has to match.
     */
    private function clearMenuCache()
    {
        User::forgetAllMenuCaches();
    }
}
