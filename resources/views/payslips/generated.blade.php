@extends('layouts.app')

@push('styles')
    <style>
        /* A withdrawn payslip stays on record but should not read as current. */
        .table-revoked > td { background: #fff6f6; color: #8a8a8a; }
        .table-revoked .font-weight-bold { font-weight: 500 !important; }
        .table-revoked:hover > td { background: #ffecec; }
    </style>
@endpush

@section('content')
    {{-- Standard page gutter, matching the rest of the app --}}
    <div class="content-wrapper arw-dash">

        {{-- Summary --}}
        @php
            $money = fn ($v) => '₹' . number_format((float) $v, 2);
            $short = function ($v) {
                $v = (float) $v;
                return $v >= 100000 ? '₹' . number_format($v / 100000, 2) . 'L' : '₹' . number_format($v);
            };
        @endphp

        <div class="row mb-3">
            {{-- Count --}}
            <div class="col-md-3 col-6 mb-2">
                <div class="card bg-white border-0 b-shadow-4 h-100">
                    <div class="card-body p-3">
                        <p class="mb-1 f-12 text-lightest text-uppercase">Payslips</p>
                        <h4 class="mb-2 f-21 text-dark-grey">{{ number_format($summary['count']) }}</h4>
                        <p class="mb-0 f-11 text-lightest">
                            {{ $summary['employees'] }} employee{{ $summary['employees'] === 1 ? '' : 's' }}
                            &middot; {{ $summary['periods'] }} period{{ $summary['periods'] === 1 ? '' : 's' }}
                        </p>
                    </div>
                </div>
            </div>

            {{-- Gross, split into what makes it up --}}
            <div class="col-md-3 col-6 mb-2">
                <div class="card bg-white border-0 b-shadow-4 h-100">
                    <div class="card-body p-3">
                        <p class="mb-1 f-12 text-lightest text-uppercase">Gross</p>
                        <h4 class="mb-2 f-21 text-dark-grey">{{ $money($summary['gross']) }}</h4>
                        @foreach ($summary['earnings'] as $label => $value)
                            <div class="d-flex justify-content-between f-11 text-lightest">
                                <span>{{ $label }}</span>
                                <span>{{ $short($value) }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>

            {{-- Deductions, one line per statutory head --}}
            <div class="col-md-3 col-6 mb-2">
                <div class="card bg-white border-0 b-shadow-4 h-100">
                    <div class="card-body p-3">
                        <p class="mb-1 f-12 text-lightest text-uppercase">Deductions</p>
                        <h4 class="mb-1 f-21 text-yellow">{{ $money($summary['deductions']) }}</h4>
                        <p class="mb-2 f-11 text-lightest">{{ $summary['deduction_pct'] }}% of gross</p>

                        @foreach ($summary['heads'] as $label => $head)
                            <div class="d-flex justify-content-between f-11" title="{{ $head['note'] }}">
                                <span class="text-dark-grey">{{ $label }}</span>
                                @if ($head['value'] > 0)
                                    <span class="text-dark-grey">{{ $short($head['value']) }}</span>
                                @else
                                    {{-- Zero here means the rule did not apply, not missing data. --}}
                                    <span class="text-lightest">not applicable</span>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>

            {{-- Net --}}
            <div class="col-md-3 col-6 mb-2">
                <div class="card bg-white border-0 b-shadow-4 h-100">
                    <div class="card-body p-3">
                        <p class="mb-1 f-12 text-lightest text-uppercase">Net Payable</p>
                        <h4 class="mb-2 f-21 text-primary">{{ $money($summary['net']) }}</h4>
                        <div class="d-flex justify-content-between f-11 text-lightest">
                            <span>Average</span>
                            <span>{{ $short($summary['avg_net']) }}</span>
                        </div>
                        @if ($summary['lop'] > 0)
                            <div class="d-flex justify-content-between f-11 text-red">
                                <span>after LOP deducted</span>
                                <span>{{ $short($summary['lop']) }}</span>
                            </div>
                        @endif
                        <div class="d-flex justify-content-between f-11 text-lightest">
                            <span>after employer PF / ESI</span>
                            <span>{{ $short($summary['employer']) }}</span>
                        </div>
                        @if ($summary['encashment'] > 0)
                            <div class="d-flex justify-content-between f-11 text-success">
                                <span>+ Leave encashment</span>
                                <span>{{ $short($summary['encashment']) }}</span>
                            </div>
                            <div class="d-flex justify-content-between f-11 text-dark-grey font-weight-bold">
                                <span>Total payable</span>
                                <span>{{ $short($summary['total_payable']) }}</span>
                            </div>
                        @endif
                        <div class="d-flex justify-content-between f-11 text-lightest">
                            <span>Gross less deductions</span>
                            @if ($summary['reconciles'])
                                <span class="text-primary">balances</span>
                            @else
                                <span class="text-red" title="Stored gross, deductions and net do not agree">does not balance</span>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>

        @if ($summary['flagged'] > 0)
            <div class="alert alert-warning f-13">
                <strong>{{ $summary['flagged'] }} payslip{{ $summary['flagged'] === 1 ? '' : 's' }} contain figures that cannot be right.</strong>
                These are internal contradictions — components that do not add up to gross, a net that does
                not match gross less deductions, or zero working days. Hover the
                <i class="fa fa-exclamation-triangle text-yellow"></i> on a row to see which.
            </div>
        @endif

        <div class="alert alert-info f-13">
            <strong>One active payslip per employee per month.</strong>
            Generating a second one for the same month is refused. If a payslip is wrong,
            <strong>revoke</strong> it with a reason — the record and the reason stay visible — and the
            month is released so a corrected payslip can be issued.
        </div>

        {{-- Filters --}}
        <div class="card bg-white border-0 b-shadow-4 mb-3">
            <div class="card-body p-3">
                <form method="GET" action="{{ route('payslips.generated') }}">
                    <div class="form-row align-items-end">
                        <div class="col-md-3 mb-2">
                            <label class="f-12 text-dark-grey">Period</label>
                            <select name="period" class="form-control form-control-sm filter-picker">
                                <option value="">All periods</option>
                                @foreach ($periods as $value => $label)
                                    <option value="{{ $value }}" @selected($filters['period'] === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-md-3 mb-2">
                            <label class="f-12 text-dark-grey">Employee</label>
                            <select name="employee" class="form-control form-control-sm filter-picker">
                                <option value="">All employees</option>
                                @foreach ($employees as $cardId => $name)
                                    <option value="{{ $cardId }}" @selected((string) $filters['employee'] === (string) $cardId)>
                                        {{ $name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-md-3 mb-2">
                            <label class="f-12 text-dark-grey">Department</label>
                            <select name="department" class="form-control form-control-sm filter-picker">
                                <option value="">All departments</option>
                                @foreach ($departments as $id => $name)
                                    <option value="{{ $id }}" @selected((string) $filters['department'] === (string) $id)>
                                        {{ $name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-md-2 mb-2">
                            <div class="custom-control custom-checkbox">
                                <input type="checkbox" class="custom-control-input" id="all_versions"
                                       name="all_versions" value="1" @checked($filters['all_versions'])>
                                <label class="custom-control-label f-12" for="all_versions">
                                    Show every version
                                </label>
                            </div>
                            <span class="f-11 text-lightest">
                                Generating appends a row; by default only the newest per month is shown.
                            </span>
                        </div>

                        <div class="col-md-1 mb-2">
                            <button type="submit" class="btn btn-sm btn-primary btn-block">@lang('app.apply')</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        {{-- The list --}}
        <div class="card bg-white border-0 b-shadow-4">
            <div class="card-header bg-white border-bottom d-flex align-items-center flex-wrap p-3">
                @php
                    $active = collect($payslips)->filter(fn ($s) => $s->status === 'active');
                    // Drafts waiting for someone to approve them, and approved
                    // payslips nobody has been sent yet.
                    $drafts = $active->where('approval_status', 'draft');
                    $unsent = $active->where('approval_status', '!=', 'draft')->filter(fn ($s) => !$s->sent_at);
                @endphp
                <h6 class="mb-0 f-14 mr-auto">Generated payslips</h6>

                @if ($drafts->isNotEmpty())
                    <button type="button" class="btn btn-sm btn-outline-info mr-2" id="approve-drafts"
                            data-ids="{{ $drafts->pluck('id')->implode(',') }}">
                        <i class="fa fa-check-double mr-1"></i>
                        Approve {{ $drafts->count() }} draft{{ $drafts->count() === 1 ? '' : 's' }}
                    </button>
                @endif

                @if ($unsent->isNotEmpty())
                    <button type="button" class="btn btn-sm btn-outline-primary mr-2" id="email-unsent"
                            data-ids="{{ $unsent->pluck('id')->implode(',') }}">
                        <i class="fa fa-paper-plane mr-1"></i>
                        E-mail {{ $unsent->count() }} unsent
                    </button>
                @endif

                <a href="{{ route('payslips.index') }}" class="btn btn-sm btn-outline-secondary">
                    <i class="fa fa-calculator mr-1"></i> Payslip calculator
                </a>
            </div>

            <div class="table-responsive">
                <table class="table table-hover mb-0 f-13">
                    <thead>
                        <tr class="bg-light">
                            <th>Employee</th>
                            <th>Designation</th>
                            <th>Department</th>
                            <th>Period</th>
                            <th class="text-right">Gross <span class="f-11 text-lightest font-weight-normal">/ components</span></th>
                            <th class="text-right">Deductions <span class="f-11 text-lightest font-weight-normal">/ heads</span></th>
                            <th class="text-right">Net salary</th>
                            <th class="text-center">Days</th>
                            <th>Status</th>
                            <th>Generated</th>
                            <th style="width:190px"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($payslips as $slip)
                            @php
                                // Hovering a revoked payslip anywhere along the row
                                // says why it was withdrawn, by whom and when.
                                $revokedNote = $slip->status === 'revoked'
                                    ? 'Revoked'
                                        . ($slip->revoked_at ? ' on ' . \Carbon\Carbon::parse($slip->revoked_at)->format('d M Y, H:i') : '')
                                        . ($slip->revoked_by_name ? ' by ' . $slip->revoked_by_name : '')
                                        . ($slip->revoke_reason ? ' — ' . $slip->revoke_reason : ' — no reason recorded')
                                    : null;
                            @endphp
                            <tr @class(['table-revoked' => $slip->status === 'revoked'])
                                @if ($revokedNote) title="{{ $revokedNote }}" @endif>
                                <td>
                                    <span class="font-weight-bold">{{ $slip->emp_name ?: '—' }}</span>
                                    @if ($slip->warnings)
                                        <i class="fa fa-exclamation-triangle text-yellow ml-1"
                                           title="{{ implode(' · ', $slip->warnings) }}"></i>
                                    @endif
                                    <br>
                                    <span class="f-11 text-lightest">
                                        Card {{ $slip->emp_id ?: '—' }}
                                        @if (!$slip->is_latest)
                                            &middot; <span class="badge badge-secondary">superseded</span>
                                        @endif
                                    </span>
                                </td>
                                <td class="f-12">{{ $slip->emp_designation ?: '—' }}</td>
                                <td class="f-12">{{ $slip->department_name ?: '—' }}</td>
                                <td>{{ $slip->period_label }}</td>
                                <td class="text-right">
                                    ₹{{ number_format((float) $slip->gross, 2) }}
                                    <br>
                                    <span class="f-11 text-lightest">
                                        B {{ number_format((float) $slip->basic) }}
                                        &middot; H {{ number_format((float) $slip->hra) }}
                                        &middot; CA {{ number_format((float) $slip->ca) }}
                                    </span>
                                </td>
                                <td class="text-right text-yellow">
                                    ₹{{ number_format((float) $slip->total_deductions, 2) }}
                                    <br>
                                    <span class="f-11 text-lightest">
                                        {{-- Each head named, so a zero reads as "did not apply". --}}
                                        PF {{ number_format((float) $slip->pf) }}
                                        &middot; PT {{ number_format((float) $slip->pt) }}
                                        &middot; ESI {{ (float) $slip->esi > 0 ? number_format((float) $slip->esi) : '—' }}
                                    </span>
                                </td>
                                <td class="text-right font-weight-bold">
                                    ₹{{ number_format((float) $slip->take_home, 2) }}
                                    @if ($slip->lop_amount > 0)
                                        <br><span class="f-11 text-red font-weight-normal"
                                            title="Attendance deduction: {{ rtrim(rtrim(number_format(max(0, (float) $slip->working_days - (float) $slip->days_present), 1), '0'), '.') }} unpaid day(s)">
                                            − ₹{{ number_format($slip->lop_amount, 2) }} LOP
                                        </span>
                                    @endif
                                    @if ((float) ($slip->leave_encashment ?? 0) > 0)
                                        <br><span class="f-11 text-success font-weight-normal"
                                            title="Leave encashment, paid with this payslip">
                                            + ₹{{ number_format((float) $slip->leave_encashment, 2) }}
                                            ({{ rtrim(rtrim(number_format((float) $slip->encashed_days, 2), '0'), '.') }}d leave)
                                        </span>
                                    @endif
                                </td>
                                <td class="text-center f-12">
                                    {{ rtrim(rtrim(number_format((float) $slip->days_present, 1), '0'), '.') }}
                                    <span class="text-lightest">/
                                        {{ rtrim(rtrim(number_format((float) $slip->working_days, 1), '0'), '.') }}</span>
                                </td>
                                <td>
                                    @php
                                        $badge = match ($slip->status) {
                                            'active' => ['success', 'Active'],
                                            'revoked' => ['danger', 'Revoked'],
                                            'superseded' => ['secondary', 'Superseded'],
                                            default => ['secondary', ucfirst((string) $slip->status)],
                                        };
                                    @endphp
                                    <span class="badge badge-{{ $badge[0] }}">{{ $badge[1] }}</span>
                                    @php
                                        // Where this payslip stands: a draft until
                                        // somebody approves it, locked once paid.
                                        $approval = match ($slip->approval_status) {
                                            'paid' => ['success', 'Paid', $slip->paid_at
                                                ? 'Paid ' . \Carbon\Carbon::parse($slip->paid_at)->format('d M Y')
                                                    . ($slip->payment_reference ? ' · ' . $slip->payment_reference : '')
                                                : 'Paid'],
                                            'approved' => ['info', 'Approved', $slip->approved_at
                                                ? 'Approved ' . \Carbon\Carbon::parse($slip->approved_at)->format('d M Y H:i')
                                                : 'Approved'],
                                            default => ['warning', 'Draft', 'Not approved yet — it cannot be e-mailed until it is'],
                                        };
                                    @endphp
                                    <span class="badge badge-{{ $approval[0] }} ml-1" title="{{ $approval[2] }}">{{ $approval[1] }}</span>
                                    @if ($revokedNote)
                                        <i class="fa fa-info-circle text-lightest ml-1" title="{{ $revokedNote }}"></i>
                                        {{-- The first words of the reason, so the table can be read without hovering. --}}
                                        <span class="d-block f-11 text-lightest" style="max-width:220px" title="{{ $revokedNote }}">
                                            {{ \Illuminate\Support\Str::limit($slip->revoke_reason ?: 'no reason recorded', 44) }}
                                        </span>
                                    @endif
                                </td>
                                <td class="f-12 text-lightest">
                                    {{ $slip->created_at ? \Carbon\Carbon::parse($slip->created_at)->format('d M Y H:i') : '—' }}
                                </td>
                                <td class="text-nowrap">
                                    {{-- The legacy renderer already accepts a payslip id. --}}
                                    <a href="{{ route('legacy.page', 'payslips') }}?id={{ $slip->id }}"
                                       target="_blank" class="btn btn-xs btn-outline-secondary" title="View payslip">
                                        <i class="fa fa-eye"></i>
                                    </a>

                                    <a href="{{ route('payslips.generated.pdf', $slip->id) }}"
                                       class="btn btn-xs btn-outline-secondary" title="Download as PDF">
                                        <i class="fa fa-file-pdf"></i>
                                    </a>

                                    @if ($slip->status === 'active' && $slip->approval_status === 'draft')
                                        <button type="button" class="btn btn-xs btn-outline-info approve-btn"
                                                data-id="{{ $slip->id }}"
                                                data-name="{{ $slip->emp_name }}"
                                                data-period="{{ $slip->period_label }}"
                                                title="Approve this payslip">
                                            <i class="fa fa-check"></i> Approve
                                        </button>
                                    @endif

                                    @if ($slip->status === 'active' && $slip->approval_status === 'approved')
                                        <button type="button" class="btn btn-xs btn-outline-success paid-btn"
                                                data-id="{{ $slip->id }}"
                                                data-name="{{ $slip->emp_name }}"
                                                title="Record that this payslip has been paid">
                                            <i class="fa fa-rupee-sign"></i> Mark paid
                                        </button>
                                    @endif

                                    @if ($slip->status === 'active' && $slip->approval_status !== 'draft')
                                        <button type="button" class="btn btn-xs btn-outline-secondary email-btn"
                                                data-id="{{ $slip->id }}"
                                                data-name="{{ $slip->emp_name }}"
                                                title="{{ $slip->sent_at
                                                    ? 'Sent ' . \Carbon\Carbon::parse($slip->sent_at)->format('d M Y H:i') . ' to ' . $slip->sent_to
                                                    : 'E-mail this payslip to the employee' }}">
                                            <i class="fa fa-envelope{{ $slip->sent_at ? ' text-success' : '' }}"></i>
                                        </button>
                                    @endif

                                    @if ($slip->status === 'active')
                                        <button type="button" class="btn btn-xs btn-outline-warning revoke-btn"
                                                data-id="{{ $slip->id }}"
                                                data-name="{{ $slip->emp_name }}"
                                                data-period="{{ $slip->period_label }}"
                                                title="Revoke this payslip">
                                            <i class="fa fa-ban"></i> Revoke
                                        </button>
                                    @endif

                                    <button type="button" class="btn btn-xs btn-outline-danger delete-btn"
                                            data-id="{{ $slip->id }}"
                                            data-name="{{ $slip->emp_name }}"
                                            data-period="{{ $slip->period_label }}"
                                            title="Delete this payslip">
                                        <i class="fa fa-trash"></i>
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="11" class="text-center text-lightest p-4">
                                    No payslips match these filters.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        $(function () {
            // container:'body' matters — the filter row sits inside a card, and
            // the card clips the dropdown, so the menu opened but its options
            // were invisible. Rendering the menu on <body> escapes the clipping.
            $('.filter-picker').selectpicker({
                liveSearch: true,
                liveSearchPlaceholder: 'Type to search…',
                noneResultsText: 'Nothing matches {0}',
                container: 'body',
                width: '100%',
                size: 10
            });

            var csrf = '{{ csrf_token() }}';

            function act(url, payload, successTitle) {
                $.post(url, payload, function (response) {
                    Swal.fire({
                        icon: response.status === 'success' ? 'success' : 'error',
                        title: response.status === 'success' ? successTitle : 'Could not continue',
                        text: response.message
                    }).then(function () { window.location.reload(); });
                }).fail(function (xhr) {
                    var message = '';

                    if (xhr.responseJSON) {
                        // Validation failures come back as a field bag.
                        message = xhr.responseJSON.message
                            || (xhr.responseJSON.errors && Object.values(xhr.responseJSON.errors)[0][0])
                            || '';
                    }

                    Swal.fire({ icon: 'error', title: 'Could not continue', text: message });
                });
            }

            // ---- approving and paying ---------------------------------------
            $(document).on('click', '.approve-btn', function () {
                var button = $(this);

                Swal.fire({
                    title: 'Approve this payslip?',
                    html: '<p class="mb-0" style="font-size:13px">' +
                          $('<div>').text(button.data('name')).html() + ' &middot; ' +
                          $('<div>').text(button.data('period')).html() +
                          '<br><span style="color:#888">Once approved it can be sent to the employee.</span></p>',
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonText: 'Approve',
                    cancelButtonText: '@lang('app.cancel')'
                }).then(function (result) {
                    if (result.isConfirmed) {
                        act('{{ route('payslips.generated.approve') }}', { ids: [button.data('id')], _token: csrf }, 'Approved');
                    }
                });
            });

            $('#approve-drafts').on('click', function () {
                var ids = String($(this).data('ids')).split(',').filter(Boolean);

                if (!ids.length) { return; }

                Swal.fire({
                    title: 'Approve ' + ids.length + ' payslip(s)?',
                    html: '<p class="mb-0" style="font-size:13px">Every draft shown here. ' +
                          'Approving records you as the approver and lets them be sent to employees.</p>',
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonText: 'Approve all',
                    cancelButtonText: '@lang('app.cancel')'
                }).then(function (result) {
                    if (result.isConfirmed) {
                        act('{{ route('payslips.generated.approve') }}', { ids: ids, _token: csrf }, 'Approved');
                    }
                });
            });

            $(document).on('click', '.paid-btn', function () {
                var button = $(this);

                Swal.fire({
                    title: 'Mark as paid?',
                    html: '<p class="mb-2" style="font-size:13px">' +
                          $('<div>').text(button.data('name')).html() + '</p>' +
                          '<p class="mb-0" style="font-size:12px;color:#b45309">A paid payslip cannot be revoked ' +
                          'or deleted afterwards — corrections go on the next one.</p>',
                    input: 'text',
                    inputLabel: 'Payment reference (optional)',
                    inputPlaceholder: 'Bank transfer or cheque number',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'Mark paid',
                    confirmButtonColor: '#16a34a',
                    cancelButtonText: '@lang('app.cancel')'
                }).then(function (result) {
                    if (result.isConfirmed) {
                        act('{{ route('payslips.generated.paid') }}',
                            { ids: [button.data('id')], reference: result.value || '', _token: csrf }, 'Marked paid');
                    }
                });
            });

            // ---- e-mailing payslips -----------------------------------------
            $(document).on('click', '.email-btn', function () {
                var button = $(this);

                Swal.fire({
                    title: 'E-mail this payslip?',
                    html: '<p class="mb-0" style="font-size:13px">To ' +
                          $('<div>').text(button.data('name')).html() +
                          ', with the payslip attached as a PDF.</p>',
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonText: 'Send',
                    cancelButtonText: '@lang('app.cancel')'
                }).then(function (result) {
                    if (result.isConfirmed) { sendPayslips([button.data('id')]); }
                });
            });

            $('#email-unsent').on('click', function () {
                var ids = String($(this).data('ids')).split(',').filter(Boolean);

                if (!ids.length) { return; }

                Swal.fire({
                    title: 'E-mail ' + ids.length + ' payslip(s)?',
                    html: '<p class="mb-0" style="font-size:13px">Each employee gets their own payslip ' +
                          'attached as a PDF. Payslips already sent are not included.</p>',
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonText: 'Send',
                    cancelButtonText: '@lang('app.cancel')'
                }).then(function (result) {
                    if (result.isConfirmed) { sendPayslips(ids); }
                });
            });

            // Sent a few at a time: each one renders a PDF, so a long run would
            // otherwise outlast the server's time limit with nothing on screen.
            function sendPayslips(ids) {
                var total = ids.length, done = 0, sent = 0, skipped = 0, reasons = [], stopped = null;
                var BATCH = 3;

                Swal.fire({
                    title: 'Sending ' + total + ' payslip(s)',
                    html: '<div class="progress mb-2" style="height:18px">' +
                          '  <div id="mail-bar" class="progress-bar progress-bar-striped progress-bar-animated"' +
                          '       role="progressbar" style="width:0%">0%</div></div>' +
                          '<p id="mail-count" class="mb-0" style="font-size:13px">Starting…</p>',
                    allowOutsideClick: false,
                    allowEscapeKey: false,
                    showConfirmButton: false,
                    didOpen: next
                });

                function paint() {
                    var pct = total ? Math.round((done / total) * 100) : 100;
                    $('#mail-bar').css('width', pct + '%').text(pct + '%');
                    $('#mail-count').html('<strong>' + sent + '</strong> sent' +
                        (skipped ? ' · <strong>' + skipped + '</strong> skipped' : '') +
                        ' · ' + done + ' of ' + total + ' processed');
                }

                function finish() {
                    var detail = reasons.slice(0, 5).map(function (r) {
                        return '<div class="text-left" style="font-size:12px">' + $('<div>').text(r).html() + '</div>';
                    }).join('');

                    Swal.fire({
                        icon: stopped ? 'error' : (sent ? 'success' : 'warning'),
                        title: stopped ? 'Stopped' : (sent ? 'Sent' : 'Nothing sent'),
                        html: '<p class="mb-2" style="font-size:13px">' +
                              (stopped ? $('<div>').text(stopped).html() + '<br>' : '') +
                              '<strong>' + sent + '</strong> sent' +
                              (skipped ? ', <strong>' + skipped + '</strong> skipped' : '') + ' of ' + total + '.</p>' +
                              (detail ? '<p class="mb-1" style="font-size:12px"><strong>Not sent:</strong></p>' + detail : '')
                    }).then(function () { window.location.reload(); });
                }

                function next() {
                    if (done >= total) { return finish(); }

                    var batch = ids.slice(done, done + BATCH);

                    $.post('{{ route('payslips.generated.email') }}', { ids: batch, _token: csrf })
                        .done(function (response) {
                            sent += response.created || 0;
                            skipped += response.skipped || 0;
                            reasons = reasons.concat(response.reasons || []);
                            done += batch.length;
                            paint();
                            next();
                        })
                        .fail(function (xhr) {
                            stopped = (xhr.responseJSON && xhr.responseJSON.message) || 'The server did not answer.';
                            finish();
                        });
                }
            }

            $(document).on('click', '.revoke-btn', function () {
                var button = $(this);

                Swal.fire({
                    title: 'Revoke this payslip?',
                    html: '<p class="mb-2" style="font-size:13px">' +
                          $('<div>').text(button.data('name')).html() + ' &middot; ' +
                          $('<div>').text(button.data('period')).html() +
                          '</p><p class="mb-0" style="font-size:12px;color:#888">The payslip and this reason stay on ' +
                          'record. The month is released so a corrected payslip can be generated.</p>',
                    input: 'textarea',
                    inputLabel: 'Reason for revoking',
                    inputPlaceholder: 'Why is this payslip being withdrawn?',
                    inputAttributes: { 'aria-label': 'Reason for revoking' },
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'Revoke',
                    confirmButtonColor: '#d33',
                    cancelButtonText: '@lang('app.cancel')',
                    inputValidator: function (value) {
                        if (!value || value.trim().length < 5) {
                            return 'Please give a reason of at least five characters.';
                        }
                    }
                }).then(function (result) {
                    if (!result.isConfirmed) { return; }

                    act('{{ url('account/payslips/generated') }}/' + button.data('id') + '/revoke',
                        { reason: result.value, _token: csrf }, 'Payslip revoked');
                });
            });

            $(document).on('click', '.delete-btn', function () {
                var button = $(this);

                Swal.fire({
                    title: 'Delete this payslip?',
                    html: '<p class="mb-2" style="font-size:13px">' +
                          $('<div>').text(button.data('name')).html() + ' &middot; ' +
                          $('<div>').text(button.data('period')).html() +
                          '</p><p class="mb-0" style="font-size:12px;color:#888">It disappears from these lists and ' +
                          'the month is released. Prefer <strong>Revoke</strong> if the payslip was issued and you ' +
                          'need a record of why it was withdrawn.</p>',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'Delete',
                    confirmButtonColor: '#d33',
                    cancelButtonText: '@lang('app.cancel')'
                }).then(function (result) {
                    if (!result.isConfirmed) { return; }

                    act('{{ url('account/payslips/generated') }}/' + button.data('id') + '/delete',
                        { _token: csrf }, 'Payslip deleted');
                });
            });
        });
    </script>
@endpush
