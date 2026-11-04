{{-- "My Team" on the dashboard of anyone people report to: what waits for
     their decision (leave, attendance corrections), how the team stands today,
     and the team's leave this week. Read only; each item opens its page. --}}
@php
    $teamIds = \App\Services\Attendance\CorrectionApprovals::teamUserIds();
    $tz = company()->timezone ?: 'Asia/Kolkata';
    $todayDate = now($tz)->startOfDay();

    $leavesWaiting = \App\Services\Leaves\LeaveApprovals::waitingFor()->unique(fn ($l) => $l->unique_id ?: 'id' . $l->id)->values();
    $correctionsWaiting = \App\Services\Attendance\CorrectionApprovals::waitingFor();

    // Today, as payroll counts it: in, on leave, not in yet, off
    $teamToday = ['in' => [], 'leave' => [], 'out' => [], 'off' => []];
    $counter = new \App\Services\Payroll\AttendanceDays($tz);

    foreach (\App\Models\User::whereIn('id', $teamIds)->orderBy('name')->limit(60)->get(['id', 'name']) as $member) {
        try {
            $day = $counter->forRange((int) $member->id, $todayDate, $todayDate)['days'][0] ?? null;
        } catch (\Throwable $e) {
            report($e);
            $day = null;
        }

        $status = $day['status'] ?? 'upcoming';
        $bucket = match (true) {
            in_array($status, ['paid_leave', 'half_leave', 'lop_leave', 'half_day_leave'], true) => 'leave',
            in_array($status, ['week_off', 'holiday', 'not_employed'], true) => 'off',
            !empty($day['first_in']) || $status === 'present' => 'in',
            default => 'out',
        };
        $teamToday[$bucket][] = $member->name;
    }

    // The team's leave from today to Sunday, approved or waiting
    $weekLeave = DB::table('leaves as l')->join('users as u', 'u.id', '=', 'l.user_id')->leftJoin('leave_types as t', 't.id', '=', 'l.leave_type_id')
        ->whereIn('l.user_id', $teamIds)->whereIn('l.status', ['approved', 'pending'])
        ->whereBetween('l.leave_date', [$todayDate->toDateString(), $todayDate->copy()->endOfWeek(\Carbon\Carbon::SUNDAY)->toDateString()])
        ->orderBy('l.leave_date')->limit(8)->get(['u.name', 'l.leave_date', 'l.status', 'l.duration', 't.type_name']);
@endphp

<div class="pd-team mb-1">
    <div class="pd-team-title">My Team <span>{{ count($teamIds) }} {{ count($teamIds) === 1 ? 'person reports' : 'people report' }} to you</span></div>
    <div class="row">
        {{-- Waiting for the manager's decision --}}
        <div class="col-xl-5 mb-3">
            <div class="pd-card">
                <div class="pd-head">Waiting for you
                    @if ($leavesWaiting->count() + $correctionsWaiting->count())
                        <span class="pd-pill pending">{{ $leavesWaiting->count() + $correctionsWaiting->count() }} to decide</span>
                    @endif
                </div>
                @if ($leavesWaiting->isEmpty() && $correctionsWaiting->isEmpty())
                    <div class="pd-muted">Nothing waiting. You'll get a notification (bell) when someone asks.</div>
                @else
                    <ul class="pd-list">
                        @foreach ($leavesWaiting->take(4) as $l)
                            @php $days = $l->unique_id ? \App\Models\Leave::where('unique_id', $l->unique_id)->count() : 1; @endphp
                            <li>
                                <span><strong>{{ $l->user?->name }}</strong> · {{ $l->type?->type_name ?: 'Leave' }} ·
                                    {{ $l->leave_date->format('d M') }}{{ $days > 1 ? ' (+' . ($days - 1) . ')' : '' }}</span>
                                <a href="{{ route('leaves.show', $l->unique_id ?: $l->id) }}" class="pd-act">Review</a>
                            </li>
                        @endforeach
                        @foreach ($correctionsWaiting->take(3) as $c)
                            <li>
                                <span><strong>{{ $c->user?->name }}</strong> · attendance correction · {{ \Carbon\Carbon::parse($c->date)->format('d M') }}</span>
                                <a href="{{ route('attendances.by_request') }}?waiting=me" class="pd-act">Review</a>
                            </li>
                        @endforeach
                    </ul>
                    <div class="pd-links">
                        @if ($leavesWaiting->isNotEmpty())<a href="{{ route('leaves.index') }}?waiting=me">All leave requests ({{ $leavesWaiting->count() }})</a>@endif
                        @if ($correctionsWaiting->isNotEmpty())<a href="{{ route('attendances.by_request') }}?waiting=me">All corrections ({{ $correctionsWaiting->count() }})</a>@endif
                    </div>
                @endif
            </div>
        </div>

        {{-- The team today --}}
        <div class="col-xl-3 col-md-6 mb-3">
            <a href="{{ route('my-team.attendance') }}" class="pd-card">
                <div class="pd-label">Team today <i class="bi bi-people"></i></div>
                <div class="pd-today">
                    <div class="ok"><b>{{ count($teamToday['in']) }}</b>In</div>
                    <div class="leave"><b>{{ count($teamToday['leave']) }}</b>On leave</div>
                    <div class="lop"><b>{{ count($teamToday['out']) }}</b>Not in</div>
                </div>
                @if ($teamToday['leave'])
                    <div class="pd-sub text-truncate" title="{{ implode(', ', $teamToday['leave']) }}">On leave: {{ implode(', ', $teamToday['leave']) }}</div>
                @elseif ($teamToday['out'])
                    <div class="pd-sub text-truncate" title="{{ implode(', ', $teamToday['out']) }}">Not in: {{ implode(', ', $teamToday['out']) }}</div>
                @endif
                <div class="pd-muted f-11 mt-1">Team attendance &rarr;</div>
            </a>
        </div>

        {{-- The team's leave this week --}}
        <div class="col-xl-4 col-md-6 mb-3">
            <div class="pd-card">
                <div class="pd-head">Team leave this week <a href="{{ route('leaves.index') }}">Team leaves</a></div>
                @if ($weekLeave->isEmpty())
                    <div class="pd-muted">Nobody is on leave this week</div>
                @else
                    <ul class="pd-list">
                        @foreach ($weekLeave as $w)
                            <li>
                                <span>{{ $w->name }} · {{ \Carbon\Carbon::parse($w->leave_date)->format('D d M') }}{{ $w->duration === 'half day' ? ' (½)' : '' }}</span>
                                <span class="pd-pill {{ $w->status }}">{{ $w->status === 'pending' ? 'Pending' : ($w->type_name ?: 'Leave') }}</span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>
    </div>
</div>
