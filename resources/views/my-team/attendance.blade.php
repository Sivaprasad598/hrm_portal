{{-- A reporting manager's team, this salary period (App\Http\Controllers\MyTeamController) --}}
@extends('layouts.app')

@section('content')
    @php
        $fmt = fn ($v) => rtrim(rtrim(number_format((float) $v, 1), '0'), '.');
        $statusMeta = \App\Http\Controllers\PayrollAttendanceController::STATUSES;
        $periodKey = fn ($p) => sprintf('%04d-%02d', $p->year, $p->month);
    @endphp

    <div class="content-wrapper">
        {{-- Waiting for the manager --}}
        <div class="row">
            <div class="col-md-6 mb-3">
                <a href="{{ route('leaves.index') }}?waiting=me" class="card border-0 b-shadow-4 p-3 d-block text-dark h-100">
                    <div class="f-12 text-lightest text-uppercase font-weight-bold">Leave requests waiting for you</div>
                    <div class="f-27 font-weight-bold {{ $leavesWaiting ? 'text-warning' : '' }}">{{ $leavesWaiting }}</div>
                    <div class="f-13 text-dark-grey">{{ $leavesWaiting ? 'Review and approve or reject' : 'Nothing to decide' }}</div>
                </a>
            </div>
            <div class="col-md-6 mb-3">
                <a href="{{ route('attendances.by_request') }}?waiting=me" class="card border-0 b-shadow-4 p-3 d-block text-dark h-100">
                    <div class="f-12 text-lightest text-uppercase font-weight-bold">Attendance corrections waiting for you</div>
                    <div class="f-27 font-weight-bold {{ $correctionsWaiting ? 'text-warning' : '' }}">{{ $correctionsWaiting }}</div>
                    <div class="f-13 text-dark-grey">{{ $correctionsWaiting ? 'Review and approve or reject' : 'Nothing to decide' }}</div>
                </a>
            </div>
        </div>

        <div class="card bg-white border-0 b-shadow-4 mb-3">
            <div class="card-header bg-white border-bottom d-flex align-items-center flex-wrap p-3">
                <div class="mr-auto">
                    <h6 class="mb-0 f-14">Team attendance · {{ \Carbon\Carbon::create($cycle->year, $cycle->month, 1)->format('F Y') }} <span class="text-lightest font-weight-normal">({{ $cycle->start->format('d M') }} – {{ $cycle->end->format('d M') }})</span></h6>
                    <p class="mb-0 f-12 text-lightest">The people who report to you, counted as payroll counts them. Open a name for their month, day by day.</p>
                </div>
                <div class="btn-group btn-group-sm mt-2 mt-md-0" role="group">
                    <a class="btn btn-secondary" href="{{ route('my-team.attendance', ['period' => $periodKey($previousPeriod)]) }}">&larr; {{ \Carbon\Carbon::create($previousPeriod->year, $previousPeriod->month, 1)->format('M') }}</a>
                    @unless ($isCurrent)
                        <a class="btn btn-secondary" href="{{ route('my-team.attendance') }}">This period</a>
                    @endunless
                    @unless ($isCurrent || $nextPeriod->start->gt($today))
                        <a class="btn btn-secondary" href="{{ route('my-team.attendance', ['period' => $periodKey($nextPeriod)]) }}">{{ \Carbon\Carbon::create($nextPeriod->year, $nextPeriod->month, 1)->format('M') }} &rarr;</a>
                    @endunless
                </div>
            </div>

            <div class="table-responsive">
                <table class="table table-hover mb-0 f-13">
                    <thead>
                        <tr>
                            <th>Employee</th>
                            @if ($isCurrent)<th>Today</th>@endif
                            <th class="text-right">Paid days</th>
                            <th class="text-right">LOP</th>
                            <th class="text-right">Late</th>
                            <th class="text-right">Leave days</th>
                            <th class="text-right">Pending leave</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rows as $r)
                            @php
                                $t = $r['today'];
                                $tStatus = $t['status'] ?? null;
                                $tLabel = $tStatus ? ($statusMeta[$tStatus][0] ?? ucfirst(str_replace('_', ' ', $tStatus))) : '—';
                                // Today, before the day is over: punched in = in, not yet = not in yet
                                if ($t && in_array($tStatus, ['absent', 'half_day'], true)) {
                                    $tLabel = $t['first_in'] ? 'In since ' . substr($t['first_in'], 11, 5) : 'Not in yet';
                                    $tStatus = $t['first_in'] ? 'present' : 'absent';
                                }
                                $tColour = $statusMeta[$tStatus][1] ?? 'light';
                                $pending = (int) ($pendingLeaveByUser[$r['user']->id] ?? 0);
                            @endphp
                            <tr>
                                <td>
                                    <a href="{{ route('attendances.by_member') }}?user_id={{ $r['user']->id }}" class="d-flex align-items-center text-dark">
                                        <img src="{{ $r['user']->image_url }}" class="taskEmployeeImg rounded-circle mr-2" width="28" height="28" alt="">
                                        <span>
                                            <span class="d-block font-weight-bold">{{ $r['user']->name }}</span>
                                            <span class="f-11 emp-designation">{{ $r['user']->employeeDetail?->designation?->name }}</span>
                                        </span>
                                    </a>
                                </td>
                                @if ($isCurrent)
                                    <td><span class="badge badge-{{ $tColour }}">{{ $tLabel }}</span></td>
                                @endif
                                <td class="text-right">{{ $r['paid'] === null ? '—' : $fmt($r['paid']) . ' / ' . $r['counted'] }}</td>
                                <td class="text-right {{ ($r['lop'] ?? 0) > 0 ? 'text-danger font-weight-bold' : '' }}">{{ $r['lop'] === null ? '—' : $fmt($r['lop']) }}</td>
                                <td class="text-right {{ $r['late'] > 0 ? 'text-danger' : '' }}">{{ $r['late'] }}</td>
                                <td class="text-right">{{ $r['leave'] }}</td>
                                <td class="text-right">
                                    @if ($pending)
                                        <a href="{{ route('leaves.index') }}" class="badge badge-warning" title="Waiting for you or for HR">{{ $pending }} pending</a>
                                    @else
                                        <span class="text-lightest">—</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
