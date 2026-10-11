@php
    $pageTitle = 'app.menu.leaveManagement';
    $iframeSrc = route('legacy.page', 'leave_management') . '?' . http_build_query([
        'company_id' => company()->id,
        'user_id' => user()->id,
    ]);
@endphp
@extends('layouts.app')

@section('content')
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body p-0">
                    <iframe
                        src="{{ $iframeSrc }}"
                        class="w-100"
                        style="min-height: calc(100vh - 220px); border: 0;"
                        title="@lang('app.menu.leaveManagement')">
                    </iframe>
                </div>
            </div>
        </div>
    </div>
@endsection
