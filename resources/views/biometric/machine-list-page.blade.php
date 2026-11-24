@extends('layouts.app')

{{-- The device-list cross-check opened on its own (a refresh, or its link): the same
     content as the side panel on Employee Mapping. --}}
@section('content')
    <div class="content-wrapper arw-dash" data-swap-links>
        @include('biometric.nav', ['active' => $activeTab])
        <div class="mb-3"><a href="{{ route('biometric.employees') }}" class="f-14"><i class="fa fa-arrow-left mr-1"></i> Employee Mapping</a></div>
        <div class="bg-white rounded" style="box-shadow: 0 1px 3px rgba(16,24,40,.06)">
            @include('biometric.ajax.machine-list-check')
        </div>
    </div>
@endsection
