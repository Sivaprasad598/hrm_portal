@extends('layouts.app')

@section('content')
@php
    $fmt = company()->date_format;
    $states = [
        'overdue' => ['Overdue', 'red'], 'grace' => ['In grace', 'amber'], 'risk' => ['At risk', 'amber'],
        'ok' => ['On schedule', 'blue'], 'none' => ['No deadline', 'grey'], 'done' => ['Done', 'green'],
    ];
    $num = fn ($v) => rtrim(rtrim(number_format((float) $v, 1), '0'), '.');
@endphp
<div class="content-wrapper pd" data-swap-links>

    <div class="d-flex justify-content-end mb-3" style="gap: 8px">
        <a href="{{ route('project-tracker.planner') }}" class="btn btn-secondary rounded f-14"><i class="fa fa-th mr-1"></i>Resource Planner</a>
        @if (\App\Services\Tracker\TrackerAccess::manage())
            <a href="{{ route('projects.create') }}" class="btn btn-primary rounded f-14 openRightModal"><i class="fa fa-plus mr-1"></i>Add Project</a>
        @endif
    </div>

    {{-- The numbers --}}
    <div class="row">
        @foreach ([
            ['Active projects', $projects->count(), ($stateCounts['ok'] ?? 0) . ' on schedule', '#3d78e3', 'fa-project-diagram'],
            ['Need attention', ($stateCounts['risk'] ?? 0) + ($stateCounts['grace'] ?? 0) + ($stateCounts['overdue'] ?? 0), ($stateCounts['overdue'] ?? 0) . ' overdue · ' . ($stateCounts['grace'] ?? 0) . ' in grace', '#f04438', 'fa-exclamation-triangle'],
            ['Done this week', $doneThisWeek, 'work items and tasks', '#12b76a', 'fa-check-circle'],
            ['Hours this week', $num($hoursThisWeek), 'logged on the projects', '#f79009', 'fa-clock'],
            ['Open blockers', $openBlockers, 'holding work up', '#7a5af8', 'fa-ban'],
        ] as [$label, $value, $sub, $colour, $icon])
            <div class="col-xl col-md-4 col-sm-6 mb-3">
                <div class="pd-tile" style="--c: {{ $colour }}">
                    <div><p>{{ $label }}</p><h3>{{ $value }}</h3><span>{{ $sub }}</span></div>
                    <span class="pd-ic"><i class="fa {{ $icon }}"></i></span>
                </div>
            </div>
        @endforeach
    </div>

    {{-- Graphs: where the hours go, how the projects stand, the hours ahead --}}
    <div class="row">
        <div class="col-xl-8 mb-3">
            <div class="pd-card h-100">
                <div class="pd-head"><h5><i class="fa fa-chart-bar mr-1"></i>Hours logged a week, by Division</h5><span class="text-dark-grey f-12">last 12 weeks</span></div>
                @if (collect($hoursChart['datasets'])->flatMap(fn ($d) => $d[1])->sum() > 0)
                    @include('project-tracker.partials.chart', ['id' => 'pd-hours', 'type' => 'bar', 'labels' => $hoursChart['labels'], 'datasets' => $hoursChart['datasets'],
                        'colors' => $hoursChart['colors'], 'stacked' => true, 'height' => 250, 'format' => 'hours'])
                @else
                    <p class="f-13 text-dark-grey mb-0">No hours logged on projects yet.</p>
                @endif
            </div>
        </div>
        <div class="col-xl-4 mb-3">
            <div class="pd-card h-100">
                <div class="pd-head"><h5><i class="fa fa-chart-pie mr-1"></i>Projects by status</h5></div>
                @php
                    $donut = collect(['ok' => '#3d78e3', 'risk' => '#f79009', 'grace' => '#fdb022', 'overdue' => '#f04438', 'none' => '#98a2b3', 'done' => '#12b76a'])
                        ->filter(fn ($c, $s) => ($stateCounts[$s] ?? 0) > 0);
                @endphp
                @if ($donut->isNotEmpty())
                    @include('project-tracker.partials.chart', ['id' => 'pd-status', 'type' => 'donut', 'labels' => $donut->keys()->map(fn ($s) => $states[$s][0])->all(),
                        'datasets' => [['Projects', $donut->keys()->map(fn ($s) => $stateCounts[$s])->all()]], 'colors' => $donut->values()->all(), 'height' => 250])
                @endif
            </div>
        </div>
        <div class="col-12 mb-3">
            <div class="pd-card">
                <div class="pd-head"><h5><i class="fa fa-user-clock mr-1"></i>Team hours ahead</h5><span class="text-dark-grey f-12">everyone on an active project · next 13 weeks</span></div>
                @include('project-tracker.partials.chart', ['id' => 'pd-capacity', 'type' => 'axis-mixed', 'labels' => $capacityChart['labels'], 'datasets' => $capacityChart['datasets'],
                    'colors' => ['#12b76a', '#cb6b4f'], 'height' => 230, 'format' => 'hours'])
            </div>
        </div>
    </div>

    {{-- Money: Super Admin, Admin, CEO, CFO --}}
    @if ($canSeeMoney && $money->isNotEmpty())
        <div class="pd-card mb-3">
            <div class="pd-head"><h5><i class="fa fa-rupee-sign mr-1"></i>Projects — revenue, cost and profit</h5>
                <span class="text-dark-grey f-12">{{ $cutoff ? 'Staff cost from payslips up to ' . $cutoff->format('M Y') . ', estimated from monthly salary after' : 'No payslips yet: staff cost estimated from monthly salary' }}</span></div>
            <div class="row">
                <div class="col-12 mb-3">
                    @include('project-tracker.partials.chart', ['id' => 'pd-money', 'type' => 'axis-mixed', 'labels' => $moneyChart['labels'], 'datasets' => $moneyChart['datasets'],
                        'colors' => ['#12b76a', '#f04438', '#3d78e3'], 'height' => 240, 'format' => 'money'])
                </div>
                <div class="col-12">
                    <div class="table-responsive">
                        <table class="pd-money">
                            <thead><tr><th>Project</th><th>Budget</th><th>Revenue</th><th>Cost</th><th>Profit</th><th>At completion</th></tr></thead>
                            <tbody>
                                @foreach ($money as $m)
                                    @php $t = $m['f']['totals']; $fc = $m['f']['forecast']; @endphp
                                    <tr>
                                        <td><a href="{{ route('projects.show', $m['project']->id) }}?tab=profitability">{{ $m['project']->project_name }}</a></td>
                                        <td>₹{{ number_format($m['f']['budget']) }}</td>
                                        <td>₹{{ number_format($t['revenue']) }}</td>
                                        <td>₹{{ number_format($t['cost']) }}</td>
                                        <td class="{{ $t['profit'] < 0 ? 'text-danger' : 'pd-pos' }}">{{ ($t['profit'] < 0 ? '−' : '') . '₹' . number_format(abs($t['profit'])) }}{{ $t['margin'] !== null ? ' · ' . $t['margin'] . '%' : '' }}</td>
                                        <td class="{{ ($fc['final_margin'] ?? 0) < 0 ? 'text-danger' : '' }}">{{ $fc['final_margin'] !== null ? $fc['final_margin'] . '% margin' : '—' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    @endif

    <div class="row">
        <div class="col-xl-8">
            {{-- Project health: the ones needing attention first --}}
            <div class="pd-card mb-3">
                <div class="pd-head"><h5><i class="fa fa-heartbeat mr-1"></i>Project health</h5><a href="{{ route('projects.index') }}" class="f-13">All projects</a></div>
                @forelse ($projects as $r)
                    @php $p = $r->project; $type = $p->projectType; $st = $states[$r->state]; @endphp
                    <a href="{{ route('projects.show', $p->id) }}?tab=tracker" class="pd-proj" style="--tc: {{ $type?->color ?? '#98a2b3' }}">
                        <span class="pd-ring" style="--p: {{ $r->pct }}"><b>{{ $r->pct }}%</b></span>
                        <span class="pd-proj-main">
                            <span class="pd-proj-name">{{ $p->project_name }}</span>
                            <span class="pd-proj-sub">{{ $type?->name ?? 'Tracker not set up' }} · {{ $r->tracker['totals']['done'] }}/{{ $r->tracker['totals']['items'] }} done
                                @if ($r->tracker['totals']['overdue']) · <b class="text-danger">{{ $r->tracker['totals']['overdue'] }} overdue</b>@endif
                                @if ($r->tracker['totals']['blocked']) · <b class="pd-warn">{{ $r->tracker['totals']['blocked'] }} blocked</b>@endif</span>
                        </span>
                        <span class="pd-proj-date">
                            @if ($p->deadline)
                                <b>{{ $p->deadline->format($fmt) }}</b>
                                <small>{{ $r->daysLeft >= 0 ? $r->daysLeft . ' days left' : abs($r->daysLeft) . ' days past' }}{{ $r->grace ? ' · +' . $r->grace . ' grace' : '' }}</small>
                            @else
                                <small>no deadline</small>
                            @endif
                        </span>
                        <span class="pd-state pd-state--{{ $st[1] }}">{{ $st[0] }}</span>
                    </a>
                @empty
                    <x-cards.no-record icon="project-diagram" message="No active projects." />
                @endforelse
            </div>

            {{-- Work due --}}
            <div class="row">
                <div class="col-md-6 mb-3">
                    <div class="pd-card h-100">
                        <div class="pd-head"><h5><i class="fa fa-exclamation-circle mr-1"></i>Overdue</h5><span class="pd-count {{ $late->count() ? 'is-hot' : '' }}">{{ $late->count() }}</span></div>
                        @forelse ($late as $task)
                            <a href="{{ route('tasks.show', $task->id) }}" class="pd-task openRightModal">
                                <span class="pd-task-name">{{ $task->heading }}</span>
                                <span class="pd-task-sub">{{ $task->project?->project_name }}</span>
                                <span class="pd-task-due text-danger">{{ $task->due_date->format($fmt) }}</span>
                            </a>
                        @empty
                            <div class="pd-clear"><i class="fa fa-check-circle mr-1"></i>Nothing overdue.</div>
                        @endforelse
                    </div>
                </div>
                <div class="col-md-6 mb-3">
                    <div class="pd-card h-100">
                        <div class="pd-head"><h5><i class="fa fa-calendar-alt mr-1"></i>Due in 7 days</h5><span class="pd-count">{{ $dueSoon->count() }}</span></div>
                        @forelse ($dueSoon as $task)
                            <a href="{{ route('tasks.show', $task->id) }}" class="pd-task openRightModal">
                                <span class="pd-task-name">{{ $task->heading }}</span>
                                <span class="pd-task-sub">{{ $task->project?->project_name }}</span>
                                <span class="pd-task-due">{{ $task->due_date->isToday() ? 'Today' : $task->due_date->format($fmt) }}</span>
                            </a>
                        @empty
                            <p class="f-13 text-dark-grey mb-0">Nothing due this week.</p>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-4">
            {{-- By Division --}}
            <div class="pd-card mb-3">
                <div class="pd-head"><h5><i class="fa fa-sitemap mr-1"></i>By Division</h5></div>
                @foreach ($byDivision as $d)
                    <div class="pd-div">
                        <div class="d-flex justify-content-between"><span><span class="pd-dot" style="background: {{ $d->color }}"></span>{{ $d->name }}</span>
                            <span class="text-dark-grey">{{ $d->count }} project{{ $d->count == 1 ? '' : 's' }}{{ $d->late ? ' · ' . $d->late . ' late' : '' }}</span></div>
                        <div class="pd-bar"><span style="width: {{ $d->pct }}%; background: {{ $d->color }}"></span></div>
                        <small class="text-dark-grey">{{ $d->pct }}% done on average</small>
                    </div>
                @endforeach
            </div>

            {{-- Team load: this week and next --}}
            <div class="pd-card mb-3">
                <div class="pd-head"><h5><i class="fa fa-users mr-1"></i>Team load</h5><a href="{{ route('project-tracker.planner') }}" class="f-13">Planner</a></div>
                <div class="pd-load-head"><span></span><span>This week</span><span>Next week</span></div>
                @forelse ($load->take(10) as $row)
                    <a href="{{ route('project-tracker.person', $row->user->id) }}" class="pd-load openRightModal">
                        <span class="pd-person"><img src="{{ $row->user->image_url }}" alt="">{{ $row->user->name }}</span>
                        @foreach (array_slice($row->weeks, 0, 2) as $w)
                            <span class="pd-cell pd-cell--{{ $w['load'] > 100 ? 'over' : ($w['load'] >= 80 ? 'full' : ($w['load'] >= 40 ? 'mid' : 'low')) }}" title="{{ $w['booked'] }} of {{ $w['capacity'] }} h booked">{{ $w['load'] > 999 ? '—' : $w['load'] . '%' }}</span>
                        @endforeach
                    </a>
                @empty
                    <p class="f-13 text-dark-grey mb-0">No one is on an active project yet.</p>
                @endforelse
            </div>
        </div>
    </div>
</div>

@include('project-tracker.partials.dash-styles')
@endsection
