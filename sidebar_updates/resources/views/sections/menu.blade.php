@php
    // ⭐ Force-clear the sidebar menu cache so fresh data loads
    cache()->forget('sidebar_menus_' . user()->id);
    cache()->forget('sidebar_menu_slugs_' . user()->id);

    $isAdmin = in_array('admin', user_roles());
    // Admin: every active menu. Other roles: only the menus assigned to their role(s) in role_menus.
    $adminMenuRoots = $isAdmin ? user()->getAccessibleMenus() : user()->getRoleAssignedMenus();
@endphp


<ul class="list-unstyled p-0 m-0">
    {{-- ==================== DATABASE MENUS ==================== --}}
    @forelse ($adminMenuRoots as $databaseMenu)
        @include('sections.database-menu-item', ['menu' => $databaseMenu])
    @empty
        <li class="accordionItem closeIt">
            <a class="nav-item text-lightest f-15 sidebar-text-color" href="{{ route('dashboard') }}" title="Dashboard">
                <i class="side-icon fa fa-home"></i>
                <span class="pl-3">Dashboard</span>
            </a>
        </li>
    @endforelse

    {{-- ==================== CUSTOM LINKS ==================== --}}
    @php $role = user()->role->last(); @endphp

    @foreach ($customLink as $item)
        @if ((in_array($role->role_id, json_decode($item->can_be_viewed_by)) || in_array('admin', user_roles())) && $item->status == 'active')
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
