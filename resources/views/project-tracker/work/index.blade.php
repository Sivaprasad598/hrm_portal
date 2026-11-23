@extends('layouts.app')

{{-- My Space -> My Work (TrackerWorkController::index): the work I am on, held-up and late first.
     "Update" opens one form for the hours, units done and step; "Held up" raises a roadblock. --}}
@section('content')
@include('recruitment.partials.styles')
@php
    $num = fn ($v) => rtrim(rtrim(number_format((float) $v, 1), '0'), '.');
    $fmt = company()->date_format;
    $startOfToday = now()->startOfDay();
@endphp
<div class="content-wrapper" data-swap-links>

    <form method="GET" action="{{ route('project-tracker.my-work') }}" id="rc-filter-form" class="bg-white rounded b-shadow-4 p-3 mb-3">
        <div class="row align-items-end">
            <div class="col-md-4 mb-2">
                <label class="f-13 text-dark-grey mb-1">Project</label>
                <select name="project" class="form-control select-picker" data-live-search="true" data-size="8">
                    <option value="">All</option>
                    @foreach ($projectsList as $p)<option value="{{ $p->id }}" @selected(request('project') == $p->id)>{{ $p->project_name }}</option>@endforeach
                </select>
            </div>
            <div class="col-md-8 mb-2">
                <label class="f-13 text-dark-grey mb-1">Search</label>
                <input type="text" name="search" value="{{ request('search') }}" class="form-control f-14" placeholder="Search in my work">
            </div>
        </div>
    </form>

    {{-- My day and week --}}
    @php
        $t = $today;
        $dayFill = $t['day'] > 0 ? min(100, round($t['logged'] / $t['day'] * 100)) : 0;
        $weekFill = $t['week_capacity'] > 0 ? min(100, round($t['week_logged'] / $t['week_capacity'] * 100)) : 0;
    @endphp
    <div class="mw-strip">
        <div class="mw-stat">
            <span>Today</span>
            <b>{{ $num($t['logged']) }} <small>of {{ $num($t['day']) }} h logged</small></b>
            <i class="mw-meter"><em style="width: {{ $dayFill }}%"></em></i>
        </div>
        <div class="mw-stat">
            <span>This week</span>
            <b>{{ $num($t['week_logged']) }} <small>of {{ $num($t['week_capacity']) }} h</small></b>
            <i class="mw-meter"><em style="width: {{ $weekFill }}%"></em></i>
        </div>
        <div class="mw-stat"><span>Open</span><b>{{ $t['open'] }}</b><small>items on me</small></div>
        <div class="mw-stat {{ $t['late'] ? 'is-bad' : '' }}"><span>Late</span><b>{{ $t['late'] }}</b><small>past the due date</small></div>
        <div class="mw-stat {{ $t['blocked'] ? 'is-warn' : '' }}"><span>Held up</span><b>{{ $t['blocked'] }}</b><small>waiting on someone</small></div>
    </div>

    @if ($t['open'] === 0)
        <div class="rc-list-card p-4"><x-cards.no-record icon="check-circle" message="{{ request()->anyFilled(['project', 'search']) ? 'Nothing matches.' : 'No open work on you. New items appear here when you are assigned.' }}" /></div>
    @endif

    @foreach ($groups as $key => $group)
        @continue($group['items']->isEmpty())
        <div class="mw-group mw-group--{{ $group['tone'] }}">
            <div class="mw-group-head"><i class="fa {{ $group['icon'] }}"></i>{{ $group['label'] }}<span>{{ $group['items']->count() }}</span></div>
            <div class="mw-list" data-no-bar>
                @foreach ($group['items'] as $task)
                    @php
                        $type = $task->project?->projectType;
                        $stages = $type?->stages ?? collect();
                        $current = $task->trackerStage;
                        $estimate = (int) $task->estimate_hours + (int) $task->estimate_minutes / 60;
                        $spent = (float) ($logged[$task->id] ?? 0);
                        $unitsPct = $task->planned_units > 0 ? min(100, round($task->done_units / $task->planned_units * 100)) : null;
                        $due = $task->due_date?->copy()->startOfDay();
                        $daysLeft = $due ? (int) $startOfToday->diffInDays($due, false) : null;
                        $next = $current ? $stages->first(fn ($s) => $s->sort > $current->sort) : $stages->first();
                        $block = $blockers->get($task->id);
                        $back = $sentBack->get($task->id);
                    @endphp
                    <div class="mw-item" style="--tc: {{ $type?->color ?: '#98a2b3' }}">
                        <div class="mw-main">
                            <div class="mw-proj">{{ $task->project?->project_name ?? 'No project' }}@if ($task->trackerParent) <span>· {{ $task->trackerParent->heading }}</span>@endif</div>
                            <a href="{{ route('project-tracker.my-work.edit', $task->id) }}" class="mw-title openRightModal">{{ $task->heading }}</a>

                            @if ($stages->isNotEmpty())
                                <div class="mw-steps" title="{{ $current?->name ?? 'No step yet' }}">
                                    @foreach ($stages as $i => $stage)
                                        <span class="{{ $current && $stage->sort < $current->sort ? 'is-past' : '' }} {{ $current && $stage->id === $current->id ? 'is-now' : '' }}" style="--sc: {{ $stage->colour($i) }}">{{ $stage->name }}</span>
                                    @endforeach
                                </div>
                            @endif

                            @if ($block)
                                <p class="mw-note mw-note--warn"><i class="fa fa-hand-paper mr-1"></i>Waiting on {{ $block->pluck('name')->filter()->implode(', ') }}: {{ \Illuminate\Support\Str::limit($block->first()->need, 120) }}</p>
                            @elseif ($back && $back->note)
                                <p class="mw-note mw-note--bad"><i class="fa fa-undo mr-1"></i>Sent back {{ \Carbon\Carbon::parse($back->logged_at)->diffForHumans() }}: {{ \Illuminate\Support\Str::limit($back->note, 140) }}</p>
                            @endif
                        </div>

                        <div class="mw-figures">
                            @if ($unitsPct !== null)
                                <div class="mw-fig"><b>{{ $num($task->done_units) }}<small> / {{ $num($task->planned_units) }} {{ $type?->unitText() }}</small></b><i class="mw-meter"><em style="width: {{ $unitsPct }}%"></em></i></div>
                            @endif
                            <div class="mw-fig {{ $estimate > 0 && $spent > $estimate ? 'is-bad' : '' }}"><b>{{ $num($spent) }}<small> / {{ $estimate > 0 ? $num($estimate) . ' h' : '— h' }}</small></b><span>{{ isset($mine[$task->id]) ? $num($mine[$task->id]) . ' h by me' : 'hours' }}</span></div>
                            <div class="mw-fig mw-due {{ $daysLeft !== null && $daysLeft < 0 ? 'is-bad' : ($daysLeft !== null && $daysLeft <= 1 ? 'is-warn' : '') }}">
                                <b>{{ $due ? $due->format('d M') : '—' }}</b>
                                <span>{{ $daysLeft === null ? 'no due date' : ($daysLeft < 0 ? abs($daysLeft) . ' ' . \Illuminate\Support\Str::plural('day', abs($daysLeft)) . ' late' : ($daysLeft === 0 ? 'today' : ($daysLeft === 1 ? 'tomorrow' : 'in ' . $daysLeft . ' days'))) }}</span>
                            </div>
                        </div>

                        <div class="mw-actions">
                            <a href="{{ route('project-tracker.my-work.edit', $task->id) }}" class="btn btn-primary btn-sm openRightModal"><i class="fa fa-pen mr-1"></i>Update</a>
                            @if ($next && !$block)
                                <button type="button" class="btn btn-secondary btn-sm mw-next" data-url="{{ route('project-tracker.my-work.update', $task->id) }}" data-stage="{{ $next->id }}" title="Move to {{ $next->name }}">{{ $next->name }} <i class="fa fa-arrow-right ml-1"></i></button>
                            @endif
                            @unless ($block)
                                <button type="button" class="btn btn-link btn-sm mw-block" data-task="{{ $task->id }}" title="Raise a roadblock: you are waiting on someone"><i class="fa fa-hand-paper mr-1"></i>Held up</button>
                            @endunless
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endforeach
</div>

