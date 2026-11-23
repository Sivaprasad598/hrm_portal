@extends('layouts.app')

@section('content')
@include('recruitment.partials.styles')
@php
    $num = fn ($v) => rtrim(rtrim(number_format((float) $v, 1), '0'), '.');
    $tone = fn ($p) => $p['capacity'] <= 0 ? ($p['booked'] > 0 ? 'over' : 'off') : ($p['load'] > 100 ? 'over' : ($p['load'] >= 80 ? 'full' : ($p['load'] >= 40 ? 'mid' : 'low')));
    $thisStart = $unit === 'month' ? now()->startOfMonth()->toDateString() : ($unit === 'day' ? now()->toDateString() : now()->startOfWeek(\Carbon\Carbon::MONDAY)->toDateString());
    $plannerUrl = fn (array $with) => route('project-tracker.planner', array_merge(request()->except('page'), $with));
@endphp
<div class="content-wrapper" data-swap-links>

    <form method="GET" action="{{ route('project-tracker.planner') }}" id="rc-filter-form" class="bg-white rounded b-shadow-4 p-3 mb-3">
        <input type="hidden" name="unit" value="{{ $unit }}">
        <input type="hidden" name="scope" value="{{ request('scope', 'projects') }}">
        <div class="row align-items-end">
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
                <label class="f-13 text-dark-grey mb-1">Project</label>
                <select name="project" class="form-control select-picker" data-live-search="true" data-size="8">
                    <option value="">Any</option>
                    @foreach ($projectsList as $p)<option value="{{ $p->id }}" @selected(request('project') == $p->id)>{{ $p->project_name }}</option>@endforeach
                </select>
            </div>
            <div class="col-md-3 mb-2">
                <label class="f-13 text-dark-grey mb-1">Search</label>
                <input type="text" name="search" value="{{ request('search') }}" class="form-control f-14" placeholder="Name or employee ID">
            </div>
        </div>
    </form>

    {{-- Week / month, who, and what the colours mean --}}
    <div class="rp-bar">
        <div class="rp-seg" data-rc-bar>
            <a href="{{ $plannerUrl(['unit' => 'day', 'from' => null]) }}" class="{{ $unit === 'day' ? 'active' : '' }}">Days</a>
            <a href="{{ $plannerUrl(['unit' => 'week', 'from' => null]) }}" class="{{ $unit === 'week' ? 'active' : '' }}">Weeks</a>
            <a href="{{ $plannerUrl(['unit' => 'month', 'from' => null]) }}" class="{{ $unit === 'month' ? 'active' : '' }}">Months</a>
        </div>
        @if ($dayRange)
            <div class="rp-seg rp-nav">
                <a href="{{ $dayRange['prev'] ? $plannerUrl(['from' => $dayRange['prev']]) : 'javascript:;' }}" class="{{ $dayRange['prev'] ? '' : 'is-off' }}" title="Earlier two weeks"><i class="fa fa-chevron-left"></i></a>
                <span>{{ $dayRange['from']->format('d M') }} – {{ $dayRange['from']->copy()->addDays(13)->format('d M') }}</span>
                <a href="{{ $dayRange['next'] ? $plannerUrl(['from' => $dayRange['next']]) : 'javascript:;' }}" class="{{ $dayRange['next'] ? '' : 'is-off' }}" title="Next two weeks"><i class="fa fa-chevron-right"></i></a>
            </div>
        @endif
        <div class="rp-seg">
            <a href="{{ route('project-tracker.planner', array_merge(request()->except('page'), ['scope' => 'projects'])) }}" class="{{ request('scope', 'projects') !== 'all' ? 'active' : '' }}" title="People on an active project">On projects</a>
            <a href="{{ route('project-tracker.planner', array_merge(request()->except('page'), ['scope' => 'all'])) }}" class="{{ request('scope') === 'all' ? 'active' : '' }}">Everyone</a>
        </div>
        <div class="rp-legend">
            <span><i class="pd-cell--low"></i>under 40%</span><span><i class="pd-cell--mid"></i>40–80%</span><span><i class="pd-cell--full"></i>80–100%</span><span><i class="pd-cell--over"></i>over-booked</span><span><i class="pd-cell--off"></i>off</span>
            <span class="rp-note">Cells show free hours · {{ $num($hoursPerDay) }} h a working day</span>
        </div>
    </div>

    <div class="rc-list-card">
        <div class="rp-scroll">
            <table class="rp-grid">
                <thead>
                    <tr>
                        <th class="rp-name">Employee</th>
                        @foreach ($columns as $c)
                            @if ($unit === 'day')
                                @php $d = \Carbon\Carbon::parse($c['start']); @endphp
                                <th class="{{ $c['start'] === $thisStart ? 'is-now' : '' }} {{ $d->isMonday() && !$loop->first ? 'rp-wk' : '' }}">{{ $d->format('D') }}<b>{{ $d->format('d M') }}</b></th>
                            @elseif ($unit === 'week')
                                <th class="{{ $c['start'] === $thisStart ? 'is-now' : '' }}"><a href="{{ $plannerUrl(['unit' => 'day', 'from' => $c['start']]) }}" class="rp-drill" title="See this week day by day">Week of<b>{{ $c['label'] }}</b></a></th>
                            @else
                                <th class="{{ $c['start'] === $thisStart ? 'is-now' : '' }}"><b>{{ $c['label'] }}</b></th>
                            @endif
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @forelse ($people as $person)
                        @php $row = $grid[$person->id] ?? []; @endphp
                        <tr>
                            <td class="rp-name">
                                <a href="{{ route('project-tracker.person', $person->id) }}" class="rp-person openRightModal">
                                    <img src="{{ $person->image_url }}" alt="">
                                    <span><b>{{ $person->name }}</b><small>{{ $person->designation ?? '—' }}{{ $person->division ? ' · ' . $person->division : '' }}</small></span>
                                </a>
                            </td>
                            @foreach ($columns as $c)
                                @php $p = $row[$c['start']] ?? ['capacity' => 0, 'booked' => 0, 'free' => 0, 'load' => 0, 'byProject' => []]; @endphp
                                <td class="{{ $unit === 'day' && \Carbon\Carbon::parse($c['start'])->isMonday() && !$loop->first ? 'rp-wk' : '' }}">
                                    <span class="rp-cell pd-cell--{{ $tone($p) }}" data-tip="{{ collect($p['byProject'])->map(fn ($h, $pid) => ($projectNames[$pid] ?? 'Project') . ': ' . $num($h) . ' h')->implode("\n") ?: 'Nothing booked' }}&#10;{{ $num($p['booked']) }} of {{ $num($p['capacity']) }} h booked">
                                        @if ($p['capacity'] <= 0 && $p['booked'] <= 0)
                                            off
                                        @else
                                            <b>{{ $num($p['free']) }}</b><small>{{ $unit === 'day' ? 'of ' . $num($p['capacity']) . ' h' : 'h free' }}</small>
                                        @endif
                                    </span>
                                </td>
                            @endforeach
                        </tr>
                    @empty
                        <tr><td colspan="{{ $columns->count() + 1 }}" class="p-4"><x-cards.no-record icon="users" message="No one here. Try Everyone, or another filter." /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @include('recruitment.partials.list-footer', ['paginator' => $people, 'sizes' => [25, 50, 100]])
    </div>
