{{-- Leave details: From - To of the whole request, and the applicant's open tasks
     that fall in it (LeaveController::show: $leaveRange, $tasksInLeave) --}}
@if (!empty($leaveRange))
    @php $dateFormat = company()->date_format; @endphp
    <div class="col-12 px-0 pb-3 d-lg-flex d-md-flex d-block">
        <p class="mb-0 text-lightest f-14 w-30 d-inline-block">From – To</p>
        <p class="mb-0 text-dark-grey f-14">
            {{ $leaveRange['from']->translatedFormat($dateFormat) }}
            @if (!$leaveRange['from']->isSameDay($leaveRange['to']))
                <span class="text-lightest mx-1">to</span> {{ $leaveRange['to']->translatedFormat($dateFormat) }}
            @endif
            <span class="badge badge-light border ml-2">{{ $leaveRange['days'] }} {{ $leaveRange['days'] == 1 ? 'day' : 'days' }}</span>
        </p>
    </div>
@endif

@isset($tasksInLeave)
    <div class="col-12 px-0 pb-3 d-lg-flex d-md-flex d-block">
        <p class="mb-0 text-lightest f-14 w-30 d-inline-block">Tasks in this period</p>
        <div class="text-dark-grey f-14 flex-grow-1">
            @forelse ($tasksInLeave as $task)
                <div class="d-flex align-items-center flex-wrap mb-1">
                    <i class="fa fa-circle f-8 mr-2" style="color: {{ $task->boardColumn->label_color ?? '#98a2b3' }}"></i>
                    <a href="{{ route('tasks.show', $task->id) }}" class="text-dark mr-2" target="_blank">{{ $task->heading }}</a>
                    @if ($task->project)
                        <span class="f-12 text-lightest mr-2">{{ $task->project->project_name }}</span>
                    @endif
                    <span class="f-12 {{ $task->due_date->between($leaveRange['from'], $leaveRange['to']) ? 'text-danger' : 'text-dark-grey' }}">
                        Due {{ $task->due_date->translatedFormat(company()->date_format) }}
                    </span>
                    @if ($task->boardColumn)
                        <span class="badge badge-light border ml-2 f-11">{{ $task->boardColumn->column_name }}</span>
                    @endif
                </div>
            @empty
                <span class="text-lightest">No open tasks during this leave</span>
            @endforelse
        </div>
    </div>
@endisset
