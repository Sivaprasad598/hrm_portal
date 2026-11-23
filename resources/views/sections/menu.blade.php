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

{{-- Every role's sidebar is Settings > Assign Menus, with the person's permissions on top.
     (My Space and My Team are menu entries too; employees had a fixed list that ignored it.) --}}
{{-- the "Soon" tag on a module marked Coming soon (Settings -> Menu Access -> Modules) --}}
<style>
    .cs-soon { position: relative; overflow: hidden; display: inline-flex; align-items: center; gap: 4px; margin-left: 8px; vertical-align: middle;
        padding: 2px 8px 2px 6px; border-radius: 999px; font-size: 9.5px; font-weight: 700; letter-spacing: .06em; line-height: 1.5;
        text-transform: uppercase; color: #fff !important;
        background: linear-gradient(135deg, var(--epic-blue, #cb6b4f), color-mix(in srgb, var(--epic-blue, #cb6b4f) 55%, #f79009));
        box-shadow: 0 2px 6px color-mix(in srgb, var(--epic-blue, #cb6b4f) 35%, transparent); }
    .cs-soon i { font-size: 8.5px; color: #fff !important; transform: rotate(-12deg); }
    .cs-soon::after { content: ''; position: absolute; top: 0; left: -60%; width: 40%; height: 100%; transform: skewX(-20deg);
        background: linear-gradient(90deg, transparent, rgba(255, 255, 255, .55), transparent); animation: cs-soon-shine 3.2s ease-in-out infinite; }
    @keyframes cs-soon-shine { 0%, 60% { left: -60%; } 100% { left: 130%; } }
    @media (prefers-reduced-motion: reduce) { .cs-soon::after { animation: none; display: none; } }
    .sidebar-mini .cs-soon, .sidebar-collapsed .cs-soon { display: none; }
</style>

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
