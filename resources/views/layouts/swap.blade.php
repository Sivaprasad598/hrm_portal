{{-- The answer to a content swap (a request with "X-Page-Swap: 1", made by
     sections/page-swap when a sidebar / tab link is clicked): only what differs
     from page to page. The browser keeps the sidebar, the top bar and every
     loaded script, puts this content in the main container and runs the page's
     scripts. Anything it cannot use (no #page-swap in the answer) is opened as a
     normal page instead.

     Each part mirrors its place in layouts.app. --}}
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ is_array(__($pageTitle)) ? $pageTitle : __($pageTitle) }}</title>
</head>
<body>
<div id="page-swap">
    {{-- the page's own styles: where @stack('datatable-styles') and @stack('styles') sit in the head --}}
    <div id="ps-styles-early">@stack('datatable-styles')</div>
    <div id="ps-styles">
        @isset($activeSettingMenu)
            <style>
                .preloader-container { margin-left: 510px; width: calc(100% - 510px) }
                .blur-code { filter: blur(3px); }
                .purchase-code { transition: filter .2s ease-out; margin-right: 4px; }
                .ql-editor { text-align: left; white-space: unset; }
            </style>
        @endisset
        @stack('styles')
    </div>

    {{-- the title in the top bar (with its back arrow on detail pages) --}}
    <div id="ps-title"><x-app-title class="d-none d-lg-flex" :pageTitle="$pageTitle"></x-app-title></div>

    {{-- the menu and the icon strip, for their highlights: worked out here exactly as on a full page --}}
    <div id="ps-menu">@include('sections.menu')</div>
    <div id="ps-strip">@unless (user()->dark_theme)@include('sections.epic-strip')@endunless</div>

    <div id="ps-content">@include('layouts.page-body')</div>

    <div id="ps-scripts">{!! \App\Support\PageScripts::globalize($__env->yieldPushContent('scripts')) !!}</div>
</div>
</body>
</html>
