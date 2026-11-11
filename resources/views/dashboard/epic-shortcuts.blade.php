{{-- The Epic HR dashboard's shortcut tiles (Users, Holidays, Events, Payroll...
     in the template): the pages this person uses, each checked the way the
     sidebar checks it, so a tile never leads to a page they cannot open. --}}
@php
    $can = fn (string $permission) => in_array(user()->permission($permission), ['all', 'added', 'owned', 'both'], true);
    $modules = user_modules();
    $selfService = \App\Services\Access\AssignedModules::selfService();

    $epicTiles = array_values(array_filter([
        in_array('employees', $modules) && !$selfService && $can('view_employees')
            ? ['Employees', route('employees.index'), 'bi bi-people'] : null,
        in_array('attendance', $modules) && $can('view_attendance')
            ? [$selfService ? 'My Attendance' : 'Attendance', $selfService ? route('attendances.by_member') : route('attendances.index'), 'bi bi-calendar2-week'] : null,
        in_array('leaves', $modules) && $can('view_leave')
            ? [$selfService ? 'My Leaves' : 'Leaves', route('leaves.index'), 'bi bi-calendar-check'] : null,
        in_array('holidays', $modules) && $can('view_holiday')
            ? ['Holidays', route('holidays.index'), 'bi bi-hand-thumbs-up'] : null,
        \App\Services\Access\PayrollAccess::any()
            ? ['Payroll', route('payroll-dashboard.index'), 'bi bi-credit-card'] : null,
        user()->employeeDetail
            ? ['My Payslips', route('my-payslips.index'), 'bi bi-receipt'] : null,
        user()->employeeDetail
            ? ['My Assets', route('employees.show', user()->id) . '?tab=assets', 'bi bi-laptop'] : null,
        ['My Calendar', route('my-calendar.index'), 'bi bi-calendar3'],
    ]));

    // Only pages the person's own menu holds (Settings -> Assign Menus): a page
    // taken off their menu loses its tile too. My Calendar is personal and not a
    // menu entry, so it stays.
    $menuRoutes = [];
    $walkMenu = function ($menus) use (&$walkMenu, &$menuRoutes) {
        foreach ($menus as $m) {
            if ($m->route) { $menuRoutes[] = strtok((string) $m->route, '|?'); }
            $walkMenu($m->children);
        }
    };
    $walkMenu(user()->visibleMenus());
    $tileRoutes = [
        'Employees' => ['employees.index'], 'Attendance' => ['attendances.index'], 'My Attendance' => ['attendances.by_member'],
        'Leaves' => ['leaves.index', 'leaves.personal'], 'My Leaves' => ['leaves.personal', 'leaves.index'],
        'Holidays' => ['holidays.index'], 'Payroll' => ['payroll-dashboard.index', 'payroll-run.index'],
        'My Payslips' => ['my-payslips.index'], 'My Assets' => ['employees.show'],
    ];
    $epicTiles = array_values(array_filter($epicTiles, fn ($t) => !isset($tileRoutes[$t[0]])
        || array_intersect($tileRoutes[$t[0]], $menuRoutes)));

    $epicTiles = array_slice($epicTiles, 0, 6);
@endphp

@if ($epicTiles)
    <div class="epic-shortcuts mb-4">
        @foreach ($epicTiles as [$label, $url, $icon])
            <a href="{{ $url }}" class="epic-shortcut">
                <span class="epic-shortcut__icon"><i class="{{ $icon }}"></i></span>
                <span class="epic-shortcut__label">{{ $label }}</span>
            </a>
        @endforeach
    </div>
@endif