<style>
    .mw-strip { display: grid; grid-template-columns: repeat(5, 1fr); gap: 12px; margin-bottom: 16px; }
    .mw-stat { background: #fff; border-radius: 12px; box-shadow: 0 1px 3px rgba(16, 24, 40, .06); padding: 14px 16px; display: flex; flex-direction: column; gap: 2px; }
    .mw-stat > span { font-size: 11.5px; font-weight: 600; text-transform: uppercase; letter-spacing: .05em; color: #667085; }
    .mw-stat b { font-size: 22px; color: #101828; } .mw-stat b small, .mw-stat > small { font-size: 12px; color: #98a2b3; font-weight: 400; }
    .mw-stat.is-bad b { color: #b42318; } .mw-stat.is-warn b { color: #b54708; }
    .mw-meter { display: block; height: 5px; border-radius: 3px; background: #f2f4f7; overflow: hidden; margin-top: 6px; }
    .mw-meter em { display: block; height: 100%; background: #12b76a; border-radius: 3px; }
    .mw-group { margin-bottom: 18px; }
    .mw-group-head { display: flex; align-items: center; gap: 8px; font-size: 13px; font-weight: 600; text-transform: uppercase; letter-spacing: .05em; color: #475467; margin: 0 2px 8px; }
    .mw-group-head span { font-size: 11.5px; background: #eaecf0; border-radius: 10px; padding: 1px 8px; }
    .mw-group--red .mw-group-head { color: #b42318; } .mw-group--amber .mw-group-head { color: #b54708; } .mw-group--blue .mw-group-head { color: #175cd3; }
    .mw-list { display: flex; flex-direction: column; gap: 8px; }
    .mw-item { display: flex; align-items: center; gap: 18px; background: #fff; border-radius: 12px; box-shadow: 0 1px 3px rgba(16, 24, 40, .06); padding: 14px 18px; border-left: 4px solid var(--tc); }
    .mw-main { flex: 1 1 auto; min-width: 0; }
    .mw-proj { font-size: 12px; color: #667085; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .mw-proj span { color: #98a2b3; }
    .mw-title { display: block; font-size: 15px; font-weight: 600; color: #101828; margin: 1px 0 6px; }
    .mw-title:hover { color: var(--epic-blue, #cb6b4f); text-decoration: none; }
    .mw-steps { display: flex; flex-wrap: wrap; gap: 4px; }
    .mw-steps span { font-size: 11px; padding: 2px 8px; border-radius: 10px; color: #98a2b3; background: #f9fafb; border: 1px solid #eaecf0; }
    .mw-steps span.is-past { color: var(--sc); background: color-mix(in srgb, var(--sc) 8%, #fff); border-color: color-mix(in srgb, var(--sc) 20%, #fff); }
    .mw-steps span.is-now { color: #fff; background: var(--sc); border-color: var(--sc); font-weight: 600; }
    .mw-note { font-size: 12.5px; margin: 8px 0 0; padding: 6px 10px; border-radius: 8px; }
    .mw-note--warn { background: #fffaeb; color: #93370d; } .mw-note--bad { background: #fef3f2; color: #912018; }
    .mw-figures { display: flex; gap: 22px; flex: none; }
    .mw-fig { display: flex; flex-direction: column; min-width: 92px; }
    .mw-fig b { font-size: 15px; color: #101828; white-space: nowrap; } .mw-fig b small { font-size: 12px; color: #98a2b3; font-weight: 400; }
    .mw-fig span { font-size: 12px; color: #98a2b3; }
    .mw-fig.is-bad b, .mw-fig.is-bad span { color: #b42318; } .mw-fig.is-warn b, .mw-fig.is-warn span { color: #b54708; }
    .mw-actions { display: flex; align-items: center; gap: 6px; flex: none; }
    .mw-actions .btn-sm { height: 34px; display: inline-flex; align-items: center; border-radius: 8px !important; padding: 0 12px !important; white-space: nowrap; }
    .mw-actions .btn-link { color: #667085; } .mw-actions .btn-link:hover { color: #b54708; text-decoration: none; }
    @media (max-width: 1199px) { .mw-item { flex-wrap: wrap; } .mw-main { flex-basis: 100%; } .mw-actions { margin-left: auto; } }
    @media (max-width: 767px) { .mw-strip { grid-template-columns: 1fr 1fr; } .mw-figures { gap: 14px; flex-wrap: wrap; } .mw-actions { margin-left: 0; width: 100%; } }
</style>
<script>
    (function () {
        // one tap to the next step
        $('.content-wrapper').off('click.mw').on('click.mw', '.mw-next', function () {
            var b = $(this);
            $.easyAjax({
                url: b.data('url'), type: 'POST', blockUI: true, container: b.closest('.mw-item'), disableButton: true, buttonSelector: b,
                data: {_token: '{{ csrf_token() }}', stage_id: b.data('stage'), date: '{{ now(company()->timezone)->format($fmt) }}'},
                success: function (r) { if (r.status === 'success') { PageSwap.reload(); } }
            });
        }).on('click.mw', '.mw-block', function () {
            openRoadblockModal($(this).data('task'));
        });
        $(document).off('roadblock:changed.mw').on('roadblock:changed.mw', function () { PageSwap.reload(); });
    })();
</script>
@include('recruitment.partials.standard-bar', ['form' => '#rc-filter-form', 'searchLabel' => 'Search in my work', 'active' => request()->anyFilled(['project', 'search'])])
@endsection
