@extends('layouts.app')

@push('styles')
    <style>
        /* The card styling lives in public/css/app-custom.css, under .arw-dash.
           What stays here is particular to this screen. */
        .band-title { font-size: 11px; letter-spacing: .08em; text-transform: uppercase; color: #8a94a6; }
        .credit { color: #1d9e6f; }
        .debit { color: #d0342c; }
        .dash-table th, .dash-table td { white-space: nowrap; }
        .dash-table thead th { position: sticky; top: 0; background: #f8f9fa; z-index: 2; }
        .drill-total { font-size: 20px; font-weight: 600; }
    </style>
@endpush

@section('content')
    @php
        $money = fn ($v) => '₹' . number_format((float) $v, 2);
        $short = function ($v) {
            $v = (float) $v;
            $sign = $v < 0 ? '-' : '';
            $v = abs($v);

            if ($v >= 10000000) { return $sign . '₹' . number_format($v / 10000000, 2) . 'Cr'; }
            if ($v >= 100000) { return $sign . '₹' . number_format($v / 100000, 2) . 'L'; }

            return $sign . '₹' . number_format($v);
        };
        $num = fn ($v) => rtrim(rtrim(number_format((float) $v, 2), '0'), '.');
    @endphp

    {{-- arw-dash scopes the card styling in public/css/app-custom.css to this screen --}}
    <div class="content-wrapper arw-dash">

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
                                <i class="fa fa-book mr-1"></i> Register (one month, to file)
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
                    <h6 class="f-16 mb-2">No payslips were issued in this stretch</h6>
                    <p class="f-13 text-lightest mb-3">
                        {{ $from->format('d M Y') }} to {{ $to->format('d M Y') }} — nothing to show yet.
                    </p>
                    <a href="{{ route('payroll-run.index') }}" class="btn btn-sm btn-primary">Run payroll</a>
                </div>
            </div>
        @else
            <div class="d-flex align-items-center flex-wrap mb-2">
                <div class="mr-auto">
                    <h6 class="mb-0 f-15">
                        {{ $from->format('d M Y') }} – {{ $to->format('d M Y') }}
                    </h6>
                    <p class="mb-0 f-12 text-lightest">
                        {{ count($periods) }} pay period(s): {{ implode(' · ', $periods) }}
                        &middot; every figure comes from the payslips as issued
                        &middot; <strong>click any card</strong> to see who is behind it
                    </p>
                </div>
            </div>

            @php
                // label, value, metric, accent, sub-line, icon
                $bands = [
                    'The headline' => [
                        ['Paid to employees', $totals['paid'], 'paid', 'indigo',
                            $totals['payslips'] . ' payslip(s) · ' . $totals['employees'] . ' employee(s)', 'hand-holding-usd'],
                        ['Gross salary', $totals['gross'], 'gross', 'sky', 'before any deduction', 'coins'],
                        ['Total deductions', $totals['deductions'], 'deductions', 'amber',
                            'taken off the employees', 'cut'],
                        ['Cost to company', $totals['cost'], 'cost', 'violet',
                            'pay plus the employer’s own share', 'building'],
                    ],
                    'What makes up the pay' => [
                        ['Basic', $totals['basic'], 'basic', 'indigo', '', 'wallet'],
                        ['House rent', $totals['hra'], 'hra', 'sky', '', 'home'],
                        ['Conveyance', $totals['ca'], 'ca', 'emerald', '', 'bus'],
                        ['Other allowances', $totals['others'], 'others', 'violet', '', 'plus-circle'],
                        ['Leave encashment', $totals['encashment'], 'encashment', 'amber', '', 'sun'],
                    ],
                    'Statutory' => [
                        ['Provident fund', $totals['pf_payable'], 'pf_payable', 'indigo',
                            'employee ' . $short($totals['pf_employee']) . ' + employer ' . $short($totals['pf_employer']), 'piggy-bank'],
                        ['Employee insurance', $totals['esi_payable'], 'esi_payable', 'emerald',
                            'employee ' . $short($totals['esi_employee']) . ' + employer ' . $short($totals['esi_employer']), 'shield-alt'],
                        ['Professional tax', $totals['pt'], 'pt', 'amber', '', 'landmark'],
                        ['Income tax', $totals['tds'], 'tds', 'rose', 'withheld from employees', 'file-invoice-dollar'],
                    ],
                ];
            @endphp

            @foreach ($bands as $band => $cards)
                <p class="band-title mb-2 mt-1">{{ $band }}</p>
                <div class="arw-stats mb-3">
                    @foreach ($cards as [$label, $value, $metric, $accent, $sub, $icon])
                        <div class="arw-stat arw-stat--{{ $accent }} pay-card" tabindex="0"
                             role="button" data-metric="{{ $metric }}" data-label="{{ $label }}"
                             title="Click to see the employees behind this">
                            <span class="arw-stat__main">
                                <span class="arw-stat__label">{{ $label }}</span>
                                <span class="arw-stat__value">{{ $short($value) }}</span>
                                <span class="arw-stat__note">{{ $sub ?: $money($value) }}</span>
                            </span>
                            <span class="arw-stat__icon"><i class="fa fa-{{ $icon }}"></i></span>
                            <span class="peek"><i class="fa fa-users"></i></span>
                        </div>
                    @endforeach
                </div>
            @endforeach

            {{-- Overtime, bonuses, advances: a card each, for whatever was used --}}
            <p class="band-title mb-2 mt-1">
                Extra pay elements
                <span class="text-lightest" style="text-transform:none; letter-spacing:0">
                    — paid on top of, or taken off, the standard salary
                </span>
            </p>
            <div class="arw-stats mb-3">
                @forelse ($elements as $element)
                    @php $earning = $element['kind'] === 'earning'; @endphp
                    <div class="arw-stat arw-stat--{{ $earning ? 'emerald' : 'rose' }} pay-card" tabindex="0"
                         role="button" data-metric="{{ $element['key'] }}" data-label="{{ $element['name'] }}"
                         title="Click to see the employees behind this">
                        <span class="arw-stat__main">
                            <span class="arw-stat__label">{{ $element['name'] }}</span>
                            <span class="arw-stat__value {{ $earning ? 'credit' : 'debit' }}">{{ $earning ? '+' : '−' }}{{ $short($element['total']) }}</span>
                            <span class="arw-stat__note">{{ $element['people'] }} employee(s)</span>
                        </span>
                        <span class="arw-stat__icon"><i class="fa fa-{{ $earning ? 'arrow-up' : 'arrow-down' }}"></i></span>
                        <span class="peek"><i class="fa fa-users"></i></span>
                    </div>
                @empty
                    <div class="col-12 mb-2">
                        <div class="card bg-white border-0 b-shadow-4">
                            <div class="card-body p-3 f-13 text-lightest">
                                No overtime, bonuses, advances or other extra elements on the payslips in this
                                stretch. They are added on the
                                <a href="{{ route('pay-elements.index') }}">Pay Elements</a> screen.
                            </div>
                        </div>
                    </div>
                @endforelse
            </div>

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
                            <p class="mb-0 f-12 text-lightest">
                                {{ count($rows) }} payslip(s). Highest paid first.
                            </p>
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

    {{-- Who is behind a figure --}}
    <div class="modal fade" id="drill-modal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <div>
                        <h6 class="modal-title f-15 mb-0" id="drill-title">—</h6>
                        <p class="mb-0 f-12 text-lightest" id="drill-range"></p>
                    </div>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body p-0">
                    <div class="p-3 border-bottom d-flex align-items-center">
                        <div class="mr-auto">
                            <span class="f-12 text-lightest d-block">Total</span>
                            <span class="drill-total" id="drill-total">—</span>
                        </div>
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="drill-copy">
                            <i class="fa fa-copy mr-1"></i> Copy as a list
                        </button>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0 f-13">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>@lang('app.employee')</th>
                                    <th>Department</th>
                                    <th>@lang('app.month')</th>
                                    <th>Payslip</th>
                                    <th class="text-right">Amount</th>
                                </tr>
                            </thead>
                            <tbody id="drill-rows"></tbody>
                        </table>
                    </div>
                    <p class="p-3 mb-0 f-12 text-lightest" id="drill-empty" style="display:none">
                        Nothing contributed to this figure in the dates chosen.
                    </p>
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
            var lastRows = [];

            function money(value) {
                return '₹' + (Math.round(value * 100) / 100).toLocaleString('en-IN', {
                    minimumFractionDigits: 2, maximumFractionDigits: 2
                });
            }

            function open(metric, label) {
                $('#drill-title').text(label);
                $('#drill-range').text(rangeText);
                $('#drill-total').text('…');
                $('#drill-rows').empty();
                $('#drill-empty').hide();
                $('#drill-modal').modal('show');

                $.get('{{ route('payroll-dashboard.breakdown') }}', $.extend({ metric: metric }, query))
                    .done(function (data) {
                        lastRows = data.rows || [];
                        $('#drill-title').text(data.label || label);
                        $('#drill-total').text(money(data.total || 0));

                        if (!lastRows.length) {
                            $('#drill-empty').show();

                            return;
                        }

                        var html = lastRows.map(function (row, i) {
                            return '<tr>' +
                                '<td>' + (i + 1) + '</td>' +
                                '<td>' + $('<div>').text(row.name || '').html() +
                                (row.note ? '<span class="f-11 text-lightest d-block">' + $('<div>').text(row.note).html() + '</span>' : '') +
                                '<span class="f-11 text-lightest d-block">' + $('<div>').text(row.code || '').html() + '</span></td>' +
                                '<td class="f-12">' + $('<div>').text(row.department || '—').html() + '</td>' +
                                '<td class="f-12">' + $('<div>').text(row.period || '').html() + '</td>' +
                                '<td class="f-12">' + $('<div>').text(row.payslip_number || '').html() + '</td>' +
                                '<td class="text-right font-weight-bold">' + money(row.amount) + '</td>' +
                                '</tr>';
                        }).join('');

                        $('#drill-rows').html(html);
                    })
                    .fail(function () {
                        $('#drill-total').text('—');
                        $('#drill-empty').text('That could not be worked out just now.').show();
                    });
            }

            $(document).on('click', '.pay-card', function () {
                open($(this).data('metric'), $(this).data('label'));
            });

            // The cards are reachable by keyboard, so they answer to it too.
            $(document).on('keydown', '.pay-card', function (event) {
                if (event.key === 'Enter' || event.key === ' ') {
                    event.preventDefault();
                    open($(this).data('metric'), $(this).data('label'));
                }
            });

            $('#drill-copy').on('click', function () {
                if (!lastRows.length) { return; }

                var text = lastRows.map(function (row) {
                    return [row.name, row.code, row.department, row.period, row.payslip_number, row.amount].join('\t');
                }).join('\n');

                navigator.clipboard.writeText(text).then(function () {
                    Swal.fire({ icon: 'success', title: 'Copied', timer: 1200, showConfirmButton: false });
                }, function () {
                    Swal.fire({ icon: 'error', title: 'Could not copy' });
                });
            });
        });
    </script>
@endpush
