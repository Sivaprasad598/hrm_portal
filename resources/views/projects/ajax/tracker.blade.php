{{-- Project tracker tab (ProjectController::show ?tab=tracker, App\Services\Tracker\ProjectTracker):
     the project's type and progress on top, then its work as a structure (Epic -> Work item -> Task)
     or as a board by the type's steps. --}}
@php
    $t = $tracker;
    $type = $t['type'];
    $unit = $t['unit'];
    $fmt = company()->date_format;
    $pct = \App\Services\Tracker\ProjectTracker::percent($t);
    $num = fn ($v) => rtrim(rtrim(number_format((float) $v, 2), '0'), '.');
    $grace = $project->grace_days ?? $type?->grace_days ?? 0;
    $deadline = $project->deadline;
    $finalDay = $deadline ? $deadline->copy()->addDays($grace) : null;
    $today = now()->startOfDay();
    $state = !$deadline ? ['No deadline', 'grey'] : ($pct >= 100 ? ['Done', 'green']
        : ($today->gt($finalDay) ? ['Overdue', 'red'] : ($today->gt($deadline) ? ['In grace', 'amber'] : ['On schedule', 'blue'])));
    $levels = \App\Services\Tracker\ProjectTracker::LEVELS;
    $stageOf = $t['stages']->keyBy('id');
    $stageIndex = $t['stages']->pluck('id')->flip();
@endphp

<div class="trk" data-swap-links>
    @if (!$type)
        {{-- Not set up yet --}}
        <div class="trk-empty">
            <span class="trk-empty-ic"><i class="fa fa-project-diagram"></i></span>
            <h4>Track this project</h4>
            <p>Choose its type — GIS, Field survey, Scan to BIM, Steel detailing, Software … — and the work gets that type's steps,
               unit of work and grace days. Then break it into Epics, Work items and Tasks and follow them on a board.</p>
            @if ($canTrack)
                <a href="{{ route('project-tracker.setup-form', $project->id) }}" class="btn btn-primary openRightModal"><i class="fa fa-cog mr-1"></i>Set up the tracker</a>
            @else
                <p class="text-dark-grey mb-0">The project manager sets this up.</p>
            @endif
        </div>
    @else
        {{-- Summary --}}
        <div class="trk-head" style="--tc: {{ $type->color ?: '#cb6b4f' }}">
            <div class="trk-ring" style="--p: {{ $pct }}"><span>{{ $pct }}<small>%</small></span></div>
            <div class="trk-head-main">
                <div class="trk-head-top">
                    <span class="trk-type"><span class="trk-type-dot"></span>{{ $type->name }}</span>
                    <span class="trk-state trk-state--{{ $state[1] }}">{{ $state[0] }}</span>
                </div>
                <div class="trk-facts">
                    <div><b>{{ $t['totals']['done'] }} / {{ $t['totals']['items'] }}</b><span>work done</span></div>
                    @if ($type->countsUnits())
                        <div><b>{{ $num($t['totals']['done_units']) }} / {{ $num($t['totals']['planned_units']) }}</b><span>{{ $unit }} in work items{{ $t['totals']['scope_units'] !== null ? ' · scope ' . $num($t['totals']['scope_units']) : '' }}</span></div>
                    @endif
                    <div><b>{{ $num($t['totals']['logged_hours']) }} / {{ $num($t['totals']['estimate_hours']) }} h</b><span>logged / estimated</span></div>
                    <div><b>{{ $deadline ? $deadline->format($fmt) : '—' }}</b><span>deadline{{ $grace ? ' · +' . $grace . ' grace day' . ($grace > 1 ? 's' : '') : '' }}</span></div>
                    <div class="{{ $t['totals']['overdue'] ? 'is-bad' : '' }}"><b>{{ $t['totals']['overdue'] }}</b><span>overdue</span></div>
                    <div class="{{ $t['totals']['blocked'] ? 'is-warn' : '' }}"><b>{{ $t['totals']['blocked'] }}</b><span>blocked</span></div>
                </div>
            </div>
            @if ($canTrack)
                <div class="trk-head-actions">
                    <a href="{{ route('project-tracker.items.create', [$project->id, 'level' => 'epic']) }}" class="btn btn-secondary openRightModal"><i class="fa fa-layer-group mr-1"></i>Epic</a>
                    <a href="{{ route('project-tracker.items.create', [$project->id, 'level' => 'item']) }}" class="btn btn-primary openRightModal"><i class="fa fa-plus mr-1"></i>Work item</a>
                    <a href="{{ route('project-tracker.setup-form', $project->id) }}" class="btn btn-secondary openRightModal" title="Type, quantity, grace days"><i class="fa fa-cog"></i></a>
                </div>
            @endif
        </div>

        {{-- How the work stands at each step --}}
        @php $workCount = max(1, $t['totals']['items']); @endphp
        <div class="trk-flow">
            @foreach ($t['stages'] as $i => $stage)
                @php $n = $t['byStage'][$stage->id]->count(); @endphp
                <div class="trk-flow-step" style="--sc: {{ $stage->colour($i) }}; flex-grow: {{ max(1, $n) }}">
                    <div class="trk-flow-bar"></div>
                    <div class="trk-flow-label"><b>{{ $n }}</b> {{ $stage->name }}</div>
                </div>
            @endforeach
            @if ($t['unstaged']->isNotEmpty())
                <div class="trk-flow-step" style="--sc: #d0d5dd; flex-grow: {{ $t['unstaged']->count() }}">
                    <div class="trk-flow-bar"></div>
                    <div class="trk-flow-label"><b>{{ $t['unstaged']->count() }}</b> no step yet</div>
                </div>
            @endif
        </div>

        {{-- Views --}}
        <div class="trk-bar">
            <div class="trk-switch" role="tablist">
                <button type="button" class="active" data-view="structure"><i class="fa fa-sitemap mr-1"></i>Structure</button>
                <button type="button" data-view="board"><i class="fa fa-columns mr-1"></i>Board</button>
            </div>
            <input type="search" class="form-control trk-search" placeholder="Search in the work" aria-label="Search in the work">
        </div>

        {{-- Structure: Epic -> Work item -> Task --}}
        @php
            $row = function ($task, $depth) use (&$row, $t, $levels, $stageOf, $stageIndex, $num, $fmt, $canTrack, $project, $unit) {
                $kids = $t['children']->get($task->id, collect());
                $level = $levels[$task->work_type] ?? $levels['task'];
                $stage = $task->project_stage_id ? $stageOf->get($task->project_stage_id) : null;
                $done = ($t['isDone'])($task);
                $late = !$done && $task->due_date && $task->due_date->copy()->endOfDay()->isPast();
                $html = view('project-tracker.project.row', compact('task', 'depth', 'kids', 'level', 'stage', 'done', 'late', 'num', 'fmt', 'canTrack', 'project', 'unit')
                    + ['stageColour' => $stage ? $stage->colour($stageIndex[$stage->id] ?? 0) : null])->render();
                foreach ($kids as $kid) { $html .= $row($kid, $depth + 1); }
                return $html;
            };
        @endphp
        <div class="trk-view trk-tree" data-view="structure">
            @if ($t['tasks']->isEmpty())
                <div class="trk-none">Nothing here yet. {{ $canTrack ? 'Add an Epic (a part of the project: a zone, a phase, a module) or a Work item.' : '' }}</div>
            @endif
            @foreach ($t['epics'] as $epic)
                {!! $row($epic, 0) !!}
            @endforeach
            @if ($t['loose']->isNotEmpty())
                @if ($t['epics']->isNotEmpty())<div class="trk-group">Not in an Epic</div>@endif
                @foreach ($t['loose'] as $task)
                    {!! $row($task, 0) !!}
                @endforeach
            @endif
        </div>

        {{-- Board: one lane per step; drag to move --}}
        <div class="trk-view trk-board" data-view="board" style="display: none">
            @foreach ($t['stages'] as $i => $stage)
                @php $cards = $t['byStage'][$stage->id]; @endphp
                <div class="trk-lane" data-stage="{{ $stage->id }}" style="--sc: {{ $stage->colour($i) }}">
                    <div class="trk-lane-head"><span>{{ $stage->name }}</span><b>{{ $cards->count() }}</b></div>
                    <div class="trk-lane-body">
                        @forelse ($cards as $task)
                            @include('project-tracker.project.card', ['task' => $task, 'level' => $levels[$task->work_type] ?? $levels['task']])
                        @empty
                            <div class="trk-lane-empty">{{ $canTrack ? 'Drag work here' : 'Nothing here' }}</div>
                        @endforelse
                    </div>
                </div>
            @endforeach
            @if ($t['unstaged']->isNotEmpty())
                <div class="trk-lane trk-lane--none" style="--sc: #d0d5dd">
                    <div class="trk-lane-head"><span>No step yet</span><b>{{ $t['unstaged']->count() }}</b></div>
                    <div class="trk-lane-body">
                        @foreach ($t['unstaged'] as $task)
                            @include('project-tracker.project.card', ['task' => $task, 'level' => $levels[$task->work_type] ?? $levels['task']])
                        @endforeach
                    </div>
                </div>
            @endif
        </div>
    @endif
</div>

@include('project-tracker.project.styles')

<script>
    (function () {
        var root = document.querySelector('.trk');
        if (!root) { return; }

        // the view, remembered for the next visit
        var key = 'trk-view';
        function show(view) {
            root.querySelectorAll('.trk-switch button').forEach(function (b) { b.classList.toggle('active', b.dataset.view === view); });
            root.querySelectorAll('.trk-view').forEach(function (v) { v.style.display = v.dataset.view === view ? '' : 'none'; });
            try { localStorage.setItem(key, view); } catch (e) {}
        }
        root.querySelectorAll('.trk-switch button').forEach(function (b) { b.addEventListener('click', function () { show(b.dataset.view); }); });
        try { if (localStorage.getItem(key) === 'board') { show('board'); } } catch (e) {}

        // search: rows and cards whose text matches (an Epic shows when anything under it matches)
        var search = root.querySelector('.trk-search');
        search && search.addEventListener('input', function () {
            var q = this.value.trim().toLowerCase();
            root.querySelectorAll('.trk-row, .trk-card').forEach(function (el) { el.style.display = !q || el.textContent.toLowerCase().indexOf(q) !== -1 ? '' : 'none'; });
        });

        // fold an Epic / Work item
        $(root).on('click', '.trk-fold', function () {
            var row = $(this).closest('.trk-row'), open = !row.hasClass('is-folded');
            row.toggleClass('is-folded', open);
            var depth = +row.data('depth');
            row.nextAll('.trk-row').each(function () {
                if (+$(this).data('depth') <= depth) { return false; }
                $(this).toggle(!open);
            });
        });

        @if ($canTrack && $type)
        // units done, in the row
        $(root).on('change', '.trk-units-input', function () {
            var input = $(this);
            $.easyAjax({
                url: '{{ route('project-tracker.items.progress', [$project->id, ':id']) }}'.replace(':id', input.data('id')), type: 'POST', blockUI: false,
                data: {_token: '{{ csrf_token() }}', done_units: input.val()},
                success: function (r) { if (r.status === 'success') { PageSwap.reload(); } }
            });
        });

        // the board: drag a card to another step
        var dragged = null;
        root.querySelectorAll('.trk-card[draggable]').forEach(function (card) {
            card.addEventListener('dragstart', function (e) { dragged = card; card.classList.add('is-dragging'); e.dataTransfer.effectAllowed = 'move'; });
            card.addEventListener('dragend', function () { card.classList.remove('is-dragging'); root.querySelectorAll('.is-drop').forEach(function (l) { l.classList.remove('is-drop'); }); });
        });
        root.querySelectorAll('.trk-lane[data-stage]').forEach(function (lane) {
            lane.addEventListener('dragover', function (e) { if (dragged) { e.preventDefault(); lane.classList.add('is-drop'); } });
            lane.addEventListener('dragleave', function () { lane.classList.remove('is-drop'); });
            lane.addEventListener('drop', function (e) {
                e.preventDefault();
                lane.classList.remove('is-drop');
                if (!dragged || dragged.closest('.trk-lane') === lane) { return; }
                var card = dragged;
                lane.querySelector('.trk-lane-body').prepend(card);   // shown at once; the page follows after the save
                $.easyAjax({
                    url: '{{ route('project-tracker.items.stage', [$project->id, ':id']) }}'.replace(':id', card.dataset.id), type: 'POST',
                    data: {_token: '{{ csrf_token() }}', stage_id: lane.dataset.stage},
                    success: function (r) { PageSwap.reload(); }, error: function () { PageSwap.reload(); }
                });
            });
        });
        @endif
    })();
</script>
