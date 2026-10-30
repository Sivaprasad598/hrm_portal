{{-- Attendance by Member: the month as a calendar (AttendanceController::employeeData).
     Each day is what payroll counts it as; a day with punches opens its attendance,
     any other past day opens how it was counted (with "Mark attendance"). --}}
@php
    $tone = [
        'present' => 'ok', 'half_day' => 'half', 'half_day_leave' => 'half', 'half_leave' => 'leave',
        'week_off' => 'off', 'holiday' => 'holiday', 'paid_leave' => 'leave', 'lop_leave' => 'lop',
        'absent' => 'lop', 'not_employed' => 'none', 'upcoming' => 'none', 'in_progress' => 'in_progress',
    ];
    $hm = function (int $seconds) {
        return $seconds > 0 ? intdiv($seconds, 3600) . 'h ' . str_pad((string) intdiv($seconds % 3600, 60), 2, '0', STR_PAD_LEFT) . 'm' : null;
    };
    $leading = $start->dayOfWeekIso - 1;   // Monday first
@endphp

<div class="mc-legend">
    @foreach (['present', 'half_day', 'paid_leave', 'lop_leave', 'absent', 'week_off', 'holiday'] as $key)
        <span><i class="mc-dot mc-{{ $tone[$key] }}"></i>{{ $statusMeta[$key][0] }}</span>
    @endforeach
    <span><i class="mc-dot mc-late"></i>Late</span>
    @if ($lop > 0)
        <span class="ml-auto text-red f-w-500">LOP so far: {{ rtrim(rtrim(number_format($lop, 1), '0'), '.') }} day(s)</span>
    @endif
</div>

<div class="mc-grid">
    @foreach (['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'] as $weekday)
        <div class="mc-head">{{ $weekday }}</div>
    @endforeach

    @for ($i = 0; $i < $leading; $i++)
        <div class="mc-blank"></div>
    @endfor

    @foreach ($days as $date => $day)
        @php
            $status = $day['status'];
            [$label] = $statusMeta[$status] ?? [ucfirst(str_replace('_', ' ', $status))];
            $isToday = $date === $today;
            // Today with a punch but not a full day yet: still going.
            if ($isToday && $day['first_in'] && in_array($status, ['absent', 'half_day'], true)) {
                $status = 'in_progress';
                $label = 'In progress';
            }
            $attendanceId = $attendanceIds[$date] ?? null;
            $opens = $status !== 'upcoming';
            $dayLeaves = $leaves[$date] ?? [];
            $worked = $hm((int) ($day['worked_minutes'] ?? 0) * 60 ?: (int) $day['seconds']);
        @endphp
        <a href="javascript:;"
           class="mc-day mc-{{ $tone[$status] ?? 'ok' }} {{ $isToday ? 'mc-today' : '' }} {{ $opens ? ($attendanceId ? 'view-attendance' : 'cycle-day') : 'mc-static' }}"
           @if ($attendanceId) data-attendance-id="{{ $attendanceId }}" @elseif ($opens) data-user-id="{{ $userId }}" data-date="{{ $date }}" @endif
           title="{{ \Carbon\Carbon::parse($date)->format('D d M') }}: {{ $label }}{{ !empty($day['note']) ? ' - ' . $day['note'] : '' }}">
            <div class="mc-top">
                <span class="mc-num">{{ (int) substr($date, 8, 2) }}</span>
                @if (isset($day['late']))
                    <span class="mc-chip mc-chip-late">{{ $day['late'] === 'late' ? 'Late' : 'Late ½' }}</span>
                @endif
            </div>

            <div class="mc-status">{{ $status === 'holiday' && !empty($day['note']) ? $day['note'] : $label }}</div>

            @if ($day['first_in'])
                <div class="mc-time">{{ substr($day['first_in'], 11, 5) }} – {{ $day['last_out'] ? substr($day['last_out'], 11, 5) : '…' }}</div>
                @if ($worked)
                    <div class="mc-sub">{{ $worked }}{{ !empty($day['ot_minutes']) ? ' · OT ' . $hm($day['ot_minutes'] * 60) : '' }}</div>
                @endif
            @endif

            @foreach ($dayLeaves as $leave)
                <div class="mc-leave {{ $leave->status === 'pending' ? 'mc-leave-pending' : '' }}">
                    {{ $leave->type_name ?: 'Leave' }}{{ $leave->duration === 'half day' ? ' (½)' : '' }}{{ $leave->status === 'pending' ? ' · pending' : '' }}
                </div>
            @endforeach
        </a>
    @endforeach
