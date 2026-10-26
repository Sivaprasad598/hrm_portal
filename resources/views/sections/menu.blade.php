@php
    // The sidebar is data now: sidebar_menus for the entries, role_menus for
    // who may see them, and the user's own permissions on top of that.
    //
    // Until the tree is seeded, fall back to the hand-written sidebar that was
    // here before — kept verbatim as sections/menu-legacy. That way there is
    // never a moment where somebody is looking at an empty sidebar, and
    // emptying the table is a safe way back.
    $databaseMenus = \App\Models\SidebarMenu::forCompany(company()?->id)
        ->where('is_active', 1)
        ->exists()
            ? user()->visibleMenus()
            : null;
@endphp

@php
    // Employees (anyone without a managing role) get four single links, no
    // groups: their dashboard, leave, payslips and the assets they hold.
    $selfService = !array_intersect(['admin', 'hr', 'manager_admin', 'ceo', 'cfo', 'client'], user_roles());
    $selfLinks = !$selfService ? [] : array_filter([
        ['Dashboard', route('dashboard'), 'bi bi-house', request()->routeIs('dashboard')],
        in_array('leaves', user_modules()) && user()->permission('view_leave') !== 'none' && user()->permission('view_leave') !== false
            ? ['Leaves', route('leaves.index'), 'bi bi-calendar-check', request()->routeIs('leaves.*')] : null,
        ['My Payslips', route('my-payslips.index'), 'bi bi-receipt', request()->routeIs('my-payslips.*')],
        user()->employeeDetail ? ['My Assets', route('employees.show', user()->id) . '?tab=assets', 'bi bi-laptop',
            request()->routeIs('employees.show') && request('tab') === 'assets'] : null,
    ]);
@endphp

@if ($selfService)
    <ul class="list-unstyled p-0 m-0">
        @foreach ($selfLinks as [$label, $url, $icon, $active])
            <li class="accordionItem closeIt">
                <a class="nav-item text-lightest f-15 sidebar-text-color {{ $active ? 'active' : '' }}" href="{{ $url }}" title="{{ $label }}">
                    <i class="side-icon {{ $icon }}" aria-hidden="true"></i>
                    <span class="pl-3">{{ $label }}</span>
                </a>
            </li>
        @endforeach
    </ul>
@elseif ($databaseMenus === null)
    @include('sections.menu-legacy')
@else
    <ul class="list-unstyled p-0 m-0">
        {{-- ==================== DATABASE MENUS ==================== --}}
        @forelse ($databaseMenus as $databaseMenu)
            @include('sections.database-menu-item', ['menu' => $databaseMenu, 'level' => 0])
        @empty
            {{-- Granted nothing, or permitted nothing. Everyone can still reach
                 their own dashboard, so the sidebar is never truly empty. --}}
            <li class="accordionItem closeIt">
                <a class="nav-item text-lightest f-15 sidebar-text-color" href="{{ route('dashboard') }}" title="@lang('app.menu.dashboard')">
                    <i class="side-icon fa fa-home"></i>
                    <span class="pl-3">@lang('app.menu.dashboard')</span>
                </a>
            </li>
        @endforelse

        {{-- ==================== CUSTOM LINKS ==================== --}}
        @php $role = user()->role->last(); @endphp

        @foreach ($customLink as $item)
            @if ($role && (in_array($role->role_id, (array) json_decode($item->can_be_viewed_by)) || in_array('admin', user_roles())) && $item->status == 'active')
                <li class="accordionItem closeIt">
                    <a class="nav-item text-lightest f-15 sidebar-text-color" href="{{ $item->url }}" target="_blank" title="{{ $item->link_title }}">
                        <i class="side-icon fa fa-link"></i>
                        <span class="pl-3">{{ $item->link_title }}</span>
                    </a>
                </li>
            @endif
        @endforeach

        {{-- ==================== AROWANA PLUGINS ==================== --}}
        @foreach ($arowanaPlugins as $item)
            @includeIf(strtolower($item) . '::sections.sidebar')
        @endforeach
    </ul>
@endif
