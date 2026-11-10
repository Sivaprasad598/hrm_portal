{{-- The Epic HR icon strip down the left edge: the logo, shortcuts to the pages
     the person uses most, and at the foot settings, their profile and the menu
     toggle. Every shortcut is checked the way the sidebar checks it, so the
     strip never offers a page the sidebar would not. --}}
@php
    $can = fn (string $permission) => in_array(user()->permission($permission), ['all', 'added', 'owned', 'both'], true);
    $modules = user_modules();
    $selfService = \App\Services\Access\AssignedModules::selfService();

    $shortcuts = array_filter([
        ['Dashboard', route('dashboard'), 'bi bi-speedometer2', request()->routeIs('dashboard*')],
        in_array('employees', $modules) && !$selfService && $can('view_employees')
            ? ['Employees', route('employees.index'), 'bi bi-people', request()->routeIs('employees.*')] : null,
        in_array('attendance', $modules) && $can('view_attendance')
            ? ['Attendance', $selfService ? route('attendances.by_member') : route('attendances.index'), 'bi bi-calendar2-week', request()->routeIs('attendances.*')] : null,
        in_array('leaves', $modules) && $can('view_leave')
            ? ['Leaves', route('leaves.index'), 'bi bi-calendar-check', request()->routeIs('leaves.*')] : null,
        user()->employeeDetail
            ? ['My Payslips', route('my-payslips.index'), 'bi bi-receipt', request()->routeIs('my-payslips.*')] : null,
    ]);

    $settingsUrl = user()->permission('manage_company_setting') === 'all'
        ? route('company-settings.index') : route('profile-settings.index');

    // An employee (no other role) has no menu panel: every page of their menu
    // (Assign Menus decides which) is an icon here instead, plus their calendar.
    $solo = \App\Services\Access\AssignedModules::plainEmployee();
    // Managers (and anyone else outside admin / HR) keep their menu panel, and
    // also get Dashboard + their My Space pages here as icons.
    if ($solo || $selfService) {
        $icons = [
            'dashboard' => 'bi bi-speedometer2', 'my-leaves' => 'bi bi-calendar-check', 'my-attendance' => 'bi bi-calendar2-week',
            'my-payslips-index' => 'bi bi-receipt', 'my-assets' => 'bi bi-laptop', 'my-ot-bank' => 'bi bi-hourglass-split',
            'my-interviews' => 'bi bi-people',
        ];
        $pages = [];
        $walk = function ($menus) use (&$walk, &$pages) {
            foreach ($menus as $m) {
                if ($m->route && $m->children->isEmpty()) { $pages[] = $m; }
                $walk($m->children);
            }
        };
        // Dashboard and the My Space pages (all an employee's menu holds anyway)
        $walk(user()->visibleMenus()->filter(fn ($m) => in_array($m->slug, ['dashboard', 'my-space'], true)
            || \Illuminate\Support\Str::startsWith((string) $m->route, 'dashboard')));

        $here = request()->route()?->getName();
        $shortcuts = [];
        foreach (collect($pages)->unique('route') as $m) {
            // "employees.show|{me}?tab=assets", "attendances.by_member?user_id={me}"
            [$route, $qs] = array_pad(explode('?', str_replace('{me}', (string) user()->id, $m->route), 2), 2, '');
            [$route, $param] = array_pad(explode('|', $route, 2), 2, null);
            parse_str($qs, $query);
            if (!\Illuminate\Support\Facades\Route::has($route)) { continue; }
            try { $url = route($route, $param !== null ? [$param] : []) . ($query ? '?' . http_build_query($query) : ''); } catch (\Throwable $e) { continue; }

            $active = match (true) {
                $route === 'leaves.personal' => request()->routeIs('leaves.*'),
                $route === 'dashboard' => request()->routeIs('dashboard*'),
                isset($query['tab']) => $here === $route && request('tab') === $query['tab'],
                default => $here === $route,
            };
            // The icon by the page it opens (menu slugs vary: "dashboard-1")
            $byPage = [
                'dashboard' => 'bi bi-speedometer2', 'leaves.personal' => 'bi bi-calendar-check', 'attendances.by_member' => 'bi bi-calendar2-week',
                'my-payslips.index' => 'bi bi-receipt', 'my-interviews.index' => 'bi bi-people',
                'employees.show:assets' => 'bi bi-laptop', 'employees.show:ot-bank' => 'bi bi-hourglass-split',
            ];
            $icon = $byPage[$route . (isset($query['tab']) ? ':' . $query['tab'] : '')] ?? $icons[$m->slug] ?? ($m->icon ?: 'bi bi-circle');
            $shortcuts[] = [$route === 'dashboard' ? 'Dashboard' : $m->name, $url, $icon, $active];
        }
        if (\Illuminate\Support\Facades\Route::has('my-calendar.index')) {
            $shortcuts[] = ['My Calendar', route('my-calendar.index'), 'bi bi-calendar3', request()->routeIs('my-calendar.*') || request()->routeIs('events.*')];
        }
    }
@endphp

<div id="epic-strip" class="epic-strip d-none d-lg-flex">
    <div class="epic-strip__top">
        <a href="{{ route('dashboard') }}" class="epic-strip__brand" title="{{ company()->company_name }}">
            {{-- The square mark (Settings > Theme Settings > Favicon): the full,
                 wide logo is unreadable at this size --}}
            <img src="{{ companyOrGlobalSetting()->favicon_url }}" alt="">
        </a>

        @foreach ($shortcuts as [$label, $url, $icon, $active])
            <a href="{{ $url }}" class="epic-strip__link {{ $active ? 'active' : '' }}"
               data-toggle="tooltip" data-placement="right" data-boundary="window" title="{{ $label }}">
                <i class="{{ $icon }}"></i>
            </a>
        @endforeach
    </div>

    <div class="epic-strip__bottom">
        {{-- Settings only for those the sidebar offers it to: not the self-service
             (employee) menu, whose profile is the picture below --}}
        @unless ($selfService)
        <a href="{{ $settingsUrl }}" class="epic-strip__link {{ (request()->is('account/settings*') || request()->routeIs('company-settings.*')) && request('view') !== 'profile' ? 'active' : '' }}"
           data-toggle="tooltip" data-placement="right" data-boundary="window" title="@lang('app.menu.settings')">
            <i class="bi bi-gear"></i>
        </a>
        @endunless
        <a href="{{ route('profile-settings.index', ['view' => 'profile']) }}" class="epic-strip__avatar {{ request('view') === 'profile' ? 'active' : '' }}"
           data-toggle="tooltip" data-placement="right" data-boundary="window" title="{{ user()->name }}">
            <img src="{{ user()->image_url }}" alt="">
        </a>
        @unless ($solo)
        <a href="javascript:;" class="epic-strip__link" id="epic-menu-toggle"
           data-toggle="tooltip" data-placement="right" data-boundary="window" title="Show / hide menu">
            <i class="bi bi-text-left"></i>
        </a>
        @endunless
    </div>
</div>
