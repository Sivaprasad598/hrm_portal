{{-- One employee's attendance for one salary period, as payroll counted it
     (PayrollAttendanceController). Used in the side panel and as a page. --}}
@php
    $b = $count['breakdown'];
    $fmt = fn ($v) => rtrim(rtrim(number_format((float) $v, 1), '0'), '.');
    $hours = fn ($s) => $s > 0 ? sprintf('%dh %02dm', intdiv($s, 3600), intdiv($s % 3600, 60)) : '—';
    $running = $count['working_days'] < $daysInCycle;
    $tiles = [
        ['Days in period', $daysInCycle, 'text-dark-grey', $cycle->start->format('d M') . ' – ' . $cycle->end->format('d M')],
        ['Counted so far', $count['working_days'], 'text-dark-grey', $running ? 'the period is still running' : 'the whole period'],
        ['Worked', $fmt($b['worked']), 'text-success', 'present days, a half day counts ½'],
        ['Week offs + holidays', $fmt($b['week_off'] + $b['holiday']), 'text-dark-grey', 'paid'],
        ['Paid leave', $fmt($b['paid_leave']), 'text-primary', 'approved, paid'],
        ['Unpaid leave + absent', $fmt($b['lop_leave'] + $b['absent']), 'text-red', 'not paid'],
        ['Days paid', $fmt($count['days_present']), 'text-success', 'Days Present on the payslip'],
        ['LOP days', $fmt($lopDays), $lopDays > 0 ? 'text-red' : 'text-dark-grey', 'counted − paid'],
    ];
    $periodUrl = fn ($p) => route('payroll.attendance', ['userId' => $employee->id, 'month' => $p->month, 'year' => $p->year]);
@endphp

