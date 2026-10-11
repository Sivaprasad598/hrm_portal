@php $pageTitle = 'app.menu.identityCard'; @endphp
@extends('layouts.app')

@section('content')
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body p-0">
                    <iframe
                        src="{{ route('legacy.page', 'identity') }}"
                        class="w-100"
                        style="min-height: calc(100vh - 220px); border: 0;"
                        title="@lang('app.menu.identityCard')">
                    </iframe>
                </div>
            </div>
        </div>
    </div>
@endsection
