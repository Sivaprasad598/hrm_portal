{{-- One line of the tracker structure: an Epic, a Work item or a Task, indented by its depth. --}}
<div class="trk-row trk-row--{{ $task->work_type }} {{ $done ? 'is-done' : '' }}" data-depth="{{ $depth }}" style="--depth: {{ $depth }}; --lc: {{ $level[2] }}">
    <div class="trk-name">
        @if ($kids->isNotEmpty())
            <button type="button" class="trk-fold" aria-label="Fold"><i class="fa fa-chevron-down"></i></button>
        @else
            <span class="trk-fold-space"></span>
        @endif
        <span class="trk-level" title="{{ $level[0] }}"><i class="fa {{ $level[1] }}"></i></span>
        <div style="min-width: 0">
            <a href="{{ route('tasks.show', $task->id) }}" class="trk-title openRightModal">{{ $task->heading }}</a>
            <div class="trk-sub">
                {{ $level[0] }}
                @if ($task->work_type === 'epic') · {{ $kids->count() }} item{{ $kids->count() == 1 ? '' : 's' }}@endif
                @if ($task->due_date) · <span class="{{ $late ? 'trk-late' : '' }}">{{ $late ? 'overdue · ' : 'due ' }}{{ $task->due_date->format($fmt) }}</span>@endif
            </div>
        </div>
    </div>

    <div class="trk-stage">
        @if ($task->work_type !== 'epic')
            @if ($stage)
                <span class="trk-pill" style="--sc: {{ $stageColour }}">{{ $stage->name }}</span>
            @else
                <span class="trk-pill" style="--sc: #98a2b3">no step</span>
            @endif
        @endif
    </div>

    <div class="trk-people">
        @foreach ($task->users->take(3) as $user)
            <img src="{{ $user->image_url }}" alt="" title="{{ $user->name }}">
        @endforeach
        @if ($task->users->count() > 3)<span class="trk-more">+{{ $task->users->count() - 3 }}</span>@endif
    </div>

    <div class="trk-units">
        @if ($task->work_type !== 'epic' && $task->planned_units)
            @php $p = min(100, round($task->done_units / max(0.01, $task->planned_units) * 100)); @endphp
            <div class="trk-units-top">
                @if ($canTrack && !$done)
                    <input type="number" step="any" min="0" class="trk-units-input" data-id="{{ $task->id }}" value="{{ $num($task->done_units) }}" aria-label="Done">
                @else
                    <b>{{ $num($task->done_units) }}</b>
                @endif
                <span>/ {{ $num($task->planned_units) }} {{ $unit === 'hours' ? '' : $unit }}</span>
            </div>
            <div class="trk-meter"><span style="width: {{ $p }}%"></span></div>
        @endif
    </div>

    <div class="trk-hours" title="logged / estimated">
        @if ($task->work_type !== 'epic')
            @php $est = (int) $task->estimate_hours + (int) $task->estimate_minutes / 60; $log = (int) $task->minutes_logged / 60; @endphp
            <b class="{{ $est && $log > $est ? 'trk-late' : '' }}">{{ $num(round($log, 1)) }}</b><span> / {{ $num($est) }} h</span>
        @endif
    </div>

    <div class="trk-act">
        @if ($canTrack)
            @if ($task->work_type === 'epic')
                <a href="{{ route('project-tracker.items.create', [$project->id, 'level' => 'item', 'parent' => $task->id]) }}" class="trk-icon openRightModal" title="Add a Work item"><i class="fa fa-plus"></i></a>
            @elseif ($task->work_type === 'item')
                <a href="{{ route('project-tracker.items.create', [$project->id, 'level' => 'task', 'parent' => $task->id]) }}" class="trk-icon openRightModal" title="Add a Task"><i class="fa fa-plus"></i></a>
            @endif
            <a href="{{ route('project-tracker.items.edit', [$project->id, $task->id]) }}" class="trk-icon openRightModal" title="Edit"><i class="fa fa-pen"></i></a>
        @endif
    </div>
</div>
