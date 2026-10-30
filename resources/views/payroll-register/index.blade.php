@extends('layouts.app')

@push('styles')
    <style>
        .register-table th, .register-table td { white-space: nowrap; }
        .register-table thead th { position: sticky; top: 0; background: #f8f9fa; z-index: 2; }
        .register-table tfoot td { font-weight: 600; background: #f8f9fa; }
        .remit-card { border-left: 3px solid #1d9e6f; }
        .ss-bar { display: flex; flex-wrap: wrap; align-items: flex-end; gap: 12px 16px; }
        .ss-bar select { min-width: 160px; }
        .ss-drafts { padding-bottom: 8px; }
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

        {{-- Which month: one row, Excel on the right --}}
        <div class="card bg-white border-0 b-shadow-4 mb-3">
            <div class="card-body p-3">
                <form method="GET" action="{{ route('payroll-register.index') }}" class="ss-bar">
                    <div>
                        <label class="f-12 text-dark-grey mb-1">@lang('app.month')</label>
                        <select name="month" class="form-control f-14">
                            @foreach (range(1, 12) as $m)
                                <option value="{{ $m }}" @selected($m === $month)>{{ \Carbon\Carbon::create(null, $m, 1)->format('F') }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="f-12 text-dark-grey mb-1">@lang('app.year')</label>
                        <select name="year" class="form-control f-14">
                            @foreach (range(now()->year - 3, now()->year) as $y)
                                <option value="{{ $y }}" @selected($y === $year)>{{ $y }}</option>
                            @endforeach
                        </select>
                    </div>
                    {{-- A draft has had nothing paid against it, so it is left out unless asked for. --}}
                    <div class="custom-control custom-switch ss-drafts">
                        <input type="checkbox" class="custom-control-input" id="include_unapproved" name="include_unapproved" value="1"
                               @checked($includeUnapproved) onchange="this.form.submit()">
                        <label class="custom-control-label f-13" for="include_unapproved">Include drafts</label>
                    </div>
                    <button type="submit" class="btn btn-primary f-14 px-4">@lang('app.apply')</button>
                    <a href="{{ route('payroll-register.export', request()->query()) }}" class="btn btn-outline-secondary f-14 ml-md-auto"
                       title="The salary statement, PF, ESI and the summary, one sheet each">
                        <i class="fa fa-file-excel mr-1"></i> Export to Excel
                    </a>
                </form>
            </div>
        </div>

        @if ((int) $totals['payslips'] === 0)
            {{-- Nothing issued for the month: say so once, instead of a page of zeros. --}}
            <div class="card bg-white border-0 b-shadow-4 mb-3">
                <div class="card-body text-center py-5">
                    <i class="fa fa-file-invoice f-21 text-lightest"></i>
                    <h6 class="f-15 mt-3 mb-1">No payslips issued for {{ $monthName }}</h6>
                    <p class="f-13 text-dark-grey mb-3">
                        {{ $cycle->start->format('d M Y') }} to {{ $cycle->end->format('d M Y') }}.
                        @unless ($includeUnapproved) Drafts are not counted here until they are approved. @endunless
                    </p>
                    <a href="{{ route('payroll-run.index', ['month' => $month, 'year' => $year]) }}" class="btn btn-primary btn-sm mr-2">Run Payroll</a>
                    <a href="{{ route('payslips.generated') }}" class="btn btn-outline-secondary btn-sm">Issued Payslips</a>
                </div>
            </div>
        @else
        {{-- What has to be remitted --}}
        <div class="row mb-3">
            @php
                $tiles = [
                    ['Paid to employees', $short($totals['paid']), 'text-primary',
                        $totals['payslips'] . ' payslip(s) · ' . $totals['paid_count'] . ' marked paid'],
                    ['Provident fund', $short($totals['pf_payable']), 'text-dark-grey',
                        'employee ' . $short($totals['pf_employee']) . ' + employer ' . $short($totals['pf_employer'])],
                    ['Employee insurance', $short($totals['esi_payable']), 'text-dark-grey',
                        'employee ' . $short($totals['esi_employee']) . ' + employer ' . $short($totals['esi_employer'])],
                    ['Cost to company', $short($totals['cost']), 'text-yellow',
                        'pay plus the employer\'s own contributions'],
                ];
            @endphp
            @foreach ($tiles as [$label, $value, $colour, $sub])
                <div class="col-md-3 col-6 mb-2">
                    <div class="card bg-white border-0 b-shadow-4 h-100 remit-card">
                        <div class="card-body p-3">
                            <p class="mb-1 f-12 text-lightest text-uppercase">{{ $label }}</p>
                            <h4 class="mb-0 f-21 {{ $colour }}">{{ $value }}</h4>
                            <p class="mb-0 f-11 text-lightest">{{ $sub }}</p>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="row mb-3">
            {{-- The figures somebody has to file --}}
            <div class="col-md-5 mb-2">
                <div class="card bg-white border-0 b-shadow-4 h-100">
                    <div class="card-header bg-white border-bottom p-3">
                        <h6 class="mb-0 f-14">To pay over for {{ $monthName }}</h6>
                        <p class="mb-0 f-12 text-lightest">PF, ESI, professional tax and income tax to deposit, from the payslips as issued.</p>
                    </div>
                    <table class="table table-sm mb-0 f-13">
                        <tbody>
                            @foreach ([
                                ['Provident fund — both shares', $totals['pf_payable']],
                                ['Employee insurance — both shares', $totals['esi_payable']],
                                ['Professional tax', $totals['pt']],
                                ['Income tax withheld', $totals['tds']],
                            ] as [$label, $value])
                                <tr>
                                    <td>{{ $label }}</td>
                                    <td class="text-right font-weight-bold">{{ $money($value) }}</td>
                                </tr>
                            @endforeach
                            <tr>
                                <td class="text-lightest f-12">Extra paid / deducted this month</td>
                                <td class="text-right f-12 text-lightest">
                                    +{{ number_format($totals['extra_earnings'], 2) }}
                                    / −{{ number_format($totals['extra_deductions'], 2) }}
                                </td>
                            </tr>
                            <tr>
                                <td class="text-lightest f-12">Leave encashed</td>
                                <td class="text-right f-12 text-lightest">{{ number_format($totals['encashment'], 2) }}</td>
                            </tr>
                            <tr>
                                <td class="text-lightest f-12">Unpaid days deducted</td>
                                <td class="text-right f-12 text-lightest">{{ $num($totals['lop_days']) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- Where the cost sat --}}
            <div class="col-md-7 mb-2">
                <div class="card bg-white border-0 b-shadow-4 h-100">
                    <div class="card-header bg-white border-bottom p-3">
                        <h6 class="mb-0 f-14">By division</h6>
                        <p class="mb-0 f-12 text-lightest">Largest cost first.</p>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-sm mb-0 f-13">
                            <thead>
                                <tr>
                                    <th>Division</th>
                                    <th class="text-center">People</th>
                                    <th class="text-right">Gross</th>
                                    <th class="text-right">Paid</th>
                                    <th class="text-right">Cost to company</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($departments as $group)
                                    <tr>
                                        <td>{{ $group['department'] }}</td>
                                        <td class="text-center">{{ $group['people'] }}</td>
                                        <td class="text-right">{{ number_format($group['gross'], 2) }}</td>
                                        <td class="text-right">{{ number_format($group['paid'], 2) }}</td>
                                        <td class="text-right font-weight-bold">{{ number_format($group['cost'], 2) }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="text-center text-lightest p-4 f-13">Nothing issued yet.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        {{-- The register itself --}}
        <div class="card bg-white border-0 b-shadow-4 mb-3">
            <div class="card-header bg-white border-bottom p-3">
                <h6 class="mb-0 f-14">{{ $monthName }} salary statement</h6>
                <p class="mb-0 f-12 text-lightest">
                    {{ $cycle->start->format('d M Y') }} to {{ $cycle->end->format('d M Y') }}
                    &middot; one line per payslip issued. A figure that looks wrong means the payslip is wrong:
                    revoke and reissue it, and this follows.
                </p>
            </div>

            <div class="table-responsive" style="max-height: 70vh">
                <table class="table table-hover mb-0 f-13 register-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>@lang('app.employee')</th>
                            <th>Payslip no.</th>
                            <th class="text-right">Gross</th>
                            <th class="text-center">Days</th>
                            <th class="text-right">PF</th>
                            <th class="text-right">ESI</th>
                            <th class="text-right">PT</th>
                            <th class="text-right">TDS</th>
                            <th class="text-right">Extras</th>
                            <th class="text-right">Net paid</th>
                            <th class="text-right">Cost</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($rows as $row)
                            <tr>
                                <td>{{ $row['serial'] }}</td>
                                <td>
                                    {{ $row['emp_name'] }}
                                    <span class="f-11 text-lightest d-block">
                                        {{ $row['emp_id'] }}{{ $row['department'] ? ' · ' . $row['department'] : '' }}
                                    </span>
                                </td>
                                <td class="f-12">{{ $row['payslip_number'] }}</td>
                                <td class="text-right">{{ number_format($row['gross'], 2) }}</td>
                                <td class="text-center">
                                    {{ $num($row['days_present']) }}<span class="text-lightest">/{{ $row['working_days'] }}</span>
                                </td>
                                <td class="text-right">{{ number_format($row['pf'], 2) }}</td>
                                <td class="text-right">{{ number_format($row['esi'], 2) }}</td>
                                <td class="text-right">{{ number_format($row['pt'], 2) }}</td>
                                <td class="text-right">{{ $row['tds'] > 0 ? number_format($row['tds'], 2) : '—' }}</td>
                                <td class="text-right">
                                    @if ($row['extra_earnings'] > 0 || $row['extra_deductions'] > 0)
                                        <span title="{{ collect($row['extras'])->map(fn ($e) => $e->label . ' ' . number_format($e->amount, 2))->implode(', ') }}">
                                            @if ($row['extra_earnings'] > 0)
                                                <span class="text-success">+{{ number_format($row['extra_earnings'], 2) }}</span>
                                            @endif
                                            @if ($row['extra_deductions'] > 0)
                                                <span class="text-red">−{{ number_format($row['extra_deductions'], 2) }}</span>
                                            @endif
                                        </span>
                                    @else
                                        <span class="text-lightest">—</span>
                                    @endif
                                </td>
                                <td class="text-right font-weight-bold">{{ number_format($row['paid'], 2) }}</td>
                                <td class="text-right">{{ number_format($row['cost'], 2) }}</td>
                                <td>
                                    <span class="badge badge-{{ $row['approval_status'] === 'paid' ? 'success' : ($row['approval_status'] === 'draft' ? 'warning' : 'light border') }}"
                                          title="{{ $row['payment_reference'] }}">
                                        {{ ucfirst($row['approval_status']) }}
                                    </span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="13" class="text-center text-lightest p-5 f-13">
                                    No payslips issued for {{ $monthName }}.
                                    <a href="{{ route('payroll-run.index', ['month' => $month, 'year' => $year]) }}">Run payroll</a>
                                    for the month first.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                    @if ($rows !== [])
                        <tfoot>
                            <tr>
                                <td colspan="3">Total</td>
                                <td class="text-right">{{ number_format($totals['gross'], 2) }}</td>
                                <td></td>
                                <td class="text-right">{{ number_format($totals['pf_employee'], 2) }}</td>
                                <td class="text-right">{{ number_format($totals['esi_employee'], 2) }}</td>
                                <td class="text-right">{{ number_format($totals['pt'], 2) }}</td>
                                <td class="text-right">{{ number_format($totals['tds'], 2) }}</td>
                                <td class="text-right">
                                    +{{ number_format($totals['extra_earnings'], 2) }}
                                    / −{{ number_format($totals['extra_deductions'], 2) }}
                                </td>
                                <td class="text-right">{{ number_format($totals['paid'], 2) }}</td>
                                <td class="text-right">{{ number_format($totals['cost'], 2) }}</td>
                                <td></td>
                            </tr>
                        </tfoot>
                    @endif
                </table>
            </div>
        </div>
        @endif
    </div>
@endsection
