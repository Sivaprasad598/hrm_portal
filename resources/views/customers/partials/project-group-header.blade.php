{{--
    Header of a collapsible "Project: …" meeting section. Opens .project-meetings-group and .project-group-body;
    the including view closes both </div>s. $projectId (0 = no project), $projectName, $count, $open
--}}
@php $groupId = 'project-meetings-' . $projectId; @endphp
<div class="card mb-3 project-meetings-group" data-project-id="{{ $projectId }}">
    <div class="card-header project-group-header d-flex justify-content-between align-items-center border-bottom-0 py-3 {{ $open ? '' : 'collapsed' }}">
        <h3 class="f-16 mb-0 font-weight-bold text-dark d-flex align-items-center">
            <i class="fa fa-chevron-down group-chevron mr-3"></i>
            {{ $projectId ? 'Project: ' . $projectName : 'No Project' }}
        </h3>
        <div class="d-flex align-items-center" style="gap: 16px;">
            <span class="f-14 text-dark">
                <span class="group-count">{{ $count }}</span> {{ \Illuminate\Support\Str::plural('meeting', $count) }}
            </span>
            <button type="button" class="btn btn-outline-primary btn-sm bg-white group-toggle"
                    aria-expanded="{{ $open ? 'true' : 'false' }}" aria-controls="{{ $groupId }}"
                    title="Show or hide this project's meetings"><i class="fa fa-chevron-up"></i></button>
        </div>
    </div>

    <div id="{{ $groupId }}" class="project-group-body" @unless ($open) style="display: none;" @endunless>
        <div class="card-body pt-2">
