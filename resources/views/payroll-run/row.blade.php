{{-- One employee's row on Payroll Run (index.blade.php, and rowSwitch sends it back on its own) --}}
@php $num ??= fn ($v) => rtrim(rtrim(number_format((float) $v, 2), '0'), '.'); @endphp
    <tr @class(['text-lightest' => $row['status'] === 'blocked']) data-row="{{ $row['user_id'] }}" data-serial="{{ $row['serial'] }}"
data-fig='@json(\App\Http\Controllers\PayrollRunController::figures($row))'>
        <td class="col-sno">{{ $row['serial'] }}</td>
        <td class="col-name">
            <span class="font-weight-bold">{{ $row['name'] }}</span>
            <br>
            <span class="f-11 text-lightest">
                {{ $row['card_id'] ?: 'no code' }}{{ $row['department'] ? ' · ' . $row['department'] : '' }}{{ !empty($row['branch']) ? ' · ' . $row['branch'] : '' }}
            </span>
            {{-- Day by day, as this row counts it: worked, week offs, holidays, leave, LOP. --}}
            <br>
            <a href="{{ route('payroll.attendance', ['userId' => $row['user_id'], 'month' => $month, 'year' => $year]) }}"
               class="openRightModal f-11" title="Attendance for this salary period, day by day">
                <i class="fa fa-calendar-alt mr-1"></i>Attendance
            </a>
            {{-- This employee's late / PF-ESI switches for the period: what applies (their own, else the company's),
                 changed right here; the row is worked out again (a draft payslip is made again). Approved / paid: revoke first. --}}
            <div class="pr-row-switches">
                @foreach (['deduct_late_comings' => 'Late deduct', 'prorate_statutory' => 'PF/ESI for paid days'] as $key => $text)
                    @php
                        $own = array_key_exists($key, $row['own']);
                        $value = $own ? $row['own'][$key] : ($switches[$key] ?? true);
                        // a draft payslip is made again with the new setting; approved / paid ones stand
                        $issued = $row['status'] === 'generated' && ($row['payslip_status'] ?? 'draft') !== 'draft';
                    @endphp
                    <label class="pr-row-switch {{ $own ? 'is-own' : '' }} {{ $issued ? 'is-locked' : '' }}"
                           title="{{ $own ? 'Set for this employee (company: ' . (($switches[$key] ?? true) ? 'on' : 'off') . ')' : 'As the company setting' }}{{ $issued ? ' - payslip ' . ($row['payslip_status'] ?? 'issued') . ': revoke it to change' : ($row['status'] === 'generated' ? ' - changing it re-makes the draft payslip' : '') }}">
                        <input type="checkbox" class="pr-row-toggle" data-user="{{ $row['user_id'] }}" data-switch="{{ $key }}"
                               data-company="{{ ($switches[$key] ?? true) ? 1 : 0 }}" @checked($value) @disabled($issued)>
                        <span class="pr-row-knob"></span>{{ $text }}
                    </label>
                @endforeach
            </div>
            @if (($row['statutory'] ?? '') !== '' || ($row['paid_by'] ?? 'bank') === 'cash')
                <br><span class="badge badge-light border f-10" title="Set on the employee record: not deducted (either side), not printed on the payslip">{{ trim(($row['statutory'] ?? '') . (($row['paid_by'] ?? '') === 'cash' ? ' · Cash' : ''), ' ·') }}</span>
            @endif
            @if (($row['basis'] ?? '') === 'exempt')
                <br><span class="badge badge-light f-10" title="Salary not based on attendance: full salary, leave still counts">Attendance-exempt</span>
            @elseif (($row['basis'] ?? '') === 'hours_off')
                <br><span class="badge badge-light f-10" title="The division does not consider hours: any punch is a full day">Hours not considered</span>
            @endif
            @if (($row['breakdown']['late_marks'] ?? 0) || ($row['breakdown']['late_half_days'] ?? 0))
                <br><span class="f-10 text-warning" title="Lates before the half-day mark (every Nth costs a deduction) / arrivals after the half-day mark (half day each)">
                    Late {{ $row['breakdown']['late_marks'] }}{{ $row['breakdown']['late_deduction'] > 0 ? ' → −' . $num($row['breakdown']['late_deduction']) . ' day' : '' }}{{ $row['breakdown']['late_half_days'] ? ' · after half-day mark ' . $row['breakdown']['late_half_days'] : '' }}
                </span>
            @endif
            @if ($b = $row['ot_bank'] ?? null)
                {{-- OT bank: brought forward, earned this period, LOP covered / encashed, carried forward. --}}
                <br><span class="f-10 text-dark-grey" title="Brought forward + earned this period - used = carried forward">
                    OT balance {{ $num($b['bf']) }}h{{ $b['earned'] > 0 ? ' +' . $num($b['earned']) . 'h' : '' }}{{ $b['comp_days'] > 0 ? ' · covers ' . $num($b['comp_days']) . ' LOP' : '' }}{{ $b['enc_hours'] > 0 ? ' · encash ' . $num($b['enc_hours']) . 'h' : '' }} → {{ $num($b['cf']) }}h
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
