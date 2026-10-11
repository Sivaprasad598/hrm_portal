@php
    $pageTitle = 'app.menu.payslips';
    // Whoever runs payroll gets the full screen — the calculator, the tabs and
    // single payslip generation. This was the administrator role alone, which
    // sent HR and the Admin to their own locked payslip instead, even though
    // they issue everybody else's on the payroll run.
    $userRole = (in_array('admin', user_roles()) || user()->permission('manage_payroll') === 'all')
        ? 'admin'
        : 'employee';
    $employeeParams = [
        'emp_name' => user()->name,
        'emp_id' => optional(user()->employeeDetail)->employee_card_id ?: '',
        'emp_designation' => optional(optional(user()->employeeDetail)->designation)->name ?: '',
        'user_role' => $userRole,
    ];
    // Admins land straight on the full calculator in the "Payslips" tab (rather than
    // the locked slip view), so they can generate/adjust a payslip immediately without
    // an extra click through a "no record found" fallback. Non-admins never see the
    // tabs at all (guarded below), so they keep seeing their own locked payslip.
    $iframeSrc = $userRole === 'admin'
        ? route('legacy.page', 'reductions')
        : route('legacy.page', 'payslips') . '?' . http_build_query(array_filter($employeeParams));
    $calculatorSrc = route('legacy.page', 'reductions') . '?compact=1';
@endphp
@extends('layouts.app')

