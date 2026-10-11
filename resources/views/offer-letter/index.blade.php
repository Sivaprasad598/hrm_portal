@php $pageTitle = 'app.menu.offerLetter'; @endphp
@extends('layouts.app')

@section('content')
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body p-0">
                    <iframe
                        src="{{ route('legacy.page', 'offer_letter') . (request()->getQueryString() ? '?' . request()->getQueryString() : '') }}"
                        class="w-100"
                        style="min-height: calc(100vh - 220px); border: 0;"
                        title="@lang('app.menu.offerLetter')">
                    </iframe>
                </div>
            </div>
        </div>
    </div>
@endsection
