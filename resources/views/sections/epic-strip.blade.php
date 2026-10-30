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
               data-toggle="tooltip" data-placement="right" title="{{ $label }}">
                <i class="{{ $icon }}"></i>
            </a>
        @endforeach
    </div>

    <div class="epic-strip__bottom">
        {{-- Settings only for those the sidebar offers it to: not the self-service
             (employee) menu, whose profile is the picture below --}}
        @unless ($selfService)
        <a href="{{ $settingsUrl }}" class="epic-strip__link {{ (request()->is('account/settings*') || request()->routeIs('company-settings.*')) && request('view') !== 'profile' ? 'active' : '' }}"
           data-toggle="tooltip" data-placement="right" title="@lang('app.menu.settings')">
            <i class="bi bi-gear"></i>
        </a>
        @endunless
        <a href="{{ route('profile-settings.index', ['view' => 'profile']) }}" class="epic-strip__avatar {{ request('view') === 'profile' ? 'active' : '' }}"
           data-toggle="tooltip" data-placement="right" title="{{ user()->name }}">
            <img src="{{ user()->image_url }}" alt="">
        </a>
        <a href="javascript:;" class="epic-strip__link" id="epic-menu-toggle"
           data-toggle="tooltip" data-placement="right" title="Show / hide menu">
            <i class="bi bi-text-left"></i>
        </a>
    </div>
</div>