@section('content')
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex align-items-end flex-wrap mb-3">
                        <div class="mr-2">
                            <label class="f-12 text-dark-grey">@lang('modules.employees.joiningDate'):</label>
                            <div>{{ optional(optional(user()->employeeDetail)->joining_date)->format(company()->date_format) ?? '--' }}</div>
                        </div>
                        <div class="mr-2">
                            <label class="f-12 text-dark-grey">@lang('app.month')</label>
                            <select id="payslip_month" class="form-control">
                            </select>
                        </div>
                        <div class="mr-2">
                            <label class="f-12 text-dark-grey">@lang('app.year')</label>
                            <select id="payslip_year" class="form-control">
                            </select>
                        </div>
                        <div class="mr-2">
                            <button id="generate-payslip" class="btn btn-primary">@lang('app.generate') @lang('app.menu.payslips')</button>
                        </div>

                        @if ($userRole === 'admin')
                            <div class="ml-auto d-flex align-items-center" id="payslip-tabs">
                                {{-- Leaves the iframe behind entirely: the list is a
                                     normal page, so it gets its own URL and the
                                     browser's back button works. --}}
                                {{-- What the month will cost, before any payslip
                                     exists, and issuing them all in one run. --}}
                                <a href="{{ route('payroll-run.index') }}" class="btn btn-outline-primary mr-2">
                                    <i class="fa fa-calculator mr-1"></i>Payroll
                                </a>
                                {{-- Overtime, bonuses, advances: what is paid or recovered
                                     on top of the standard salary. --}}
                                <a href="{{ route('pay-elements.index') }}" class="btn btn-outline-primary mr-2">
                                    <i class="fa fa-sliders-h mr-1"></i>Pay Elements
                                </a>
                                <a href="{{ route('payslips.generated') }}"
                                   class="btn btn-outline-primary mr-2">
                                    <i class="fa fa-list mr-1"></i>@lang('app.menu.generatedPayslips')
                                </a>
                                <button type="button" class="btn btn-secondary btn-active mr-2" id="tab-btn-payslips" data-tab="payslips">@lang('app.menu.payslips')</button>
                                <button type="button" class="btn btn-secondary" id="tab-btn-calculation" data-tab="calculation">Payslip Calculation</button>
                            </div>
                        @endif
                    </div>

                    <div id="tab-pane-payslips">
                        <iframe
                            id="payslip-iframe"
                            src="{{ $iframeSrc }}"
                            class="w-100"
                            style="min-height: calc(100vh - 220px); border: 0;"
                            title="@lang('app.menu.payslips')">
                        </iframe>
                    </div>

                    @if ($userRole === 'admin')
                        <div id="tab-pane-calculation" style="display:none;">
                            <iframe
                                id="calculation-iframe"
                                data-src="{{ $calculatorSrc }}"
                                class="w-100"
                                style="min-height: calc(100vh - 220px); border: 0;"
                                title="Payslip Calculation">
                            </iframe>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script>
    (function(){
        // Joining date from server (Y-m-d) or null
        var joining = '{{ optional(optional(user()->employeeDetail)->joining_date)->format('Y-m-d') ?? '' }}';
        var joiningYear = joining ? parseInt(joining.split('-')[0], 10) : null;
        var joiningMonth = joining ? parseInt(joining.split('-')[1], 10) : null;

        var now = new Date();
        var currentYear = now.getFullYear();
        var currentMonth = now.getMonth() + 1;

        var years = [];
        var startYear = joiningYear || currentYear;
        var endYear = Math.max(currentYear, startYear) + 5; // include future 5 years
        for (var y = startYear; y <= endYear; y++) years.push(y);

        var monthNames = ["January","February","March","April","May","June","July","August","September","October","November","December"];

        var $month = document.getElementById('payslip_month');
        var $year = document.getElementById('payslip_year');

        function populateYears(){
            $year.innerHTML = '';
            years.forEach(function(y){
                var opt = document.createElement('option'); opt.value = y; opt.text = y;
                $year.appendChild(opt);
            });
            $year.value = currentYear;
        }

        function populateMonthsFor(year){
            $month.innerHTML = '';
            var start = 1;
            if (joiningYear && year == joiningYear && joiningMonth) start = joiningMonth;
            for (var m = start; m <= 12; m++){
                var opt = document.createElement('option'); opt.value = m; opt.text = monthNames[m-1];
                $month.appendChild(opt);
            }
            // If current year selected and current month available, default to current month
            if (year == currentYear && currentMonth >= (joiningMonth || 1)) {
                $month.value = currentMonth;
            }
        }

        populateYears();
        populateMonthsFor(parseInt($year.value, 10));

        var $iframe = document.getElementById('payslip-iframe');
        var iframeBaseSrc = {!! json_encode($iframeSrc) !!};

        function buildIframeSrc(month, year) {
            var src = iframeBaseSrc;
            if (month && year) {
                src += (src.indexOf('?') === -1 ? '?' : '&') + 'month=' + encodeURIComponent(month) + '&year=' + encodeURIComponent(year);
            }
            return src;
        }

        $year.addEventListener('change', function(){ populateMonthsFor(parseInt(this.value,10)); });

        document.getElementById('generate-payslip').addEventListener('click', function(){
            var month = $month.value;
            var year = $year.value;
            if (!month || !year){ alert('Please select month and year'); return; }
            $iframe.src = buildIframeSrc(month, year);
        });

        $iframe.src = buildIframeSrc($month.value, $year.value);

        // -------------------------------------------------------------------
        // Payslips / Payslip Calculation tabs (admin only — these elements
        // don't exist in the DOM for a non-admin, so guard on their presence).
        // -------------------------------------------------------------------
        var tabBtnPayslips = document.getElementById('tab-btn-payslips');
        var tabBtnCalculation = document.getElementById('tab-btn-calculation');
        var tabPanePayslips = document.getElementById('tab-pane-payslips');
        var tabPaneCalculation = document.getElementById('tab-pane-calculation');
        var calculationIframe = document.getElementById('calculation-iframe');

        if (tabBtnPayslips && tabBtnCalculation) {
            function activateTab(tab) {
                var isPayslips = tab === 'payslips';
                tabBtnPayslips.classList.toggle('btn-active', isPayslips);
                tabBtnCalculation.classList.toggle('btn-active', !isPayslips);
                tabPanePayslips.style.display = isPayslips ? '' : 'none';
                tabPaneCalculation.style.display = isPayslips ? 'none' : '';

                if (!isPayslips && calculationIframe && !calculationIframe.src) {
                    calculationIframe.src = calculationIframe.dataset.src;
                }
            }

            tabBtnPayslips.addEventListener('click', function () { activateTab('payslips'); });
            tabBtnCalculation.addEventListener('click', function () { activateTab('calculation'); });
        }
    })();
</script>
@endpush
