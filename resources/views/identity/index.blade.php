@php $pageTitle = 'app.menu.identityCard'; @endphp
@extends('layouts.app')

@section('content')
    {{-- standard page: the grey page area, the form as its own white card (inside the frame).
         The frame follows its content (sections.legacy-frame), an open dropdown list included. --}}
    <div class="content-wrapper">
        <iframe
            src="{{ route('legacy.page', 'identity') }}"
            class="w-100 d-block"
            style="border: 0; background: transparent;"
            title="@lang('app.menu.identityCard')">
        </iframe>
    </div>
@endsection
