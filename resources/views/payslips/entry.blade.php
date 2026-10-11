@php $pageTitle = 'app.menu.payslipEntry'; @endphp
@extends('layouts.app')

@section('content')
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body p-0">
                    <iframe
                        src="{{ route('legacy.page', 'payslip_entry') }}"
                        class="w-100"
                        style="min-height: calc(100vh - 220px); border: 0;"
                        title="@lang('app.menu.payslipEntry')">
                    </iframe>
                </div>
            </div>
        </div>
    </div>
@endsection
