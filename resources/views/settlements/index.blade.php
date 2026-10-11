@extends('layouts.app')

@push('styles')
    <style>
        .settle-table th, .settle-table td { white-space: nowrap; }
        .draft-line td { padding: 3px 8px; }
        .draft-line .total td { font-weight: 600; border-top: 1px solid #e2e8f0; font-size: 15px; }
        .draft-line .less td { color: #d0342c; }
        .why { color: #8a94a6; font-size: 11px; }
    </style>
@endpush

@section('content')
    @php
        $money = fn ($v) => '₹' . number_format((float) $v, 2);
        $short = function ($v) {
            $v = (float) $v;

            return $v >= 100000 ? '₹' . number_format($v / 100000, 2) . 'L' : '₹' . number_format($v);
        };
        $badge = ['draft' => 'light border', 'agreed' => 'warning', 'paid' => 'success', 'cancelled' => 'danger'];
    @endphp

    <div class="content-wrapper arw-dash">

        <div class="row mb-3">
            @foreach ([
                ['Drafts', $totals['draft'], 'text-dark-grey', 'still being worked out'],
                ['Agreed', $totals['agreed'], 'text-yellow', 'figures fixed, awaiting payment'],
                ['Paid', $totals['paid'], 'text-success', 'settled and closed'],
                ['Still owed', $short($totals['owed']), 'text-primary', 'drafted and agreed together'],
            ] as [$label, $value, $colour, $sub])
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

        {{-- Start one --}}
        <div class="card bg-white border-0 b-shadow-4 mb-3">
            <div class="card-header bg-white border-bottom p-3">
                <h6 class="mb-0 f-14">Work out a settlement</h6>
                <p class="mb-0 f-12 text-lightest">
                    Salary to the last day, leave not taken and gratuity where the service qualifies — less
                    notice not served, advances still owed and the statutory deductions. Nothing is saved until
                    you keep it.
                </p>
            </div>
            <div class="card-body p-3">
                <div class="row align-items-end">
                    <div class="col-md-4 mb-2">
                        <label class="f-12 text-dark-grey">@lang('app.employee')</label>
                        <select id="s-employee" class="form-control f-14" data-live-search="true">
                            <option value="">Choose…</option>
                            @foreach ($employees as $employee)
                                <option value="{{ $employee->id }}">
                                    {{ $employee->name }}{{ $employee->employee_card_id ? ' · ' . $employee->employee_card_id : '' }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3 mb-2">
                        <label class="f-12 text-dark-grey">Last working day</label>
                        <input type="date" id="s-last-day" class="form-control f-14" value="{{ now()->toDateString() }}">
                    </div>
                    <div class="col-md-2 mb-2">
                        <button type="button" class="btn btn-primary f-14 w-100" id="s-work-out">Work it out</button>
                    </div>
                </div>

                @if ($awaiting !== [])
                    <p class="f-12 text-lightest mb-1 mt-2">Resigned and not yet settled:</p>
                    @foreach ($awaiting as $person)
                        <a href="#" class="badge badge-light border mr-1 mb-1 f-12 pick-leaver"
                           data-user="{{ $person->user_id }}"
                           data-day="{{ $person->last_working_date ? \Carbon\Carbon::parse($person->last_working_date)->toDateString() : now()->toDateString() }}">
                            {{ $person->name }}
                            @if ($person->last_working_date)
                                · {{ \Carbon\Carbon::parse($person->last_working_date)->format('d M Y') }}
                            @endif
                        </a>
                    @endforeach
                @endif

                {{-- What it comes to --}}
                <div id="s-result" class="d-none mt-3">
                    <hr>
                    <div class="row">
                        <div class="col-md-7">
                            <table class="table table-sm f-13 draft-line" id="s-workings"></table>
                        </div>
                        <div class="col-md-5">
                            <p class="f-12 text-dark-grey mb-1">Adjust before keeping it</p>
                            <div class="row">
                                <div class="col-6 mb-2">
                                    <label class="f-11 text-lightest">Leave days to pay</label>
                                    <input type="number" step="0.5" min="0" class="form-control form-control-sm f-13 adjust" id="s-leave">
                                </div>
                                <div class="col-6 mb-2">
                                    <label class="f-11 text-lightest">Notice days short</label>
                                    <input type="number" step="1" min="0" class="form-control form-control-sm f-13 adjust" id="s-notice">
                                </div>
                                <div class="col-6 mb-2">
                                    <label class="f-11 text-lightest">Bonus / anything agreed</label>
                                    <input type="number" step="0.01" min="0" class="form-control form-control-sm f-13 adjust" id="s-bonus">
                                </div>
                                <div class="col-6 mb-2">
                                    <label class="f-11 text-lightest">Anything else to recover</label>
                                    <input type="number" step="0.01" min="0" class="form-control form-control-sm f-13 adjust" id="s-recover">
                                </div>
                                <div class="col-12 mb-2">
                                    <label class="f-11 text-lightest">Note on the statement</label>
                                    <input type="text" class="form-control form-control-sm f-13" id="s-note" maxlength="500">
                                </div>
                                <div class="col-12">
                                    <button type="button" class="btn btn-sm btn-primary" id="s-keep">
                                        <i class="fa fa-save mr-1"></i> Keep as a draft
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Everything on record --}}
        <div class="card bg-white border-0 b-shadow-4">
            <div class="card-header bg-white border-bottom p-3">
                <h6 class="mb-0 f-14">Settlements</h6>
                <p class="mb-0 f-12 text-lightest">
                    A draft is recomputed whenever it is opened. Agreeing one fixes the figures for good.
                </p>
            </div>
            <div class="table-responsive">
                <table class="table table-hover mb-0 f-13 settle-table">
                    <thead>
                        <tr>
                            <th>@lang('app.employee')</th>
                            <th>Last day</th>
                            <th class="text-right">Owed</th>
                            <th class="text-right">Recovered</th>
                            <th class="text-right">Net</th>
                            <th>Status</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($settlements as $row)
                            <tr class="{{ $row->status === 'cancelled' ? 'text-lightest' : '' }}">
                                <td>
                                    {{ $row->name }}
                                    <span class="f-11 text-lightest d-block">{{ $row->employee_card_id ?: 'no code' }}</span>
                                </td>
                                <td>{{ \Carbon\Carbon::parse($row->last_working_day)->format('d M Y') }}</td>
                                <td class="text-right">{{ number_format($row->total_payable, 2) }}</td>
                                <td class="text-right text-red">{{ number_format($row->total_deductions, 2) }}</td>
                                <td class="text-right font-weight-bold {{ $row->net_payable < 0 ? 'text-red' : '' }}">
                                    {{ number_format($row->net_payable, 2) }}
                                </td>
                                <td>
                                    <span class="badge badge-{{ $badge[$row->status] ?? 'light' }}">{{ ucfirst($row->status) }}</span>
                                    @if ($row->payment_reference)
                                        <span class="f-11 text-lightest d-block">{{ $row->payment_reference }}</span>
                                    @endif
                                </td>
                                <td class="text-right">
                                    <a href="{{ route('settlements.statement', ['id' => $row->id]) }}" class="f-12 mr-2">Statement</a>
                                    @if ($row->status === 'draft')
                                        <a href="#" class="f-12 mr-2 agree-settlement" data-id="{{ $row->id }}">Agree</a>
                                    @elseif ($row->status === 'agreed')
                                        <a href="#" class="f-12 mr-2 pay-settlement" data-id="{{ $row->id }}">Mark paid</a>
                                    @endif
                                    @if (in_array($row->status, ['draft', 'agreed']))
                                        <a href="#" class="f-12 text-red cancel-settlement" data-id="{{ $row->id }}">Withdraw</a>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center text-lightest p-5 f-13">
                                    Nobody has been settled yet.
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
            var csrf = '{{ csrf_token() }}';

            $('#s-employee').selectpicker({ liveSearch: true, container: 'body', width: '100%' });

            function money(v) {
                return (Math.round(Math.abs(v) * 100) / 100).toLocaleString('en-IN',
                    { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            }

            function workOut(keepAdjustments) {
                var userId = $('#s-employee').val();

                if (!userId) {
                    Swal.fire({ icon: 'info', title: 'Choose who is leaving' });

                    return;
                }

                var params = { user_id: userId, last_working_day: $('#s-last-day').val() };

                if (keepAdjustments) {
                    if ($('#s-leave').val() !== '') { params.leave_days = $('#s-leave').val(); }
                    if ($('#s-notice').val() !== '') { params.notice_shortfall_days = $('#s-notice').val(); }
                    if ($('#s-bonus').val() !== '') { params.bonus_and_other = $('#s-bonus').val(); }
                    if ($('#s-recover').val() !== '') { params.other_recovery = $('#s-recover').val(); }
                }

                $.get('{{ route('settlements.draft') }}', params)
                    .done(function (data) {
                        if (!data.ok) {
                            Swal.fire({ icon: 'error', title: data.message });
                            $('#s-result').addClass('d-none');

                            return;
                        }

                        $('#s-result').removeClass('d-none');
                        $('#s-workings').html((data.workings || []).map(function (line) {
                            var isTotal = line[3] === 'total';
                            var amount = line[1];

                            return '<tr class="' + (isTotal ? 'total' : '') + ' ' + (amount < 0 ? 'less' : '') + '">' +
                                '<td>' + $('<div>').text(line[0]).html() +
                                (line[2] ? '<div class="why">' + $('<div>').text(line[2]).html() + '</div>' : '') +
                                '</td><td class="text-right">' + (amount < 0 ? '− ' : '') + money(amount) + '</td></tr>';
                        }).join(''));

                        if (!keepAdjustments) {
                            $('#s-leave').val(data.leave_days || 0);
                            $('#s-notice').val(data.notice_shortfall_days || 0);
                            $('#s-bonus').val('');
                            $('#s-recover').val('');
                        }
                    })
                    .fail(function (xhr) {
                        var body = xhr.responseJSON || {};
                        var first = body.errors ? body.errors[Object.keys(body.errors)[0]][0] : null;
                        Swal.fire({ icon: 'error', title: first || 'That could not be worked out' });
                    });
            }

            $('#s-work-out').on('click', function () { workOut(false); });
            $(document).on('change', '.adjust', function () { workOut(true); });

            $(document).on('click', '.pick-leaver', function (event) {
                event.preventDefault();
                $('#s-employee').selectpicker('val', String($(this).data('user')));
                $('#s-last-day').val($(this).data('day'));
                workOut(false);
            });

            $('#s-keep').on('click', function () {
                $.post('{{ route('settlements.store') }}', {
                    user_id: $('#s-employee').val(),
                    last_working_day: $('#s-last-day').val(),
                    leave_days: $('#s-leave').val(),
                    notice_shortfall_days: $('#s-notice').val(),
                    bonus_and_other: $('#s-bonus').val(),
                    other_recovery: $('#s-recover').val(),
                    note: $('#s-note').val(),
                    _token: csrf
                }).done(function (response) {
                    Swal.fire({ icon: response.status === 'success' ? 'success' : 'error', title: response.message })
                        .then(function () { window.location.reload(); });
                }).fail(function (xhr) {
                    var body = xhr.responseJSON || {};
                    Swal.fire({ icon: 'error', title: body.message || 'Could not keep it' });
                });
            });

            function act(url, data, question, confirmText) {
                Swal.fire({
                    title: question,
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonText: confirmText,
                    cancelButtonText: '@lang('app.cancel')',
                    input: data.needsInput ? 'text' : undefined,
                    inputPlaceholder: data.placeholder || ''
                }).then(function (result) {
                    if (!result.isConfirmed) { return; }

                    if (data.needsInput) {
                        if (!result.value) { return; }

                        data.params[data.inputName] = result.value;
                    }

                    $.post(url, $.extend({ _token: csrf }, data.params))
                        .done(function (response) {
                            Swal.fire({ icon: response.status === 'success' ? 'success' : 'error', title: response.message })
                                .then(function () { window.location.reload(); });
                        })
                        .fail(function () { Swal.fire({ icon: 'error', title: 'That did not work' }); });
                });
            }

            $(document).on('click', '.agree-settlement', function (event) {
                event.preventDefault();
                act('{{ route('settlements.agree') }}', { params: { id: $(this).data('id') } },
                    'Agree this settlement?', 'Agree');
            });

            $(document).on('click', '.pay-settlement', function (event) {
                event.preventDefault();
                act('{{ route('settlements.paid') }}',
                    { params: { id: $(this).data('id') }, needsInput: true,
                      inputName: 'payment_reference', placeholder: 'Payment reference (optional)' },
                    'Mark as paid?', 'Mark paid');
            });

            $(document).on('click', '.cancel-settlement', function (event) {
                event.preventDefault();
                act('{{ route('settlements.cancel') }}',
                    { params: { id: $(this).data('id') }, needsInput: true,
                      inputName: 'reason', placeholder: 'Why is it being withdrawn?' },
                    'Withdraw this settlement?', 'Withdraw');
            });
        });
    </script>
@endpush
