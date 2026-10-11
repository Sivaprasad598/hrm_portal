<!-- TAB CONTENT START -->
<div class="tab-pane fade show active" role="tabpanel">
    <div class="p-20">
        @if ($canOrganiseMeeting)
            <x-forms.button-secondary icon="users" data-task-id="{{ $task->id }}" class="open-meeting-modal mb-3">
                @lang('modules.tasks.requestMeeting')
            </x-forms.button-secondary>
        @endif

        @forelse ($meetings as $meeting)
            @php
                $tz = company()->timezone;
                $myAttendance = $meeting->attendee->firstWhere('user_id', user()->id);
                $canManage = $meeting->host == user()->id || in_array('admin', user_roles()) || $task->isProjectAdmin();
                $isPast = $meeting->end_date_time->isPast();
            @endphp
            <div @class(['card border rounded mb-3', 'bg-light' => $meeting->status != 'pending'])>
                <div class="card-body p-3">
                    <div class="d-flex flex-wrap justify-content-between align-items-start">
                        <div>
                            <h5 class="f-14 f-w-500 text-dark mb-1">
                                <i class="fa fa-users mr-1 text-lightest"></i>{{ $meeting->event_name }}
                                @if ($meeting->status == 'completed')
                                    <span class="badge badge-success ml-1">@lang('modules.tasks.meetingCompleted')</span>
                                @elseif ($meeting->status == 'cancelled')
                                    <span class="badge badge-secondary ml-1">@lang('modules.tasks.meetingCancelled')</span>
                                @elseif ($isPast)
                                    <span class="badge badge-warning ml-1">@lang('modules.tasks.meetingAwaitingMinutes')</span>
                                @else
                                    <span class="badge badge-primary ml-1">@lang('modules.tasks.meetingUpcoming')</span>
                                @endif
                            </h5>
                            <div class="f-12 text-dark-grey">
                                <i class="fa fa-clock mr-1"></i>
                                {{ $meeting->start_date_time->copy()->tz($tz)->translatedFormat(company()->date_format . ' ' . company()->time_format) }}
                                – {{ $meeting->end_date_time->copy()->tz($tz)->translatedFormat(company()->time_format) }}
                                @if ($meeting->event_link)
                                    &middot; <a href="{{ $meeting->event_link }}" target="_blank" rel="noopener"><i class="fa fa-video"></i> @lang('modules.tasks.meetingLink')</a>
                                @elseif ($meeting->where && $meeting->where != '--')
                                    &middot; <i class="fa fa-map-marker-alt"></i> {{ $meeting->where }}
                                @endif
                                &middot; @lang('modules.tasks.meetingOrganiser'): {{ $meeting->user?->name ?? '--' }}
                                @if ($meeting->followUpOf)
                                    &middot; @lang('modules.tasks.meetingFollowUpOf'): {{ $meeting->followUpOf->event_name }}
                                @endif
                            </div>
                        </div>

                        <div class="text-right">
                            @if ($meeting->status == 'pending')
                                @if ($myAttendance && $meeting->host != user()->id && !$isPast)
                                    <x-forms.button-primary icon="check" class="meeting-respond btn-xs mr-1 {{ $myAttendance->response == 'accepted' ? 'disabled' : '' }}"
                                                            data-meeting-id="{{ $meeting->id }}" data-response="accepted">
                                        @lang('modules.tasks.meetingAccept')
                                    </x-forms.button-primary>
                                    <x-forms.button-secondary icon="times" class="meeting-respond btn-xs mr-1 {{ $myAttendance->response == 'declined' ? 'disabled' : '' }}"
                                                              data-meeting-id="{{ $meeting->id }}" data-response="declined">
                                        @lang('modules.tasks.meetingDecline')
                                    </x-forms.button-secondary>
                                @endif
                                @if ($canManage)
                                    <x-forms.button-primary icon="clipboard-check" class="meeting-minutes btn-xs mr-1" data-meeting-id="{{ $meeting->id }}">
                                        @lang('modules.tasks.meetingAddMinutes')
                                    </x-forms.button-primary>
                                    <x-forms.button-secondary icon="calendar-alt" class="edit-meeting btn-xs mr-1" data-meeting-id="{{ $meeting->id }}">
                                        @lang('modules.tasks.meetingReschedule')
                                    </x-forms.button-secondary>
                                    <x-forms.button-secondary icon="ban" class="meeting-cancel btn-xs" data-meeting-id="{{ $meeting->id }}">
                                        @lang('modules.tasks.meetingCancel')
                                    </x-forms.button-secondary>
                                @endif
                            @elseif ($meeting->status == 'completed' && $canOrganiseMeeting)
                                <x-forms.button-secondary icon="redo" class="open-meeting-modal btn-xs"
                                                          data-task-id="{{ $task->id }}" data-follow-up-of="{{ $meeting->id }}">
                                    @lang('modules.tasks.scheduleFollowUp')
                                </x-forms.button-secondary>
                            @endif
                        </div>
                    </div>

                    @if ($meeting->description)
                        <div class="f-13 text-dark mt-2">
                            <span class="text-lightest f-12">@lang('modules.tasks.meetingAgenda'):</span>
                            {!! nl2br(e(strip_tags($meeting->description))) !!}
                        </div>
                    @endif

                    <div class="mt-2">
                        @foreach ($meeting->attendee as $attendee)
                            @continue(!$attendee->user)
                            <span class="badge badge-light border mr-1 mb-1 p-1 f-11">
                                <img src="{{ $attendee->user->image_url }}" class="rounded-circle mr-1" width="16" height="16" alt="">
                                {{ $attendee->user->name }}
                                @if ($attendee->response == 'accepted')
                                    <i class="fa fa-check-circle text-success ml-1" title="@lang('modules.tasks.meetingAccepted')"></i>
                                @elseif ($attendee->response == 'declined')
                                    <i class="fa fa-times-circle text-danger ml-1" title="@lang('modules.tasks.meetingDeclined')"></i>
                                @else
                                    <i class="fa fa-question-circle text-lightest ml-1" title="@lang('modules.tasks.meetingPending')"></i>
                                @endif
                            </span>
                        @endforeach
                    </div>

                    @if ($meeting->status == 'completed' && $meeting->meeting_outcome)
                        <div class="f-13 text-dark mt-2 border-top pt-2">
                            <span class="text-lightest f-12">@lang('modules.tasks.meetingOutcome')
                                &middot; {{ $meeting->completed_at?->tz($tz)->translatedFormat(company()->date_format . ' ' . company()->time_format) }}</span>
                            <div>{!! nl2br(e($meeting->meeting_outcome)) !!}</div>
                        </div>
                    @endif
                </div>
            </div>
        @empty
            <x-cards.no-record icon="users" :message="__('messages.noRecordFound')"/>
        @endforelse
    </div>
</div>
<!-- TAB CONTENT END -->
