{{-- Attendance by Member, whole year: the twelve months as small calendars
     (AttendanceController::employeeData with month=all). Each day is coloured
     as payroll counts it; a month opens in full on click. --}}
@php
    $tone = [
        'present' => 'ok', 'half_day' => 'half', 'half_day_leave' => 'half', 'half_leave' => 'leave',
        'week_off' => 'off', 'holiday' => 'holiday', 'paid_leave' => 'leave', 'lop_leave' => 'lop',
        'absent' => 'lop', 'not_employed' => 'none', 'upcoming' => 'none',
    ];
    $byMonth = $days->groupBy(fn ($d, $date) => (int) substr($date, 5, 2), true);
    $fmt = fn ($v) => rtrim(rtrim(number_format((float) $v, 1), '0'), '.');
@endphp

<div class="mc-legend">
    @foreach (['present', 'half_day', 'paid_leave', 'lop_leave', 'absent', 'week_off', 'holiday'] as $key)
        <span><i class="mc-dot mc-{{ $tone[$key] }}"></i>{{ $statusMeta[$key][0] }}</span>
    @endforeach
    @if ($lop > 0)
        <span class="ml-auto text-red f-w-500">LOP in {{ $start->year }}: {{ $fmt($lop) }} day(s)</span>
    @endif
</div>

<div class="my-grid">
    @for ($m = 1; $m <= 12; $m++)
        @php
            $first = \Carbon\Carbon::create($start->year, $m, 1);
            $monthDays = $byMonth->get($m, collect());
            $count = fn (array $statuses) => $monthDays->whereIn('status', $statuses)->count();
            $present = $count(['present']) + 0.5 * $count(['half_day', 'half_day_leave']);
            $leave = $count(['paid_leave', 'half_leave']);
            $lopDays = $count(['absent', 'lop_leave']);
            $future = $first->toDateString() > $today;
        @endphp
        <a href="javascript:;" class="my-month {{ $future ? 'my-future' : '' }}" data-month="{{ $m }}" title="Open {{ $first->format('F') }}">
            <div class="my-head">
                <span>{{ $first->format('F') }}</span>
                @unless ($future)
                    <span class="my-stats">
                        <b class="t-ok">{{ $fmt($present) }}</b> in
                        @if ($leave)· <b class="t-leave">{{ $leave }}</b> leave @endif
                        @if ($lopDays)· <b class="t-lop">{{ $lopDays }}</b> LOP @endif
                    </span>
                @endunless
            </div>
            <div class="my-days">
                @foreach (['M', 'T', 'W', 'T', 'F', 'S', 'S'] as $w)
                    <i class="my-w">{{ $w }}</i>
                @endforeach
                @for ($i = 1; $i < $first->dayOfWeekIso; $i++)
                    <i></i>
                @endfor
                @foreach ($monthDays as $date => $day)
                    @php
                        $status = $day['status'];
                        $label = $statusMeta[$status][0] ?? ucfirst(str_replace('_', ' ', $status));
                        if ($date === $today && $day['first_in'] && in_array($status, ['absent', 'half_day'], true)) {
                            $status = 'present';
                            $label = 'In progress';
                        }
                        $hasLeave = !empty($leaves[$date]);
                    @endphp
                    <i class="my-d mc-{{ $tone[$status] ?? 'none' }} {{ $date === $today ? 'my-today' : '' }} {{ $hasLeave && $status === 'upcoming' ? 'my-planned' : '' }}"
                       title="{{ \Carbon\Carbon::parse($date)->format('D d M') }}: {{ $hasLeave && $status === 'upcoming' ? collect($leaves[$date])->map(fn ($l) => ($l->type_name ?: 'Leave') . ($l->status === 'pending' ? ' (pending)' : ''))->implode(', ') : $label }}">{{ (int) substr($date, 8, 2) }}</i>
                @endforeach
            </div>
        </a>
    @endfor
</div>

<style>
    .mc-legend { display: flex; flex-wrap: wrap; gap: 6px 16px; align-items: center; font-size: 12px; color: #475467; margin-bottom: 12px; }
    .mc-legend span { display: inline-flex; align-items: center; gap: 6px; }
    .mc-dot { width: 10px; height: 10px; border-radius: 3px; display: inline-block; }
    .mc-dot.mc-ok { background: #12b76a; } .mc-dot.mc-half { background: #f79009; } .mc-dot.mc-leave { background: #2e90fa; }
    .mc-dot.mc-lop { background: #f04438; } .mc-dot.mc-off { background: #98a2b3; } .mc-dot.mc-holiday { background: #7a5af8; }

    .my-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 14px; }
    .my-month { display: block; border: 1px solid #e4e7ec; border-radius: 10px; padding: 10px 12px; color: #344054; background: #fff; transition: box-shadow .15s; }
    .my-month:hover { text-decoration: none; color: #101828; box-shadow: 0 4px 12px rgba(16, 24, 40, .12); }
    .my-future { opacity: .55; }
    .my-head { display: flex; justify-content: space-between; align-items: baseline; gap: 6px; font-weight: 600; font-size: 14px; margin-bottom: 8px; }
    .my-stats { font-weight: 400; font-size: 11px; color: #667085; white-space: nowrap; }
    .my-stats .t-ok { color: #067647; } .my-stats .t-leave { color: #175cd3; } .my-stats .t-lop { color: #b42318; }
    .my-days { display: grid; grid-template-columns: repeat(7, minmax(0, 1fr)); gap: 3px; }
    .my-days i { font-style: normal; text-align: center; font-size: 10px; line-height: 20px; border-radius: 4px; }
    .my-days .my-w { color: #98a2b3; font-weight: 600; }
    .my-d { color: #344054; background: #f9fafb; }
    .my-d.mc-ok { background: #d1fadf; } .my-d.mc-half { background: #fef0c7; } .my-d.mc-leave { background: #d1e9ff; }
    .my-d.mc-lop { background: #fee4e2; color: #b42318; } .my-d.mc-off { background: #eaecf0; color: #667085; }
    .my-d.mc-holiday { background: #ebe9fe; } .my-d.mc-none { background: #fff; color: #98a2b3; border: 1px solid #f2f4f7; }
    .my-d.my-planned { background: #eff8ff; color: #175cd3; border: 1px dashed #84caff; }
    .my-d.my-today { outline: 2px solid var(--epic-blue, #1d82f5); outline-offset: -1px; font-weight: 700; }

    @media (max-width: 1399.98px) { .my-grid { grid-template-columns: repeat(3, minmax(0, 1fr)); } }
    @media (max-width: 991.98px) { .my-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
    @media (max-width: 575.98px) { .my-grid { grid-template-columns: 1fr; } }
</style>
