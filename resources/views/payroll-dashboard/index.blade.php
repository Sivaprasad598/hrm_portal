@extends('layouts.app')

@push('styles')
    <style>
        .pd-strip { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; padding: 10px 14px; background: #f5f7fb; border: 1px solid #e6eaf2; border-radius: 10px; }
        .pd-chip { font-size: 12px; padding: 4px 10px; border-radius: 14px; background: #fff; border: 1px solid #dfe3eb; color: #475467; }
        .pd-chip--dark { background: #1f2a4d; border-color: #1f2a4d; color: #fff; }
        .pd-chip--warn { background: #fff7e6; border-color: #fcd9a3; color: #a15c07; }
        .pd-section-title { font-size: 13px; font-weight: 700; letter-spacing: .04em; text-transform: uppercase; color: #1f2a4d; margin: 0 0 10px; }
        .pd-section-title small { font-weight: 400; text-transform: none; letter-spacing: 0; color: #98a2b3; margin-left: 6px; }
        .pd-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap: 14px; }
        .pd-grid--small { grid-template-columns: repeat(auto-fill, minmax(170px, 1fr)); }
        .pd-card { position: relative; border-radius: 12px; padding: 14px 16px 12px; color: #fff; cursor: pointer; overflow: hidden;
                   box-shadow: 0 4px 14px rgba(16, 24, 40, .12); transition: transform .12s ease, box-shadow .12s ease; }
        .pd-card:hover, .pd-card:focus { transform: translateY(-2px); box-shadow: 0 8px 22px rgba(16, 24, 40, .2); outline: 0; }
        .pd-card__icon { position: absolute; right: 14px; top: 12px; font-size: 26px; opacity: .25; }
        .pd-card__label { font-size: 11px; font-weight: 700; letter-spacing: .06em; text-transform: uppercase; opacity: .9; }
        .pd-card__value { font-size: 22px; font-weight: 700; margin-top: 2px; white-space: nowrap; }
        .pd-card__sub { font-size: 11.5px; opacity: .85; }
        .pd-card__lines { margin-top: 10px; padding-top: 8px; border-top: 1px solid rgba(255, 255, 255, .25); }
        .pd-card__line { display: flex; justify-content: space-between; gap: 8px; font-size: 12px; line-height: 1.7; }
        .pd-card__line span:first-child { opacity: .85; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .pd-card__line span:last-child { font-weight: 600; white-space: nowrap; }
        .pd-card__more { font-size: 11px; opacity: .8; text-align: right; margin-top: 4px; }
        .pd-blue { background: linear-gradient(135deg, #2f5fa7, #3f7bd1); }
        .pd-green { background: linear-gradient(135deg, #1d8a5c, #2bb47a); }
        .pd-teal { background: linear-gradient(135deg, #17807a, #26a69a); }
        .pd-red { background: linear-gradient(135deg, #b4332b, #d9534f); }
        .pd-orange { background: linear-gradient(135deg, #c4561b, #e8803a); }
        .pd-indigo { background: linear-gradient(135deg, #3c3f9e, #5c62d6); }
        .pd-dark { background: linear-gradient(135deg, #232c3f, #3b4660); }
        .pd-purple { background: linear-gradient(135deg, #6a3d9a, #8e5cc2); }
        .pd-amber { background: linear-gradient(135deg, #a8620b, #d98c1f); }
        .pd-mini { border-radius: 10px; padding: 10px 12px; cursor: pointer; background: #fff !important; border: 1px solid #eaecf0; border-left: 4px solid; transition: box-shadow .12s ease; }
        .pd-mini:hover, .pd-mini:focus { box-shadow: 0 6px 16px rgba(16, 24, 40, .1); outline: 0; }
        .pd-mini.pd-green { border-left-color: #2bb47a; }
        .pd-mini.pd-red { border-left-color: #d9534f; }
        .pd-mini.is-nil { opacity: .55; }
        .pd-mini__label { font-size: 12px; font-weight: 600; color: #344054; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .pd-mini__value { font-size: 17px; font-weight: 700; white-space: nowrap; }
        .pd-mini.pd-green .pd-mini__value { color: #1d8a5c; }
        .pd-mini.pd-red .pd-mini__value { color: #b4332b; }
        .pd-mini__sub { font-size: 11px; color: #98a2b3; }
        .pd-btn-purple { background: #6a3d9a; color: #fff; }
        .pd-btn-teal { background: #17807a; color: #fff; }
        .pd-btn-amber { background: #a8620b; color: #fff; }
        .pd-btn-red { background: #b4332b; color: #fff; }
        .pd-btn-dark { background: #1f2a4d; color: #fff; }
        .pd-download:hover { color: #fff; opacity: .9; }
        .pd-modal-head { background: #1f2a4d; color: #fff; }
        .pd-modal-head p { color: #c7cde0; }
        .pd-table th, .pd-table td { white-space: nowrap; }
        .pd-table thead th { position: sticky; top: 0; background: #2f5fa7; color: #fff; z-index: 2; font-size: 12px; }
        .pd-table tfoot td { background: #fffbea; font-weight: 700; position: sticky; bottom: 0; }
        .dash-table th, .dash-table td { white-space: nowrap; }
        .dash-table thead th { position: sticky; top: 0; background: #f8f9fa; z-index: 2; }
        .credit { color: #1d9e6f; }
        .debit { color: #d0342c; }
    </style>
@endpush

@section('content')
    @php
        $money = fn ($v) => '₹' . number_format((float) $v, 2);
    @endphp

    <div class="content-wrapper">

        {{-- Which stretch of time --}}
        <div class="card bg-white border-0 b-shadow-4 mb-3">
            <div class="card-body p-3">
                <form method="GET" action="{{ route('payroll-dashboard.index') }}">
                    <div class="row align-items-end">
                        <div class="col-md-3 mb-2">
                            <label class="f-12 text-dark-grey">Period</label>
                            <select name="preset" class="form-control f-14" onchange="this.form.from.value=''; this.form.to.value=''; this.form.submit();">
                                @foreach ($presets as $key => $label)
                                    <option value="{{ $key }}" @selected($preset === $key)>{{ $label }}</option>
                                @endforeach
                                <option value="custom" @selected($preset === 'custom')>Chosen dates</option>
                            </select>
                        </div>
                        <div class="col-md-2 mb-2">
                            <label class="f-12 text-dark-grey">From</label>
                            <input type="date" name="from" class="form-control f-14" value="{{ $from->toDateString() }}">
                        </div>
                        <div class="col-md-2 mb-2">
                            <label class="f-12 text-dark-grey">To</label>
                            <input type="date" name="to" class="form-control f-14" value="{{ $to->toDateString() }}">
                        </div>
                        <div class="col-md-2 mb-2">
                            <button type="submit" class="btn btn-primary f-14 w-100">@lang('app.apply')</button>
                        </div>
                        <div class="col-md-3 mb-2">
                            <a href="{{ route('payroll-register.index') }}" class="btn btn-outline-secondary f-14 w-100">
                                <i class="fa fa-book mr-1"></i> Salary Statement
                            </a>
                        </div>
                        <div class="col-12">
                            <label class="f-12 text-lightest mb-0 mr-3">
                                <input type="checkbox" name="include_drafts" value="1" @checked($includeDrafts)
                                       onchange="this.form.submit()">
                                Include payslips still in draft
                            </label>
                            <span class="f-12 text-lightest">
                                A payslip counts here when its pay period <strong>started</strong> between these dates,
                                so no payslip is ever counted twice.
                            </span>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        @if ($rows === [])
            <div class="card bg-white border-0 b-shadow-4">
                <div class="card-body text-center p-5">
                    <h6 class="f-16 mb-2">No payslips in this stretch</h6>
                    <p class="f-13 text-lightest mb-3">
                        {{ $from->format('d M Y') }} to {{ $to->format('d M Y') }}{{ $includeDrafts ? '' : ' — drafts are left out; tick "Include payslips still in draft" to see them' }}.
                    </p>
                    <a href="{{ route('payroll-run.index') }}" class="btn btn-sm btn-primary">Run payroll</a>
                </div>
            </div>
        @else
            <div class="pd-strip mb-3">
                <span class="pd-chip pd-chip--dark"><i class="fa fa-calendar-alt mr-1"></i>{{ implode(' · ', $periods) }}</span>
                <span class="pd-chip">{{ $totals['payslips'] }} payslip(s) · {{ $totals['employees'] }} employee(s)</span>
                <span class="pd-chip">{{ $totals['paid_count'] }} paid</span>
                @if ($includeDrafts)
                    <span class="pd-chip pd-chip--warn">drafts included</span>
                @endif
                <span class="ml-auto f-12 text-lightest">Click any card for the full list</span>
            </div>

            @foreach ($sections as $section)
                <div class="pd-section-title">{{ $section['title'] }} <small>{{ $section['note'] }}</small></div>
                <div class="pd-grid mb-4">
                    @foreach ($section['cards'] as $card)
                        @include('payroll-dashboard.card', ['card' => $card])
                    @endforeach
                </div>
            @endforeach

            {{-- Every pay element in the catalogue: credits and debits apart --}}
            @foreach (['earning' => ['Pay Elements — Credits', 'added to pay', 'green', '+'], 'deduction' => ['Pay Elements — Debits', 'taken from pay', 'red', '−']] as $kind => [$title, $note, $tone, $sign])
                <div class="pd-section-title">{{ $title }} <small>{{ $note }}</small></div>
                <div class="pd-grid pd-grid--small mb-4">
                    @foreach ($elementCards[$kind] as $element)
                        <div class="pd-mini pd-{{ $tone }} {{ abs($element['total']) < 0.005 ? 'is-nil' : '' }}" role="button" tabindex="0"
                             data-card="{{ $element['key'] }}" data-title="{{ $element['name'] }}">
                            <div class="pd-mini__label" title="{{ $element['name'] }}">{{ $element['name'] }}</div>
                            <div class="pd-mini__value">{{ $sign }}{{ $money($element['total']) }}</div>
                            <div class="pd-mini__sub">{{ $element['people'] }} employee(s)</div>
                        </div>
                    @endforeach
                </div>
            @endforeach

            {{-- Statutory files for one pay period --}}
            @if ($canDownload)
                <div class="pd-section-title">Download Statutory Reports <small>for one pay period</small></div>
                <div class="card bg-white border-0 b-shadow-4 mb-4">
                    <div class="card-body p-3 d-flex flex-wrap align-items-center" style="gap: 8px">
                        <select id="pd-return-period" class="form-control f-14 mr-2" style="width: auto">
                            @foreach ($returnPeriods as $p)
                                <option value="{{ $p['month'] }}|{{ $p['year'] }}">{{ $p['label'] }}</option>
                            @endforeach
                        </select>
                        @foreach ([
                            ['pf', 'fa-file-alt', 'PF — ECR (text)', 'pd-btn-purple'],
                            ['sheet:pf', 'fa-file-excel', 'PF — Excel', 'pd-btn-purple'],
                            ['sheet:esi', 'fa-file-excel', 'ESI — Excel', 'pd-btn-teal'],
                            ['sheet:pt', 'fa-file-excel', 'PT — Excel', 'pd-btn-amber'],
                            ['sheet:tds', 'fa-file-excel', 'TDS — Excel', 'pd-btn-red'],
                            ['sheet:all', 'fa-file-excel', 'All in one workbook', 'pd-btn-dark'],
                            ['bundle', 'fa-file-archive', 'Download ALL (ZIP)', 'pd-btn-dark'],
                        ] as [$what, $icon, $text, $class])
                            <a href="javascript:;" class="btn btn-sm f-13 pd-download {{ $class }}" data-what="{{ $what }}">
                                <i class="fa {{ $icon }} mr-1"></i>{{ $text }}
                            </a>
                        @endforeach
                        <span class="f-12 text-lightest w-100 mt-1">
                            Built from the payslips of that period{{ $includeDrafts ? ', drafts included' : ' (approved and paid only)' }}.
                            Missing UAN, ESI numbers or PAN are listed at the bottom of each sheet.
                        </span>
                    </div>
                </div>
            @endif

            <div class="row">
                {{-- Where the cost sat --}}
                <div class="col-md-5 mb-3">
                    <div class="card bg-white border-0 b-shadow-4 h-100">
                        <div class="card-header bg-white border-bottom p-3">
                            <h6 class="mb-0 f-14">By department</h6>
                            <p class="mb-0 f-12 text-lightest">Largest cost first.</p>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-sm mb-0 f-13">
                                <thead>
                                    <tr>
                                        <th>Department</th>
                                        <th class="text-center">Payslips</th>
                                        <th class="text-right">Paid</th>
                                        <th class="text-right">Cost</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($departments as $group)
                                        <tr>
                                            <td>{{ $group['department'] }}</td>
                                            <td class="text-center">{{ $group['people'] }}</td>
                                            <td class="text-right">{{ number_format($group['paid'], 2) }}</td>
                                            <td class="text-right font-weight-bold">{{ number_format($group['cost'], 2) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                {{-- Every individual payout in the stretch --}}
                <div class="col-md-7 mb-3">
                    <div class="card bg-white border-0 b-shadow-4 h-100">
                        <div class="card-header bg-white border-bottom p-3">
                            <h6 class="mb-0 f-14">Individual payouts</h6>
                            <p class="mb-0 f-12 text-lightest">{{ count($rows) }} payslip(s). Highest paid first.</p>
                        </div>
                        <div class="table-responsive" style="max-height: 52vh">
                            <table class="table table-hover mb-0 f-13 dash-table">
                                <thead>
                                    <tr>
                                        <th>@lang('app.employee')</th>
                                        <th>@lang('app.month')</th>
                                        <th class="text-right">Gross</th>
                                        <th class="text-right">Deductions</th>
                                        <th class="text-right">Extras</th>
                                        <th class="text-right">Paid</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach (collect($rows)->sortByDesc('paid') as $row)
                                        <tr>
                                            <td>
                                                {{ $row['emp_name'] }}
                                                <span class="f-11 text-lightest d-block">
                                                    {{ $row['emp_id'] }}{{ $row['department'] ? ' · ' . $row['department'] : '' }}
                                                </span>
                                            </td>
                                            <td class="f-12">{{ $row['period'] }}</td>
                                            <td class="text-right">{{ number_format($row['gross'], 2) }}</td>
                                            <td class="text-right">{{ number_format($row['total_deductions'], 2) }}</td>
                                            <td class="text-right">
                                                @if ($row['extra_earnings'] > 0 || $row['extra_deductions'] > 0)
                                                    @if ($row['extra_earnings'] > 0)
                                                        <span class="credit">+{{ number_format($row['extra_earnings'], 2) }}</span>
                                                    @endif
                                                    @if ($row['extra_deductions'] > 0)
                                                        <span class="debit">−{{ number_format($row['extra_deductions'], 2) }}</span>
                                                    @endif
                                                @else
                                                    <span class="text-lightest">—</span>
                                                @endif
                                            </td>
                                            <td class="text-right font-weight-bold">{{ number_format($row['paid'], 2) }}</td>
                                            <td class="text-right">
                                                <a href="{{ route('payslips.generated') }}?payslip={{ $row['id'] }}"
                                                   class="f-12" title="{{ $row['payslip_number'] }}">open</a>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        @endif
    </div>

    {{-- The list behind a card --}}
    <div class="modal fade" id="pd-modal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-scrollable" role="document">
            <div class="modal-content">
                <div class="modal-header pd-modal-head">
                    <div>
                        <h5 class="modal-title mb-0" id="pd-title">—</h5>
                        <p class="mb-0 f-12" id="pd-range"></p>
                    </div>
                    <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body p-0">
                    <div class="p-3 border-bottom d-flex flex-wrap align-items-center" style="gap: 10px">
                        <div id="pd-summary" class="d-flex flex-wrap mr-auto" style="gap: 16px"></div>
                        <input type="search" id="pd-search" class="form-control form-control-sm" placeholder="Search employee" style="width: 200px">
                        <button type="button" class="btn btn-sm btn-success" id="pd-csv"><i class="fa fa-download mr-1"></i>Export CSV</button>
                    </div>
                    <div class="table-responsive" style="max-height: 62vh">
                        <table class="table table-hover mb-0 f-13 pd-table">
                            <thead id="pd-head"></thead>
                            <tbody id="pd-rows"></tbody>
                            <tfoot id="pd-foot"></tfoot>
                        </table>
                    </div>
                    <p class="p-4 mb-0 text-center f-13 text-lightest" id="pd-empty" style="display:none">Nothing in this list for the dates chosen.</p>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        $(function () {
            var query = {
                from: '{{ $from->toDateString() }}',
                to: '{{ $to->toDateString() }}',
                include_drafts: {{ $includeDrafts ? 1 : 0 }}
            };
            var rangeText = '{{ $from->format('d M Y') }} – {{ $to->format('d M Y') }}';
            var current = null;

            function esc(value) { return $('<div>').text(value == null ? '' : value).html(); }
            function money(value) {
                return '₹' + (Math.round((value || 0) * 100) / 100).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            }
            function cell(value, type) {
                if (type === 'money') { return money(value); }
                if (type === 'num') { return (Math.round((value || 0) * 100) / 100).toLocaleString('en-IN'); }
                return esc(value);
            }

            // Rows matching the search, with a total under every figure column.
            function draw() {
                var term = ($('#pd-search').val() || '').toLowerCase();
                var cols = current.columns;
                var rows = current.rows.filter(function (row) {
                    return !term || (row[0] + ' ' + row[1] + ' ' + row[2]).toLowerCase().indexOf(term) !== -1;
                });
                var sums = cols.map(function () { return 0; });

                $('#pd-rows').html(rows.map(function (row, i) {
                    return '<tr><td>' + (i + 1) + '</td>' + row.map(function (value, c) {
                        if (cols[c][1] !== 'text') { sums[c] += Number(value) || 0; }
                        return '<td class="' + (cols[c][1] === 'text' ? '' : 'text-right') + '">' + cell(value, cols[c][1]) + '</td>';
                    }).join('') + '</tr>';
                }).join(''));

                $('#pd-foot').html(rows.length ? '<tr><td></td>' + cols.map(function (col, c) {
                    return c === 0 ? '<td>TOTAL</td>' : '<td class="text-right">' + (col[1] === 'text' ? '' : cell(sums[c], col[1])) + '</td>';
                }).join('') + '</tr>' : '');

                $('#pd-summary').html('<span class="f-12 text-lightest">' + rows.length + ' record(s)</span>' + cols.map(function (col, c) {
                    return col[1] === 'money' ? '<span class="f-12 text-lightest">' + esc(col[0]) + ': <strong class="text-dark">' + money(sums[c]) + '</strong></span>' : '';
                }).join(''));
                $('#pd-empty').toggle(!rows.length);
            }

            function open(card, title) {
                current = null;
                $('#pd-title').text(title);
                $('#pd-range').text(rangeText);
                $('#pd-head, #pd-rows, #pd-foot, #pd-summary').empty();
                $('#pd-search').val('');
                $('#pd-empty').hide();
                $('#pd-modal').modal('show');

                $.get('{{ route('payroll-dashboard.table') }}', $.extend({ card: card }, query)).done(function (data) {
                    current = data;
                    $('#pd-title').text(data.title || title);
                    $('#pd-head').html('<tr><th>#</th>' + data.columns.map(function (col) {
                        return '<th class="' + (col[1] === 'text' ? '' : 'text-right') + '">' + esc(col[0]) + '</th>';
                    }).join('') + '</tr>');
                    draw();
                }).fail(function () {
                    $('#pd-empty').text('That list could not be loaded just now.').show();
                });
            }

            $(document).on('click', '[data-card]', function () { open($(this).data('card'), $(this).data('title')); });
            $(document).on('keydown', '[data-card]', function (e) {
                if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); open($(this).data('card'), $(this).data('title')); }
            });
            $('#pd-search').on('input', function () { if (current) { draw(); } });

            $('#pd-csv').on('click', function () {
                if (!current || !current.rows.length) { return; }
                var quote = function (v) { v = v == null ? '' : String(v); return /[",\n]/.test(v) ? '"' + v.replace(/"/g, '""') + '"' : v; };
                var csv = [current.columns.map(function (c) { return quote(c[0]); }).join(',')]
                    .concat(current.rows.map(function (row) { return row.map(quote).join(','); })).join('\n');
                var link = document.createElement('a');
                link.href = URL.createObjectURL(new Blob(['﻿' + csv], { type: 'text/csv;charset=utf-8' }));
                link.download = (current.title || 'payroll').replace(/[^a-z0-9]+/gi, '-').toLowerCase() + '-' + query.from + '-to-' + query.to + '.csv';
                link.click();
            });

            // Statutory files: the period picked, drafts as on the dashboard.
            $('.pd-download').on('click', function () {
                var parts = ($('#pd-return-period').val() || '').split('|');
                var what = $(this).data('what');
                var params = { month: parts[0], year: parts[1], include_drafts: query.include_drafts };
                var url = what === 'pf' ? '{{ route('statutory-returns.pf') }}'
                    : what === 'bundle' ? '{{ route('statutory-returns.bundle') }}'
                    : '{{ route('statutory-returns.spreadsheet') }}';

                if (what.indexOf('sheet:') === 0) { params.which = what.split(':')[1]; }

                window.location = url + '?' + $.param(params);
            });
        });
    </script>
@endpush
