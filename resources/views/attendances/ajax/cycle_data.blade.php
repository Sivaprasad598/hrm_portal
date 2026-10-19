{{-- The attendance grid for a salary month (AttendanceController::cycleData):
     the pay cycle day by day, each cell what payroll counted the day as. --}}
@php
    $fmt = fn ($v) => rtrim(rtrim(number_format((float) $v, 1), '0'), '.');
    $time = fn ($v) => $v ? substr($v, 11, 5) : '—';
@endphp

<div class="d-flex flex-wrap justify-content-between align-items-center mb-2">
    <p class="f-13 text-dark-grey mb-1">
        Salary month <strong>{{ \Carbon\Carbon::create($year, $month, 1)->format('F Y') }}</strong>:
        {{ $cycle->start->format('d M Y') }} – {{ $cycle->end->format('d M Y') }} ({{ $cycleDates->count() }} days), counted exactly as payroll counts it.
    </p>
    <div class="f-11 text-dark-grey mb-1">
        @foreach (['present', 'half_day', 'week_off', 'holiday', 'paid_leave', 'lop_leave', 'absent', 'not_employed'] as $key)
            @php [$label, $colour, $icon] = $statusMeta[$key]; @endphp
            <span class="mr-2 text-nowrap"><i class="fa {{ $icon }} text-{{ $colour === 'light' ? 'lightest' : $colour }}"></i> {{ $label }}</span>
        @endforeach
    </div>
</div>

<div class="table-responsive">
    <table class="table table-bordered table-sm mb-0 f-12 cycle-grid">
        <thead class="thead-light">
            <tr>
                <th class="cycle-name">@lang('app.employee')</th>
                @foreach ($cycleDates as $date)
                    <th class="text-center px-1 {{ $date->day === 1 ? 'cycle-month-start' : '' }}">
                        {{ $date->day }}<br><span class="f-10 text-lightest">{{ $date->format('D')[0] }}{{ $date->day === 1 ? ' ' . $date->format('M') : '' }}</span>
                    </th>
                @endforeach
                <th class="text-right" title="Days paid (Days Present on the payslip)">Paid</th>
                <th class="text-right" title="Days counted so far (Working Days on the payslip)">Days</th>
                <th class="text-right">LOP</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($cycleRows as $row)
                @php
                    $employee = $row['employee'];
                    $count = $row['count'];
                    $lop = max(0, $count['working_days'] - $count['days_present']);
                @endphp
                <tr>
                    <td class="cycle-name text-nowrap">
                        @if ($canOpenPayrollView)
                            <a href="{{ route('payroll.attendance', ['userId' => $employee->id, 'month' => $month, 'year' => $year]) }}"
                               class="openRightModal text-darkest-grey" title="Day by day, as payroll counts it">{{ $employee->name }}</a>
                        @else
                            {{ $employee->name }}
                        @endif
                        <div class="f-10 text-lightest">{{ $employee->employee_id }}{{ $employee->designation_name ? ' · ' . $employee->designation_name : '' }}</div>
                    </td>
                    @foreach ($count['days'] as $day)
                        @php
                            [$label, $colour, $icon] = $statusMeta[$day['status']] ?? [$day['status'], 'light', 'fa-circle'];
                            $tip = \Carbon\Carbon::parse($day['date'])->format('D d M') . ': ' . $label
                                . (!empty($day['note']) ? ' — ' . $day['note'] : '')
                                . ($day['first_in'] ? ' · in ' . $time($day['first_in']) . ', out ' . $time($day['last_out']) : '')
                                . ($day['status'] !== 'upcoming' ? ' · paid ' . $fmt($day['paid']) : '');
                        @endphp
                        <td class="text-center px-1 {{ substr($day['date'], 8, 2) === '01' ? 'cycle-month-start' : '' }}" title="{{ $tip }}">
                            @if ($day['status'] !== 'upcoming')
                                <i class="fa {{ $icon }} text-{{ $colour === 'light' ? 'lightest' : $colour }}"></i>
                            @endif
                        </td>
                    @endforeach
                    <td class="text-right font-weight-bold text-success">{{ $fmt($count['days_present']) }}</td>
                    <td class="text-right">{{ $count['working_days'] }}</td>
                    <td class="text-right {{ $lop > 0 ? 'text-red font-weight-bold' : 'text-lightest' }}">{{ $fmt($lop) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="{{ $cycleDates->count() + 4 }}" class="text-center text-lightest py-4">No employees for these filters.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
@include('attendances.ajax.pager')

<style>
    .cycle-grid .cycle-name { position: sticky; left: 0; background: #fff; z-index: 1; min-width: 170px; }
    .cycle-grid thead .cycle-name { background: #f1f1f3; }
    .cycle-grid .cycle-month-start { border-left: 2px solid #99a5b5 !important; }
    .cycle-grid td, .cycle-grid th { vertical-align: middle; }
</style>