</div>

@include('project-tracker.partials.dash-styles')
<style>
    .rp-bar { display: flex; flex-wrap: wrap; align-items: center; gap: 10px 14px; margin-bottom: 12px; }
    .rp-seg { display: inline-flex; padding: 3px; background: #fff; border-radius: 9px; box-shadow: 0 1px 2px rgba(16, 24, 40, .08); }
    .rp-seg a { padding: 6px 14px; border-radius: 7px; font-size: 13.5px; font-weight: 500; color: #475467; }
    .rp-seg a:hover { text-decoration: none; background: #f2f4f7; }
    .rp-seg a.active { background: var(--epic-blue, #cb6b4f); color: #fff; }
    .rp-legend { display: flex; flex-wrap: wrap; gap: 6px 14px; margin-left: auto; font-size: 12px; color: #667085; align-items: center; }
    .rp-legend i { display: inline-block; width: 14px; height: 14px; border-radius: 4px; vertical-align: -3px; margin-right: 5px; box-shadow: inset 0 0 0 1px rgba(16, 24, 40, .15); }
    .rp-legend i.pd-cell--low { background: #a6f4c5; } .rp-legend i.pd-cell--mid { background: #b2ddff; } .rp-legend i.pd-cell--full { background: #fedf89; } .rp-legend i.pd-cell--over { background: #fecdca; }
    .rp-note { color: #98a2b3; }
    .rp-scroll { overflow-x: auto; }
    .rp-grid { width: 100%; border-collapse: separate; border-spacing: 0; font-size: 13px; }
    .rp-grid th { position: sticky; top: 0; background: #fff; padding: 12px 6px; text-align: center; font-size: 11px; font-weight: 500; color: #98a2b3;
        text-transform: uppercase; letter-spacing: .03em; border-bottom: 1px solid #eaecf0; white-space: nowrap; }
    .rp-grid th b { display: block; font-size: 12.5px; color: #344054; text-transform: none; letter-spacing: 0; }
    .rp-grid th.is-now b { color: var(--epic-blue, #cb6b4f); }
    .rp-grid td { padding: 5px 4px; border-bottom: 1px solid #f2f4f7; text-align: center; }
    .rp-grid .rp-name { position: sticky; left: 0; z-index: 1; background: #fff; text-align: left; min-width: 230px; padding-left: 16px; border-right: 1px solid #f2f4f7; }
    .rp-grid th.rp-name { z-index: 2; }
    .rp-person { display: flex; align-items: center; gap: 10px; color: inherit; }
    .rp-person:hover { text-decoration: none; }
    .rp-person:hover b { color: var(--epic-blue, #cb6b4f); }
    .rp-person img { width: 32px; height: 32px; border-radius: 50%; object-fit: cover; flex: none; }
    .rp-person span { display: flex; flex-direction: column; min-width: 0; }
    .rp-person b { font-weight: 600; color: #101828; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 200px; }
    .rp-person small { font-size: 11.5px; color: #667085; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 200px; }
    .rp-cell { position: relative; display: flex; flex-direction: column; align-items: center; justify-content: center; min-width: 62px; height: 42px; border-radius: 8px; line-height: 1.1; cursor: default; }
    .rp-cell b { font-size: 13.5px; }
    .rp-cell small { font-size: 10px; font-weight: 500; opacity: .8; }
    .rp-cell:hover::after { content: attr(data-tip); position: absolute; z-index: 5; bottom: calc(100% + 6px); left: 50%; transform: translateX(-50%); white-space: pre; text-align: left;
        background: #101828; color: #fff; font-size: 12px; font-weight: 400; padding: 8px 10px; border-radius: 8px; box-shadow: 0 6px 16px rgba(16, 24, 40, .2); pointer-events: none; }
    .rp-nav { align-items: center; }
    .rp-nav span { padding: 0 10px; font-size: 13px; font-weight: 600; color: #344054; white-space: nowrap; }
    .rp-nav a.is-off { opacity: .35; pointer-events: none; }
    .rp-drill { color: inherit; display: block; }
    .rp-drill:hover { text-decoration: none; color: var(--epic-blue, #cb6b4f); }
    .rp-grid .rp-wk { border-left: 2px solid #eaecf0; }
</style>
@include('recruitment.partials.standard-bar', ['form' => '#rc-filter-form', 'searchLabel' => 'Search people', 'active' => request()->anyFilled(['division', 'designation', 'project', 'search'])])
@endsection
