{{-- Tabs across the top of a grouped page: the other pages of its group that this
     person can open (App\Services\Access\PageGroups). The sidebar shows the group
     as one entry, so this is how its pages are reached. --}}
@php
    $pageTabs = \App\Services\Access\PageGroups::tabsFor(request()->route()?->getName());
@endphp

@if ($pageTabs)
    <style>
        .page-tabs { display: flex; gap: 6px; padding: 12px 28px 0; background: #fff; border-bottom: 1px solid #e8eef3; overflow-x: auto; }
        .page-tabs a { padding: 8px 14px 10px; font-size: 14px; color: #667085; white-space: nowrap; border-bottom: 2px solid transparent; }
        .page-tabs a:hover { color: #1d2939; text-decoration: none; }
        .page-tabs a.active { color: #1d82f5; border-bottom-color: #1d82f5; font-weight: 500; }
        @media (max-width: 991.98px) { .page-tabs { padding: 8px 16px 0; } }
    </style>

    <nav class="page-tabs" aria-label="Related pages">
        @foreach ($pageTabs as $tab)
            <a href="{{ $tab['url'] }}" class="{{ request()->routeIs($tab['route']) ? 'active' : '' }}"
               @if (request()->routeIs($tab['route'])) aria-current="page" @endif>{{ $tab['label'] }}</a>
        @endforeach
    </nav>
@endif
