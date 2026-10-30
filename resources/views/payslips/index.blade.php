@php
    $pageTitle = 'Payslip Calculator';
    // Whoever runs payroll gets the calculator and single payslip generation.
    // Anyone else is sent to their own payslips before this page is reached
    // (LegacyController::payslips); the locked slip below is the fallback.
    $userRole = \App\Services\Access\PayrollAccess::can('prepare')
        ? 'admin'
        : 'employee';
    $employeeParams = [
        'emp_name' => user()->name,
        'emp_id' => optional(user()->employeeDetail)->employee_card_id ?: '',
        'emp_designation' => optional(optional(user()->employeeDetail)->designation)->name ?: '',
        'user_role' => $userRole,
    ];
    // Salary Calculator: every input of the salary formula and the slip it makes.
    // Generate One Payslip: one employee, one month, issued from the same formula.
    $calculatorSrc = $userRole === 'admin'
        ? route('legacy.page', 'reductions')
        : route('legacy.page', 'payslips') . '?' . http_build_query(array_filter($employeeParams));
    $singleSrc = route('legacy.page', 'reductions') . '?compact=1';
@endphp
@extends('layouts.app')

@section('content')
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    @if ($userRole === 'admin')
                        {{-- The tabs above lead to Run Payroll, Issued Payslips and the rest;
                             here there are just the two views of this page. --}}
                        <div class="calc-switch mb-3" role="tablist">
                            <button type="button" class="calc-option active" data-pane="calculator" role="tab" aria-selected="true">
                                <i class="fa fa-calculator"></i>
                                <span><strong>Salary Calculator</strong><small>Try the salary formula: change any input and see the payslip it makes</small></span>
                            </button>
                            <button type="button" class="calc-option" data-pane="single" role="tab" aria-selected="false">
                                <i class="fa fa-file-invoice"></i>
                                <span><strong>Generate One Payslip</strong><small>Issue a payslip for one employee and month</small></span>
                            </button>
                        </div>
                    @endif

                    <div id="pane-calculator">
                        <iframe src="{{ $calculatorSrc }}" class="w-100" style="min-height: calc(100vh - 220px); border: 0;"
                            title="Salary Calculator"></iframe>
                    </div>

                    @if ($userRole === 'admin')
                        <div id="pane-single" style="display: none;">
                            <iframe data-src="{{ $singleSrc }}" class="w-100" style="min-height: calc(100vh - 220px); border: 0;"
                                title="Generate One Payslip"></iframe>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endsection

@push('styles')
    <style>
        .calc-switch { display: flex; flex-wrap: wrap; gap: 10px; }
        .calc-option { display: flex; align-items: center; gap: 12px; padding: 10px 16px; border: 1px solid #d0d5dd; border-radius: 10px;
            background: #fff; text-align: left; cursor: pointer; min-width: 260px; }
        .calc-option i { font-size: 18px; color: #667085; }
        .calc-option strong { display: block; font-size: 14px; color: #101828; }
        .calc-option small { display: block; font-size: 12px; color: #667085; }
        .calc-option:hover { border-color: #1d82f5; }
        .calc-option.active { border-color: #1d82f5; background: #eff8ff; box-shadow: 0 0 0 1px #1d82f5; }
        .calc-option.active i { color: #1d82f5; }
    </style>
@endpush

@push('scripts')
<script>
    (function () {
        var options = document.querySelectorAll('.calc-option');

        options.forEach(function (option) {
            option.addEventListener('click', function () {
                var pane = option.dataset.pane;

                options.forEach(function (o) {
                    o.classList.toggle('active', o === option);
                    o.setAttribute('aria-selected', o === option ? 'true' : 'false');
                });

                document.getElementById('pane-calculator').style.display = pane === 'calculator' ? '' : 'none';
                document.getElementById('pane-single').style.display = pane === 'single' ? '' : 'none';

                // The single-payslip view loads the first time it is opened.
                var frame = document.querySelector('#pane-single iframe');
                if (pane === 'single' && frame && !frame.src) {
                    frame.src = frame.dataset.src;
                }
            });
        });
    })();
</script>
@endpush
