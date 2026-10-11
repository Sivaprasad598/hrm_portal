@extends('layouts.app')

@section('content')
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <iframe
                        id="reductions-iframe"
                        src="{{ route('legacy.page', 'reductions') }}"
                        class="w-100"
                        style="min-height: calc(100vh - 220px); border: 0;"
                        title="@lang('app.menu.salaryCalculator')">
                    </iframe>
                </div>
            </div>
        </div>
    </div>
@endsection
