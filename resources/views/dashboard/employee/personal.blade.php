{{-- The employee's own dashboard (App\Services\Employee\PersonalDashboard): only
     their figures, each card opening their own page. Shown under the usual
     welcome bar and clock-in button; Super Admin, Admin and HR keep the
     company-wide dashboard. --}}
@php
    $me = user();
    $d = (new \App\Services\Employee\PersonalDashboard())->build($me);
    $fmt = fn ($v) => rtrim(rtrim(number_format((float) $v, 1), '0'), '.');
    $money = fn ($v) => company()->currency->currency_symbol . number_format((float) $v, 0);
    $statusMeta = \App\Http\Controllers\PayrollAttendanceController::STATUSES;
    $tone = ['present' => 'ok', 'half_day' => 'half', 'half_day_leave' => 'half', 'half_leave' => 'leave', 'week_off' => 'off',
        'holiday' => 'holiday', 'paid_leave' => 'leave', 'lop_leave' => 'lop', 'absent' => 'lop', 'not_employed' => 'none', 'upcoming' => 'none'];
    $attendanceUrl = in_array(user()->permission('view_attendance'), ['all', 'added', 'owned', 'both']) ? route('attendances.by_member') : null;
    $leavesUrl = route('leaves.index');
    $assetsUrl = route('employees.show', $me->id) . '?tab=assets';
@endphp

