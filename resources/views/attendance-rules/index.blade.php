@extends('layouts.app')

{{-- Attendance Rules (AttendanceRulesController): how a day is counted, in plain
     words, from the settings in force. Opened from Attendance -> Dashboard. --}}

@push('styles')
    <style>
        .ar-card h5 { font-size: 15px; font-weight: 600; color: #101828; margin-bottom: 4px; }
        .ar-card .ar-sub { font-size: 12.5px; color: #667085; margin-bottom: 12px; }
        .ar-card ul { padding-left: 18px; margin-bottom: 0; }
        .ar-card li { font-size: 13.5px; color: #344054; margin-bottom: 6px; line-height: 1.55; }
        .ar-card li:last-child { margin-bottom: 0; }
        .ar-num { display: inline-flex; align-items: center; justify-content: center; width: 24px; height: 24px; border-radius: 50%;
            background: color-mix(in srgb, var(--epic-blue, #cb6b4f) 12%, #fff); color: var(--epic-blue, #cb6b4f); font-size: 12px; font-weight: 600; margin-right: 8px; }
        .ar-key { display: inline-block; padding: 1px 8px; border-radius: 6px; background: #f2f4f7; font-weight: 600; color: #101828; }
        .ar-flow { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; font-size: 13px; }
        .ar-flow .step { background: #f8f9fa; border: 1px solid #eaecf0; border-radius: 8px; padding: 6px 10px; }
        .ar-flow .arrow { color: #98a2b3; }
        .ar-table th { white-space: nowrap; }
        .ar-tag { font-size: 11.5px; padding: 2px 8px; border-radius: 10px; font-weight: 600; white-space: nowrap; }
        .ar-full { background: #ecfdf3; color: #027a48; }
        .ar-half { background: #fffaeb; color: #b54708; }
        .ar-absent { background: #fef3f2; color: #b42318; }
        .ar-off { background: #f2f4f7; color: #475467; }
    </style>
@endpush

@section('content')
<div class="content-wrapper arw-dash">

    <div class="card bg-white border-0 b-shadow-4 mb-3">
        <div class="card-body p-3 d-flex flex-wrap align-items-center justify-content-between">
            <div>
                <h4 class="f-18 mb-1">Attendance Rules</h4>
                <p class="f-13 text-dark-grey mb-0">How every working day is counted for attendance, overtime, leave and salary, in short.
                    These are the rules set in Settings right now{{ $effectiveFrom ? ', in force from ' . $effectiveFrom : '' }}.</p>
            </div>
            @if (user()->permission('manage_attendance_setting') === 'all')
                <a href="{{ route('attendance-settings.index') }}" class="btn-secondary rounded f-14 mt-2 mt-md-0" target="_blank"><i class="fa fa-cog mr-1"></i> Change the settings</a>
            @endif
        </div>
    </div>

    {{-- 1. The idea in one line --}}
    <div class="card bg-white border-0 b-shadow-4 mb-3 ar-card">
        <div class="card-body p-3">
            <h5><span class="ar-num">1</span>The idea in one line</h5>
            <p class="ar-sub">From the punches to the payslip.</p>
            <div class="ar-flow">
                <span class="step">Biometric punches{{ $useBiometric ? '' : ' / clock-in' }}</span><span class="arrow">→</span>
                <span class="step">First punch & last punch of the day</span><span class="arrow">→</span>
                <span class="step">Hours worked (breaks taken off)</span><span class="arrow">→</span>
                <span class="step"><b>Full day</b>, <b>Half day</b> or <b>Absent</b></span><span class="arrow">→</span>
                <span class="step">Extra hours become <b>Overtime</b></span><span class="arrow">→</span>
                <span class="step">Paid days & LOP on the <b>payslip</b></span>
            </div>
        </div>
    </div>

    <div class="row">
        {{-- 2. Shifts --}}
        <div class="col-12 mb-3">
            <div class="card bg-white border-0 b-shadow-4 ar-card h-100">
                <div class="card-body p-3">
                    <h5><span class="ar-num">2</span>Shifts: the hours each person is expected to work</h5>
                    <p class="ar-sub">Each employee follows their shift (or the shift roster for a date). Everyone else follows the default shift.</p>
                    <div class="table-responsive">
                        <table class="table table-sm f-13 mb-2 ar-table">
                            <thead><tr><th>Shift</th><th>Timings</th><th>Working days</th><th>Breaks</th><th>Full day needs</th><th>Half day needs</th><th>Late after</th><th>Half-day mark</th></tr></thead>
                            <tbody>
                                @foreach ($shifts as $shift)
                                    <tr>
                                        <td class="font-weight-600">{{ $shift->name }} @if ($shift->default)<span class="badge badge-light ml-1">default</span>@endif</td>
                                        <td>{{ $shift->times }}</td>
                                        <td>{{ $shift->days }}</td>
                                        <td>{{ $shift->flexible ? 'included' : $shift->breaks }}</td>
                                        <td>{{ $shift->full }} worked</td>
                                        <td>{{ $shift->half }} worked</td>
                                        <td>{{ $shift->late_at ?? 'no late mark' }}</td>
                                        <td>{{ $shift->half_mark ?? '—' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <ul>
                        @if ($breaks->isNotEmpty())
                            <li>Breaks: <b>{{ $breaks->implode(', ') }}</b>. A break is taken off only if the person was in the office during it
                                (someone who leaves at 1:00 PM loses no lunch time).</li>
                        @endif
                        <li>Days not in the shift's working days are <b>week offs</b> (paid). Holidays from the holiday list are paid too.</li>
                    </ul>
                </div>
            </div>
        </div>

        {{-- 3. How a day is counted --}}
        <div class="col-lg-6 mb-3">
            <div class="card bg-white border-0 b-shadow-4 ar-card h-100">
                <div class="card-body p-3">
                    <h5><span class="ar-num">3</span>How a day is counted</h5>
                    <p class="ar-sub">Hours worked = last punch − first punch − the breaks inside that time.</p>
                    <ul>
                        <li>Only the <b>first</b> and the <b>last</b> punch of the day matter{{ $countAnywhere ? ', at any time of the day' : ', counted only within the shift timings' }}. Punches in between are ignored.</li>
                        <li><span class="ar-tag ar-full">Full day</span> worked at least the full-day hours of the shift
                            ({{ $main->full ?? '—' }} on {{ $main->name ?? 'the default shift' }}), with <b>{{ $grace }} minutes</b> of grace.</li>
                        <li><span class="ar-tag ar-full">Full day</span> also when someone came in before the late mark and stayed until the shift ended, even if the hours fall a little short.</li>
                        <li><span class="ar-tag ar-half">Half day</span> worked at least the half-day hours ({{ $main->half ?? '—' }}), with the same grace. Counts as ½ day paid, ½ day LOP.</li>
                        <li><span class="ar-tag ar-absent">Absent</span> less than the half-day hours, or no punch at all on a working day with no approved leave. 1 day LOP.</li>
                        <li>Only one punch in the day (forgot to punch out): the day cannot be measured. Raise an attendance correction so HR can fix it.</li>
                    </ul>
                </div>
            </div>
        </div>

        {{-- 4. Lates --}}
        <div class="col-lg-6 mb-3">
            <div class="card bg-white border-0 b-shadow-4 ar-card h-100">
                <div class="card-body p-3">
                    <h5><span class="ar-num">4</span>Late marks</h5>
                    <p class="ar-sub">Coming in late does not cut pay by itself; repeated lates do.</p>
                    <ul>
                        <li>A day is <b>late</b> when the first punch is after the shift start + its late minutes
                            ({{ $main && $main->late_at ? 'after ' . $main->late_at . ' on ' . $main->name : 'per shift' }}).</li>
                        <li>Every <span class="ar-key">{{ $lateEvery }} lates</span> in a pay period cost
                            <span class="ar-key">{{ $hours($lateCost) }} day</span> of pay (LOP). Example: {{ $lateEvery * 2 }} lates = {{ $hours($lateCost * 2) }} day(s) cut.</li>
                        <li>Coming in after the <b>half-day mark</b>{{ $main && $main->half_mark ? ' (' . $main->half_mark . ')' : '' }} makes the day a
                            <span class="ar-tag ar-half">Half day</span> at most, however long the person stays.</li>
                        <li>Flexible shifts have no late mark and no half-day mark.</li>
                    </ul>
                </div>
            </div>
        </div>

        {{-- 5. Overtime --}}
        <div class="col-lg-6 mb-3">
            <div class="card bg-white border-0 b-shadow-4 ar-card h-100">
                <div class="card-body p-3">
                    <h5><span class="ar-num">5</span>Overtime (OT)</h5>
                    @if (!$ot->on)
                        <p class="f-13 mb-0">Overtime is <b>switched off</b>: nobody earns OT from attendance.</p>
                    @else
                        <p class="ar-sub">Extra time is counted automatically from the same punches.</p>
                        <ul>
                            <li><b>Working day:</b> OT = time in office beyond the shift (full-day hours + breaks). Up to <b>{{ $ot->capWeekday }}h</b> a day.</li>
                            <li><b>Week off or holiday:</b> every hour worked is OT. Up to <b>{{ $ot->capOff }}h</b> a day.</li>
                            <li>Less than <b>{{ $ot->min }} minutes</b> of extra time in a day is not counted.</li>
                            <li>Rates: working day <span class="ar-key">× {{ $ot->rateWeekday }}</span>, week off <span class="ar-key">× {{ $ot->rateWeekend }}</span>,
                                holiday <span class="ar-key">× {{ $ot->rateHoliday }}</span>. Example: 4h on a week off = {{ $hours(4 * (float) $ot->rateWeekend) }} OT hours.</li>
                            <li>The money value of an OT hour = {{ $ot->basis }} ÷ (days in the pay period × daily work hours).</li>
                        </ul>
                    @endif
                </div>
            </div>
        </div>

        {{-- 6. OT bank, half days and LOP --}}
        <div class="col-lg-6 mb-3">
            <div class="card bg-white border-0 b-shadow-4 ar-card h-100">
                <div class="card-body p-3">
                    <h5><span class="ar-num">6</span>How OT links with half days and LOP</h5>
                    @if (!$ot->on)
                        <p class="f-13 mb-0">Overtime is off, so OT cannot cover LOP.</p>
                    @else
                        <p class="ar-sub">OT is {{ $ot->bank ? 'saved in a bank for each employee' : 'paid in the month it is earned' }}.</p>
                        <ul>
                            @if ($ot->bank)
                                <li>OT hours go into the employee's <b>OT bank</b> and carry forward{{ $ot->cap ? ' (up to ' . $hours($ot->cap) . 'h)' : ' with no limit and no expiry' }}.</li>
                            @endif
                            @if ($ot->cover)
                                <li>The bank can <b>cover LOP</b>: a half day or an absent day can be paid from OT instead of being cut.
                                    One LOP day uses one full day's work hours ({{ $main->full ?? '—' }}) from the bank; a half day uses half of that.</li>
                                <li>{{ $ot->coverAuto ? 'This happens automatically in payroll, as far as the bank allows.' : 'HR decides it in Payroll → Run Payroll → "OT balance … Manage" for each employee.' }}</li>
                                <li>Only <b>absent days and unpaid (LOP) leave</b> can be covered; paid leave is already paid.</li>
                            @endif
                            @if ($ot->encash)
                                <li>Banked hours can also be <b>encashed</b> (paid as money) in payroll, at the OT hourly value.</li>
                            @endif
                            <li>Each employee can see their balance under My Space → My Overtime; HR sees everyone under Payroll → Pay Elements & OT → OT Hours.</li>
                        </ul>
                    @endif
                </div>
            </div>
        </div>

        {{-- 7. Leave --}}
        <div class="col-lg-6 mb-3">
            <div class="card bg-white border-0 b-shadow-4 ar-card h-100">
                <div class="card-body p-3">
                    <h5><span class="ar-num">7</span>Leave</h5>
                    <p class="ar-sub">Approved leave fills a day that has no attendance.</p>
                    <ul>
                        <li>Paid leave: <b>{{ $leaveTypes->where('paid', true)->pluck('name')->implode(', ') ?: '—' }}</b> (the day is paid).
                            Unpaid: <b>{{ $leaveTypes->where('paid', false)->pluck('name')->implode(', ') ?: '—' }}</b> (the day is LOP).</li>
                        <li>A leave counts only once it is fully approved: {{ implode(' → ', $leaveSteps) }}.
                            @if ($escalateDays > 0) If a step does not decide in {{ $escalateDays }} day(s), it moves to the next. @endif</li>
                        <li>Sandwich rule is <b>{{ $sandwich ? 'on' : 'off' }}</b>:
                            {{ $sandwich ? 'week offs and holidays between two leave days of one request count as leave.' : 'week offs and holidays inside a leave are not counted as leave.' }}</li>
                        <li>Pending or rejected leave does not cover the day; with no punch the day is absent.</li>
                    </ul>
                </div>
            </div>
        </div>

        {{-- 8. Salary --}}
        <div class="col-lg-6 mb-3">
            <div class="card bg-white border-0 b-shadow-4 ar-card h-100">
                <div class="card-body p-3">
                    <h5><span class="ar-num">8</span>What the payslip uses</h5>
                    <p class="ar-sub">Pay period: {{ $cycleText }}. Now: {{ $cycleNow }}.</p>
                    <ul>
                        <li><b>Days paid</b> = full days + ½ for each half day + week offs + holidays + paid leave
                            − late deductions{{ $ot->on && $ot->cover ? ' + LOP days covered from the OT bank' : '' }}.</li>
                        <li><b>LOP days</b> = days in the period − days paid. Each LOP day costs one day's pay (salary after PF and ESI ÷ days in the period).</li>
                        <li>A payslip is made only after the pay period has ended{{ $useBiometric ? ' and the biometric data has been pulled up to its last day' : '' }}.</li>
                        @if ($hoursOffDivisions)
                            <li>Divisions where hours are not checked ({{ implode(', ', $hoursOffDivisions) }}): any punch on a working day is a full day; no punch and no leave is absent.</li>
                        @endif
                        <li>Employees marked "Salary not based on attendance"{{ $exemptCount ? ' (' . $exemptCount . ' now)' : '' }}: every working day is paid; only unpaid leave is cut.</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    {{-- 9. Examples --}}
    @if ($examples)
        <div class="card bg-white border-0 b-shadow-4 mb-3 ar-card">
            <div class="card-header bg-white border-bottom p-3">
                <h5 class="mb-0"><span class="ar-num">9</span>Examples on {{ $main->name ?? 'the default shift' }} ({{ $main->times ?? '' }})</h5>
                <p class="ar-sub mb-0 mt-1">Worked out by the same rules payroll uses, with today's settings.</p>
            </div>
            <div class="table-responsive">
                <table class="table table-hover f-13 mb-0 ar-table">
                    <thead><tr><th>Situation</th><th>In</th><th>Out</th><th>Hours worked</th><th>Late</th><th>Counted as</th><th>Overtime</th></tr></thead>
                    <tbody>
                        @foreach ($examples as $ex)
                            <tr>
                                <td class="font-weight-500">{{ $ex->label }}</td>
                                <td>{{ $ex->in }}</td>
                                <td>{{ $ex->out }}</td>
                                <td>{{ $ex->worked }}</td>
                                <td>{{ $ex->late }}</td>
                                <td>
                                    <span class="ar-tag {{ $ex->credit === null ? 'ar-off' : ($ex->credit >= 1 ? 'ar-full' : ($ex->credit >= 0.5 ? 'ar-half' : 'ar-absent')) }}">{{ $ex->result }}</span>
                                </td>
                                <td>{{ $ex->ot }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

</div>
@endsection
