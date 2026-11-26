@extends('layouts.app')

{{-- Dashboard -> Executive Dashboard (ExecutiveDashboardController): Company view (CEO) and Finance view
     (CFO). Headline tiles, every module's counts as cards, then the graphs and lists for the view. --}}
@section('content')
@php
    $inr = fn ($v) => ((float) $v < 0 ? '-' : '') . '₹' . \App\Services\Payroll\SalaryRevisionLetter::inr(abs(round((float) $v)));
    // ₹ in lakh / crore for the big tiles
    $short = function ($v) use ($inr) {
        $v = (float) $v;
        $sign = $v < 0 ? '-' : '';
        if (abs($v) >= 10000000) { return $sign . '₹' . rtrim(rtrim(number_format(abs($v) / 10000000, 2), '0'), '.') . ' Cr'; }
        if (abs($v) >= 100000) { return $sign . '₹' . rtrim(rtrim(number_format(abs($v) / 100000, 2), '0'), '.') . ' L'; }
        return $inr($v);
    };
    $show = fn ($v) => is_array($v) && ($v[0] ?? null) === 'money' ? $short($v[1]) : $v;
    $pay = $payroll['last'];
    $inv = $invoices;
    $url = fn ($m) => route('executive-dashboard.index', ['view' => $m]);

    $tiles = $mode === 'cfo' ? [
        ['Invoiced · FY ' . $inv['fy'], $short($inv['invoiced']), $inv['count'] . ' invoices · GST ' . $short($inv['gst']), '#3d78e3', 'fa-file-invoice'],
        ['Collected', $short($inv['collected']), $inv['invoiced'] > 0 ? round($inv['collected'] / $inv['invoiced'] * 100) . '% of invoiced' : 'nothing invoiced yet', '#12b76a', 'fa-hand-holding-usd'],
        ['Outstanding', $short($inv['outstanding']), 'issued, not yet paid', '#f79009', 'fa-hourglass-half'],
        ['Overdue', $short($inv['overdue']), $inv['overdueCount'] . ' past the due date', '#f04438', 'fa-exclamation-triangle'],
        ['Payroll cost · ' . ($pay?->name ?? '—'), $pay ? $short($pay->ctc) : '—', $pay ? 'net pay ' . $short($pay->net) . ' · ' . $pay->slips . ' payslips' : 'no payroll run yet', '#7a5af8', 'fa-wallet'],
        ['Project profit', $projectMoney ? $short($projectMoney['profit']) : '—', $projectMoney && $projectMoney['margin'] !== null ? $projectMoney['margin'] . '% margin on ' . $short($projectMoney['revenue']) : 'open projects', ($projectMoney['profit'] ?? 0) < 0 ? '#f04438' : '#067647', 'fa-chart-line'],
    ] : [
        ['Employees', number_format($people['active']), '+' . $people['joined'] . ' joined · ' . $people['exited'] . ' left this month', '#3d78e3', 'fa-users'],
        ['Present today', $attendance['present'], $attendance['pct'] . '% · ' . $attendance['onLeave'] . ' on leave' . ($attendance['holiday'] ? ' · ' . $attendance['holiday'] : ''), '#12b76a', 'fa-user-check'],
        ['Active projects', $work['active'], $work['overdue'] . ' past deadline · ' . $work['dueSoon'] . ' due in 14 days', '#cb6b4f', 'fa-project-diagram'],
        ['Open positions', $hiring['positions'], $hiring['openings'] . ' openings · ' . $hiring['pipeline'] . ' candidates', '#ee46bc', 'fa-user-plus'],
        ['Invoiced · FY ' . $inv['fy'], $short($inv['invoiced']), $short($inv['outstanding']) . ' outstanding', '#067647', 'fa-file-invoice'],
        ['Payroll cost · ' . ($pay?->name ?? '—'), $pay ? $short($pay->ctc) : '—', $pay ? $pay->slips . ' payslips' : 'no payroll run yet', '#7a5af8', 'fa-wallet'],
    ];
@endphp
@include('project-tracker.partials.dash-styles')

<div class="content-wrapper ex" data-swap-links>

    {{-- Greeting, the view switch, and what is waiting on someone --}}
    @php
        $hour = (int) $asOf->format('G');
        $greeting = $hour < 12 ? 'Good morning' : ($hour < 17 ? 'Good afternoon' : 'Good evening');
    @endphp
    <div class="ex-hero">
        <div class="ex-hero-top">
            <div class="ex-hello">
                <h2>{{ $greeting }}, {{ \Illuminate\Support\Str::of(user()->name)->before(' ') ?: user()->name }}</h2>
                <p>{{ $mode === 'cfo' ? 'The money' : 'The company' }} at a glance · {{ $asOf->format('l, d F Y') }} · updated {{ $asOf->format('h:i a') }}</p>
            </div>
            <div class="ex-actions">
                <div class="ex-switch" role="tablist">
                    <a href="{{ $url('ceo') }}" class="{{ $mode === 'ceo' ? 'is-on' : '' }}"><i class="fa fa-building mr-1"></i>Company</a>
                    <a href="{{ $url('cfo') }}" class="{{ $mode === 'cfo' ? 'is-on' : '' }}"><i class="fa fa-rupee-sign mr-1"></i>Finance</a>
                </div>
                @if ($ownDashboard)
                    <a href="{{ $ownDashboard }}" class="btn btn-secondary rounded f-14 ex-mine"><i class="fa fa-user mr-1"></i>My dashboard</a>
                @endif
            </div>
        </div>
        <div class="ex-attn">
            <span class="ex-attn-title"><i class="fa fa-bell mr-1"></i>Needs attention</span>
            @forelse ($attention as $a)
                <{{ $a->url ? 'a href=' . e($a->url) : 'span' }} class="ex-chip is-{{ $a->tone }}"><b>{{ $a->count }}</b>{{ $a->label }}</{{ $a->url ? 'a' : 'span' }}>
            @empty
                <span class="ex-chip is-ok"><i class="fa fa-check-circle mr-1"></i>Nothing waiting: all clear</span>
            @endforelse
        </div>
    </div>

    {{-- The headline figures --}}
    <div class="ex-tiles">
        @foreach ($tiles as [$label, $value, $sub, $colour, $icon])
            <div class="ex-tile" style="--c: {{ $colour }}">
                <span class="ex-tile-ic"><i class="fa {{ $icon }}"></i></span>
                <div class="ex-tile-body">
                    <p title="{{ $label }}">{{ $label }}</p>
                    <h3>{{ $value }}</h3>
                    <span>{{ $sub }}</span>
                </div>
            </div>
        @endforeach
    </div>

    {{-- Team today, as on the private dashboard: their decisions, their team, the company's day --}}
    @php
        $td = $teamDay;
        $waitingCount = $td['leaves']->count() + $td['corrections']->count();
        $initials = fn ($name) => collect(preg_split('/\s+/', trim((string) $name)))->filter()->take(2)->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))->implode('');
        $when = fn ($days) => $days === 0 ? 'Today' : ($days === 1 ? 'Tomorrow' : 'In ' . $days . ' days');
    @endphp
    <div class="pd-head mt-1"><h5><i class="fa fa-user-friends mr-1"></i>Team today</h5><span class="text-dark-grey f-12">{{ $td['today']->format('l, d M') }}</span></div>
    <div class="row">
        <div class="col-xl-4 mb-3">
            <div class="pd-card h-100">
                <div class="pd-head"><h5><i class="fa fa-inbox mr-1"></i>Waiting for you</h5>
                    <span class="f-12 {{ $waitingCount ? 'text-warning' : 'text-dark-grey' }}">{{ $waitingCount ? $waitingCount . ' to decide' : 'nothing waiting' }}</span></div>
                @if (!$waitingCount)
                    <div class="pd-clear"><i class="fa fa-check-circle mr-1"></i>No leave or attendance corrections are waiting for you.</div>
                @else
                    @foreach ($td['leaves']->take(5) as $l)
                        <div class="ex-row">
                            <span class="ex-av">{{ $initials($l->user?->name) }}</span>
                            <div class="ex-row-main"><b>{{ $l->user?->name }}</b><small>{{ $l->type?->type_name ?: 'Leave' }} · {{ $l->leave_date->format('d M') }}</small></div>
                            <a href="{{ route('leaves.show', $l->unique_id ?: $l->id) }}" class="btn btn-secondary btn-sm rounded ex-review">Review</a>
                        </div>
                    @endforeach
                    @foreach ($td['corrections']->take(3) as $c)
                        <div class="ex-row">
                            <span class="ex-av">{{ $initials($c->user?->name) }}</span>
                            <div class="ex-row-main"><b>{{ $c->user?->name }}</b><small>Attendance correction · {{ \Carbon\Carbon::parse($c->date)->format('d M') }}</small></div>
                            <a href="{{ route('attendances.by_request') }}?waiting=me" class="btn btn-secondary btn-sm rounded ex-review">Review</a>
                        </div>
                    @endforeach
                    <div class="ex-links">
                        @if ($td['leaves']->isNotEmpty())<a href="{{ route('leaves.index') }}?waiting=me">All leave requests ({{ $td['leaves']->count() }}) →</a>@endif
                        @if ($td['corrections']->isNotEmpty())<a href="{{ route('attendances.by_request') }}?waiting=me">All corrections ({{ $td['corrections']->count() }}) →</a>@endif
                    </div>
                @endif
            </div>
        </div>
        <div class="col-xl-3 col-md-6 mb-3">
            <div class="pd-card h-100">
                @if ($td['team'])
                    <div class="pd-head"><h5><i class="fa fa-users mr-1"></i>My team</h5><span class="text-dark-grey f-12">{{ $td['team']['size'] }} report to you</span></div>
                    @php $tin = count($td['team']['in']); $tleave = count($td['team']['leave']); $tout = count($td['team']['out']); @endphp
                @else
                    <div class="pd-head"><h5><i class="fa fa-building mr-1"></i>Company today</h5><span class="text-dark-grey f-12">{{ $people['active'] }} people</span></div>
                    @php $tin = $attendance['present']; $tleave = $attendance['onLeave']; $tout = $attendance['absent']; @endphp
                @endif
                <div class="ex-today">
                    <div class="is-in"><b>{{ $tin }}</b>In</div>
                    <div class="is-leave"><b>{{ $tleave }}</b>On leave</div>
                    <div class="is-out"><b>{{ $tout }}</b>Not in</div>
                </div>
                @if ($td['team'] && $td['team']['out'])
                    <div class="ex-names" title="{{ implode(', ', $td['team']['out']) }}">Not in: {{ implode(', ', array_slice($td['team']['out'], 0, 6)) }}{{ count($td['team']['out']) > 6 ? '…' : '' }}</div>
                @endif
                <div class="ex-mini2"><span><i class="fa fa-home mr-1"></i>{{ $td['wfh']->count() }} working from home</span><span><i class="fa fa-clock mr-1"></i>{{ $attendance['late'] }} late</span></div>
                <a href="{{ $td['team'] && Route::has('my-team.attendance') ? route('my-team.attendance') : route('attendances.index') }}" class="ex-more">{{ $td['team'] ? 'Team attendance' : 'Attendance register' }} →</a>
            </div>
        </div>
        {{-- Office hours: who was in each hour slot today, by branch --}}
        <div class="col-xl-5 col-md-12 mb-3">
            <div class="pd-card h-100">
                <div class="pd-head"><h5><i class="fa fa-clock mr-1"></i>Office hours</h5><span class="text-dark-grey f-12">in office, by hour</span></div>
                <div class="table-responsive">
                    <table class="ex-hours">
                        <thead><tr><th></th>@foreach ($officeHours['slots'] as $slot)<th>{{ $slot['label'] }}</th>@endforeach</tr></thead>
                        <tbody>
                            <tr class="is-total"><td>All</td>@foreach ($officeHours['slots'] as $slot)<td>{{ $slot['started'] ? $slot['total'] : '–' }}</td>@endforeach</tr>
                            @foreach ($officeHours['branches'] as $b)
                                <tr><td title="{{ $b['size'] }} people">{{ $b['name'] }}</td>@foreach ($b['counts'] as $c)<td class="{{ $c === 0 ? 'is-zero' : '' }}">{{ $c === null ? '–' : $c }}</td>@endforeach</tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6 mb-3">
            <div class="pd-card h-100">
                <div class="pd-head"><h5><i class="fa fa-plane-departure mr-1"></i>On leave today</h5><span class="text-dark-grey f-12">{{ $td['onLeave']->count() }} {{ \Illuminate\Support\Str::plural('person', $td['onLeave']->count()) }}</span></div>
                @forelse ($td['onLeave']->take(6) as $l)
                    <div class="ex-row">
                        <span class="ex-av">{{ $initials($l->name) }}</span>
                        <div class="ex-row-main"><b>{{ $l->name }}</b><small>{{ $l->duration === 'half day' ? ($l->half_day_type === 'first_half' ? 'First half' : 'Second half') : 'Full day' }}</small></div>
                        <span class="ex-tag" style="--t: {{ $l->color ?: '#3d78e3' }}">{{ $l->type_name ?: 'Leave' }}</span>
                    </div>
                @empty
                    <div class="ex-empty-line"><i class="fa fa-check-circle mr-1"></i>Everyone is in today.</div>
                @endforelse
                @if ($td['wfh']->isNotEmpty())
                    <div class="ex-names mt-2" title="{{ $td['wfh']->implode(', ') }}"><i class="fa fa-home mr-1"></i>Working from home: {{ $td['wfh']->take(5)->implode(', ') }}{{ $td['wfh']->count() > 5 ? '…' : '' }}</div>
                @endif
            </div>
        </div>
        <div class="col-xl-3 col-md-6 mb-3">
            <div class="pd-card h-100">
                <div class="pd-head"><h5><i class="fa fa-birthday-cake mr-1"></i>Birthdays</h5><span class="text-dark-grey f-12">next 7 days</span></div>
                @forelse ($td['birthdays']->take(5) as $b)
                    <a href="{{ route('employees.show', $b->id) }}" class="ex-row">
                        <span class="ex-av is-cake">{{ $initials($b->name) }}</span>
                        <div class="ex-row-main"><b>{{ $b->name }}</b><small>{{ $b->designation ?: '—' }}</small></div>
                        <span class="ex-when {{ $b->days === 0 ? 'is-today' : '' }}">{{ $b->days === 0 ? '🎂 Today' : $when($b->days) }}<small>{{ $b->on->format('d M') }}</small></span>
                    </a>
                @empty
                    <div class="ex-empty-line">No birthdays this week.</div>
                @endforelse
            </div>
        </div>
        <div class="col-xl-3 col-md-6 mb-3">
            <div class="pd-card h-100">
                <div class="pd-head"><h5><i class="fa fa-award mr-1"></i>Work anniversaries</h5><span class="text-dark-grey f-12">next 7 days</span></div>
                @forelse ($td['anniversaries']->take(5) as $w)
                    <a href="{{ route('employees.show', $w->id) }}" class="ex-row">
                        <span class="ex-av is-award">{{ $initials($w->name) }}</span>
                        <div class="ex-row-main"><b>{{ $w->name }}</b><small>{{ $w->years }} {{ \Illuminate\Support\Str::plural('year', $w->years) }} · {{ $w->designation ?: '—' }}</small></div>
                        <span class="ex-when {{ $w->days === 0 ? 'is-today' : '' }}">{{ $when($w->days) }}<small>{{ $w->on->format('d M') }}</small></span>
                    </a>
                @empty
                    <div class="ex-empty-line">No work anniversaries this week.</div>
                @endforelse
            </div>
        </div>
        <div class="col-xl-3 col-md-6 mb-3">
            <div class="pd-card h-100">
                <div class="pd-head"><h5><i class="fa fa-user-plus mr-1"></i>Joining soon</h5><span class="text-dark-grey f-12">next 30 days</span></div>
                @forelse ($td['joinings'] as $j)
                    <a href="{{ route('employees.show', $j->id) }}" class="ex-row">
                        <span class="ex-av is-new">{{ $initials($j->name) }}</span>
                        <div class="ex-row-main"><b>{{ $j->name }}</b><small>{{ $j->designation ?: '—' }}</small></div>
                        @php $jd = \Carbon\Carbon::parse($j->joining_date); $jdays = (int) $td['today']->diffInDays($jd); @endphp
                        <span class="ex-when {{ $jdays === 0 ? 'is-today' : '' }}">{{ $when($jdays) }}<small>{{ $jd->format('d M') }}</small></span>
                    </a>
                @empty
                    <div class="ex-empty-line">Nobody is joining in the next 30 days.
                        @if ($hiring['offers'] || $hiring['pipeline'])<br><a href="{{ route('recruitment.dashboard') }}">{{ $hiring['pipeline'] }} candidates in recruitment →</a>@endif</div>
                @endforelse
            </div>
        </div>
    </div>

    @if ($mode === 'ceo')
        {{-- Company: people over the year, the Divisions, attendance, projects needing attention --}}
        <div class="row">
            <div class="col-xl-8 mb-3">
                <div class="pd-card h-100">
                    <div class="pd-head"><h5><i class="fa fa-users mr-1"></i>Joiners and leavers</h5>
                        <span class="text-dark-grey f-12">last 12 months · headcount {{ $people['trend'][0]['count'] }} → {{ end($people['trend'])['count'] }}</span></div>
                    @include('project-tracker.partials.chart', ['id' => 'ex-people', 'type' => 'bar', 'labels' => array_column($people['trend'], 'label'),
                        'datasets' => [['Joined', array_column($people['trend'], 'joined')], ['Left', array_column($people['trend'], 'left')]],
                        'colors' => ['#12b76a', '#f04438'], 'height' => 240])
                </div>
            </div>
            <div class="col-xl-4 mb-3">
                <div class="pd-card h-100">
                    <div class="pd-head"><h5><i class="fa fa-sitemap mr-1"></i>People by Division</h5><span class="text-dark-grey f-12">{{ $people['active'] }} people</span></div>
                    @php $top = $people['divisions']->take(8); $rest = $people['divisions']->slice(8)->sum('c'); $maxDiv = max(1, (int) $top->max('c')); @endphp
                    @foreach ($top as $i => $d)
                        <div class="ex-age ex-div">
                            <span title="{{ $d->name }}">{{ $d->name }}</span>
                            <div class="pd-bar"><span style="width: {{ $d->c / $maxDiv * 100 }}%; background: {{ ['#cb6b4f', '#3d78e3', '#12b76a', '#f79009', '#7a5af8', '#0ba5ec', '#ee46bc', '#98a2b3'][$i % 8] }}"></span></div>
                            <b>{{ $d->c }}</b>
                        </div>
                    @endforeach
                    @if ($rest > 0)<div class="ex-age ex-div"><span>Others</span><div class="pd-bar"><span style="width: {{ $rest / $maxDiv * 100 }}%; background: #d0d5dd"></span></div><b>{{ $rest }}</b></div>@endif
                </div>
            </div>
            <div class="col-xl-6 mb-3">
                <div class="pd-card h-100">
                    <div class="pd-head"><h5><i class="fa fa-user-check mr-1"></i>Attendance</h5><span class="text-dark-grey f-12">share of employees who clocked in · last 14 days</span></div>
                    @include('project-tracker.partials.chart', ['id' => 'ex-att', 'type' => 'line', 'labels' => array_column($attendance['trend'], 'label'),
                        'datasets' => [['Present', array_column($attendance['trend'], 'pct')]], 'colors' => ['#12b76a'], 'height' => 220, 'format' => '%'])
                </div>
            </div>
            <div class="col-xl-6 mb-3">
                <div class="pd-card h-100">
                    <div class="pd-head"><h5><i class="fa fa-exclamation-triangle mr-1"></i>Projects past their deadline</h5>
                        <span class="f-12 {{ $work['overdue'] ? 'text-danger' : 'text-dark-grey' }}">{{ $work['overdue'] }} {{ \Illuminate\Support\Str::plural('project', $work['overdue']) }}</span></div>
                    @forelse ($work['late'] as $p)
                        <a href="{{ route('projects.show', $p->id) }}" class="pd-task">
                            <span class="pd-task-name">{{ $p->project_name }}</span>
                            <span class="pd-task-sub">{{ (int) $p->completion_percent }}% done</span>
                            <span class="pd-task-due text-danger">{{ \Carbon\Carbon::parse($p->deadline)->diffForHumans(['parts' => 1]) }}</span>
                        </a>
                    @empty
                        <div class="pd-clear"><i class="fa fa-check-circle mr-1"></i>Every active project is within its deadline.
                            {{ $work['dueSoon'] ? $work['dueSoon'] . ' due in the next 14 days.' : '' }}</div>
                    @endforelse
                    <div class="ex-mini mt-3">
                        <div><b>{{ $hiring['interviews'] }}</b><span>interviews next 7 days</span></div>
                        <div><b>{{ $hiring['offers'] }}</b><span>offers waiting</span></div>
                        <div><b>{{ $attendance['pendingLeaves'] }}</b><span>leave requests waiting</span></div>
                        <div><b>{{ $people['leaving'] }}</b><span>serving notice</span></div>
                    </div>
                </div>
            </div>
        </div>
    @else
        {{-- Finance: invoicing through the year, payroll cost, receivables, clients, statutory dues, project profit --}}
        <div class="row">
            <div class="col-xl-6 mb-3">
                <div class="pd-card h-100">
                    <div class="pd-head"><h5><i class="fa fa-file-invoice mr-1"></i>Invoiced by month</h5><span class="text-dark-grey f-12">FY {{ $inv['fy'] }}</span></div>
                    @if (array_sum($inv['months']['values']) > 0)
                        @include('project-tracker.partials.chart', ['id' => 'ex-inv', 'type' => 'bar', 'labels' => $inv['months']['labels'],
                            'datasets' => [['Invoiced', $inv['months']['values']]], 'colors' => ['#3d78e3'], 'height' => 240, 'format' => 'money'])
                    @else
                        <div class="ex-empty"><div class="ex-empty-ic"><i class="fa fa-file-invoice"></i></div>No invoices issued this financial year yet.</div>
                    @endif
                </div>
            </div>
            <div class="col-xl-6 mb-3">
                <div class="pd-card h-100">
                    <div class="pd-head"><h5><i class="fa fa-wallet mr-1"></i>Payroll cost by month</h5><span class="text-dark-grey f-12">{{ count($payroll['periods']) > 1 ? 'last ' . count($payroll['periods']) . ' pay periods' : 'pay periods run so far' }}</span></div>
                    @if ($payroll['periods'])
                        @include('project-tracker.partials.chart', ['id' => 'ex-pay', 'type' => 'bar', 'labels' => array_map(fn ($r) => $r->label, $payroll['periods']),
                            'datasets' => [['Net pay', array_map(fn ($r) => $r->net, $payroll['periods'])], ['Deductions', array_map(fn ($r) => $r->deductions, $payroll['periods'])],
                                ['Employer PF / ESI', array_map(fn ($r) => $r->employer, $payroll['periods'])]],
                            'colors' => ['#7a5af8', '#f79009', '#0ba5ec'], 'stacked' => true, 'height' => 240, 'format' => 'money'])
                    @else
                        <div class="ex-empty"><div class="ex-empty-ic"><i class="fa fa-wallet"></i></div>No payroll has been run yet.</div>
                    @endif
                </div>
            </div>
            <div class="col-xl-4 mb-3">
                <div class="pd-card h-100">
                    <div class="pd-head"><h5><i class="fa fa-hourglass-half mr-1"></i>Receivables by age</h5><span class="text-dark-grey f-12">{{ $short($inv['outstanding']) }} owed</span></div>
                    @php $maxAge = max(1, max($inv['ageing'])); @endphp
                    @foreach ($inv['ageing'] as $age => $amount)
                        <div class="ex-age">
                            <span>{{ $age }}</span>
                            <div class="pd-bar"><span style="width: {{ $amount / $maxAge * 100 }}%; background: {{ $age === 'Not due' ? '#12b76a' : ($age === '1–30 days' ? '#f79009' : '#f04438') }}"></span></div>
                            <b>{{ $inr($amount) }}</b>
                        </div>
                    @endforeach
                </div>
            </div>
            <div class="col-xl-4 mb-3">
                <div class="pd-card h-100">
                    <div class="pd-head"><h5><i class="fa fa-handshake mr-1"></i>Top clients</h5><span class="text-dark-grey f-12">invoiced · FY {{ $inv['fy'] }}</span></div>
                    @forelse ($inv['clients'] as $c)
                        <div class="ex-age"><span>{{ $c->client_name }}</span><small>{{ $c->n }} {{ \Illuminate\Support\Str::plural('invoice', $c->n) }}</small><b>{{ $inr($c->total) }}</b></div>
                    @empty
                        <div class="ex-empty"><div class="ex-empty-ic"><i class="fa fa-handshake"></i></div>No invoices to clients this year yet.</div>
                    @endforelse
                </div>
            </div>
            <div class="col-xl-4 mb-3">
                <div class="pd-card h-100">
                    <div class="pd-head"><h5><i class="fa fa-landmark mr-1"></i>Statutory dues</h5><span class="text-dark-grey f-12">{{ $pay?->name ?? 'no payroll yet' }}</span></div>
                    @if ($pay)
                        @foreach ([['PF (employee)', $pay->pf], ['ESI (employee)', $pay->esi], ['Employer PF / ESI', $pay->employerShare], ['Professional Tax', $pay->pt], ['TDS', $pay->tds]] as [$what, $amount])
                            <div class="ex-age"><span>{{ $what }}</span><b>{{ $inr($amount) }}</b></div>
                        @endforeach
                        <div class="ex-age ex-total"><span>To pay over</span><b>{{ $inr($pay->statutory) }}</b></div>
                    @else
                        <div class="ex-empty"><div class="ex-empty-ic"><i class="fa fa-landmark"></i></div>Shown after the first payroll run.</div>
                    @endif
                </div>
            </div>
            @if ($projectMoney && $projectMoney['rows']->isNotEmpty())
                <div class="col-12 mb-3">
                    <div class="pd-card">
                        <div class="pd-head"><h5><i class="fa fa-chart-line mr-1"></i>Projects — revenue, cost and profit</h5><span class="text-dark-grey f-12">open projects, to date</span></div>
                        <div class="table-responsive">
                            <table class="pd-money">
                                <thead><tr><th>Project</th><th>Revenue</th><th>Cost</th><th>Profit</th><th>Margin</th></tr></thead>
                                <tbody>
                                    @foreach ($projectMoney['rows'] as $r)
                                        <tr>
                                            <td><a href="{{ route('projects.show', $r->id) }}?tab=profitability">{{ $r->name }}</a></td>
                                            <td>{{ $inr($r->revenue) }}</td><td>{{ $inr($r->cost) }}</td>
                                            <td class="{{ $r->profit < 0 ? 'text-danger' : 'pd-pos' }}">{{ $inr($r->profit) }}</td>
                                            <td>{{ $r->margin !== null ? $r->margin . '%' : '—' }}</td>
                                        </tr>
                                    @endforeach
                                    <tr class="ex-sum"><td>All open projects</td><td>{{ $inr($projectMoney['revenue']) }}</td><td>{{ $inr($projectMoney['cost']) }}</td>
                                        <td class="{{ $projectMoney['profit'] < 0 ? 'text-danger' : 'pd-pos' }}">{{ $inr($projectMoney['profit']) }}</td>
                                        <td>{{ $projectMoney['margin'] !== null ? $projectMoney['margin'] . '%' : '—' }}</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            @endif
        </div>
    @endif

    {{-- Every module at a glance --}}
    <div class="pd-head mt-2"><h5><i class="fa fa-th-large mr-1"></i>Modules</h5><span class="text-dark-grey f-12">click a card to open the module</span></div>
    <div class="ex-modules">
        @foreach ($modules as $m)
            @php
                $lead = $m->stats[0];
                $rest = array_slice($m->stats, 1);
                $tag = $m->url && !$m->soon ? 'a' : 'div';
            @endphp
            <{{ $tag === 'a' ? 'a href=' . e($m->url) : 'div' }} class="ex-mod {{ $m->soon ? 'is-soon' : '' }}" style="--c: {{ $m->colour }}">
                <div class="ex-mod-head">
                    <span class="ex-mod-ic"><i class="fa {{ $m->icon }}"></i></span>
                    <b>{{ $m->title }}</b>
                    @if ($m->soon)<span class="ex-soon">Soon</span>@endif
                </div>
                <div class="ex-mod-lead {{ isset($lead[2]) ? 'is-' . $lead[2] : '' }}">
                    <strong>{{ $show($lead[1]) }}</strong>
                    <span>{{ $lead[0] }}</span>
                </div>
                <div class="ex-mod-grid">
                    @foreach ($rest as $stat)
                        <div class="{{ isset($stat[2]) ? 'is-' . $stat[2] : '' }}" title="{{ $stat[0] }}">
                            <b>{{ $show($stat[1]) }}</b>
                            <span>{{ $stat[0] }}</span>
                        </div>
                    @endforeach
                </div>
                <div class="ex-mod-foot">{{ $m->soon ? 'Being built' : 'Open ' . $m->title }} @unless ($m->soon)<i class="fa fa-arrow-right"></i>@endunless</div>
            </{{ $tag }}>
        @endforeach
    </div>
</div>

<style>
    .ex-hero { position: relative; overflow: hidden; border-radius: 16px; padding: 22px 24px 18px; margin-bottom: 16px; color: #fff;
        background: linear-gradient(120deg, var(--epic-blue, #cb6b4f) 0%, color-mix(in srgb, var(--epic-blue, #cb6b4f) 70%, #1d2939) 100%);
        box-shadow: 0 6px 18px color-mix(in srgb, var(--epic-blue, #cb6b4f) 25%, transparent); }
    .ex-hero::after { content: ''; position: absolute; right: -60px; top: -80px; width: 260px; height: 260px; border-radius: 50%; background: rgba(255, 255, 255, .07); pointer-events: none; }
    .ex-hero-top { display: flex; justify-content: space-between; align-items: flex-start; gap: 16px; flex-wrap: wrap; position: relative; z-index: 1; }
    html body .ex-hero .ex-hello h2 { margin: 0; font-size: 22px; font-weight: 700; color: #fff !important; }
    html body .ex-hero .ex-hello p { margin: 4px 0 0; font-size: 13.5px; color: rgba(255, 255, 255, .85) !important; }
    .ex-actions { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
    .ex-hero .ex-switch { background: rgba(255, 255, 255, .16); box-shadow: none; }
    .ex-hero .ex-switch a { color: rgba(255, 255, 255, .9); }
    .ex-hero .ex-switch a:hover { color: #fff; }
    .ex-hero .ex-switch a.is-on { background: #fff; color: var(--epic-blue, #cb6b4f); }
    html body .ex-hero .ex-mine { background: rgba(255, 255, 255, .16) !important; border-color: transparent !important; color: #fff !important; height: 38px; display: inline-flex; align-items: center; }
    html body .ex-hero .ex-mine:hover { background: rgba(255, 255, 255, .26) !important; }
    .ex-attn { display: flex; align-items: center; flex-wrap: wrap; gap: 8px; margin-top: 18px; padding-top: 14px; border-top: 1px solid rgba(255, 255, 255, .18); position: relative; z-index: 1; }
    .ex-attn-title { font-size: 12px; font-weight: 600; letter-spacing: .05em; text-transform: uppercase; color: rgba(255, 255, 255, .8); margin-right: 4px; }
    .ex-chip { display: inline-flex; align-items: center; gap: 6px; padding: 5px 12px 5px 6px; border-radius: 999px; font-size: 13px; background: #fff; color: #344054; }
    .ex-chip b { min-width: 24px; height: 22px; padding: 0 7px; border-radius: 999px; display: inline-grid; place-items: center; font-size: 12px; color: #fff; background: #667085; }
    .ex-chip.is-bad b { background: #f04438; } .ex-chip.is-warn b { background: #f79009; } .ex-chip.is-info b { background: #3d78e3; }
    .ex-chip.is-ok { padding-left: 12px; color: #067647; }
    a.ex-chip:hover { text-decoration: none; color: #101828; box-shadow: 0 2px 8px rgba(16, 24, 40, .18); }
    .ex-tile { display: flex; align-items: flex-start; gap: 14px; height: 100%; background: #fff; border-radius: 14px; padding: 18px; box-shadow: 0 1px 3px rgba(16, 24, 40, .06);
        border-bottom: 3px solid color-mix(in srgb, var(--c) 55%, #fff); }
    .ex-tile-ic { flex: 0 0 44px; width: 44px; height: 44px; border-radius: 12px; display: grid; place-items: center; font-size: 18px;
        background: color-mix(in srgb, var(--c) 12%, #fff); color: var(--c); }
    .ex-tile-body { min-width: 0; }
    .ex-tile-body p { margin: 0 0 2px; font-size: 11.5px; font-weight: 600; letter-spacing: .05em; text-transform: uppercase; color: #667085; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .ex-tile-body h3 { margin: 0; font-size: 26px; font-weight: 700; color: #101828; line-height: 1.2; }
    .ex-tile-body span { font-size: 12.5px; color: #98a2b3; }
    /* Team today */
    .ex-row { display: flex; align-items: center; gap: 10px; padding: 8px 0; border-top: 1px solid #f2f4f7; color: inherit; }
    .pd-head + .ex-row { border-top: 0; padding-top: 0; }
    a.ex-row:hover { text-decoration: none; color: inherit; } a.ex-row:hover b { color: var(--epic-blue, #cb6b4f); }
    .ex-av { flex: 0 0 32px; width: 32px; height: 32px; border-radius: 50%; display: grid; place-items: center; font-size: 11.5px; font-weight: 600;
        background: #eef4ff; color: #3538cd; }
    .ex-av.is-cake { background: #fdf2fa; color: #c11574; } .ex-av.is-award { background: #fffaeb; color: #b54708; } .ex-av.is-new { background: #ecfdf3; color: #067647; }
    .ex-row-main { flex: 1; min-width: 0; display: flex; flex-direction: column; }
    .ex-row-main b { font-size: 13.5px; font-weight: 600; color: #101828; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .ex-row-main small { font-size: 12px; color: #667085; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    html body .ex-review { height: 28px; padding: 0 12px !important; display: inline-flex; align-items: center; font-size: 12px !important; }
    .ex-tag { font-size: 11.5px; font-weight: 600; padding: 2px 9px; border-radius: 999px; white-space: nowrap;
        color: var(--t); background: color-mix(in srgb, var(--t) 12%, #fff); }
    .ex-when { display: flex; flex-direction: column; align-items: flex-end; font-size: 12px; font-weight: 600; color: #475467; white-space: nowrap; }
    .ex-when small { font-weight: 400; color: #98a2b3; font-size: 11px; }
    .ex-when.is-today { color: var(--epic-blue, #cb6b4f); }
    .ex-links { display: flex; flex-wrap: wrap; gap: 4px 16px; padding-top: 10px; border-top: 1px solid #f2f4f7; font-size: 12.5px; }
    .ex-hours { width: 100%; border-collapse: collapse; font-size: 12.5px; }
    .ex-hours th { font-weight: 500; color: #667085; font-size: 11.5px; text-align: center; padding: 4px 6px 8px; white-space: nowrap; }
    .ex-hours td { text-align: center; padding: 7px 6px; border-top: 1px solid #f2f4f7; color: #101828; font-weight: 600; }
    .ex-hours td:first-child, .ex-hours th:first-child { text-align: left; font-weight: 500; color: #344054; white-space: nowrap; }
    .ex-hours tr.is-total td { background: #f9fafb; font-weight: 700; }
    .ex-hours td.is-zero { color: #d0d5dd; font-weight: 400; }
    .ex-today { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 8px; margin-bottom: 10px; }
    .ex-today div { border-radius: 10px; padding: 10px 4px; text-align: center; font-size: 11.5px; color: #475467; border: 1px solid #eaecf0; }
    .ex-today b { display: block; font-size: 22px; color: #101828; line-height: 1.2; }
    .ex-today .is-in { background: #ecfdf3; border-color: #abefc6; } .ex-today .is-leave { background: #eff8ff; border-color: #b2ddff; }
    .ex-today .is-out { background: #fef3f2; border-color: #fecdca; }
    .ex-names { font-size: 12.5px; color: #475467; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .ex-mini2 { display: flex; justify-content: space-between; gap: 8px; font-size: 12.5px; color: #667085; margin: 6px 0 10px; }
    .ex-more { font-size: 12.5px; font-weight: 600; }
    .ex-empty-line { padding: 14px 0 4px; font-size: 13px; color: #98a2b3; }
    .ex-switch { display: inline-flex; background: #fff; border-radius: 10px; padding: 4px; box-shadow: 0 1px 3px rgba(16, 24, 40, .06); }
    .ex-switch a { padding: 7px 16px; border-radius: 7px; font-size: 14px; color: #475467; white-space: nowrap; }
    .ex-switch a:hover { text-decoration: none; color: #101828; }
    .ex-switch a.is-on { background: var(--epic-blue, #cb6b4f); color: #fff; }
    .ex-asof { font-size: 12.5px; color: #98a2b3; }
    .ex-tiles { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 16px; margin-bottom: 16px; }
    @media (min-width: 1700px) { .ex-tiles { grid-template-columns: repeat(6, minmax(0, 1fr)); } }
    @media (max-width: 767px) { .ex-tiles { grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 10px; } }
    .ex .pd-tile h3 { font-size: 24px; }
    .ex-count { font-size: 12px; font-weight: 600; background: #f2f4f7; color: #475467 !important; border-radius: 10px; padding: 2px 10px; white-space: nowrap; }
    .ex-count.is-hot { background: #fef3f2; color: #b42318 !important; }
    .ex .pd-tile p { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .ex .pd-tile > div { min-width: 0; }
    .ex-mini { display: grid; grid-template-columns: repeat(4, 1fr); gap: 8px; border-top: 1px solid #f2f4f7; padding-top: 12px; }
    .ex-mini div { display: flex; flex-direction: column; }
    .ex-mini b { font-size: 18px; color: #101828; }
    .ex-mini span { font-size: 11.5px; color: #667085; }
    .ex-age { display: grid; grid-template-columns: 96px 1fr auto; align-items: center; gap: 10px; padding: 7px 0; font-size: 13px; border-bottom: 1px solid #f2f4f7; }
    .ex-age:last-child { border-bottom: 0; }
    .ex-age > span { color: #475467; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .ex-age > small { color: #98a2b3; font-size: 12px; }
    .ex-age > b { color: #101828; font-weight: 600; white-space: nowrap; text-align: right; }
    .ex-age .pd-bar { margin: 0; }
    .ex-age:not(:has(.pd-bar)):not(:has(small)) { grid-template-columns: 1fr auto; }
    .ex-total { border-top: 1px solid #eaecf0; margin-top: 4px; } .ex-total > span { font-weight: 600; color: #101828; }
    .ex-empty { padding: 34px 12px; text-align: center; color: #98a2b3; font-size: 13.5px; }
    .ex-empty-ic { font-size: 24px; color: #d0d5dd; margin-bottom: 8px; }
    .ex-div { grid-template-columns: 110px 1fr 40px; }
    .pd-money tr.ex-sum td { font-weight: 600; border-top: 1px solid #eaecf0; border-bottom: 0; }
    .ex-modules { display: grid; grid-template-columns: repeat(auto-fill, minmax(230px, 1fr)); gap: 14px; margin-bottom: 20px; }
    .ex-mod { position: relative; display: flex; flex-direction: column; background: #fff; border-radius: 14px; overflow: hidden; color: inherit;
        box-shadow: 0 1px 3px rgba(16, 24, 40, .06); border: 1px solid #eef0f3; transition: box-shadow .15s, transform .15s, border-color .15s; }
    a.ex-mod:hover { text-decoration: none; color: inherit; box-shadow: 0 10px 24px rgba(16, 24, 40, .10); transform: translateY(-2px);
        border-color: color-mix(in srgb, var(--c) 35%, #fff); }
    .ex-mod-head { display: flex; align-items: center; gap: 10px; padding: 14px 16px 12px;
        background: linear-gradient(180deg, color-mix(in srgb, var(--c) 9%, #fff), #fff); }
    .ex-mod-ic { flex: 0 0 36px; width: 36px; height: 36px; border-radius: 10px; display: grid; place-items: center; font-size: 15px; color: #fff;
        background: var(--c); box-shadow: 0 4px 10px color-mix(in srgb, var(--c) 30%, transparent); }
    .ex-mod-head b { flex: 1; font-size: 15px; font-weight: 600; color: #101828; }
    .ex-soon { font-size: 10px; font-weight: 700; letter-spacing: .05em; text-transform: uppercase; padding: 2px 8px; border-radius: 10px;
        color: var(--epic-blue, #cb6b4f); background: color-mix(in srgb, var(--epic-blue, #cb6b4f) 12%, #fff); }
    .ex-mod.is-soon { opacity: .75; }
    .ex-mod-lead { padding: 2px 16px 12px; display: flex; align-items: center; gap: 8px; min-width: 0; }
    .ex-mod-lead strong { font-size: 28px; font-weight: 700; color: #101828; line-height: 1.1; white-space: nowrap; }
    .ex-mod-lead span { font-size: 12.5px; color: #667085; line-height: 1.25; overflow: hidden; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; }
    .ex-mod-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 6px; padding: 0 12px 12px; margin-top: auto; }
    .ex-mod-grid > div { border-radius: 9px; background: #f9fafb; padding: 8px 6px; text-align: center; min-width: 0; }
    .ex-mod-grid b { display: block; font-size: 15px; font-weight: 700; color: #101828; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .ex-mod-grid span { display: block; font-size: 10.5px; color: #667085; line-height: 1.25; margin-top: 2px; overflow: hidden; text-overflow: ellipsis;
        display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; }
    .ex-mod-grid .is-good { background: #ecfdf3; } .ex-mod-grid .is-good b { color: #067647; }
    .ex-mod-grid .is-warn { background: #fffaeb; } .ex-mod-grid .is-warn b { color: #b54708; }
    .ex-mod-grid .is-bad { background: #fef3f2; } .ex-mod-grid .is-bad b { color: #b42318; }
    .ex-mod-lead.is-warn strong { color: #b54708; } .ex-mod-lead.is-bad strong { color: #b42318; } .ex-mod-lead.is-good strong { color: #067647; }
    .ex-mod-foot { display: flex; justify-content: space-between; align-items: center; padding: 10px 16px; border-top: 1px solid #f2f4f7;
        font-size: 12.5px; font-weight: 600; color: #98a2b3; transition: color .15s; }
    a.ex-mod:hover .ex-mod-foot { color: var(--c); }
    @media (max-width: 767px) { .ex-mini { grid-template-columns: repeat(2, 1fr); } }
</style>
@endsection
