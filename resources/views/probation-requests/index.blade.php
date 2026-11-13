@extends('layouts.app')

@section('content')
    {{-- same grey page area as the Divisions page (no white card around the frame) --}}
    <div class="content-wrapper">
        <iframe
            src="{{ route('legacy.page', 'probation_period_request') }}"
            class="w-100 d-block"
            style="min-height: calc(100vh - 200px); border: 0; background: transparent;"
            title="@lang('app.menu.probationPeriodRequests')">
        </iframe>
    </div>
@endsection
