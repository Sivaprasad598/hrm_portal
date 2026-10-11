@extends('layouts.app')

@push('datatable-styles')
    <script src="{{ asset('vendor/jquery/frappe-charts.min.iife.js') }}"></script>
    @include('sections.datatable_css')
    <style>
        /* Finance Report: View toggle in filter bar */
        .finance-view-toggle.btn-group .btn {
            padding: 6px 10px;
            font-size: 12px;
            line-height: 1;
            border-color: #d9dee3;
            background: #fff;
            color: #6c757d;
            box-shadow: none;
        }

        .finance-view-toggle.btn-group .btn.btn-active {
            background: #28a745;
            border-color: #28a745;
            color: #fff;
        }

        .finance-view-toggle.btn-group .btn:focus {
            box-shadow: none;
        }
    </style>
@endpush

@section('filter-section')

    <x-filters.filter-box>
        <!-- VIEW -->
        <div class="select-box d-flex py-2 px-lg-2 px-md-2 px-0 border-right-grey border-right-grey-sm-0">
            <p class="mb-0 pr-2 f-14 text-dark-grey d-flex align-items-center">@lang('app.view')</p>
            <div class="select-status d-flex">
                <div class="btn-group btn-group-sm finance-view-toggle" role="group" aria-label="view-range">
                    <button type="button" class="btn btn-secondary btn-active" data-range="day">@lang('app.day')</button>
                    <button type="button" class="btn btn-secondary" data-range="week">@lang('app.week')</button>
                    <button type="button" class="btn btn-secondary" data-range="month">@lang('app.month')</button>
                    <button type="button" class="btn btn-secondary" data-range="year">@lang('app.year')</button>
                    <button type="button" class="btn btn-secondary" data-range="custom">@lang('app.custom')</button>
                </div>
            </div>
        </div>

        <!-- DURATION -->
        <div class="select-box d-flex py-2 px-lg-2 px-md-2 px-0 border-right-grey border-right-grey-sm-0">
            <p class="mb-0 pr-2 f-14 text-dark-grey d-flex align-items-center">@lang('app.duration')</p>
            <div class="select-status d-flex">
                <input type="text"
                    class="position-relative text-dark form-control border-0 p-2 text-left f-14 f-w-500 border-additional-grey"
                    id="datatableRange2" placeholder="@lang('placeholders.dateRange')">
            </div>
        </div>

        <!-- CLIENT -->
        @if (in_array('clients', user_modules()))
            <div class="select-box d-flex py-2 px-lg-2 px-md-2 px-0 border-right-grey border-right-grey-sm-0">
                <p class="mb-0 pr-2 f-14 text-dark-grey d-flex align-items-center">@lang('app.client')</p>
                <div class="select-status">
                    <select class="form-control select-picker" name="employee" id="clientID" data-live-search="true"
                        data-size="8">
                        <option value="all">@lang('app.all')</option>
                        @foreach ($clients as $client)
                            <x-user-option :user="$client" />
                        @endforeach
                    </select>
                </div>
            </div>
        @endif

        <!-- PROJECT -->
        <div class="select-box d-flex py-2 px-lg-2 px-md-2 px-0 border-right-grey border-right-grey-sm-0">
            <p class="mb-0 pr-2 f-14 text-dark-grey d-flex align-items-center">@lang('app.project')</p>
            <div class="select-status">
                <select class="form-control select-picker" name="project_id" id="project_id" data-live-search="true"
                    data-size="8">
                    <option value="all">@lang('app.all')</option>
                    @foreach ($projects as $project)
                        <option value="{{ $project->id }}">{{ $project->project_name }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <!-- STATUS -->
        <div class="select-box d-flex py-2 px-lg-2 px-md-2 px-0 border-right-grey border-right-grey-sm-0">
            <p class="mb-0 pr-2 f-14 text-dark-grey d-flex align-items-center">@lang('app.status')</p>
            <div class="select-status">
                <select class="form-control select-picker" name="status" id="status" data-size="8">
                    <option value="complete">@lang('app.complete')</option>
                    <option value="pending">@lang('app.pending')</option>
                    <option value="all">@lang('app.all')</option>
                </select>
            </div>
        </div>

        <!-- SEARCH -->
        <div class="select-box d-flex py-2 px-lg-2 px-md-2 px-0 border-right-grey border-right-grey-sm-0">
            <p class="mb-0 pr-2 f-14 text-dark-grey d-flex align-items-center">@lang('app.search')</p>
            <div class="select-status d-flex">
                <input type="text"
                    class="form-control border-0 p-2 text-left f-14 f-w-500 border-additional-grey"
                    id="search-text-field" placeholder="@lang('placeholders.search')">
            </div>
        </div>

        <!-- APPLY + RESET -->
        <div class="select-box d-flex py-1 px-lg-2 px-md-2 px-0">
            <x-forms.button-primary class="btn-xs" id="apply-filters" icon="filter">
                @lang('app.apply')
            </x-forms.button-primary>

            <x-forms.button-secondary class="btn-xs ml-2 d-none" id="reset-filters" icon="times-circle">
                @lang('app.clearFilters')
            </x-forms.button-secondary>
        </div>

    </x-filters.filter-box>

@endsection

@section('content')
    <!-- CONTENT WRAPPER START -->
    <div class="content-wrapper">
        <!-- KPI ROW -->
        <div class="row mb-4">
            <div class="col-md-6 col-xl-3 mb-3 mb-xl-0">
                <x-cards.widget :title="__('modules.dashboard.totalEarnings')" value="0" icon="coins"
                    widgetId="totalEarnings" />
            </div>

            <div class="col-md-6 col-xl-3 mb-3 mb-xl-0">
                <x-cards.widget :title="__('modules.invoices.totalInvoices')" value="0" icon="file-invoice"
                    widgetId="totalInvoices" />
            </div>

            <div class="col-md-6 col-xl-3 mb-3 mb-xl-0">
                <x-cards.widget :title="__('modules.payments.pendingAmount')" value="0" icon="hourglass-half"
                    widgetId="pendingAmount" />
                <div class="f-12 text-lightest mt-2">
                    <span id="pendingCount">0</span> @lang('app.invoices')
                </div>
            </div>

            <div class="col-md-6 col-xl-3">
                <x-cards.widget :title="__('modules.dashboard.avgDailyReceipt')" value="0" icon="chart-line"
                    widgetId="avgDailyReceipt" />
                <div class="f-12 text-lightest mt-2">
                    <span id="paidDays">0</span> @lang('app.days')
                </div>
            </div>
        </div>

        <!-- CHART -->
        <div class="row mb-4">
            <div class="col-12 col-xl-8 mb-4 mb-xl-0">
                <x-cards.data id="task-chart-card" :title="__($pageTitle)">
                    <x-slot name="action">
                        <div class="d-flex align-items-center">
                            <div class="btn-group btn-group-sm mr-3" role="group" aria-label="trend-type">
                                <button type="button" class="btn btn-secondary btn-active" id="trend-line">
                                    <i class="bi bi-graph-up mr-1"></i>@lang('app.line')
                                </button>
                                <button type="button" class="btn btn-secondary" id="trend-bar">
                                    <i class="bi bi-bar-chart mr-1"></i>@lang('app.bar')
                                </button>
                            </div>
                            <div class="d-flex align-items-center" id="table-actions"></div>
                        </div>
                    </x-slot>

                    <div class="mt-2" style="height: 260px">
                        <canvas id="trendChart"></canvas>
                    </div>
                    <div class="d-none" id="legacy-trend-html"></div>
                </x-cards.data>
            </div>

            <div class="col-12 col-xl-4">
                <x-cards.data :title="__('modules.dashboard.byProject')">
                    <div style="height: 260px" class="d-flex align-items-center justify-content-center">
                        <canvas id="byProjectChart"></canvas>
                    </div>
                </x-cards.data>
            </div>
        </div>

        <div class="row mb-4">
            <div class="col-12 col-xl-6 mb-4 mb-xl-0">
                <x-cards.data :title="__('modules.dashboard.monthlyComparison')">
                    <div style="height: 260px" class="d-flex align-items-center justify-content-center">
                        <canvas id="monthlyComparisonChart"></canvas>
                    </div>
                </x-cards.data>
            </div>
            <div class="col-12 col-xl-6">
                <x-cards.data :title="__('modules.dashboard.topClients')">
                    <div style="height: 260px" class="d-flex align-items-center justify-content-center">
                        <canvas id="topClientsChart"></canvas>
                    </div>
                </x-cards.data>
            </div>
        </div>

        <!-- TRANSACTION LOG -->
        <x-cards.data :title="__('modules.payments.transactionLog')" padding="false">
            {!! $dataTable->table(['class' => 'table table-hover border-0 w-100']) !!}
        </x-cards.data>
    </div>
    <!-- CONTENT WRAPPER END -->

@endsection

@push('scripts')
    @include('sections.datatable_js')
    <script src="{{ asset('vendor/jquery/Chart.min.js') }}"></script>

    <script type="text/javascript">

        function getDate() {
            var start = moment().clone().startOf('month');
            var end = moment();

            $('#datatableRange2').daterangepicker({
                locale: daterangeLocale,
                linkedCalendars: false,
                startDate: start,
                endDate: end,
                ranges: daterangeConfig
            }, cb);
        }

        $(function() {
            getDate()
            $('#datatableRange2').on('apply.daterangepicker', function(ev, picker) {
                showTable();
            });
        });

    </script>


    <script>
        let trendChartInstance = null;
        let byProjectChartInstance = null;
        let monthlyChartInstance = null;
        let topClientsChartInstance = null;
        let trendType = 'line';

        $('#payments-table').on('preXhr.dt', function(e, settings, data) {

            var dateRangePicker = $('#datatableRange2').data('daterangepicker');
            var startDate = $('#datatableRange2').val();

            if (startDate == '') {
                startDate = null;
                endDate = null;
            } else {
                startDate = dateRangePicker.startDate.format('{{ company()->moment_date_format }}');
                endDate = dateRangePicker.endDate.format('{{ company()->moment_date_format }}');
            }

            var projectID = $('#project_id').val();
            if (!projectID) {
                projectID = 0;
            }
            var clientID = $('#clientID').val();

            var searchText = $('#search-text-field').val();
            var status = $('#status').val();

            data['clientID'] = clientID;
            data['projectID'] = projectID;
            data['startDate'] = startDate;
            data['endDate'] = endDate;
            data['searchText'] = searchText;
            data['status'] = status;
        });
        const showTable = () => {
            window.LaravelDataTables["payments-table"].draw(true);
            pieChart();
        }

        $('#apply-filters').click(function (e) {
            e.preventDefault();
            $('#reset-filters').removeClass('d-none');
            showTable();
        });

        $('#clientID, #project_id, #status')
            .on('change keyup',
                function() {
                    if ($('#project_id').val() != "all") {
                        $('#reset-filters').removeClass('d-none');
                        showTable();
                    } else if ($('#status').val() != "all") {
                        $('#reset-filters').removeClass('d-none');
                        showTable();
                    } else if ($('#clientID').val() != "all") {
                        $('#reset-filters').removeClass('d-none');
                        showTable();
                    } else {
                        $('#reset-filters').addClass('d-none');
                        showTable();
                    }
                });

        $('#search-text-field').on('keyup', function() {
            if ($('#search-text-field').val() != "") {
                $('#reset-filters').removeClass('d-none');
                showTable();
            }
        });

        $('#reset-filters').click(function() {
            $('#filter-form')[0].reset();
            // getDate()

            $('.filter-box .select-picker').selectpicker("refresh");
            $('#reset-filters').addClass('d-none');
            showTable();
        });

        $('#reset-filters-2').click(function() {
            $('#filter-form')[0].reset();

            $('.filter-box .select-picker').selectpicker("refresh");
            $('#reset-filters').addClass('d-none');
            showTable();
        });

        function renderTrendChart(labels, values) {
            const el = document.getElementById('trendChart');
            if (!el) return;

            if (trendChartInstance) {
                trendChartInstance.destroy();
            }

            trendChartInstance = new Chart(el.getContext('2d'), {
                type: trendType,
                data: {
                    labels: labels,
                    datasets: [{
                        label: "{{ __('modules.dashboard.totalEarnings') }}",
                        data: values,
                        borderColor: "{{ $appTheme->header_color }}",
                        backgroundColor: trendType === 'bar' ? "{{ $appTheme->header_color }}" : "rgba(0,0,0,0)",
                        borderWidth: 2,
                        fill: false
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: {
                        x: { grid: { display: false } },
                        y: { grid: { color: '#f1f5f9' } }
                    }
                }
            });
        }

        function renderDoughnut(id, chartRef, labels, values, colors) {
            const el = document.getElementById(id);
            if (!el) return null;
            if (chartRef) chartRef.destroy();

            return new Chart(el.getContext('2d'), {
                type: 'doughnut',
                data: { labels, datasets: [{ data: values, backgroundColor: colors, borderWidth: 0 }] },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { position: 'bottom' } },
                    cutout: '70%'
                }
            });
        }

        function renderMonthly(labels, currentYearLabel, currentValues, previousYearLabel, previousValues) {
            const el = document.getElementById('monthlyComparisonChart');
            if (!el) return;
            if (monthlyChartInstance) monthlyChartInstance.destroy();

            monthlyChartInstance = new Chart(el.getContext('2d'), {
                type: 'bar',
                data: {
                    labels,
                    datasets: [
                        { label: currentYearLabel, data: currentValues, backgroundColor: "rgba(34, 197, 94, 0.65)" },
                        { label: previousYearLabel, data: previousValues, backgroundColor: "rgba(100, 116, 139, 0.20)" }
                    ]
                },
                options: { responsive: true, maintainAspectRatio: false }
            });
        }

        function renderTopClients(labels, values) {
            const el = document.getElementById('topClientsChart');
            if (!el) return;
            if (topClientsChartInstance) topClientsChartInstance.destroy();

            topClientsChartInstance = new Chart(el.getContext('2d'), {
                type: 'bar',
                data: {
                    labels,
                    datasets: [{ data: values, backgroundColor: "rgba(34, 197, 94, 0.65)" }]
                },
                options: { indexAxis: 'y', responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } } }
            });
        }

        function pieChart() {
            var dateRangePicker = $('#datatableRange2').data('daterangepicker');
            var startDate = $('#datatableRange2').val();

            if (startDate == '') {
                startDate = null;
                endDate = null;
            } else {
                startDate = dateRangePicker.startDate.format('{{ company()->moment_date_format }}');
                endDate = dateRangePicker.endDate.format('{{ company()->moment_date_format }}');
            }

            var data = new Array();
            var projectID = $('#project_id').val();
            var clientID = $('#clientID').val();
            var searchText = $('#search-text-field').val();
            var status = $('#status').val();

            var url = "{{ route('finance-report.chart') }}";

            $.easyAjax({
                url: url,
                container: '#task-chart-card',
                blockUI: true,
                type: "POST",
                data: {
                    startDate: startDate,
                    endDate: endDate,
                    projectID: projectID,
                    clientID: clientID,
                    status: status,
                    _token: '{{ csrf_token() }}'
                },
                success: function(response) {
                    $('#legacy-trend-html').html(response.html);
                    $('#totalEarnings').html(response.totalEarnings);
                    if (typeof response.totalInvoices !== 'undefined') {
                        $('#totalInvoices').html(response.totalInvoices);
                    }
                    if (typeof response.pendingAmount !== 'undefined') {
                        $('#pendingAmount').html(response.pendingAmount);
                    }
                    if (typeof response.pendingCount !== 'undefined') {
                        $('#pendingCount').html(response.pendingCount);
                    }
                    if (typeof response.avgDailyReceipt !== 'undefined') {
                        $('#avgDailyReceipt').html(response.avgDailyReceipt);
                    }
                    if (typeof response.paidDays !== 'undefined') {
                        $('#paidDays').html(response.paidDays);
                    }

                    if (response.charts && response.charts.trend) {
                        renderTrendChart(response.charts.trend.labels, response.charts.trend.values);
                    }

                    if (response.charts && response.charts.byProject) {
                        byProjectChartInstance = renderDoughnut(
                            'byProjectChart',
                            byProjectChartInstance,
                            response.charts.byProject.labels,
                            response.charts.byProject.values,
                            response.charts.byProject.colors
                        );
                    }

                    if (response.charts && response.charts.monthlyComparison) {
                        renderMonthly(
                            response.charts.monthlyComparison.labels,
                            response.charts.monthlyComparison.currentYearLabel,
                            response.charts.monthlyComparison.currentYearValues,
                            response.charts.monthlyComparison.previousYearLabel,
                            response.charts.monthlyComparison.previousYearValues
                        );
                    }

                    if (response.charts && response.charts.topClients) {
                        renderTopClients(
                            response.charts.topClients.labels,
                            response.charts.topClients.values
                        );
                    }
                }
            });
        }
        pieChart();

        $(document).on('click', '#trend-line', function () {
            trendType = 'line';
            $('#trend-line').addClass('btn-active');
            $('#trend-bar').removeClass('btn-active');
            pieChart();
        });

        $(document).on('click', '#trend-bar', function () {
            trendType = 'bar';
            $('#trend-bar').addClass('btn-active');
            $('#trend-line').removeClass('btn-active');
            pieChart();
        });

        $(document).on('click', '[data-range]', function () {
            const range = $(this).data('range');
            $('[data-range]').removeClass('btn-active');
            $(this).addClass('btn-active');

            if (range === 'custom') return;

            const picker = $('#datatableRange2').data('daterangepicker');
            const today = moment();
            let start = today.clone();

            if (range === 'day') start = today.clone();
            if (range === 'week') start = today.clone().subtract(6, 'days');
            if (range === 'month') start = today.clone().startOf('month');
            if (range === 'year') start = today.clone().startOf('year');

            picker.setStartDate(start);
            picker.setEndDate(today);
            $('#datatableRange2').val(start.format('{{ company()->moment_date_format }}') + ' - ' + today.format('{{ company()->moment_date_format }}'));
            showTable();
        });

    </script>
@endpush
