{{-- HR and Admin, above the company dashboard: what waits for their decision,
     and their own figures (App\Services\Employee\PersonalDashboard) - they are
     employees too. Anyone who also has people reporting to them gets My Team. --}}
@php
    $me = user();
    $d = (new \App\Services\Employee\PersonalDashboard())->build($me);
    $fmt = fn ($v) => rtrim(rtrim(number_format((float) $v, 1), '0'), '.');

    $leavesWaiting = \App\Services\Leaves\LeaveApprovals::waitingFor()->unique(fn ($l) => $l->unique_id ?: 'id' . $l->id)->values();
    $correctionsWaiting = \App\Services\Attendance\CorrectionApprovals::waitingFor();
    $hasTeam = (bool) \App\Services\Attendance\CorrectionApprovals::teamUserIds();
@endphp

@include('dashboard.employee.pd-styles')

@if ($hasTeam)
    @include('dashboard.employee.team')
@endif

<div class="row">
    {{-- Waiting for their decision (the HR step) --}}
    @unless ($hasTeam)
        <div class="col-xl-5 mb-3">
            <div class="pd-card">
                <div class="pd-head">Waiting for you
                    @if ($leavesWaiting->count() + $correctionsWaiting->count())
                        <span class="pd-pill pending">{{ $leavesWaiting->count() + $correctionsWaiting->count() }} to decide</span>
                    @endif
                </div>
                @if ($leavesWaiting->isEmpty() && $correctionsWaiting->isEmpty())
                    <div class="pd-muted">No leave or attendance corrections are waiting for HR.</div>
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
    @endunless

    {{-- Their own salary period --}}
    <div class="col-xl-{{ $hasTeam ? '4' : '3' }} col-md-6 mb-3">
        @php $a = $d['attendance']; @endphp
        <a href="{{ route('attendances.by_member') }}?user_id={{ $me->id }}" class="pd-card">
            <div class="pd-label">My attendance <i class="bi bi-calendar-check"></i></div>
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

    {{-- Their own leave --}}
    <div class="col-xl-{{ $hasTeam ? '4' : '2' }} col-md-6 mb-3">
        <div class="pd-card">
            <div class="pd-label">My leave <a href="{{ route('leaves.personal') }}"><i class="bi bi-calendar2-heart"></i></a></div>
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
                <div class="pd-sub mt-1">{{ $pending }} request{{ $pending > 1 ? 's' : '' }} pending</div>
            @endif
            <a href="{{ route('leaves.personal') }}" class="pd-apply">My leaves &amp; apply &rarr;</a>
        </div>
    </div>

    {{-- Their OT bank, where it is in use; else their payslip --}}
    <div class="col-xl-{{ $hasTeam ? '4' : '2' }} col-md-6 mb-3">
        @if ($d['otBank'] !== null)
            <a href="{{ route('employees.show', $me->id) }}?tab=ot-bank" class="pd-card">
                <div class="pd-label">My OT bank <i class="bi bi-hourglass-split"></i></div>
                <div class="pd-big">{{ $fmt($d['otBank']) }} <small>hours</small></div>
                <div class="pd-sub">available to use</div>
            </a>
        @else
            <a href="{{ route('my-payslips.index') }}" class="pd-card">
                <div class="pd-label">My payslips <i class="bi bi-receipt"></i></div>
                <div class="pd-sub">{{ $d['payslip'] ? $d['payslip']['month'] : 'No payslip issued yet' }}</div>
            </a>
        @endif
    </div>
</div>
