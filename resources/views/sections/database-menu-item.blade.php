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

    // A route field may carry the person and a query: "employees.show|{me}?tab=assets",
    // "attendances.by_member?user_id={me}" ({me} = the signed-in person)
    $parseRoute = function (?string $route) {
        $route = (string) $route;
        $query = [];
        if (str_contains($route, '?')) {
            [$route, $qs] = explode('?', $route, 2);
            parse_str(str_replace('{me}', (string) user()->id, $qs), $query);
        }
        $params = [];
        if (str_contains($route, '|')) {
            [$route, $ps] = explode('|', $route, 2);
            $params = array_map(fn ($p) => str_replace('{me}', (string) user()->id, trim($p)), explode(',', $ps));
        }

        return [$route, $params, $query];
    };

    $resolveUrl = function ($item) use ($routeFallbacks, $parseRoute) {
        [$route, $params, $query] = $parseRoute($item->route);

        if ($route && \Illuminate\Support\Facades\Route::has($route)) {
            try {
                return route($route, $params) . ($query ? '?' . http_build_query($query) : '');
            } catch (\Throwable $e) {
                return null;
            }
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

    $isItemActive = function ($item) use ($resolveUrl, $currentRouteName, $currentUrl, $parseRoute) {
        // A group's entry stands for all its pages (App\Services\Access\PageGroups).
        if ($item->group_routes) {
            return \App\Services\Access\PageGroups::routeIsWithin($item->group_routes);
        }

        [$route, $params, $query] = $parseRoute($item->route);

        if ($route && $route === $currentRouteName) {
            // Same page: the person and the query must match too (My Attendance is
            // by_member?user_id=me; Team Attendance's people are by_member of others)
            $routeParams = array_values(request()->route()?->parameters() ?? []);
            foreach ($params as $i => $p) {
                if ((string) ($routeParams[$i] ?? '') !== (string) $p) {
                    return false;
                }
            }
            foreach ($query as $k => $v) {
                if ((string) request($k) !== (string) $v) {
                    return false;
                }
            }

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
    $menuLabel = $menu->group_label ?? $menu->menu_name ?? $menu->name;
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
        @php
            // What is waiting for this person, on the My Team entries
            $waiting = match ($menu->slug) {
                'team-leaves' => \App\Services\Leaves\LeaveApprovals::waitingFor()->unique(fn ($l) => $l->unique_id ?: 'id' . $l->id)->count(),
                'attendance-requests' => \App\Services\Attendance\CorrectionApprovals::waitingFor()->count(),
                default => 0,
            };
        @endphp
        <a class="{{ $level > 1 ? 'f-13' : 'f-14' }} text-lightest {{ $isActive ? 'active' : '' }}"
           @if ($isActive) data-server-active="1" @endif
           @if ($subLinkStyle) style="{{ $subLinkStyle }}" @endif
           href="{{ $menuUrl ?: 'javascript:;' }}" title="{{ $menuLabel }}">
            @if ($subIcon)<i class="{{ $subIcon }} sidebar-sub-icon" aria-hidden="true"></i>@endif{{ $menuLabel }}
            @if ($waiting)<span class="epic-menu-count" title="Waiting for you">{{ $waiting }}</span>@endif
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
