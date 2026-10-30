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
