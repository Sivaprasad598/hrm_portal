@php
    $pageTitle = 'Leave Balances';
    $iframeSrc = route('legacy.page', 'leave_management') . '?' . http_build_query([
        'company_id' => company()->id,
        'user_id' => user()->id,
    ]);
@endphp
@extends('layouts.app')

@section('content')
    {{-- On the grey page like the other pages: no white card around the frame --}}
    <div class="content-wrapper">
        <iframe
            src="{{ $iframeSrc }}"
            class="w-100 d-block"
            style="min-height: calc(100vh - 160px); border: 0; background: transparent;"
            title="@lang('app.menu.leaveManagement')">
        </iframe>
    </div>
@endsection
