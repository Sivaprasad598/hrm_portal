{{-- ============================================================
     DATABASE SIDEBAR MENU ITEM
     Uses the original admin sidebar markup (accordionItem / nav-item /
     accordionItemContent) so it is styled by sidebar.scss + theme_css
     and toggled by the accordion script in main.js.
     ============================================================ --}}
@php
    $routeFallbacks = [
        'my-calendar' => 'my-calendar.index',
        'clients' => 'clients.index',
        'employees' => 'employees.index',
        'employee-exit' => 'employee-exit.index',
        'employee-termination' => 'employee-termination.index',
        'employee-resignation' => 'resignation-management.index',
        'probation-period-requests' => 'probation-requests.index',
        'resignation' => 'resignations.index',
        'leaves' => 'leaves.index',
        'leave-management' => 'leave-management.index',
        'leave-year-end' => 'leave-year-end.index',
        'identity-card' => 'identity.index',
        'payslips' => 'payslips.index',
        'shift-roster' => 'shifts.index',
        'attendance' => 'attendances.index',
        'holiday' => 'holidays.index',
        'tickets' => 'tickets.index',
        'events' => 'events.index',
        'messages' => 'messages.index',
        'notice-board' => 'notices.index',
        'settings' => user()->permission('manage_company_setting') == 'all'
            ? 'company-settings.index'
            : 'profile-settings.index',
    ];

    // Default Bootstrap Icons for main menus that have no icon set in the DB
    $defaultIcons = [
        'dashboard' => 'bi bi-house',
        'my-calendar' => 'bi bi-calendar',
        'leads' => 'bi bi-person',
        'clients' => 'bi bi-people',
        'hr' => 'bi bi-person-badge',
        'biometric' => 'bi bi-fingerprint',
        'work' => 'bi bi-briefcase',
        'finance' => 'bi bi-cash-coin',
        'tickets' => 'bi bi-ticket-perforated',
        'events' => 'bi bi-calendar-event',
        'messages' => 'bi bi-chat-left-text',
        'notice-board' => 'bi bi-pin-angle',
        'reports' => 'bi bi-bar-chart',
        'settings' => 'bi bi-gear',
    ];

    $resolveUrl = function ($item) use ($routeFallbacks) {
        $route = $item->route;

        if ($route && \Illuminate\Support\Facades\Route::has($route)) {
            return route($route);
        }

        if ($route && filter_var($route, FILTER_VALIDATE_URL)) {
            return $route;
        }

        if ($route) {
            return url(preg_replace('#^/?public/#', '', ltrim($route, '/')));
        }

        $fallbackRoute = $routeFallbacks[$item->slug] ?? ($item->slug . '.index');

        return \Illuminate\Support\Facades\Route::has($fallbackRoute) ? route($fallbackRoute) : null;
    };

    $currentRouteName = request()->route() ? request()->route()->getName() : null;
    $currentUrl = url()->current();

    $isItemActive = function ($item) use ($resolveUrl, $currentRouteName, $currentUrl) {
        // A group's entry stands for all its pages (App\Services\Access\PageGroups).
        if ($item->group_routes) {
            return \App\Services\Access\PageGroups::routeIsWithin($item->group_routes);
        }

        if ($item->route && $item->route === $currentRouteName) {
            return true;
        }

        $url = $resolveUrl($item);

        return $url && rtrim($url, '/') === rtrim($currentUrl, '/');
    };

    $isTreeActive = function ($item) use (&$isTreeActive, $isItemActive) {
        if ($isItemActive($item)) {
            return true;
        }

        foreach ($item->children as $child) {
            if ($isTreeActive($child)) {
                return true;
            }
        }

        return false;
    };

    $level = $level ?? 0;

    $menuUrl = $menu->group_url ?: $resolveUrl($menu);
    $menuLabel = $menu->group_label ?? \App\Services\Access\PageGroups::RENAMED[$menu->slug] ?? $menu->menu_name ?? $menu->name;
    $menuIcon = $menu->icon ? $menu->display_icon : ($defaultIcons[$menu->slug] ?? 'bi bi-circle');

    // Filter here too: levels below children.children are lazy-loaded without the is_active constraint
    $children = $menu->children
        ->filter(fn ($child) => (int) $child->is_active === 1)
        ->sortBy(function ($child) {
            return (int) ($child->sort_order ?? 0);
        })
        ->values();

    // Related pages are one entry here and tabs on the page (App\Services\Access\PageGroups).
    if ($level === 0) {
        $children = \App\Services\Access\PageGroups::collapse($children);
    }

    $isActive = $isItemActive($menu);
    $isOpen = $isTreeActive($menu);

    // Dashboard is one link to the private dashboard; only the Super Admin also
    // has the Advanced Dashboard, so only they get the two as a sub-menu.
    if ($level === 0 && $menu->slug === 'dashboard' && !in_array('admin', user_roles())) {
        $menuUrl = route('dashboard');
        $isActive = request()->routeIs('dashboard');
        $isOpen = $isActive;
        $children = collect();
    }

    // Settings is a single link: its pages are listed in the settings page's own sidebar
    // (components/setting-sidebar). Its DB children are kept only for menu assignment.
    if ($level === 0 && $menu->slug === 'settings') {
        $menuUrl = route($routeFallbacks['settings']);
        $isActive = $isOpen;
        $children = collect();
    }
