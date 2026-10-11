@extends('layouts.app')

@push('styles')
    <style>
        /* The pay table is wide; the name column stays put while the figures scroll. */
        .payroll-table th, .payroll-table td { white-space: nowrap; }
        .payroll-table thead th { position: sticky; top: 0; background: #f8f9fa; z-index: 2; }
        .payroll-table .col-name { position: sticky; left: 0; background: #fff; z-index: 1; }
        .payroll-table thead .col-name { z-index: 3; background: #f8f9fa; }
        .payroll-table tfoot td { font-weight: 600; background: #f8f9fa; }
        .bootstrap-select .dropdown-menu { z-index: 1060; }
    </style>
@endpush

@section('content')
    @php
        $money = fn ($v) => '₹' . number_format((float) $v, 2);
        $short = function ($v) {
            $v = (float) $v;
            return $v >= 100000 ? '₹' . number_format($v / 100000, 2) . 'L' : '₹' . number_format($v);
        };
        $num = fn ($v) => rtrim(rtrim(number_format((float) $v, 2), '0'), '.');
    @endphp

    <div class="content-wrapper arw-dash">

        {{-- What is being costed --}}
        <div class="card bg-white border-0 b-shadow-4 mb-3">
            <div class="card-body p-3">
                <form method="GET" action="{{ route('payroll-run.index') }}" id="payroll-filters">
                    <div class="row align-items-end">
                        <div class="col-md-2 mb-2">
                            <label class="f-12 text-dark-grey">@lang('app.month')</label>
                            <select name="month" class="form-control f-14">
                                @foreach (range(1, 12) as $m)
                                    <option value="{{ $m }}" @selected($m === $month)>
                                        {{ \Carbon\Carbon::create(null, $m, 1)->format('F') }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-2 mb-2">
                            <label class="f-12 text-dark-grey">@lang('app.year')</label>
                            <select name="year" class="form-control f-14">
                                @foreach (range(now()->year - 3, now()->year) as $y)
                                    <option value="{{ $y }}" @selected($y === $year)>{{ $y }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-4 mb-2">
                            <label class="f-12 text-dark-grey">@lang('app.employee')</label>
                            <select name="employees[]" id="employee-filter" class="form-control f-14" multiple
                                    data-live-search="true" title="Everyone">
                                @foreach ($employees as $employee)
                                    <option value="{{ $employee->id }}"
                                            @selected(in_array((string) $employee->id, $selectedEmployees))>
                                        {{ $employee->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-2 mb-2">
                            <label class="f-12 text-dark-grey">@lang('modules.employees.department')</label>
                            <select name="department" class="form-control f-14">
                                <option value="">All</option>
                                @foreach ($departments as $id => $name)
                                    <option value="{{ $id }}" @selected((string) $id === (string) $departmentId)>{{ $name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-2 mb-2">
                            <button type="submit" class="btn btn-primary f-14 w-100">@lang('app.apply')</button>
                        </div>
                        <div class="col-12">
                            {{-- People taken from the biometric devices have no salary
                                 on record yet, so they are out of the way by default. --}}
                            <label class="f-12 text-lightest mb-0">
                                <input type="checkbox" name="include_unpaid" value="1" @checked($includeUnpaid)
                                       onchange="this.form.submit()">
                                Include employees with no salary on record
                            </label>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        {{-- Who payroll cannot value, said up front. Without this they are
             simply absent, and only a failed run would reveal why. --}}
        @if ($leftOut['total'] > 0)
            <div class="alert alert-info f-13 d-flex align-items-start">
                <i class="fa fa-info-circle mr-2 mt-1"></i>
                <div class="mr-auto">
                    <strong>{{ $leftOut['total'] }} of {{ $leftOut['active'] }} employees cannot be paid yet.</strong>
                    @if ($leftOut['no_ctc'] > 0)
                        {{ $leftOut['no_ctc'] }} have no salary on record{{ $leftOut['no_code'] > 0 ? ',' : '.' }}
                    @endif
                    @if ($leftOut['no_code'] > 0)
                        {{ $leftOut['no_code'] }} have a salary but no employee code.
                    @endif
                    Fill them in for everybody at once with the
                    <a href="{{ route('employee-import.index') }}"><strong>spreadsheet import</strong></a>,
                    or one at a time under <a href="{{ route('employees.index') }}">Employees</a>.
                    @unless ($includeUnpaid)
                        <span class="text-lightest">They are left off the table below until then.</span>
                    @endunless
                </div>
                @unless ($includeUnpaid)
                    <a class="btn btn-sm btn-outline-secondary ml-2 flex-shrink-0"
                       href="{{ route('payroll-run.index', array_merge(request()->query(), ['include_unpaid' => 1])) }}">
                        Show them
                    </a>
                @endunless
            </div>
        @endif

        {{-- The figure payroll deducts is written on to the employee; the Income
             Tax screen recomputes it. Nothing else would say they disagree. --}}
        @if (($taxDrift['drifted'] ?? 0) > 0)
            <div class="alert alert-warning f-13 d-flex align-items-start">
                <i class="fa fa-exclamation-triangle mr-2 mt-1"></i>
                <div class="mr-auto">
                    <strong>{{ $taxDrift['drifted'] }} employee(s) are having the wrong income tax deducted.</strong>
                    What payroll takes off no longer matches what the calculation says — usually because a salary,
                    a declaration or the rates have changed since it was last set.
                </div>
                <a class="btn btn-sm btn-outline-secondary ml-2 flex-shrink-0" href="{{ route('income-tax.index') }}">
                    Put it right
                </a>
            </div>
        @endif

        @if ($isCurrentMonth)
            <div class="alert alert-warning f-13">
                <strong>{{ $monthName }} is still running.</strong>
                These figures cover the days so far, so anything that happens in the rest of the month
                is not counted yet. Issue payslips once the month has ended, or revoke and reissue then.
            </div>
        @endif

        {{-- What the month costs --}}
        <div class="row mb-3">
            @php
                $tiles = [
                    ['Employees', number_format($totals['employees']), 'text-dark-grey',
                        $totals['generated'] . ' already paid · ' . $totals['blocked'] . ' cannot be paid'],
                    ['Gross', $short($totals['gross']), 'text-dark-grey', 'CTC ÷ 12, before deductions'],
                    ['Deductions', $short($totals['deductions']), 'text-yellow',
                        'PF ' . $short($totals['pf']) . ' · ESI ' . $short($totals['esi']) . ' · PT ' . $short($totals['pt'])],
                    ['Total payable', $short($totals['final_net']), 'text-primary',
                        $totals['after_lop'] > 0 ? 'after ' . $short($totals['after_lop']) . ' LOP' : 'no LOP this month'],
                ];
            @endphp
            @foreach ($tiles as [$label, $value, $colour, $sub])
                <div class="col-md-3 col-6 mb-2">
                    <div class="card bg-white border-0 b-shadow-4 h-100">
                        <div class="card-body p-3">
                            <p class="mb-1 f-12 text-lightest text-uppercase">{{ $label }}</p>
                            <h4 class="mb-0 f-21 {{ $colour }}">{{ $value }}</h4>
                            <p class="mb-0 f-11 text-lightest">{{ $sub }}</p>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        {{-- The pay table --}}
        <div class="card bg-white border-0 b-shadow-4 mb-3">
            <div class="card-header bg-white border-bottom d-flex align-items-center flex-wrap p-3">
                <div class="mr-auto">
                    <h6 class="mb-0 f-14">{{ $monthName }} payroll</h6>
                    <p class="mb-0 f-12 text-lightest">
                        {{ $cycle->start->format('d M Y') }} to {{ $cycle->end->format('d M Y') }}
                        ({{ $cycle->days() }} days){{ $cycle->start->day === 1 ? '' : ' · pay cycle set to start on the ' . $cycle->start->day . 'th' }}
                        &middot; every figure here is what the payslip will say.
                    </p>
                </div>

                {{-- Where the money has gone, over any stretch of dates. --}}
                <a class="btn btn-sm btn-outline-secondary mr-2"
                   href="{{ route('payroll-dashboard.index') }}">
                    <i class="fa fa-chart-pie mr-1"></i> Dashboard
                </a>

                {{-- What this month actually paid, once payslips exist. --}}
                <a class="btn btn-sm btn-outline-secondary mr-2"
                   href="{{ route('payroll-register.index', ['month' => $month, 'year' => $year]) }}">
                    <i class="fa fa-book mr-1"></i> Register
                </a>

                {{-- Overtime, bonuses, advances for this month. --}}
                <a class="btn btn-sm btn-outline-secondary mr-2"
                   href="{{ route('pay-elements.index', ['month' => $month, 'year' => $year]) }}">
                    <i class="fa fa-sliders-h mr-1"></i> Pay Elements
                </a>

                {{-- Which design these payslips will be issued in. Said here
                     because this is where they are issued, and because it is
                     fixed on each slip at that moment: changing it afterwards
                     does not restyle anything already generated. --}}
                <span class="f-12 text-lightest mr-3">
                    Payslip design:
                    <strong class="text-dark-grey">{{ $payslipTemplateName }}</strong>
                    <a href="{{ route('payslip-template.preview', $payslipTemplate) }}" target="_blank"
                       class="ml-1" title="Preview this design">preview</a>
                    @if (user()->permission('manage_company_setting') == 'all')
                        <a href="{{ route('payslip-settings.index') }}#payslipTemplateTabPane" class="ml-1">change</a>
                    @endif
                </span>

                <a class="btn btn-sm btn-outline-secondary mr-2"
                   href="{{ route('payroll-run.export', request()->query()) }}">
                    <i class="fa fa-file-excel mr-1"></i> Export to Excel
                </a>

                <button type="button" class="btn btn-sm btn-primary" id="generate-all"
                        @disabled($totals['payable'] - $totals['generated'] < 1)>
                    <i class="fa fa-bolt mr-1"></i>
                    Generate {{ max(0, $totals['payable'] - $totals['generated']) }} payslip(s)
                </button>
            </div>

            <div class="table-responsive" style="max-height: 70vh">
                <table class="table table-hover mb-0 f-13 payroll-table">
                    <thead>
                        <tr>
                            <th>S.No</th>
                            <th class="col-name">Employee Name</th>
                            <th class="text-right">Gross</th>
                            <th class="text-right">Basic</th>
                            <th class="text-right">HRA</th>
                            <th class="text-right">CA</th>
                            <th class="text-right">Other</th>
                            <th class="text-right">PF</th>
                            <th class="text-right">ESI</th>
                            <th class="text-right">PT</th>
                            <th class="text-right">TDS</th>
                            <th class="text-right">Net Salary</th>
                            <th class="text-center">LOP</th>
                            <th class="text-center">No.of. Days</th>
                            <th class="text-center">Present Days</th>
                            <th class="text-right">Per day</th>
                            <th class="text-right">After LOP</th>
                            <th class="text-right">Extras</th>
                            <th class="text-right">Net Salary</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($rows as $row)
                            <tr @class(['text-lightest' => $row['status'] === 'blocked'])>
                                <td>{{ $row['serial'] }}</td>
                                <td class="col-name">
                                    <span class="font-weight-bold">{{ $row['name'] }}</span>
                                    <br>
                                    <span class="f-11 text-lightest">
                                        {{ $row['card_id'] ?: 'no code' }}{{ $row['department'] ? ' · ' . $row['department'] : '' }}
                                    </span>
                                </td>
                                <td class="text-right">{{ number_format($row['gross'], 2) }}</td>
                                <td class="text-right">{{ number_format($row['basic'], 2) }}</td>
                                <td class="text-right">{{ number_format($row['hra'], 2) }}</td>
                                <td class="text-right">{{ number_format($row['ca'], 2) }}</td>
                                <td class="text-right">{{ number_format($row['other'], 2) }}</td>
                                <td class="text-right">{{ number_format($row['pf'], 2) }}</td>
                                <td class="text-right">{{ number_format($row['esi'], 2) }}</td>
                                <td class="text-right">{{ number_format($row['pt'], 2) }}</td>
                                <td class="text-right">
                                    {{-- A figure per employee, set by the accountant. --}}
                                    @if ($row['status'] === 'generated')
                                        {{ $row['tds'] > 0 ? number_format($row['tds'], 2) : '—' }}
                                    @else
                                        <a href="#" class="set-tds" data-user="{{ $row['user_id'] }}"
                                           data-name="{{ $row['name'] }}" data-amount="{{ $row['tds'] }}"
                                           title="Set this employee's monthly income tax">
                                            {{ $row['tds'] > 0 ? number_format($row['tds'], 2) : 'set' }}
                                        </a>
                                    @endif
                                </td>
                                <td class="text-right">{{ number_format($row['net'], 2) }}</td>
                                <td class="text-center">{{ $num($row['lop_days']) }}</td>
                                <td class="text-center">{{ $row['working_days'] }}</td>
                                <td class="text-center">{{ $num($row['days_present']) }}</td>
                                <td class="text-right">{{ number_format($row['per_day'], 2) }}</td>
                                <td class="text-right {{ $row['after_lop'] > 0 ? 'text-red' : '' }}">
                                    {{ number_format($row['after_lop'], 2) }}
                                </td>
                                <td class="text-right">
                                    {{-- Overtime, a bonus, arrears, an advance being recovered. --}}
                                    @forelse ($row['extras'] as $extra)
                                        <div class="f-11 {{ $extra->kind === 'earning' ? 'text-success' : 'text-red' }}"
                                             title="{{ $extra->note }}">
                                            {{ $extra->kind === 'earning' ? '+' : '−' }}{{ number_format($extra->amount, 2) }}
                                            <span class="text-lightest">{{ \Illuminate\Support\Str::limit($extra->label, 18) }}</span>
                                            @unless ($extra->payslip_id)
                                                <a href="#" class="remove-extra text-lightest ml-1"
                                                   data-id="{{ $extra->id }}" title="Remove">&times;</a>
                                            @endunless
                                        </div>
                                    @empty
                                        <span class="text-lightest">—</span>
                                    @endforelse

                                    {{-- Always offered. If the month's payslip is
                                         already out the endpoint says so, rather
                                         than the option quietly disappearing. --}}
                                    <a href="#" class="add-extra f-11" data-user="{{ $row['user_id'] }}"
                                       data-name="{{ $row['name'] }}"
                                       data-issued="{{ $row['status'] === 'generated' ? 1 : 0 }}">+ add</a>
                                </td>
                                <td class="text-right font-weight-bold">{{ number_format($row['final_net'], 2) }}</td>
                                <td>
                                    @if ($row['status'] === 'generated')
                                        <span class="badge badge-success" title="Payslip already issued for this month">Issued</span>
                                    @elseif ($row['status'] === 'blocked')
                                        <span class="badge badge-danger" title="{{ implode(', ', $row['blockers']) }}">
                                            {{ implode(', ', $row['blockers']) }}
                                        </span>
                                    @else
                                        <span class="badge badge-light border">To issue</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="20" class="text-center text-lightest p-4">
                                    No employees match these filters.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                    @if ($rows !== [])
                        <tfoot>
                            <tr>
                                <td></td>
                                <td class="col-name">Total — {{ $monthName }}</td>
                                <td class="text-right">{{ number_format($totals['gross'], 2) }}</td>
                                <td colspan="4"></td>
                                <td class="text-right">{{ number_format($totals['pf'], 2) }}</td>
                                <td class="text-right">{{ number_format($totals['esi'], 2) }}</td>
                                <td class="text-right">{{ number_format($totals['pt'], 2) }}</td>
                                <td class="text-right">{{ number_format($totals['tds'], 2) }}</td>
                                <td class="text-right">{{ number_format($totals['net'], 2) }}</td>
                                <td class="text-center">{{ $num($totals['lop_days']) }}</td>
                                <td colspan="3"></td>
                                <td class="text-right">{{ number_format($totals['after_lop'], 2) }}</td>
                                <td class="text-right f-11">
                                    @if ($totals['extra_earnings'] > 0)<span class="text-success">+{{ number_format($totals['extra_earnings'], 2) }}</span>@endif
                                    @if ($totals['extra_deductions'] > 0)<span class="text-red d-block">−{{ number_format($totals['extra_deductions'], 2) }}</span>@endif
                                    @if ($totals['extra_earnings'] <= 0 && $totals['extra_deductions'] <= 0)—@endif
                                </td>
                                <td class="text-right">{{ number_format($totals['final_net'], 2) }}</td>
                                <td></td>
                            </tr>
                        </tfoot>
                    @endif
                </table>
            </div>
        </div>

        <p class="f-11 text-lightest">
            PF is both sides together, capped at the wage ceiling; ESI likewise, where the salary is within
            the limit; Net Salary is gross less PF, ESI and PT; After LOP is the unpaid days at the per-day rate.
            The rates are on the payroll settings screen.
        </p>
    </div>
@endsection

@push('scripts')
    <script>
        $(function () {
            $('#employee-filter').selectpicker({
                liveSearch: true,
                actionsBox: true,
                selectedTextFormat: 'count > 2',
                countSelectedText: '{0} employees',
                noneSelectedText: 'Everyone',
                container: 'body',
                width: '100%'
            });

            var csrf = '{{ csrf_token() }}';
            var generateRoute = "{{ route('payroll-run.generate') }}";
            var month = {{ $month }}, year = {{ $year }};

            // Only those who can actually be paid and are not paid already.
            var pending = @json(collect($rows)->where('status', 'pending')->pluck('user_id')->values());
            var BATCH_SIZE = 5;

            // ---- extras: overtime, bonus, arrears, recoveries ---------------
            $(document).on('click', '.add-extra', function (event) {
                event.preventDefault();
                var row = $(this);

                Swal.fire({
                    title: 'Add to ' + row.data('name') + '’s pay',
                    html:
                        '<p class="mb-2" style="font-size:12px;color:#888">For {{ $monthName }}. ' +
                        'It appears on the payslip as its own line.</p>' +
                        (row.data('issued')
                            ? '<p class="mb-2" style="font-size:12px;color:#d0342c">This month’s payslip has ' +
                              'already been issued, so it cannot take another line. Revoke and reissue it, or ' +
                              'add the amount to next month instead.</p>'
                            : '') +
                        '<select id="extra-kind" class="swal2-select" style="width:100%">' +
                        '  <option value="earning">Pay extra (overtime, bonus, arrears)</option>' +
                        '  <option value="deduction">Deduct (advance, loan, recovery)</option>' +
                        '</select>' +
                        '<input id="extra-label" class="swal2-input" placeholder="What is it? e.g. Overtime">' +
                        '<input id="extra-amount" class="swal2-input" type="number" step="0.01" min="0.01" placeholder="Amount">' +
                        '<input id="extra-note" class="swal2-input" placeholder="Note (optional)">',
                    showCancelButton: true,
                    confirmButtonText: 'Add',
                    cancelButtonText: '@lang('app.cancel')',
                    preConfirm: function () {
                        var label = $('#extra-label').val(), amount = parseFloat($('#extra-amount').val());

                        if (!label || !label.trim()) { Swal.showValidationMessage('Give it a name'); return false; }
                        if (!amount || amount <= 0) { Swal.showValidationMessage('Enter an amount'); return false; }

                        return { kind: $('#extra-kind').val(), label: label.trim(), amount: amount, note: $('#extra-note').val() };
                    }
                }).then(function (result) {
                    if (!result.isConfirmed) { return; }

                    $.post('{{ route('payroll-run.component.add') }}', {
                        user_id: row.data('user'), month: month, year: year,
                        kind: result.value.kind, label: result.value.label,
                        amount: result.value.amount, note: result.value.note, _token: csrf
                    }).done(function (response) {
                        Swal.fire({ icon: response.status === 'success' ? 'success' : 'error', title: response.message })
                            .then(function () { window.location.reload(); });
                    }).fail(function (xhr) {
                        Swal.fire({ icon: 'error', title: (xhr.responseJSON && xhr.responseJSON.message) || 'Could not add it' });
                    });
                });
            });

            // ---- income tax: a monthly figure per employee ------------------
            $(document).on('click', '.set-tds', function (event) {
                event.preventDefault();
                var cell = $(this);

                Swal.fire({
                    title: 'Income tax for ' + cell.data('name'),
                    html:
                        '<p class="mb-2" style="font-size:12px;color:#888">The monthly TDS figure. It is ' +
                        'deducted on every payslip issued from now on, until it is changed.</p>' +
                        '<input id="tds-amount" class="swal2-input" type="number" step="0.01" min="0" ' +
                        'placeholder="0.00" value="' + (parseFloat(cell.data('amount')) || '') + '">',
                    showCancelButton: true,
                    confirmButtonText: 'Save',
                    cancelButtonText: '@lang('app.cancel')',
                    preConfirm: function () {
                        var amount = parseFloat($('#tds-amount').val());

                        if (isNaN(amount) || amount < 0) {
                            Swal.showValidationMessage('Enter an amount, or 0 for none');

                            return false;
                        }

                        return amount;
                    }
                }).then(function (result) {
                    if (!result.isConfirmed) { return; }

                    $.post('{{ route('payroll-run.tds') }}', { user_id: cell.data('user'), amount: result.value, _token: csrf })
                        .done(function () { window.location.reload(); })
                        .fail(function (xhr) {
                            Swal.fire({ icon: 'error', title: (xhr.responseJSON && xhr.responseJSON.message) || 'Could not save it' });
                        });
                });
            });
            $(document).on('click', '.remove-extra', function (event) {
                event.preventDefault();
                var id = $(this).data('id');

                Swal.fire({
                    title: 'Remove this line?',
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonText: 'Remove',
                    cancelButtonText: '@lang('app.cancel')'
                }).then(function (result) {
                    if (!result.isConfirmed) { return; }

                    $.post('{{ route('payroll-run.component.remove') }}', { id: id, _token: csrf })
                        .done(function () { window.location.reload(); })
                        .fail(function (xhr) {
                            Swal.fire({ icon: 'error', title: (xhr.responseJSON && xhr.responseJSON.message) || 'Could not remove it' });
                        });
                });
            });

            $('#generate-all').on('click', function () {
                if (!pending.length) { return; }

                Swal.fire({
                    title: 'Issue ' + pending.length + ' payslip(s)?',
                    html: '<p class="mb-2" style="font-size:13px">For <strong>{{ $monthName }}</strong>, ' +
                          'using the figures shown. Anyone already holding a payslip for this month is left alone.</p>' +
                          @json($isCurrentMonth
                              ? '<p class="mb-0" style="font-size:12px;color:#b45309">This month has not ended, so only the days so far are counted.</p>'
                              : ''),
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonText: 'Issue payslips',
                    cancelButtonText: '@lang('app.cancel')'
                }).then(function (result) {
                    if (result.isConfirmed) { run(pending.slice()); }
                });
            });

            // Issued a few at a time, so the screen can report as it goes and a
            // long run cannot outlast the server's time limit.
            function run(ids) {
                var total = ids.length, done = 0, created = 0, skipped = 0, reasons = [], stopped = null;

                Swal.fire({
                    title: 'Issuing ' + total + ' payslip(s)',
                    html:
                        '<div class="progress mb-2" style="height:18px">' +
                        '  <div id="pay-bar" class="progress-bar progress-bar-striped progress-bar-animated"' +
                        '       role="progressbar" style="width:0%">0%</div>' +
                        '</div>' +
                        '<p id="pay-count" class="mb-0" style="font-size:13px">Starting…</p>',
                    allowOutsideClick: false,
                    allowEscapeKey: false,
                    showConfirmButton: false,
                    didOpen: next
                });

                function paint() {
                    var pct = total ? Math.round((done / total) * 100) : 100;
                    $('#pay-bar').css('width', pct + '%').text(pct + '%');
                    $('#pay-count').html('<strong>' + created + '</strong> issued' +
                        (skipped ? ' · <strong>' + skipped + '</strong> skipped' : '') +
                        ' · ' + done + ' of ' + total + ' processed');
                }

                function finish() {
                    var detail = reasons.slice(0, 5).map(function (r) {
                        return '<div class="text-left" style="font-size:12px">' + $('<div>').text(r).html() + '</div>';
                    }).join('');

                    if (reasons.length > 5) {
                        detail += '<div class="text-left" style="font-size:12px">and ' + (reasons.length - 5) + ' more</div>';
                    }

                    Swal.fire({
                        icon: stopped ? 'error' : (created ? 'success' : 'warning'),
                        title: stopped ? 'Stopped' : (created ? 'Payslips issued' : 'Nothing issued'),
                        html: '<p class="mb-2" style="font-size:13px">' +
                              (stopped ? $('<div>').text(stopped).html() + '<br>' : '') +
                              '<strong>' + created + '</strong> issued' +
                              (skipped ? ', <strong>' + skipped + '</strong> skipped' : '') +
                              ' out of ' + total + '.</p>' +
                              (detail ? '<p class="mb-1" style="font-size:12px"><strong>Skipped:</strong></p>' + detail : '')
                    }).then(function () { window.location.reload(); });
                }

                function next() {
                    if (done >= total) { return finish(); }

                    var batch = ids.slice(done, done + BATCH_SIZE);

                    $.post(generateRoute, { user_ids: batch, month: month, year: year, _token: csrf })
                        .done(function (response) {
                            created += response.created || 0;
                            skipped += response.skipped || 0;
                            reasons = reasons.concat(response.reasons || []);
                            done += batch.length;
                            paint();
                            next();
                        })
                        .fail(function (xhr) {
                            stopped = xhr.status === 403
                                ? 'You do not have permission to issue payslips.'
                                : ((xhr.responseJSON && xhr.responseJSON.message) || 'The server did not answer.');
                            finish();
                        });
                }
            }
        });
    </script>
@endpush
