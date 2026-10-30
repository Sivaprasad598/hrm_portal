@extends('layouts.app')

@push('styles')
    <style>
        /* The pay table is wide and long: the headings stay at the top, S.No and the
           name stay at the left, the totals at the bottom, while the figures scroll.
           (The theme clips tables - overflow: hidden - which would stop all of that.) */
        .payroll-table { overflow: visible !important; border-collapse: separate; border-spacing: 0; }
        .payroll-table th, .payroll-table td { white-space: nowrap; }
        .payroll-table thead th { position: sticky; top: 0; z-index: 3; background: #f8f9fa !important; box-shadow: inset 0 -1px 0 #dee2e6; }
        .payroll-table .col-sno { position: sticky; left: 0; z-index: 2; width: 56px; min-width: 56px; max-width: 56px; background: #fff; }
        .payroll-table .col-name { position: sticky; left: 56px; z-index: 2; background: #fff; box-shadow: inset -1px 0 0 #dee2e6; }
        .payroll-table thead .col-sno, .payroll-table thead .col-name { z-index: 4; }
        .payroll-table tbody tr:hover > .col-sno, .payroll-table tbody tr:hover > .col-name { background: #f2f4f7; }
        .payroll-table tfoot td { position: sticky; bottom: 0; z-index: 3; font-weight: 600; background: #f8f9fa; box-shadow: inset 0 1px 0 #dee2e6; }
        .payroll-table tfoot .col-sno, .payroll-table tfoot .col-name { z-index: 4; background: #f8f9fa; }
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
                <strong>{{ $monthName }} is still running</strong> (until {{ $cycle->end->format('d M') }}).
                The figures below are an estimate from the days so far. Payslips can be issued from
                {{ $cycle->end->copy()->addDay()->format('d M') }}{{ $autoPayslips ? ', when drafts are also made automatically' : '' }} —
                a payslip issued now would leave the rest of the cycle unpaid once it is locked.
            </div>
        @elseif ($deviceBehind)
            <div class="alert alert-warning f-13">
                <strong>Device attendance only reaches {{ $deviceThrough ? $deviceThrough->format('d M') : 'no date' }}.</strong>
                Employees on the biometric device cannot be paid for {{ $monthName }} until it reaches
                {{ $cycle->end->format('d M') }}. <a href="{{ route('biometric.overview') }}">Pull now on Biometric → Overview</a>.
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
                    <i class="fa fa-book mr-1"></i> Salary Statement
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

                @php $otRules = \App\Services\Attendance\WorkRules::forCompany(company()->id); @endphp
                @if ($otRules && $otRules->s->ot_enabled && ($otRules->s->ot_mode ?? 'bank') === 'pay')
                    <button type="button" class="btn btn-sm btn-outline-primary mr-2" id="calculate-ot"
                            title="Put each employee's overtime for this period on the run as an editable line">
                        <i class="fa fa-clock mr-1"></i> Calculate overtime
                    </button>
                @endif

                <a class="btn btn-sm btn-outline-secondary mr-2"
                   href="{{ route('payroll-run.export', request()->query()) }}">
                    <i class="fa fa-file-excel mr-1"></i> Export to Excel
                </a>

                <button type="button" class="btn btn-sm btn-primary" id="generate-all"
                        @if ($isCurrentMonth) title="Available from {{ $cycle->end->copy()->addDay()->format('d M') }}" @endif
                        @disabled($isCurrentMonth || $totals['payable'] - $totals['generated'] < 1)>
                    <i class="fa fa-bolt mr-1"></i>
                    Generate {{ max(0, $totals['payable'] - $totals['generated']) }} payslip(s)
                </button>
            </div>

            <div class="table-responsive" style="max-height: 70vh">
                <table class="table table-hover mb-0 f-13 payroll-table" style="overflow: visible !important">
                    <thead>
                        <tr>
                            <th class="col-sno">S.No</th>
                            <th class="col-name">Employee Name</th>
                            <th class="text-right">Gross</th>
                            <th class="text-right">Basic</th>
                            <th class="text-right">HRA</th>
                            <th class="text-right">CA</th>
                            <th class="text-right">LTA</th>
                            <th class="text-right">CEA</th>
                            <th class="text-right">Special Allowance</th>
                            <th class="text-right">PF</th>
                            <th class="text-right">PF After LOP</th>
                            <th class="text-right">ESI After LOP</th>
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
                                <td class="col-sno">{{ $row['serial'] }}</td>
                                <td class="col-name">
                                    <span class="font-weight-bold">{{ $row['name'] }}</span>
                                    <br>
                                    <span class="f-11 text-lightest">
                                        {{ $row['card_id'] ?: 'no code' }}{{ $row['department'] ? ' · ' . $row['department'] : '' }}
                                    </span>
                                    {{-- Day by day, as this row counts it: worked, week offs, holidays, leave, LOP. --}}
                                    <br>
                                    <a href="{{ route('payroll.attendance', ['userId' => $row['user_id'], 'month' => $month, 'year' => $year]) }}"
                                       class="openRightModal f-11" title="Attendance for this salary period, day by day">
                                        <i class="fa fa-calendar-alt mr-1"></i>Attendance
                                    </a>
                                    @if (($row['basis'] ?? '') === 'exempt')
                                        <br><span class="badge badge-light f-10" title="Salary not based on attendance: full salary, leave still counts">Attendance-exempt</span>
                                    @elseif (($row['basis'] ?? '') === 'hours_off')
                                        <br><span class="badge badge-light f-10" title="The department does not consider hours: any punch is a full day">Hours not considered</span>
                                    @endif
                                    @if (($row['breakdown']['late_marks'] ?? 0) || ($row['breakdown']['late_half_days'] ?? 0))
                                        <br><span class="f-10 text-warning" title="Lates before the half-day mark (every Nth costs a deduction) / arrivals after the half-day mark (half day each)">
                                            Late {{ $row['breakdown']['late_marks'] }}{{ $row['breakdown']['late_deduction'] > 0 ? ' → −' . $num($row['breakdown']['late_deduction']) . ' day' : '' }}{{ $row['breakdown']['late_half_days'] ? ' · after half-day mark ' . $row['breakdown']['late_half_days'] : '' }}
                                        </span>
                                    @endif
                                    @if ($b = $row['ot_bank'] ?? null)
                                        {{-- OT bank: brought forward, earned this period, LOP covered / encashed, carried forward. --}}
                                        <br><span class="f-10 text-dark-grey" title="Brought forward + earned this period - used = carried forward">
                                            OT bank {{ $num($b['bf']) }}h{{ $b['earned'] > 0 ? ' +' . $num($b['earned']) . 'h' : '' }}{{ $b['comp_days'] > 0 ? ' · covers ' . $num($b['comp_days']) . ' LOP' : '' }}{{ $b['enc_hours'] > 0 ? ' · encash ' . $num($b['enc_hours']) . 'h' : '' }} → {{ $num($b['cf']) }}h
                                        </span>
                                        @unless ($row['status'] === 'generated' || $b['issued'])
                                            <a href="#" class="ot-bank f-10 ml-1" data-user="{{ $row['user_id'] }}"
                                               data-name="{{ $row['name'] }}" data-bank="{{ json_encode($b) }}">Manage</a>
                                        @endunless
                                    @elseif (($row['ot']['hours'] ?? 0) > 0)
                                        <br><span class="f-10 {{ $row['ot_on_run'] === null ? 'text-primary' : 'text-success' }}" title="{{ $row['ot']['note'] }}">
                                            OT {{ $num($row['ot']['hours']) }}h = {{ number_format($row['ot']['amount'], 2) }}{{ $row['ot_on_run'] === null ? ' (not on the run yet)' : '' }}
                                        </span>
                                    @endif
                                </td>
                                <td class="text-right">{{ number_format($row['gross'], 2) }}</td>
                                <td class="text-right">{{ number_format($row['basic'], 2) }}</td>
                                <td class="text-right">{{ number_format($row['hra'], 2) }}</td>
                                <td class="text-right">{{ number_format($row['ca'], 2) }}</td>
                                <td class="text-right">{{ number_format($row['lta'] ?? 0, 2) }}</td>
                                <td class="text-right">{{ number_format($row['cea'] ?? 0, 2) }}</td>
                                <td class="text-right">{{ number_format($row['other'], 2) }}</td>
                                <td class="text-right">{{ number_format($row['pf_full'], 2) }}</td>
                                <td class="text-right">{{ number_format($row['pf'], 2) }}</td>
                                <td class="text-right">{{ number_format($row['esi'], 2) }}</td>
                                <td class="text-right">{{ number_format($row['esi_full'], 2) }}</td>
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
                                {{-- While the period is running only the days so far are counted. --}}
                                <td class="text-center">
                                    {{ $row['working_days'] }}
                                    @if ($row['working_days'] < $cycle->days())
                                        <div class="f-10 text-lightest text-nowrap" title="The period has {{ $cycle->days() }} days; the rest are counted as they happen">of {{ $cycle->days() }} so far</div>
                                    @endif
                                </td>
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
                                        @if ($row['payslip_id'])
                                            <a href="{{ route('payslips.generated.pdf', $row['payslip_id']) }}" target="_blank" class="f-11 ml-1" title="Open the payslip"><i class="fa fa-file-pdf mr-1"></i>PDF</a>
                                        @endif
                                    @elseif ($row['status'] === 'blocked')
                                        <span class="badge badge-danger" title="{{ implode(', ', $row['blockers']) }}">
                                            {{ implode(', ', $row['blockers']) }}
                                        </span>
                                    @else
                                        <span class="badge badge-light border">To issue</span>
                                        <button type="button" class="btn btn-xs btn-outline-primary f-11 py-0 px-2 ml-1 generate-one"
                                                data-user="{{ $row['user_id'] }}" data-name="{{ $row['name'] }}"
                                                @if ($isCurrentMonth) disabled title="Available from {{ $cycle->end->copy()->addDay()->format('d M') }}" @else title="Issue this employee's payslip only" @endif>
                                            <i class="fa fa-bolt mr-1"></i>Generate
                                        </button>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="24" class="text-center text-lightest p-4">
                                    No employees match these filters.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                    @if ($rows !== [])
                        <tfoot>
                            <tr>
                                <td class="col-sno"></td>
                                <td class="col-name">Total — {{ $monthName }}</td>
                                <td class="text-right">{{ number_format($totals['gross'], 2) }}</td>
                                <td colspan="6"></td>
                                <td class="text-right">{{ number_format($totals['pf_full'], 2) }}</td>
                                <td class="text-right">{{ number_format($totals['pf'], 2) }}</td>
                                <td class="text-right">{{ number_format($totals['esi'], 2) }}</td>
                                <td class="text-right">{{ number_format($totals['esi_full'], 2) }}</td>
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


{{-- OT bank for one employee and this period --}}
<div class="modal fade" id="ot-bank-modal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title f-16">OT bank: <span id="ot-name"></span></h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
            </div>
            <div class="modal-body f-13">
                <table class="table table-sm mb-3">
                    <tr><td class="text-dark-grey">Brought forward</td><td class="text-right" id="ot-bf"></td></tr>
                    <tr><td class="text-dark-grey">Overtime this period</td><td class="text-right" id="ot-earned"></td></tr>
                    <tr class="font-weight-600"><td>Available</td><td class="text-right" id="ot-available"></td></tr>
                    <tr><td class="text-dark-grey">LOP days this period</td><td class="text-right" id="ot-lop"></td></tr>
                </table>

                <div class="form-group">
                    <label class="f-13 mb-1">Cover LOP days <span class="text-lightest">(up to <span id="ot-cover-max"></span>; a day = <span id="ot-dayh"></span>)</span></label>
                    <div class="d-flex align-items-center">
                        <input type="number" step="0.5" min="0" id="ot-cover" class="form-control form-control-sm mr-2" style="max-width:120px">
                        <span class="text-dark-grey mr-auto">uses <b id="ot-cover-hours">0h</b></span>
                        <button type="button" class="btn btn-sm btn-outline-primary text-nowrap" id="ot-cover-all">Cover max</button>
                    </div>
                </div>
                <div class="form-group">
                    <label class="f-13 mb-1">Encash hours <span class="text-lightest">(at <span id="ot-hourly"></span> an hour)</span></label>
                    <div class="d-flex align-items-center">
                        <input type="number" step="0.01" min="0" id="ot-encash" class="form-control form-control-sm mr-2" style="max-width:120px">
                        <span class="text-dark-grey mr-auto">pays <b id="ot-encash-amount">₹0.00</b></span>
                        <button type="button" class="btn btn-sm btn-outline-primary text-nowrap" id="ot-encash-rest" title="Encash every hour the LOP cover does not use">Encash the rest</button>
                    </div>
                    <small class="text-lightest d-none" id="ot-encash-rest-note">Encashing what is left after the cover; changing the days updates it.</small>
                </div>
                <p class="mb-0">Carried forward: <b id="ot-left"></b></p>

                <details class="mt-3">
                    <summary class="text-dark-grey">Correct the balance (HR / Admin)</summary>
                    <div class="d-flex mt-2">
                        <input type="number" step="0.5" id="ot-adjust-hours" class="form-control form-control-sm mr-2" style="max-width:120px" placeholder="+/- hours">
                        <input type="text" id="ot-adjust-reason" class="form-control form-control-sm mr-2" placeholder="Reason" maxlength="400">
                        <button type="button" class="btn btn-sm btn-outline-secondary text-nowrap" id="ot-adjust">Correct</button>
                    </div>
                </details>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary btn-sm" id="ot-save">Save</button>
            </div>
        </div>
    </div>
</div>

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

            // ---- OT bank: cover LOP, encash, correct ------------------------
            var otAllowCover = {{ ($otRules->s->ot_compensate ?? 1) ? 'true' : 'false' }};
            var otAllowEncash = {{ ($otRules->s->ot_encash ?? 1) ? 'true' : 'false' }};
            var otUser = null, otBank = null, otEncashRest = false;
            var hrs = function (v) { return (Math.round(v * 100) / 100) + 'h'; };

            // Hours the cover leaves in the bank (rounded down, so it never asks for more than there is).
            function otRest() {
                var days = parseFloat($('#ot-cover').val()) || 0;
                return Math.max(0, Math.floor((otBank.available - days * otBank.day_hours) * 100) / 100);
            }

            function otRefresh() {
                var days = parseFloat($('#ot-cover').val()) || 0;
                if (otEncashRest) { $('#ot-encash').val(otRest()); }
                var enc = parseFloat($('#ot-encash').val()) || 0;
                var left = otBank.available - days * otBank.day_hours - enc;
                $('#ot-cover-hours').text(hrs(days * otBank.day_hours));
                $('#ot-encash-amount').text('₹' + (enc * otBank.hourly).toFixed(2));
                $('#ot-left').text(hrs(left)).toggleClass('text-red', left < 0);
            }

            $('body').on('click', '.ot-bank', function (e) {
                e.preventDefault();
                otUser = $(this).data('user');
                otBank = $(this).data('bank');
                $('#ot-name').text($(this).data('name'));
                $('#ot-bf').text(hrs(otBank.bf));
                $('#ot-earned').text(hrs(otBank.earned));
                $('#ot-available').text(hrs(otBank.available));
                $('#ot-lop').text(otBank.lop_days);
                $('#ot-dayh').text(hrs(otBank.day_hours));
                $('#ot-hourly').text('₹' + Number(otBank.hourly).toFixed(2));
                $('#ot-cover-max').text(otBank.max_comp_days);
                $('#ot-cover').attr('max', otBank.max_comp_days).val(otBank.comp_days || 0).prop('disabled', !otAllowCover);
                $('#ot-encash').val(otBank.enc_hours || 0).prop('disabled', !otAllowEncash);
                $('#ot-cover-all').prop('disabled', !otAllowCover || !(otBank.max_comp_days > 0));
                $('#ot-encash-rest').prop('disabled', !otAllowEncash || !(otBank.available > 0));
                otEncashRest = false;
                $('#ot-encash-rest-note').addClass('d-none');
                $('#ot-adjust-hours, #ot-adjust-reason').val('');
                otRefresh();
                $('#ot-bank-modal').modal('show');
            });

            $('#ot-cover').on('input change', otRefresh);
            $('#ot-encash').on('input', function () {
                otEncashRest = false;   // typed by hand: no longer follows the cover
                $('#ot-encash-rest-note').addClass('d-none');
                otRefresh();
            });

            $('#ot-cover-all').on('click', function () {
                $('#ot-cover').val(otBank.max_comp_days);
                otRefresh();
            });

            $('#ot-encash-rest').on('click', function () {
                otEncashRest = true;
                $('#ot-encash-rest-note').removeClass('d-none');
                otRefresh();
            });

            $('#ot-save').on('click', function () {
                $.easyAjax({
                    url: "{{ route('payroll-run.ot-bank') }}", type: 'POST', blockUI: true, disableButton: true, buttonSelector: '#ot-save',
                    data: { _token: '{{ csrf_token() }}', user_id: otUser, month: {{ $month }}, year: {{ $year }},
                        compensate_days: $('#ot-cover').val(), encash_hours: $('#ot-encash').val() },
                    success: function (r) { if (r.status === 'success') { window.location.reload(); } }
                });
            });

            $('#ot-adjust').on('click', function () {
                $.easyAjax({
                    url: "{{ route('payroll-run.ot-bank.adjust') }}", type: 'POST', blockUI: true, disableButton: true, buttonSelector: '#ot-adjust',
                    data: { _token: '{{ csrf_token() }}', user_id: otUser, hours: $('#ot-adjust-hours').val(), reason: $('#ot-adjust-reason').val() },
                    success: function (r) { if (r.status === 'success') { window.location.reload(); } }
                });
            });

            $('#calculate-ot').on('click', function () {
                $.easyAjax({
                    url: "{{ route('payroll-run.overtime') }}",
                    type: 'POST',
                    blockUI: true,
                    disableButton: true,
                    buttonSelector: '#calculate-ot',
                    data: {
                        _token: '{{ csrf_token() }}', month: {{ $month }}, year: {{ $year }},
                        department: @json($departmentId), employees: @json(array_values($selectedEmployees))
                    },
                    success: function (response) {
                        if (response.status === 'success') { window.location.reload(); }
                    }
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

            // One employee's payslip, from their row.
            $('body').on('click', '.generate-one', function () {
                var id = $(this).data('user'), name = $(this).data('name');

                Swal.fire({
                    title: 'Issue ' + $('<div>').text(name).html() + '’s payslip?',
                    html: '<p class="mb-0" style="font-size:13px">For <strong>{{ $monthName }}</strong>, using the figures in their row ' +
                          '(OT bank cover and encashment included). Nobody else is touched.</p>',
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonText: 'Issue payslip',
                    cancelButtonText: '@lang('app.cancel')'
                }).then(function (result) {
                    if (result.isConfirmed) { run([id]); }
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