</div>

<style>
    .mc-legend { display: flex; flex-wrap: wrap; gap: 6px 16px; align-items: center; font-size: 12px; color: #475467; margin-bottom: 12px; }
    .mc-legend span { display: inline-flex; align-items: center; gap: 6px; }
    .mc-dot { width: 10px; height: 10px; border-radius: 3px; display: inline-block; }
    .mc-grid { display: grid; grid-template-columns: repeat(7, minmax(0, 1fr)); gap: 6px; }
    .mc-head { font-size: 12px; font-weight: 600; color: #667085; text-transform: uppercase; letter-spacing: .03em; padding: 4px 6px; }
    .mc-blank { min-height: 96px; }
    .mc-day { display: block; min-height: 96px; padding: 6px 8px; border-radius: 8px; border: 1px solid #e4e7ec; background: #fff;
        color: #344054; font-size: 12px; line-height: 1.35; position: relative; transition: box-shadow .15s; overflow: hidden; }
    .mc-day:hover { text-decoration: none; color: #101828; box-shadow: 0 2px 8px rgba(16, 24, 40, .12); }
    .mc-day.mc-static { cursor: default; box-shadow: none; }
    .mc-top { display: flex; align-items: center; justify-content: space-between; gap: 4px; }
    .mc-num { font-size: 14px; font-weight: 600; }
    .mc-status { font-weight: 500; margin-top: 2px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .mc-time { color: #475467; margin-top: 2px; }
    .mc-sub { color: #667085; font-size: 11px; }
    .mc-leave { margin-top: 3px; font-size: 11px; padding: 1px 6px; border-radius: 10px; background: #eef4ff; color: #3538cd; display: inline-block; }
    .mc-leave-pending { background: #fffaeb; color: #b54708; }
    .mc-chip { font-size: 10px; padding: 0 6px; border-radius: 10px; font-weight: 600; }
    .mc-chip-late { background: #fef0c7; color: #b54708; }
    .mc-today { outline: 2px solid #1d82f5; outline-offset: -2px; }

    /* Day colours: a soft fill and a matching left edge */
    .mc-day.mc-ok { background: #ecfdf3; border-left: 4px solid #12b76a; }
    .mc-day.mc-half { background: #fffaeb; border-left: 4px solid #f79009; }
    .mc-day.mc-leave { background: #eff8ff; border-left: 4px solid #2e90fa; }
    .mc-day.mc-lop { background: #fef3f2; border-left: 4px solid #f04438; }
    .mc-day.mc-off { background: #f2f4f7; border-left: 4px solid #98a2b3; }
    .mc-day.mc-holiday { background: #f4f3ff; border-left: 4px solid #7a5af8; }
    .mc-day.mc-none { background: #fff; color: #98a2b3; }
    .mc-day.mc-in_progress { background: #ecfdf3; border-left: 4px dashed #12b76a; }
    .mc-dot.mc-ok { background: #12b76a; } .mc-dot.mc-half { background: #f79009; } .mc-dot.mc-leave { background: #2e90fa; }
    .mc-dot.mc-lop { background: #f04438; } .mc-dot.mc-off { background: #98a2b3; } .mc-dot.mc-holiday { background: #7a5af8; }
    .mc-dot.mc-late { background: #fdb022; }

    @media (max-width: 767.98px) {
        .mc-grid { gap: 3px; }
        .mc-head { font-size: 10px; padding: 2px; }
        .mc-day, .mc-blank { min-height: 64px; padding: 4px; }
        .mc-time, .mc-sub, .mc-leave, .mc-status { display: none; }
        .mc-num { font-size: 12px; }
    }
</style>