<style>
    .pd-card { background: #fff; border-radius: 10px; box-shadow: 0 1px 3px rgba(16, 24, 40, .08); padding: 18px 20px; height: 100%; display: block; color: inherit; }
    a.pd-card:hover { text-decoration: none; color: inherit; box-shadow: 0 4px 12px rgba(16, 24, 40, .12); }
    .pd-label { font-size: 12px; font-weight: 600; letter-spacing: .04em; text-transform: uppercase; color: #667085; margin-bottom: 8px; display: flex; justify-content: space-between; }
    .pd-label i { font-size: 16px; color: #98a2b3; }
    .pd-big { font-size: 26px; font-weight: 700; color: #101828; line-height: 1.2; }
    .pd-big small { font-size: 14px; font-weight: 500; color: #667085; }
    .pd-sub { font-size: 13px; color: #475467; margin-top: 4px; }
    .pd-sub .bad { color: #d92d20; font-weight: 600; }
    .pd-leaves { display: flex; flex-wrap: wrap; gap: 6px 14px; }
    .pd-leaves div { font-size: 13px; color: #475467; }
    .pd-leaves strong { font-size: 20px; color: #101828; margin-right: 3px; }
    .pd-head { font-size: 15px; font-weight: 600; color: #101828; margin-bottom: 12px; display: flex; justify-content: space-between; align-items: center; }
    .pd-head a { font-size: 12px; font-weight: 500; }
    .pd-week { display: grid; grid-template-columns: repeat(7, minmax(0, 1fr)); gap: 6px; }
    .pd-day { border-radius: 8px; padding: 8px 4px; text-align: center; font-size: 12px; border: 1px solid #eaecf0; }
    .pd-day b { display: block; font-size: 15px; color: #101828; }
    .pd-day span { display: block; color: #475467; font-size: 11px; line-height: 1.3; min-height: 28px; }
    .pd-day.ok { background: #ecfdf3; border-color: #abefc6; } .pd-day.half { background: #fffaeb; border-color: #fedf89; }
    .pd-day.leave { background: #eff8ff; border-color: #b2ddff; } .pd-day.lop { background: #fef3f2; border-color: #fecdca; }
    .pd-day.off { background: #f2f4f7; } .pd-day.holiday { background: #f4f3ff; border-color: #d9d6fe; }
    .pd-day.today { outline: 2px solid #1d82f5; outline-offset: -1px; }
    .pd-list { list-style: none; margin: 0; padding: 0; }
    .pd-list li { display: flex; justify-content: space-between; gap: 8px; padding: 8px 0; border-bottom: 1px solid #f2f4f7; font-size: 13px; }
    .pd-list li:last-child { border-bottom: 0; }
    .pd-muted { color: #667085; font-size: 13px; }
    .pd-pill { font-size: 11px; padding: 2px 8px; border-radius: 10px; white-space: nowrap; }
    .pd-pill.approved { background: #ecfdf3; color: #067647; } .pd-pill.pending { background: #fffaeb; color: #b54708; }
    .pd-pill.rejected { background: #fef3f2; color: #b42318; }
    @media (max-width: 575.98px) { .pd-day span { display: none; } .pd-day { padding: 6px 2px; } }
</style>

<div class="row">
    {{-- This salary period, as payroll counts it --}}
    <div class="col-xl-3 col-md-6 mb-3">
        @php $a = $d['attendance']; @endphp
        <a href="{{ $attendanceUrl ?: 'javascript:;' }}" class="pd-card">
            <div class="pd-label">This month <i class="bi bi-calendar-check"></i></div>
            @if ($a)
                <div class="pd-big">{{ $fmt($a['paid']) }} <small>/ {{ $a['counted'] }} days paid</small></div>
                <div class="pd-sub">
                    <span class="{{ $a['lop'] > 0 ? 'bad' : '' }}">{{ $fmt($a['lop']) }} LOP</span> ·
                    <span class="{{ $a['late'] > 0 ? 'bad' : '' }}">{{ $a['late'] }} late</span>
                </div>
                <div class="pd-muted f-11 mt-1">{{ $a['cycle']->start->format('d M') }} – {{ $a['cycle']->end->format('d M') }}</div>
            @else
                <div class="pd-muted">Not available</div>
            @endif
        </a>
    </div>

    {{-- Leave balance --}}
    <div class="col-xl-3 col-md-6 mb-3">
        <a href="{{ $leavesUrl }}" class="pd-card">
            <div class="pd-label">Leave balance <i class="bi bi-calendar2-heart"></i></div>
            @if ($d['leave'])
                <div class="pd-leaves">
                    @foreach ($d['leave'] as $l)
                        <div title="{{ $l['name'] }}"><strong>{{ $fmt($l['remaining']) }}</strong>{{ $l['code'] }}</div>
                    @endforeach
                </div>
            @else
                <div class="pd-muted">No leave types assigned</div>
            @endif
            @php $pending = collect($d['requests'] ?? [])->where('status', 'pending')->count(); @endphp
            @if ($pending)
                <div class="pd-sub mt-2">{{ $pending }} request{{ $pending > 1 ? 's' : '' }} pending</div>
            @endif
        </a>
    </div>

    {{-- Last payslip --}}
    <div class="col-xl-3 col-md-6 mb-3">
        <a href="{{ $d['payslip'] ? route('my-payslips.show', $d['payslip']['id']) : route('my-payslips.index') }}" class="pd-card">
            <div class="pd-label">Last payslip <i class="bi bi-receipt"></i></div>
            @if ($d['payslip'])
                <div class="pd-big">{{ $money($d['payslip']['net']) }}</div>
                <div class="pd-sub">{{ $d['payslip']['month'] }} · net pay</div>
            @else
                <div class="pd-muted">No payslip issued yet</div>
            @endif
        </a>
    </div>

    {{-- My assets --}}
    <div class="col-xl-3 col-md-6 mb-3">
        <a href="{{ $assetsUrl }}" class="pd-card">
            <div class="pd-label">My assets <i class="bi bi-laptop"></i></div>
            @if ($d['assets'])
                <div class="pd-big">{{ count($d['assets']) }} <small>held</small></div>
                <div class="pd-sub text-truncate" title="{{ implode(', ', $d['assets']) }}">{{ implode(', ', array_slice($d['assets'], 0, 3)) }}</div>
            @else
                <div class="pd-big">0</div>
                <div class="pd-sub">No assets assigned to you</div>
            @endif
        </a>
    </div>
</div>

<div class="row">
    {{-- This week --}}
    <div class="col-xl-7 mb-3">
        <div class="pd-card">
            <div class="pd-head">This week
                @if ($attendanceUrl)<a href="{{ $attendanceUrl }}">Full calendar</a>@endif
            </div>
            @if ($d['week'])
                <div class="pd-week">
                    @foreach ($d['week'] as $day)
                        @php
                            $date = \Carbon\Carbon::parse($day['date']);
                            $status = $day['status'];
                            $label = $statusMeta[$status][0] ?? ucfirst(str_replace('_', ' ', $status));
                            if ($day['date'] === $d['today']->toDateString() && $day['first_in'] && in_array($status, ['absent', 'half_day'], true)) {
                                $status = 'in_progress';
                                $label = 'In progress';
                            }
                            $short = ['Week off (paid)' => 'Week off', 'Holiday (paid)' => 'Holiday', 'Absent (LOP)' => 'Absent', 'Unpaid leave (LOP)' => 'LOP leave'][$label] ?? $label;
                        @endphp
                        <div class="pd-day {{ $tone[$status] ?? ($status === 'in_progress' ? 'ok' : '') }} {{ $day['date'] === $d['today']->toDateString() ? 'today' : '' }}"
                             title="{{ $date->format('D d M') }}: {{ $label }}{{ !empty($day['note']) ? ' - ' . $day['note'] : '' }}">
                            {{ $date->format('D') }}<b>{{ $date->format('d') }}</b>
                            <span>{{ $status === 'upcoming' ? '' : $short }}{{ $day['first_in'] ? ' ' . substr($day['first_in'], 11, 5) : '' }}</span>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="pd-muted">Not available</div>
            @endif
        </div>
    </div>

    {{-- My leave requests --}}
    <div class="col-xl-5 mb-3">
        <div class="pd-card">
            <div class="pd-head">My leave requests <a href="{{ $leavesUrl }}">All</a></div>
            @if ($d['requests'])
                <ul class="pd-list">
                    @foreach ($d['requests'] as $r)
                        <li>
                            <span>{{ $r['type'] ?: 'Leave' }}{{ $r['half'] ? ' (½)' : '' }} · {{ $r['date']->format('d M Y') }}</span>
                            <span class="pd-pill {{ $r['status'] }}">{{ ucfirst($r['status']) }}{{ $r['waiting_on'] ? ' · with ' . strtolower($r['waiting_on']) : '' }}</span>
                        </li>
                    @endforeach
                </ul>
            @else
                <div class="pd-muted">No leave requests yet. <a href="{{ $leavesUrl }}">Apply for leave</a></div>
            @endif
        </div>
    </div>
</div>

<div class="row">
    {{-- Upcoming holidays --}}
    <div class="col-xl-4 col-md-6 mb-3">
        <div class="pd-card">
            <div class="pd-head">Upcoming holidays</div>
            @if ($d['holidays'])
                <ul class="pd-list">
                    @foreach ($d['holidays'] as $h)
                        <li><span>{{ $h['name'] }}</span><span class="pd-muted">{{ $h['date']->format('D, d M') }}</span></li>
                    @endforeach
                </ul>
            @else
                <div class="pd-muted">No holidays coming up</div>
            @endif
        </div>
    </div>

    {{-- Colleagues this week: names only --}}
    <div class="col-xl-{{ $d['otBank'] !== null ? '5' : '8' }} col-md-6 mb-3">
        <div class="pd-card">
            <div class="pd-head">Celebrations this week</div>
            @if ($d['celebrations'])
                <ul class="pd-list">
                    @foreach ($d['celebrations'] as $c)
                        <li>
                            <span>{{ $c['icon'] }} {{ $c['me'] ? 'You' : $c['name'] }} · {{ $c['what'] }}</span>
                            <span class="pd-muted">{{ $c['in'] === 0 ? 'Today' : ($c['in'] === 1 ? 'Tomorrow' : $d['today']->copy()->addDays($c['in'])->format('D, d M')) }}</span>
                        </li>
                    @endforeach
                </ul>
            @else
                <div class="pd-muted">No birthdays or work anniversaries this week</div>
            @endif
        </div>
    </div>

    {{-- OT bank, where it is in use --}}
    @if ($d['otBank'] !== null)
        <div class="col-xl-3 col-md-6 mb-3">
            <a href="{{ route('employees.show', $me->id) }}?tab=ot-bank" class="pd-card">
                <div class="pd-label">OT bank <i class="bi bi-hourglass-split"></i></div>
                <div class="pd-big">{{ $fmt($d['otBank']) }} <small>hours</small></div>
                <div class="pd-sub">available to use</div>
            </a>
        </div>
    @endif
</div>