@endphp

@if ($level === 0)
    {{-- Top level: class must stay exactly "accordionItem openIt|closeIt" for the toggle in main.js --}}
    <li class="accordionItem {{ $isOpen ? 'openIt' : 'closeIt' }}" data-menu-id="{{ $menu->id }}">
        @if ($children->isNotEmpty())
            <a class="nav-item text-lightest f-15 sidebar-text-color accordionItemHeading {{ $isOpen ? 'active' : '' }}"
               title="{{ $menuLabel }}">
                <i class="side-icon {{ $menuIcon }}" aria-hidden="true"></i>
                <span class="pl-3">{{ $menuLabel }}</span>
            </a>

            <div class="accordionItemContent pb-2">
                @foreach ($children as $childMenu)
                    @include('sections.database-menu-item', ['menu' => $childMenu, 'level' => 1])
                @endforeach
            </div>
        @else
            <a class="nav-item text-lightest f-15 sidebar-text-color {{ $isActive ? 'active' : '' }}"
               @if ($menuUrl) href="{{ $menuUrl }}" @endif
               title="{{ $menuLabel }}">
                <i class="side-icon {{ $menuIcon }}" aria-hidden="true"></i>
                <span class="pl-3">{{ $menuLabel }}</span>
            </a>
        @endif
    </li>
@else
    {{-- Sub levels use the theme's sub-menu link style; deeper levels get extra indent --}}
    {{-- Inline !important is needed: theme-custom.css forces "margin: 2px 10px !important" on these links --}}
    @php
        $subLinkStyle = $level > 1
            ? 'margin-left: ' . (10 + ($level - 1) * 22) . 'px !important; border-left: 2px solid rgba(148, 163, 184, .45);'
            : '';

        // Submenus/children show an icon only when one is set in Menu Management
        $subIcon = $menu->icon ? $menu->display_icon : null;
    @endphp

    @if ($children->isNotEmpty())
        {{-- Submenu with its own children: acts as a dropdown toggle --}}
        <a class="{{ $level > 1 ? 'f-13' : 'f-14' }} text-lightest sidebar-submenu-toggle d-flex align-items-center justify-content-between {{ $isOpen ? 'active is-open' : '' }}"
           style="{{ $subLinkStyle }}"
           href="javascript:;" title="{{ $menuLabel }}" aria-expanded="{{ $isOpen ? 'true' : 'false' }}">
            <span>
                @if ($subIcon)<i class="{{ $subIcon }} sidebar-sub-icon" aria-hidden="true"></i>@endif{{ $menuLabel }}
            </span>
            <i class="bi bi-chevron-right sidebar-submenu-arrow" aria-hidden="true"></i>
        </a>

        <div class="sidebar-submenu-children" @unless ($isOpen) style="display: none;" @endunless>
            @foreach ($children as $childMenu)
                @include('sections.database-menu-item', ['menu' => $childMenu, 'level' => $level + 1])
            @endforeach
        </div>
    @else
        <a class="{{ $level > 1 ? 'f-13' : 'f-14' }} text-lightest {{ $isActive ? 'active' : '' }}"
           @if ($subLinkStyle) style="{{ $subLinkStyle }}" @endif
           href="{{ $menuUrl ?: 'javascript:;' }}" title="{{ $menuLabel }}">
            @if ($subIcon)<i class="{{ $subIcon }} sidebar-sub-icon" aria-hidden="true"></i>@endif{{ $menuLabel }}
        </a>
    @endif
@endif

@once
    <style>
        .sidebar-submenu-toggle .sidebar-submenu-arrow {
            font-size: 11px;
            transition: transform .2s ease;
        }

        .sidebar-submenu-toggle.is-open .sidebar-submenu-arrow {
            transform: rotate(90deg);
        }

        .sidebar-sub-icon {
            width: 16px;
            margin-right: 8px;
            font-size: 12px;
            text-align: center;
        }
    </style>

    <script>
        document.addEventListener('click', function (e) {
            var toggle = e.target.closest('.sidebar-submenu-toggle');
            if (!toggle) return;

            e.preventDefault();
            var children = toggle.nextElementSibling;
            var open = children.style.display === 'none';

            children.style.display = open ? '' : 'none';
            toggle.classList.toggle('is-open', open);
            toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        });
    </script>
@endonce
