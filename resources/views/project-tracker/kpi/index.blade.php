@extends('layouts.app')

{{-- Work -> Employee KPIs (TrackerKpiController::index): the people who worked on projects in a month,
     best score first; a name opens their KPIs in the side panel. --}}
@section('content')
@include('recruitment.partials.styles')
@php
    $num = fn ($v) => rtrim(rtrim(number_format((float) $v, 1), '0'), '.');
    $metricKeys = array_keys(\App\Services\Tracker\EmployeeKpi::METRICS);
@endphp
<div class="content-wrapper" data-swap-links>

    <form method="GET" action="{{ route('project-tracker.kpis') }}" id="rc-filter-form" class="bg-white rounded b-shadow-4 p-3 mb-3">
        <div class="row align-items-end">
            <div class="col-md-3 mb-2 rc-col-wide">
                <label class="f-13 text-dark-grey mb-1">Month</label>
                <select name="month" class="form-control select-picker" data-size="8">
                    @foreach ($months as $m)<option value="{{ $m->format('Y-m') }}" @selected($m->format('Y-m') === $month->format('Y-m'))>{{ $m->format('F Y') }}</option>@endforeach
                </select>
            </div>
            <div class="col-md-3 mb-2">
                <label class="f-13 text-dark-grey mb-1">Division</label>
                <select name="division" class="form-control select-picker" data-live-search="true" data-size="8">
                    <option value="">All</option>
                    @include('departments.partials.tree-options', ['departments' => $divisions, 'selected' => request('division')])
                </select>
            </div>
            <div class="col-md-3 mb-2">
                <label class="f-13 text-dark-grey mb-1">Designation</label>
                <select name="designation" class="form-control select-picker" data-live-search="true" data-size="8">
                    <option value="">All</option>
                    @foreach ($designations as $d)<option value="{{ $d->id }}" @selected(request('designation') == $d->id)>{{ $d->name }}</option>@endforeach
                </select>
            </div>
            <div class="col-md-3 mb-2">
                <label class="f-13 text-dark-grey mb-1">Search</label>
                <input type="text" name="search" value="{{ request('search') }}" class="form-control f-14" placeholder="Name or employee ID">
            </div>
        </div>
    </form>

    {{-- The month at a glance --}}
    <div class="row">
        <div class="col-xl-7 mb-3">
            <div class="kx-tiles h-100">
                <div class="kx-tile"><span>People</span><b>{{ $summary['people'] }}</b><small>worked on projects in {{ $month->format('M Y') }}</small></div>
                <div class="kx-tile"><span>Average score</span><b style="color: {{ \App\Services\Tracker\EmployeeKpi::band($summary['avg'])[1] }}">{{ $summary['avg'] ?? '—' }}</b><small>{{ $summary['scored'] }} with enough data to score</small></div>
                <div class="kx-tile"><span>Utilisation</span><b>{{ $summary['utilisation'] !== null ? $summary['utilisation'] . '%' : '—' }}</b><small>hours logged of hours available</small></div>
                <div class="kx-tile"><span>Hours logged</span><b>{{ $num($summary['hours']) }}</b><small>{{ $summary['rework'] }} {{ \Illuminate\Support\Str::plural('item', $summary['rework']) }} sent back for rework</small></div>
            </div>
        </div>
        <div class="col-xl-5 mb-3">
            <div class="pd-card h-100">
                <div class="pd-head"><h5><i class="fa fa-chart-bar mr-1"></i>How the scores spread</h5><span class="text-dark-grey f-12">people by score</span></div>
                @include('project-tracker.partials.chart', ['id' => 'kx-bands', 'type' => 'bar', 'labels' => array_keys($bands),
                    'datasets' => [['People', array_values($bands)]], 'colors' => ['#3d78e3'], 'height' => 170])
            </div>
        </div>
    </div>

    <div class="rc-list-card">
        <div class="table-responsive">
            <table class="table kx-table mb-0">
                <thead>
                    <tr>
                        <th class="kx-rank">#</th>
                        <th>Employee</th>
                        <th class="text-center">Score</th>
                        @foreach (\App\Services\Tracker\EmployeeKpi::METRICS as [$label, $hint])<th class="text-center" title="{{ $hint }}">{{ $label }}</th>@endforeach
                        <th class="text-right">Hours</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($people as $i => $person)
                        @php $k = $person->kpi; $score = $k['score'] ?? null; [$bandLabel, $bandColour] = \App\Services\Tracker\EmployeeKpi::band($score); @endphp
                        <tr>
                            <td class="kx-rank">{{ $score !== null ? $rankFrom + $i + 1 : '' }}</td>
                            <td>
                                <a href="{{ route('project-tracker.kpis.show', [$person->id, 'month' => $month->format('Y-m')]) }}" class="kx-person openRightModal">
                                    <img src="{{ $person->image_url }}" alt="">
                                    <span><b>{{ $person->name }}</b><small>{{ $person->designation ?? '—' }}{{ $person->division ? ' · ' . $person->division : '' }}</small></span>
                                </a>
                            </td>
                            <td class="text-center"><span class="kx-score" style="--c: {{ $bandColour }}" title="{{ $bandLabel }}">{{ $score ?? '—' }}</span></td>
                            @foreach ($metricKeys as $key)
                                @php $v = $k['metrics'][$key] ?? null; @endphp
                                <td class="text-center">
                                    @if ($v === null)
                                        <span class="text-lightest">—</span>
                                    @else
                                        <span class="kx-metric"><b>{{ $v }}%</b><i><em style="width: {{ min(100, $v) }}%; background: {{ $key === 'utilisation' ? ($v > 110 ? '#f04438' : ($v >= 85 ? '#12b76a' : ($v >= 60 ? '#3d78e3' : '#f79009'))) : ($v >= 85 ? '#12b76a' : ($v >= 60 ? '#3d78e3' : '#f04438')) }}"></em></i></span>
                                    @endif
                                </td>
                            @endforeach
                            <td class="text-right">{{ $num($k['hours'] ?? 0) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="{{ count($metricKeys) + 4 }}" class="p-4"><x-cards.no-record icon="chart-bar" message="No project work recorded for this month." /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @include('recruitment.partials.list-footer', ['paginator' => $people, 'sizes' => [25, 50, 100]])
    </div>
</div>

@include('project-tracker.partials.dash-styles')
<style>
    .kx-tiles { display: grid; grid-template-columns: repeat(2, 1fr); gap: 12px; }
    .kx-tile { background: #fff; border-radius: 12px; box-shadow: 0 1px 3px rgba(16, 24, 40, .06); padding: 16px 18px; display: flex; flex-direction: column; }
    .kx-tile span { font-size: 11.5px; font-weight: 600; text-transform: uppercase; letter-spacing: .05em; color: #667085; }
    .kx-tile b { font-size: 26px; color: #101828; margin: 4px 0 2px; }
    .kx-tile small { font-size: 12px; color: #98a2b3; }
    .kx-table th { font-size: 11.5px; font-weight: 600; text-transform: uppercase; letter-spacing: .04em; color: #667085; background: #f9fafb; border-top: 0 !important; white-space: nowrap; padding: 12px 14px !important; }
    .kx-table td { vertical-align: middle !important; padding: 10px 14px !important; font-size: 13.5px; border-top: 1px solid #f2f4f7 !important; }
    .kx-rank { width: 44px; color: #98a2b3; font-weight: 600; }
    .kx-person { display: flex; align-items: center; gap: 10px; color: inherit; min-width: 200px; }
    .kx-person:hover { text-decoration: none; } .kx-person:hover b { color: var(--epic-blue, #cb6b4f); }
    .kx-person img { width: 34px; height: 34px; border-radius: 50%; object-fit: cover; flex: none; }
    .kx-person span { display: flex; flex-direction: column; min-width: 0; }
    .kx-person b { font-weight: 600; color: #101828; } .kx-person small { color: #667085; font-size: 12px; }
    .kx-score { --c: #98a2b3; display: inline-grid; place-items: center; min-width: 40px; height: 28px; padding: 0 8px; border-radius: 14px; font-weight: 700;
        color: var(--c); background: color-mix(in srgb, var(--c) 13%, #fff); }
    .kx-metric { display: inline-flex; flex-direction: column; align-items: center; gap: 4px; min-width: 64px; }
    .kx-metric b { font-weight: 600; font-size: 13px; color: #344054; }
    .kx-metric i { display: block; width: 56px; height: 4px; border-radius: 2px; background: #f2f4f7; overflow: hidden; }
    .kx-metric em { display: block; height: 100%; border-radius: 2px; }
    @media (max-width: 575px) { .kx-tiles { grid-template-columns: 1fr 1fr; } .kx-tile b { font-size: 21px; } }
</style>
@include('recruitment.partials.standard-bar', ['form' => '#rc-filter-form', 'searchLabel' => 'Search people', 'active' => request()->anyFilled(['month', 'division', 'designation', 'search'])])
@endsection
