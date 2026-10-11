<!-- TAB CONTENT START -->
<div class="tab-pane fade show active" role="tabpanel" aria-labelledby="nav-email-tab">

    @php
        // Each status period runs from its change until the next change (or now)
        $statusLogs = $task->statusLogs()->with('toColumn', 'user')->get();
        $statusPeriods = [];
        $statusTotals = [];

        foreach ($statusLogs as $index => $statusLog) {
            $periodEnd = $statusLogs->get($index + 1)?->changed_at;
            $seconds = abs(($periodEnd ?? now())->diffInSeconds($statusLog->changed_at));
            $column = $statusLog->toColumn;

            $statusPeriods[] = ['log' => $statusLog, 'column' => $column, 'seconds' => $seconds, 'current' => is_null($periodEnd)];

            if ($column) {
                $statusTotals[$column->id] ??= ['column' => $column, 'seconds' => 0];
                $statusTotals[$column->id]['seconds'] += $seconds;
            }
        }

        $formatDuration = fn ($seconds) => $seconds < 60
            ? $seconds . 's'
            : \Carbon\CarbonInterval::seconds($seconds)->cascade()->forHumans(['short' => true, 'parts' => 2]);
    @endphp

    <div class="p-20 border-bottom-grey">
        <h4 class="f-14 f-w-500 text-dark mb-3">@lang('modules.tasks.statusTimeline')</h4>

        @if (count($statusPeriods))
            <div class="d-flex flex-wrap mb-3">
                @foreach ($statusTotals as $total)
                    <span class="badge badge-light border mr-2 mb-2 p-2 f-12">
                        <i class="fa fa-circle mr-1" style="color: {{ $total['column']->label_color }}"></i>
                        {{ $total['column']->column_name }}: <strong>{{ $formatDuration($total['seconds']) }}</strong>
                    </span>
                @endforeach
            </div>

            <ul class="list-unstyled mb-0">
                @foreach ($statusPeriods as $period)
                    <li class="f-12 text-dark-grey mb-1">
                        <i class="fa fa-circle mr-1" style="color: {{ $period['column']?->label_color ?? '#999' }}"></i>
                        @lang('modules.tasks.statusChangedTo') <strong>{{ $period['column']?->column_name ?? '--' }}</strong>
                        @if ($period['log']->user)
                            &middot; {{ $period['log']->user->name }}
                        @endif
                        &middot; {{ $period['log']->changed_at->timezone(company()->timezone)->translatedFormat(company()->date_format . ' ' . company()->time_format) }}
                        &middot; <span class="text-dark">{{ $formatDuration($period['seconds']) }}</span>
                        @if ($period['current'])
                            <span class="badge badge-primary ml-1">@lang('modules.tasks.statusNow')</span>
                        @endif
                    </li>
                @endforeach
            </ul>
        @else
            <p class="f-12 text-lightest mb-0">@lang('modules.tasks.statusTimelineEmpty')</p>
        @endif
    </div>

    <div class="d-flex flex-wrap p-20">
        @forelse ($task->history as $activ)
            <div class="card file-card w-100 rounded-0 border-0 comment p-2">
                <div class="card-horizontal">
                    <div class="card-img my-1 ml-0">
                        <img src="{{ $activ->user->image_url }}" alt="{{ $activ->user->name }}">
                    </div>
                    <div class="card-body border-0 pl-0 py-1 mb-2">
                        <div class="d-flex flex-grow-1">
                            <h4 class="card-title f-12 font-weight-normal text-dark mr-3 mb-1">
                                {{ __('modules.tasks.' . $activ->details) }} <a
                                    href="{{ route('employees.show', $activ->user_id) }}"
                                    class="text-darkest-grey">{{ $activ->user->name }}</a>
                            </h4>
                        </div>
                        <div class="card-text f-11 text-lightest text-justify">
                            @if (!is_null($activ->sub_task_id))
                                <span class="text-primary">{{ $activ->subTask->title }}</span>
                            @elseif (!is_null($activ->board_column_id))
                                <span class="badge badge-primary" style="background-color: {{ $activ->boardColumn->label_color }}">{{ $activ->boardColumn->column_name }}</span>
                            @endif

                            <span class="f-11 text-lightest">
                                {{ $activ->created_at->timezone(company()->timezone)->translatedFormat(company()->date_format .' '. company()->time_format)  }}</span>
                        </div>
                    </div>
                </div>
            </div>
        @empty
            <x-cards.no-record icon="history" :message="__('messages.noRecordFound')" />
        @endforelse

    </div>

</div>
<!-- TAB CONTENT END -->
