{{-- The Epic HR colours chosen under Settings > Theme Settings, over the
     stylesheets' own; nothing when the template's are in use --}}
@php($epicColorsCss = \App\Services\Theme\EpicColors::css())
@if ($epicColorsCss)
    <style id="epic-colors">{!! $epicColorsCss !!}</style>
@endif
