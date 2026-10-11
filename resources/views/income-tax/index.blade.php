@extends('layouts.app')

@push('styles')
    <style>
        .tax-table th, .tax-table td { white-space: nowrap; }
        .tax-table thead th { position: sticky; top: 0; background: #f8f9fa; z-index: 2; }
        .relief-line { color: #1d9e6f; }
        .slab-row input { width: 100%; }
        .workings td { padding: 3px 8px; }
        .workings .total td { font-weight: 600; border-top: 1px solid #e2e8f0; }
    </style>
@endpush

@section('content')
    @php
        $money = fn ($v) => '₹' . number_format((float) $v, 2);
        $short = function ($v) {
            $v = (float) $v;

            return $v >= 100000 ? '₹' . number_format($v / 100000, 2) . 'L' : '₹' . number_format($v);
        };
    @endphp

    <div class="content-wrapper arw-dash">

        @if ($needsReview)
            <div class="alert alert-warning f-13">
                <strong>The tax rates have not been confirmed yet.</strong>
                The slabs, standard deduction and rebate for {{ $year }} were seeded as a starting point so the
                calculation could work at all. Check them against the current Finance Act on the
                <a href="{{ route('income-tax.index', ['tab' => 'rates', 'financial_year' => $year]) }}">Rates</a>
                tab — saving them there marks them confirmed. Until then, treat every figure on this screen,
                and any Form 16, as a draft.
            </div>
        @endif

        <div class="card bg-white border-0 b-shadow-4 mb-3">
            <div class="card-body p-3">
                <form method="GET" action="{{ route('income-tax.index') }}" class="row align-items-end">
                    <input type="hidden" name="tab" value="{{ $tab }}">
                    <div class="col-md-3 mb-2">
                        <label class="f-12 text-dark-grey">Financial year</label>
                        <select name="financial_year" class="form-control f-14" onchange="this.form.submit()">
                            @foreach ($years as $option)
                                <option value="{{ $option }}" @selected($option === $year)>{{ $option }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-9 mb-2">
                        <p class="f-12 text-lightest mb-0">
                            Tax is worked out by projecting the whole year, taking off what the chosen regime
                            allows, applying the slabs, then spreading what is left over the months remaining.
                            A rise or a late declaration therefore corrects itself over the rest of the year
                            rather than landing in one month.
                        </p>
                    </div>
                </form>
            </div>
        </div>

        <div class="row mb-3">
            @foreach ([
                ['Tax for the year', $short($totals['annual_tax']), 'text-dark-grey', $totals['employees'] . ' employee(s) with a salary'],
                ['Already deducted', $short($totals['deducted']), 'text-dark-grey', 'on payslips issued so far'],
                ['Still to deduct', $short($totals['outstanding']), 'text-yellow', 'over the months remaining'],
                ['Per month from now', $short($totals['monthly']), 'text-primary', $totals['declared'] . ' declaration(s) on record'],
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

        <ul class="nav nav-tabs border-0 mb-0" role="tablist">
            @foreach (['employees' => 'Employees', 'rates' => 'Rates for ' . $year] as $key => $label)
                <li class="nav-item">
                    <a class="nav-link f-14 {{ $tab === $key ? 'active' : '' }}" data-toggle="tab" href="#tab-{{ $key }}">{{ $label }}</a>
                </li>
            @endforeach
        </ul>

        <div class="tab-content">
            {{-- ------------------------------------------------- employees --}}
            <div class="tab-pane fade {{ $tab === 'employees' ? 'show active' : '' }}" id="tab-employees">
                <div class="card bg-white border-0 b-shadow-4">
                    <div class="card-header bg-white border-bottom d-flex align-items-center flex-wrap p-3">
                        <div class="mr-auto">
                            <h6 class="mb-0 f-14">What each employee owes</h6>
                            <p class="mb-0 f-12 text-lightest">
                                Click a row to see every step of the calculation, or to record what they declared.
                            </p>
                        </div>
                        <button type="button" class="btn btn-sm btn-primary" id="apply-all">
                            <i class="fa fa-check mr-1"></i> Use these figures on payroll
                        </button>
                    </div>

                    <div class="table-responsive" style="max-height: 62vh">
                        <table class="table table-hover mb-0 f-13 tax-table">
                            <thead>
                                <tr>
                                    <th>@lang('app.employee')</th>
                                    <th>Regime</th>
                                    <th class="text-right">Gross for the year</th>
                                    <th class="text-right">Taxable</th>
                                    <th class="text-right">Tax for the year</th>
                                    <th class="text-right">Deducted</th>
                                    <th class="text-right">Per month</th>
                                    <th class="text-right">Payroll uses</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($rows as $row)
                                    <tr>
                                        <td>
                                            {{ $row['name'] }}
                                            <span class="f-11 text-lightest d-block">
                                                {{ $row['code'] ?: 'no code' }}
                                                @if (!$row['pan']) · <span class="text-red">no PAN</span> @endif
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge badge-light border">{{ ucfirst($row['regime']) }}</span>
                                            @if (!$row['has_declaration'])
                                                <span class="f-11 text-lightest d-block">nothing declared</span>
                                            @elseif (!$row['proofs_received'])
                                                <span class="f-11 text-yellow d-block">proofs awaited</span>
                                            @endif
                                        </td>
                                        <td class="text-right">{{ number_format($row['projected_gross'], 0) }}</td>
                                        <td class="text-right">{{ number_format($row['taxable'], 0) }}</td>
                                        <td class="text-right font-weight-bold">{{ number_format($row['annual_tax'], 0) }}</td>
                                        <td class="text-right">{{ number_format($row['already_deducted'], 0) }}</td>
                                        <td class="text-right">{{ number_format($row['monthly_tds'], 0) }}</td>
                                        <td class="text-right {{ abs($row['monthly_tds'] - $row['current_monthly']) >= 0.5 ? 'text-red' : 'text-lightest' }}">
                                            {{ number_format($row['current_monthly'], 0) }}
                                        </td>
                                        <td class="text-right">
                                            <a href="#" class="f-12 mr-2 show-workings" data-user="{{ $row['user_id'] }}"
                                               data-name="{{ $row['name'] }}">Workings</a>
                                            <a href="{{ route('income-tax.form-16', ['user_id' => $row['user_id'], 'financial_year' => $year]) }}"
                                               class="f-12">Form 16</a>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="9" class="text-center text-lightest p-5 f-13">
                                            No employee has a salary on record yet, so there is nothing to tax.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            {{-- ----------------------------------------------------- rates --}}
            <div class="tab-pane fade {{ $tab === 'rates' ? 'show active' : '' }}" id="tab-rates">
                @foreach (['new', 'old'] as $regime)
                    @php $setting = $settings[$regime] ?? null; @endphp
                    @if ($setting)
                        <div class="card bg-white border-0 b-shadow-4 mb-3">
                            <div class="card-header bg-white border-bottom p-3">
                                <h6 class="mb-0 f-14">
                                    {{ ucfirst($regime) }} regime — {{ $year }}
                                    @if ($setting->needs_review)
                                        <span class="badge badge-warning ml-2">not confirmed</span>
                                    @else
                                        <span class="badge badge-success ml-2">confirmed</span>
                                    @endif
                                </h6>
                                <p class="mb-0 f-12 text-lightest">
                                    {{ $setting->allows_chapter_via ? 'Allows the usual deductions and the house rent exemption.' : 'Allows no deductions beyond the standard one.' }}
                                    Saving marks these as confirmed.
                                </p>
                            </div>
                            <div class="card-body p-3">
                                <form class="rates-form" data-regime="{{ $regime }}">
                                    <div class="row">
                                        <div class="col-md-3 mb-2">
                                            <label class="f-12 text-dark-grey">Standard deduction</label>
                                            <input type="number" step="1" min="0" class="form-control f-14"
                                                   name="standard_deduction" value="{{ (int) $setting->standard_deduction }}">
                                        </div>
                                        <div class="col-md-3 mb-2">
                                            <label class="f-12 text-dark-grey">No tax up to (87A)</label>
                                            <input type="number" step="1" min="0" class="form-control f-14"
                                                   name="rebate_upto" value="{{ (int) $setting->rebate_upto }}">
                                        </div>
                                        <div class="col-md-3 mb-2">
                                            <label class="f-12 text-dark-grey">Most that rebate removes</label>
                                            <input type="number" step="1" min="0" class="form-control f-14"
                                                   name="rebate_max" value="{{ (int) $setting->rebate_max }}">
                                        </div>
                                        <div class="col-md-3 mb-2">
                                            <label class="f-12 text-dark-grey">Cess %</label>
                                            <input type="number" step="0.01" min="0" class="form-control f-14"
                                                   name="cess_pct" value="{{ (float) $setting->cess_pct }}">
                                        </div>
                                    </div>

                                    <p class="f-12 text-dark-grey mb-1 mt-2">Slabs — leave the last "up to" blank for everything above it</p>
                                    <div class="slabs">
                                        @foreach ($slabs[$regime] ?? [] as $slab)
                                            <div class="row slab-row mb-1">
                                                <div class="col-md-4">
                                                    <input type="number" step="1" min="0" class="form-control form-control-sm f-13 slab-upto"
                                                           value="{{ $slab->upto !== null ? (int) $slab->upto : '' }}" placeholder="and above">
                                                </div>
                                                <div class="col-md-3">
                                                    <div class="input-group input-group-sm">
                                                        <input type="number" step="0.01" min="0" max="100"
                                                               class="form-control f-13 slab-rate" value="{{ (float) $slab->rate }}">
                                                        <div class="input-group-append"><span class="input-group-text f-12">%</span></div>
                                                    </div>
                                                </div>
                                                <div class="col-md-2">
                                                    <a href="#" class="drop-slab f-12 text-lightest">remove</a>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                    <a href="#" class="add-slab f-12">+ add a slab</a>

                                    <div class="mt-3">
                                        <button type="button" class="btn btn-sm btn-primary save-rates">
                                            Save and mark confirmed
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    @endif
                @endforeach
            </div>
        </div>
    </div>

    {{-- The calculation, step by step, and what they declared --}}
    <div class="modal fade" id="workings-modal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h6 class="modal-title f-15 mb-0" id="workings-title">—</h6>
                    <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                </div>
                <div class="modal-body">
                    <table class="table table-sm f-13 workings mb-3" id="workings-rows"></table>

                    <h6 class="f-14 mb-2">What they declared</h6>
                    <form id="declaration-form">
                        <input type="hidden" name="user_id" id="d-user">
                        <input type="hidden" name="financial_year" value="{{ $year }}">
                        <div class="row">
                            <div class="col-md-4 mb-2">
                                <label class="f-12 text-dark-grey">Regime</label>
                                <select name="regime" id="d-regime" class="form-control f-14">
                                    <option value="new">New — lower rates, no deductions</option>
                                    <option value="old">Old — higher rates, deductions allowed</option>
                                </select>
                            </div>
                            @foreach ([
                                ['section_80c', 'Section 80C', 'up to 1,50,000'],
                                ['section_80d', 'Section 80D (health)', 'up to 1,00,000'],
                                ['section_80ccd_1b', '80CCD(1B) pension', 'up to 50,000'],
                                ['home_loan_interest', 'Home loan interest', 'up to 2,00,000'],
                                ['other_deductions', 'Other deductions', ''],
                                ['rent_paid_annual', 'Rent paid in the year', 'for the HRA exemption'],
                                ['previous_employer_income', 'Pay from a previous employer', ''],
                                ['previous_employer_tax', 'Tax they deducted', ''],
                            ] as [$field, $label, $hint])
                                <div class="col-md-4 mb-2">
                                    <label class="f-12 text-dark-grey">{{ $label }}</label>
                                    <input type="number" step="0.01" min="0" class="form-control f-14"
                                           name="{{ $field }}" id="d-{{ $field }}">
                                    @if ($hint)<span class="f-11 text-lightest">{{ $hint }}</span>@endif
                                </div>
                            @endforeach
                            <div class="col-12">
                                <label class="f-12 text-lightest mr-3">
                                    <input type="checkbox" name="metro_city" id="d-metro" value="1"> Lives in a metro city
                                </label>
                                <label class="f-12 text-lightest">
                                    <input type="checkbox" name="proofs_received" id="d-proofs" value="1"> Proofs received
                                </label>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-sm btn-primary" id="save-declaration">Save declaration</button>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        $(function () {
            var csrf = '{{ csrf_token() }}';
            var year = '{{ $year }}';

            function money(v) {
                return (Math.round(v * 100) / 100).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            }

            $(document).on('click', '.show-workings', function (event) {
                event.preventDefault();
                var userId = $(this).data('user');

                $('#workings-title').text($(this).data('name'));
                $('#workings-rows').html('<tr><td>Working it out…</td></tr>');
                $('#d-user').val(userId);
                $('#workings-modal').modal('show');

                $.get('{{ route('income-tax.workings') }}', { user_id: userId, financial_year: year })
                    .done(function (data) {
                        if (!data.ok) {
                            $('#workings-rows').html('<tr><td>' + $('<div>').text(data.message || 'Nothing to show').html() + '</td></tr>');
                        } else {
                            $('#workings-rows').html((data.workings || []).map(function (line) {
                                var kind = line[2] || '';
                                var note = line[3] ? ' <span class="text-lightest f-11">(' + $('<div>').text(line[3]).html() + ')</span>' : '';

                                return '<tr class="' + (kind === 'total' ? 'total' : '') + '">' +
                                    '<td class="' + (kind === 'relief' ? 'relief-line' : '') + '">' +
                                    $('<div>').text(line[0]).html() + note + '</td>' +
                                    '<td class="text-right ' + (kind === 'relief' ? 'relief-line' : '') + '">' +
                                    (line[1] < 0 ? '−' : '') + money(Math.abs(line[1])) + '</td></tr>';
                            }).join(''));
                        }

                        var d = data.declaration || {};
                        $('#d-regime').val(d.regime || 'new');
                        ['section_80c', 'section_80d', 'section_80ccd_1b', 'home_loan_interest',
                         'other_deductions', 'rent_paid_annual', 'previous_employer_income',
                         'previous_employer_tax'].forEach(function (f) {
                            $('#d-' + f).val(parseFloat(d[f]) || '');
                        });
                        $('#d-metro').prop('checked', !!(+d.metro_city));
                        $('#d-proofs').prop('checked', !!(+d.proofs_received));
                    })
                    .fail(function () {
                        $('#workings-rows').html('<tr><td>That could not be worked out.</td></tr>');
                    });
            });

            $('#save-declaration').on('click', function () {
                $.post('{{ route('income-tax.declaration') }}',
                    $('#declaration-form').serialize() + '&_token=' + csrf)
                    .done(function (response) {
                        Swal.fire({ icon: response.status === 'success' ? 'success' : 'error', title: response.message })
                            .then(function () { window.location.reload(); });
                    })
                    .fail(function (xhr) {
                        var body = xhr.responseJSON || {};
                        var first = body.errors ? body.errors[Object.keys(body.errors)[0]][0] : null;
                        Swal.fire({ icon: 'error', title: first || 'Could not save it' });
                    });
            });

            $('#apply-all').on('click', function () {
                Swal.fire({
                    title: 'Use the computed figures?',
                    text: 'Each employee’s monthly tax is set to what this screen worked out. Payslips already issued do not change.',
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonText: 'Use them',
                    cancelButtonText: '@lang('app.cancel')'
                }).then(function (result) {
                    if (!result.isConfirmed) { return; }

                    $.post('{{ route('income-tax.apply') }}', { financial_year: year, _token: csrf })
                        .done(function (response) {
                            Swal.fire({ icon: 'success', title: response.message })
                                .then(function () { window.location.reload(); });
                        })
                        .fail(function () { Swal.fire({ icon: 'error', title: 'That did not work' }); });
                });
            });

            // ---- the rates tab ---------------------------------------------
            $(document).on('click', '.add-slab', function (event) {
                event.preventDefault();
                $(this).prev('.slabs').append(
                    '<div class="row slab-row mb-1">' +
                    '<div class="col-md-4"><input type="number" step="1" min="0" class="form-control form-control-sm f-13 slab-upto" placeholder="and above"></div>' +
                    '<div class="col-md-3"><div class="input-group input-group-sm">' +
                    '<input type="number" step="0.01" min="0" max="100" class="form-control f-13 slab-rate" value="0">' +
                    '<div class="input-group-append"><span class="input-group-text f-12">%</span></div></div></div>' +
                    '<div class="col-md-2"><a href="#" class="drop-slab f-12 text-lightest">remove</a></div></div>');
            });

            $(document).on('click', '.drop-slab', function (event) {
                event.preventDefault();
                $(this).closest('.slab-row').remove();
            });

            $(document).on('click', '.save-rates', function () {
                var form = $(this).closest('.rates-form');
                var slabs = form.find('.slab-row').map(function () {
                    return {
                        upto: $(this).find('.slab-upto').val() || null,
                        rate: $(this).find('.slab-rate').val() || 0
                    };
                }).get();

                $.post('{{ route('income-tax.rates') }}', {
                    financial_year: year,
                    regime: form.data('regime'),
                    standard_deduction: form.find('[name=standard_deduction]').val(),
                    rebate_upto: form.find('[name=rebate_upto]').val(),
                    rebate_max: form.find('[name=rebate_max]').val(),
                    cess_pct: form.find('[name=cess_pct]').val(),
                    slabs: slabs,
                    _token: csrf
                }).done(function (response) {
                    Swal.fire({ icon: 'success', title: response.message })
                        .then(function () { window.location.reload(); });
                }).fail(function (xhr) {
                    var body = xhr.responseJSON || {};
                    var first = body.errors ? body.errors[Object.keys(body.errors)[0]][0] : null;
                    Swal.fire({ icon: 'error', title: first || 'Could not save the rates' });
                });
            });
        });
    </script>
@endpush