<div class="payroll-attendance">
    <div class="d-flex flex-wrap justify-content-between align-items-start mb-3">
        <div>
            <h4 class="f-18 mb-1">{{ $employee->name }}
                <span class="f-13 text-lightest">{{ $employee->employee_id }}{{ $employee->designation ? ' · ' . $employee->designation : '' }}{{ $employee->department ? ' · ' . $employee->department : '' }}</span>
            </h4>
            <p class="f-13 text-dark-grey mb-0">
                Salary period <strong>{{ $cycle->start->format('d M Y') }} – {{ $cycle->end->format('d M Y') }}</strong>
                ({{ \Carbon\Carbon::create($year, $month, 1)->format('F Y') }} payroll, {{ $daysInCycle }} days)
            </p>
        </div>
        <div class="d-flex flex-wrap align-items-center mt-2 mt-md-0">
            <a href="{{ $periodUrl($previousPeriod) }}" class="btn btn-sm btn-outline-secondary mr-1 pa-step" title="Previous salary period">
                <i class="fa fa-chevron-left"></i> {{ \Carbon\Carbon::create($previousPeriod->year, $previousPeriod->month, 1)->format('M Y') }}
            </a>
            <a href="{{ $periodUrl($nextPeriod) }}" class="btn btn-sm btn-outline-secondary mr-2 pa-step" title="Next salary period">
                {{ \Carbon\Carbon::create($nextPeriod->year, $nextPeriod->month, 1)->format('M Y') }} <i class="fa fa-chevron-right"></i>
            </a>
            <a href="{{ route('attendances.index', ['period' => 'cycle', 'month' => $month, 'year' => $year, 'employee' => $employee->id]) }}"
               class="btn btn-sm btn-outline-primary" target="_blank">
                <i class="fa fa-calendar-alt mr-1"></i> Attendance grid
            </a>
        </div>
    </div>

    @if ($running)
        <div class="alert alert-warning f-13 py-2">
            The period runs until {{ $cycle->end->format('d M Y') }}: {{ $count['working_days'] }} of {{ $daysInCycle }} days are counted so far,
            and the rest are counted as they happen.
        </div>
    @endif

    @if ($payslip)
        <div class="alert alert-info f-13 py-2">
            Payslip <strong>{{ $payslip->payslip_number }}</strong> ({{ $payslip->approval_status }}) was issued for this period with
            {{ $fmt($payslip->working_days) }} working days, {{ $fmt($payslip->days_present) }} paid and {{ $fmt($payslip->lop_days) }} LOP.
            @if (abs((float) $payslip->days_present - $count['days_present']) >= 0.5 || (int) $payslip->working_days !== $count['working_days'])
                <span class="text-red">Attendance now gives {{ $count['working_days'] }} counted and {{ $fmt($count['days_present']) }} paid — it has changed since.</span>
            @endif
        </div>
    @endif

    <div class="row">
        @foreach ($tiles as [$label, $value, $class, $sub])
            <div class="col-lg-3 col-md-4 col-6 mb-2">
                <div class="border rounded p-2 h-100 bg-white">
                    <div class="f-11 text-lightest text-uppercase">{{ $label }}</div>
                    <div class="f-18 font-weight-bold {{ $class }}">{{ $value }}</div>
                    <div class="f-11 text-lightest">{{ $sub }}</div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="table-responsive mt-2">
        <table class="table table-sm table-hover mb-0 f-13">
            <thead class="thead-light">
                <tr>
                    <th>Date</th>
                    <th>Shift</th>
                    <th>First in</th>
                    <th>Last out</th>
                    <th>Hours</th>
                    <th>Counted as</th>
                    <th class="text-right">Paid</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($count['days'] as $day)
                    @php
                        [$label, $colour, $icon] = $statusMeta[$day['status']] ?? [$day['status'], 'light', 'fa-circle'];
                        $date = \Carbon\Carbon::parse($day['date']);
                        $timeOf = fn ($v) => $v ? (substr($v, 0, 10) === $day['date'] ? substr($v, 11) : \Carbon\Carbon::parse($v)->format('d M H:i')) : '—';
                    @endphp
                    <tr class="{{ $date->day === 1 ? 'border-top-dark' : '' }} {{ $day['status'] === 'upcoming' ? 'text-lightest' : '' }}">
                        <td class="text-nowrap">{{ $date->format('D, d M') }}</td>
                        <td class="text-nowrap">{{ $day['shift'] ?? '—' }} <span class="text-lightest f-11">{{ $day['shift_hours'] }}</span></td>
                        <td>{{ $timeOf($day['first_in']) }}</td>
                        <td>{{ $timeOf($day['last_out']) }}</td>
                        <td>{{ $hours($day['seconds']) }}</td>
                        <td>
                            <span class="badge badge-{{ $colour }} f-11"><i class="fa {{ $icon }} mr-1"></i>{{ $label }}</span>
                            @if (!empty($day['note']))<span class="f-11 text-lightest ml-1">{{ $day['note'] }}</span>@endif
                            @if (($day['leave_paid'] ?? 0) > 0 && $day['status'] !== 'paid_leave')<span class="f-11 text-primary ml-1">+ {{ $fmt($day['leave_paid']) }} paid leave</span>@endif
                            @if (($day['leave_unpaid'] ?? 0) > 0 && $day['status'] !== 'lop_leave')<span class="f-11 text-red ml-1">+ {{ $fmt($day['leave_unpaid']) }} unpaid leave</span>@endif
                        </td>
                        <td class="text-right {{ $day['paid'] < 1 && $day['status'] !== 'upcoming' ? 'text-red font-weight-bold' : '' }}">
                            {{ $day['status'] === 'upcoming' ? '' : $fmt($day['paid']) }}
                        </td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr class="font-weight-bold">
                    <td colspan="6" class="text-right">Days paid / counted</td>
                    <td class="text-right">{{ $fmt($count['days_present']) }} / {{ $count['working_days'] }}</td>
                </tr>
            </tfoot>
        </table>
    </div>
    <p class="f-11 text-lightest mt-2 mb-0">
        A day is worked in full or as a half day by the time between the first and last punch, against the shift's own thresholds.
        Week offs follow the employee's shift; holidays, approved paid leave and week offs are paid; absence and unpaid leave are LOP.
    </p>
</div>

<script>
    // Inside the side panel the period arrows reload the panel rather than leaving the page.
    $(function () {
        if (!$('#right-modal-content .payroll-attendance').length) { return; }

        $('#right-modal-content').off('click', '.pa-step').on('click', '.pa-step', function (e) {
            e.preventDefault();
            $.easyAjax({ url: this.href, type: 'GET', blockUI: true, container: '#right-modal-content',
                success: function (r) { if (r.status === 'success') { $('#right-modal-content').html(r.html); $(RIGHT_MODAL_TITLE).html(r.title); } } });
        });
    });
</script>
