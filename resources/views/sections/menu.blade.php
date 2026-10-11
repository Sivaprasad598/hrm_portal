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

@if ($databaseMenus === null)
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
