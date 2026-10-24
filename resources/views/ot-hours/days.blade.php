@php
    $hm = fn ($m) => \App\Services\Payroll\OtDaily::hm((int) $m);
    $types = ['weekday' => ['Weekday', 'light'], 'weekend' => ['Week-off', 'info'], 'holiday' => ['Holiday', 'warning']];
    $moves = ['compensated' => 'Covered LOP', 'encashed' => 'Encashed', 'adjusted' => 'Correction', 'earned' => 'Earned'];
    $fmt = company()->date_format;
@endphp

<h5 class="f-16 mb-1">{{ $employee->name }} <span class="f-13 text-lightest">{{ $employee->employee_id }}</span></h5>
<p class="f-13 text-dark-grey mb-3">Salary period {{ $period->describe() }}</p>

<div class="table-responsive">
    <table class="table table-sm table-bordered f-13 mb-0">
        <thead class="bg-light">
            <tr><th>Date</th><th>Day</th><th>In</th><th>Out</th><th class="text-right">Worked</th><th class="text-right">Overtime</th><th class="text-right">Rate</th><th class="text-right">Banked</th></tr>
        </thead>
        <tbody>
        @forelse ($otDays as $d)
            @php [$label, $colour] = $types[$d->day_type] ?? [$d->day_type, 'light']; @endphp
            <tr>
                <td class="text-nowrap">{{ \Carbon\Carbon::parse($d->date)->format('D, ' . $fmt) }}</td>
                <td><span class="badge badge-{{ $colour }}">{{ $label }}</span></td>
                <td>{{ $d->first_in ? \Carbon\Carbon::parse($d->first_in)->format('H:i') : '--' }}</td>
                <td>{{ $d->last_out ? \Carbon\Carbon::parse($d->last_out)->format('H:i') : '--' }}</td>
                <td class="text-right">{{ $hm($d->worked_minutes) }}</td>
                <td class="text-right">{{ $hm($d->ot_minutes) }}</td>
                <td class="text-right">{{ \App\Services\Payroll\OtBank::fmt((float) $d->rate) }}×</td>
                <td class="text-right font-weight-600">{{ $hm($d->bank_minutes) }}@if ($d->note)<br><span class="f-11 text-warning">{{ $d->note }}</span>@endif</td>
            </tr>
        @empty
            <tr><td colspan="8" class="text-center text-dark-grey py-3">{{ $pastRows->isNotEmpty() ? 'No day-by-day overtime from the system in this period.' : 'No overtime in this period.' }}</td></tr>
        @endforelse
        </tbody>
        @if ($otDays->isNotEmpty())
            <tfoot>
                <tr class="font-weight-bold">
                    <td colspan="5" class="text-right">{{ $otDays->count() }} day(s)</td>
                    <td class="text-right">{{ $hm($otDays->sum('ot_minutes')) }}</td>
                    <td></td>
                    <td class="text-right">{{ $hm($otDays->sum('bank_minutes')) }}</td>
                </tr>
            </tfoot>
        @endif
    </table>
</div>

@if ($pastRows->isNotEmpty())
    @php $pf = fn ($v) => \App\Services\Payroll\OtBank::fmt((float) $v); @endphp
    <h6 class="f-14 mt-4 mb-2">Past records <span class="badge badge-info f-10">past</span> <span class="f-12 font-weight-normal text-lightest">kept before the system</span></h6>
    <table class="table table-sm table-bordered f-13 mb-0">
        <thead class="bg-light"><tr><th>Entered as</th><th class="text-right">Banked</th><th class="text-right">Used for LOP</th><th class="text-right">Encashed</th><th>Note</th><th>Source</th></tr></thead>
        <tbody>
        @foreach ($pastRows as $p)
            <tr>
                <td>{{ $p->date ? \Carbon\Carbon::parse($p->date)->format('D, ' . $fmt) : 'Month total' }}</td>
                <td class="text-right font-weight-600">{{ $pf($p->ot_hours) }}h{{ $p->raw_hours !== null ? ' (worked ' . $pf($p->raw_hours) . 'h)' : '' }}</td>
                <td class="text-right">{{ $p->used_hours > 0 ? $pf($p->used_hours) . 'h' : '--' }}{{ $p->used_days ? ' · ' . $pf($p->used_days) . ' day(s)' : '' }}</td>
                <td class="text-right">{{ $p->encashed_hours > 0 ? $pf($p->encashed_hours) . 'h' : '--' }}{{ $p->encashed_amount ? ' · ₹' . number_format((float) $p->encashed_amount, 2) : '' }}</td>
                <td>{{ $p->note ?: '--' }}</td>
                <td>{{ $p->source === 'import' ? 'Imported' : 'Manual' }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
@endif

@if ($movements->isNotEmpty())
    <h6 class="f-14 mt-4 mb-2">Used from the bank in this period</h6>
    <table class="table table-sm table-bordered f-13 mb-0">
        <thead class="bg-light"><tr><th>Movement</th><th class="text-right">Hours</th><th class="text-right">Days / Amount</th><th>Details</th><th>Payslip</th></tr></thead>
        <tbody>
        @foreach ($movements as $m)
            <tr>
                <td>{{ $moves[$m->type] ?? ucfirst($m->type) }}</td>
                <td class="text-right {{ $m->hours < 0 ? 'text-red' : 'text-success' }}">{{ $m->hours > 0 ? '+' : '' }}{{ \App\Services\Payroll\OtBank::fmt((float) $m->hours) }}h</td>
                <td class="text-right">{{ $m->days ? \App\Services\Payroll\OtBank::fmt((float) $m->days) . ' day(s)' : ($m->amount ? '₹' . number_format((float) $m->amount, 2) : '--') }}</td>
                <td>{{ $m->note ?: '--' }}</td>
                <td>{!! $m->payslip_number ? e($m->payslip_number) : ($m->type === 'adjusted' ? '--' : '<span class="badge badge-warning">Pending</span>') !!}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
@endif
