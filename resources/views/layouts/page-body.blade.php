{{-- What changes from page to page inside the main container: the tab row, the page's
     filters and its content. Used by the full layout (layouts.app) and by the slim
     answer for a content swap (layouts.swap), so the two can never differ.

     The page's inline scripts go through PageScripts::globalize(): their top-level
     const / let are written as var, so the same script can run again when the page
     is opened a second time without a full reload. --}}

{{-- Tabs between related pages, shown as one entry in the sidebar --}}
@include('sections.page-tabs')

{!! \App\Support\PageScripts::globalize($__env->yieldContent('filter-section')) !!}

<x-app-title class="d-block d-lg-none" :pageTitle="$pageTitle"></x-app-title>

{!! \App\Support\PageScripts::globalize($__env->yieldContent('content')) !!}

{{-- Assets and Work: "Coming soon" over the page for now --}}
@include('sections.coming-soon')
@include('sections.hr-filter-layout')
