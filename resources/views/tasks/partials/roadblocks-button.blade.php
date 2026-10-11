{{-- Roadblocks report button for the task view switcher (list / board / calendar / waiting approval / roadblocks) --}}
@php
    $openRoadblockCount = \App\Models\TaskBlocker::open()->whereHas('task')
        ->when(!in_array('admin', user_roles()), fn ($q) => $q->where(fn ($q2) => $q2->where('raised_by', user()->id)->orWhere('blocking_user_id', user()->id)))
        ->count();
@endphp
<a href="{{ route('task-blockers.index') }}" class="btn btn-secondary f-14 {{ ($active ?? false) ? 'btn-active' : '' }}" data-toggle="tooltip"
    data-original-title="@lang('modules.tasks.roadblocks')">
    <i class="side-icon fa fa-hand-paper"></i>
    @if ($openRoadblockCount > 0)<span class="badge badge-pill badge-danger position-absolute">{{ $openRoadblockCount }}</span>@endif
</a>
