<!-- TAB CONTENT START -->
<div class="tab-pane fade show active" role="tabpanel">
    <div class="p-20">
        @php
            $canRaise = !in_array($task->boardColumn->slug, ['completed', 'waiting_approval'])
                && (in_array(user()->id, $taskUsers) || $task->canChangeStatus());
        @endphp

        @if ($canRaise)
            <x-forms.button-secondary icon="plus" data-task-id="{{ $task->id }}" class="open-roadblock-modal mb-3">
                @lang('modules.tasks.raiseRoadblock')
            </x-forms.button-secondary>
        @endif

        @forelse ($blockers as $blocker)
            <div @class(['card border rounded mb-3', 'border-danger' => $blocker->status == 'open' && $blocker->urgency == 'urgent'])>
                <div class="card-body p-3">
                    <div class="d-flex flex-wrap justify-content-between align-items-start">
                        <div class="f-13 text-dark-grey">
                            @if ($blocker->status == 'open')
                                <span class="badge badge-warning">@lang('modules.tasks.roadblockOpen')</span>
                            @elseif ($blocker->status == 'resolved')
                                <span class="badge badge-success">@lang('modules.tasks.roadblockResolved')</span>
                            @else
                                <span class="badge badge-secondary">@lang('modules.tasks.roadblockCancelled')</span>
                            @endif

                            @if ($blocker->urgency == 'urgent')
                                <span class="badge badge-danger">🚩 @lang('modules.tasks.roadblockUrgent')</span>
                            @endif

                            @if ($blocker->isOverdue())
                                <span class="badge badge-danger">@lang('modules.tasks.roadblockOverdue')</span>
                            @endif

                            <span class="ml-1">
                                @lang('modules.tasks.roadblockWaitingOn')
                                <strong>{{ $blocker->blockingUser?->name ?? '--' }}</strong>
                                &middot; @lang('modules.tasks.roadblockRaisedBy') {{ $blocker->raisedBy?->name ?? '--' }}
                                &middot; {{ $blocker->created_at->timezone(company()->timezone)->translatedFormat(company()->date_format . ' ' . company()->time_format) }}
                            </span>
                        </div>

                        <div>
                            @if ($blocker->canResolve())
                                <x-forms.button-primary icon="check" class="close-roadblock btn-xs mr-1"
                                                        data-blocker-id="{{ $blocker->id }}" data-action="resolve">
                                    @lang('modules.tasks.roadblockResolve')
                                </x-forms.button-primary>
                            @endif
                            @if ($blocker->canCancel())
                                <x-forms.button-secondary icon="times" class="close-roadblock btn-xs"
                                                          data-blocker-id="{{ $blocker->id }}" data-action="cancel">
                                    @lang('modules.tasks.roadblockCancel')
                                </x-forms.button-secondary>
                            @endif
                        </div>
                    </div>

                    <p class="f-13 text-dark mt-2 mb-1">{!! nl2br(e($blocker->need)) !!}</p>

                    <div class="f-12 text-lightest">
                        @if ($blocker->needed_by)
                            <span @class(['text-danger f-w-500' => $blocker->isOverdue()])>
                                @lang('modules.tasks.roadblockNeededBy'): {{ $blocker->needed_by->translatedFormat(company()->date_format) }}
                            </span>
                        @endif
                        @if ($blocker->linkedTask)
                            &middot; <a href="{{ route('tasks.show', $blocker->linked_task_id) }}" class="openRightModal">
                                <i class="fa fa-link"></i> {{ $blocker->linkedTask->heading }}</a>
                        @endif
                    </div>

                    @if ($blocker->status != 'open')
                        <div class="f-12 text-dark-grey mt-2 border-top pt-2">
                            {{ $blocker->status == 'resolved' ? __('modules.tasks.roadblockResolved') : __('modules.tasks.roadblockCancelled') }}
                            @if ($blocker->resolvedBy) &middot; {{ $blocker->resolvedBy->name }} @endif
                            @if ($blocker->resolved_at)
                                &middot; {{ $blocker->resolved_at->timezone(company()->timezone)->translatedFormat(company()->date_format . ' ' . company()->time_format) }}
                                &middot; {{ $blocker->resolved_at->diffForHumans($blocker->created_at, true) }}
                            @endif
                            @if ($blocker->resolution_note)
                                <div class="mt-1">{!! nl2br(e($blocker->resolution_note)) !!}</div>
                            @endif
                        </div>
                    @endif
                </div>
            </div>
        @empty
            <x-cards.no-record icon="hand-paper" :message="__('messages.noRecordFound')"/>
        @endforelse
    </div>
</div>
<!-- TAB CONTENT END -->
