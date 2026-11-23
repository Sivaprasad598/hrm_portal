{{-- A card on the tracker board. --}}
@php
    $doneHere = ($tracker['isDone'])($task);
    $lateHere = !$doneHere && $task->due_date && $task->due_date->copy()->endOfDay()->isPast();
    $parentName = $task->parent_id ? optional($tracker['tasks']->firstWhere('id', $task->parent_id))->heading : null;
@endphp
<div class="trk-card {{ $lateHere ? 'is-late' : '' }}" data-id="{{ $task->id }}" @if ($canTrack) draggable="true" @endif>
    <div class="trk-card-top">
        <span class="trk-level" style="--lc: {{ $level[2] }}" title="{{ $level[0] }}"><i class="fa {{ $level[1] }}"></i></span>
        <a href="{{ route('tasks.show', $task->id) }}" class="trk-title openRightModal">{{ $task->heading }}</a>
    </div>
    @if ($parentName)<div class="trk-sub"><i class="fa fa-level-up-alt fa-rotate-90 mr-1"></i>{{ $parentName }}</div>@endif
    @if ($task->planned_units)
        @php $p = min(100, round($task->done_units / max(0.01, $task->planned_units) * 100)); @endphp
        <div class="trk-card-units"><span>{{ rtrim(rtrim(number_format($task->done_units, 2), '0'), '.') }} / {{ rtrim(rtrim(number_format($task->planned_units, 2), '0'), '.') }} {{ $tracker['unit'] === 'hours' ? '' : $tracker['unit'] }}</span><b>{{ $p }}%</b></div>
        <div class="trk-meter"><span style="width: {{ $p }}%"></span></div>
    @endif
    <div class="trk-card-foot">
        <span class="trk-people">@foreach ($task->users->take(3) as $user)<img src="{{ $user->image_url }}" alt="" title="{{ $user->name }}">@endforeach</span>
        @if ($task->due_date)<span class="{{ $lateHere ? 'trk-late' : '' }}"><i class="fa fa-calendar-alt mr-1"></i>{{ $task->due_date->format(company()->date_format) }}</span>@endif
    </div>
</div>
