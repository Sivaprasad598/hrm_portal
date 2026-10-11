{{-- ============================================================
     HIERARCHICAL MENU NODE
     Main menu → toggles to show submenus
     Submenu → toggles to show child menus
     ============================================================ --}}

<div class="menu-node" data-menu-id="{{ $menu->id }}">

    {{-- ==================== MAIN MENU HEADER ==================== --}}
    <div class="menu-node-header">
        <div class="menu-node-left">
            <i class="menu-toggle-icon fa fa-chevron-right"></i>

            <div class="menu-icon-box">
                <i class="{{ $menu->display_icon }}"></i>
            </div>

            <div class="menu-info">
                <div class="menu-name">
                    {{ $menu->name }}
                    <span class="menu-badge badge-main">Main</span>
                    <span class="text-muted ml-1" style="font-size: 11px;">#{{ $menu->sort_order }}</span>
                </div>
                <div class="menu-meta">
                    @if ($menu->route)
                        <code>{{ $menu->route }}</code>
                    @endif
                    @if ($menu->children->count() > 0)
                        <span class="ml-2">
                            <i class="fa fa-sitemap"></i>
                            {{ $menu->children->count() }} {{ Str::plural('sub menu', $menu->children->count()) }}
                        </span>
                    @endif
                </div>
            </div>
        </div>

        <div class="menu-actions">
            <button type="button" class="btn-icon btn-add add-child-btn"
                    data-parent-id="{{ $menu->id }}" title="Add submenu">
                <i class="fa fa-plus"></i>
            </button>
            <button type="button" class="btn-icon btn-edit edit-menu-btn"
                    data-id="{{ $menu->id }}" title="Edit">
                <i class="fa fa-pencil-alt"></i>
            </button>
            <button type="button" class="btn-icon btn-delete delete-menu-btn"
                    data-id="{{ $menu->id }}" title="Delete">
                <i class="fa fa-trash"></i>
            </button>
        </div>
    </div>

    {{-- ==================== SUBMENUS ==================== --}}
    @if ($menu->children->count() > 0)
        <div class="menu-children">
            @foreach ($menu->children->sortBy('sort_order') as $subMenu)
                <div class="sub-node" data-menu-id="{{ $subMenu->id }}">

                    <div class="sub-node-header">
                        <div class="menu-node-left">
                            <i class="menu-toggle-icon fa fa-chevron-right"></i>

                            <div class="menu-icon-box level-1">
                                <i class="{{ $subMenu->display_icon }}"></i>
                            </div>

                            <div class="menu-info">
                                <div class="menu-name">
                                    {{ $subMenu->name }}
                                    <span class="menu-badge badge-sub">Sub</span>
                                    <span class="text-muted ml-1" style="font-size: 11px;">#{{ $subMenu->sort_order }}</span>
                                </div>
                                <div class="menu-meta">
                                    @if ($subMenu->route)
                                        <code>{{ $subMenu->route }}</code>
                                    @endif
                                    @if ($subMenu->children->count() > 0)
                                        <span class="ml-2">
                                            <i class="fa fa-list"></i>
                                            {{ $subMenu->children->count() }} {{ Str::plural('child', $subMenu->children->count()) }}
                                        </span>
                                    @endif
                                </div>
                            </div>
                        </div>

                        <div class="menu-actions">
                            <button type="button" class="btn-icon btn-add add-child-btn"
                                    data-parent-id="{{ $subMenu->id }}" title="Add child menu">
                                <i class="fa fa-plus"></i>
                            </button>
                            <button type="button" class="btn-icon btn-edit edit-menu-btn"
                                    data-id="{{ $subMenu->id }}" title="Edit">
                                <i class="fa fa-pencil-alt"></i>
                            </button>
                            <button type="button" class="btn-icon btn-delete delete-menu-btn"
                                    data-id="{{ $subMenu->id }}" title="Delete">
                                <i class="fa fa-trash"></i>
                            </button>
                        </div>
                    </div>

                    {{-- ==================== CHILD MENUS ==================== --}}
                    @if ($subMenu->children->count() > 0)
                        <div class="child-children">
                            @foreach ($subMenu->children->sortBy('sort_order') as $childMenu)
                                <div class="child-node" data-menu-id="{{ $childMenu->id }}">
                                    <div class="menu-node-left">
                                        <div class="menu-icon-box level-2">
                                            <i class="{{ $childMenu->display_icon }}"></i>
                                        </div>

                                        <div class="menu-info">
                                            <div class="menu-name child">
                                                {{ $childMenu->name }}
                                                <span class="menu-badge badge-child">Child</span>
                                                <span class="text-muted ml-1" style="font-size: 10px;">#{{ $childMenu->sort_order }}</span>
                                            </div>
                                            @if ($childMenu->route)
                                                <div class="menu-meta">
                                                    <code>{{ $childMenu->route }}</code>
                                                </div>
                                            @endif
                                        </div>
                                    </div>

                                    <div class="menu-actions">
                                        <button type="button" class="btn-icon btn-edit edit-menu-btn"
                                                data-id="{{ $childMenu->id }}" title="Edit">
                                            <i class="fa fa-pencil-alt"></i>
                                        </button>
                                        <button type="button" class="btn-icon btn-delete delete-menu-btn"
                                                data-id="{{ $childMenu->id }}" title="Delete">
                                            <i class="fa fa-trash"></i>
                                        </button>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif

                </div>
            @endforeach
        </div>
    @endif

</div>
