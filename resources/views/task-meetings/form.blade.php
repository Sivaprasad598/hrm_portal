@php
    // Used for both "Request Meeting" (create / follow-up) and "Reschedule" (edit)
    $isEdit = !is_null($meeting);
@endphp
<div class="modal-header">
    <h5 class="modal-title">
        {{ $isEdit ? __('modules.tasks.meetingReschedule') : ($followUpOf ? __('modules.tasks.scheduleFollowUp') : __('modules.tasks.requestMeeting')) }}
    </h5>
    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">×</span></button>
</div>
<div class="modal-body">
    <x-form id="save-meeting-form">
        <input type="hidden" name="task_id" value="{{ $task->id }}">
        @if ($followUpOf)
            <input type="hidden" name="follow_up_of" value="{{ $followUpOf->id }}">
        @endif
        @if ($isEdit)
            <input type="hidden" name="_method" value="PUT">
        @endif

        <p class="f-13 text-dark-grey mb-0">@lang('app.task'): <strong>{{ $task->heading }}</strong></p>

        <div class="row">
            <div class="col-md-12">
                <x-forms.text fieldId="meeting_event_name" fieldName="event_name" fieldRequired="true"
                              :fieldLabel="__('modules.tasks.meetingTitle')" :fieldValue="$defaultTitle"/>
            </div>

            <div class="col-md-12">
                <x-forms.textarea fieldId="meeting_description" fieldName="description"
                                  :fieldLabel="__('modules.tasks.meetingAgenda')"
                                  :fieldPlaceholder="__('modules.tasks.meetingAgendaPlaceholder')"
                                  :fieldValue="strip_tags($defaultAgenda)"/>
            </div>

            <div class="col-md-4">
                <x-forms.datepicker fieldId="meeting_date" fieldName="meeting_date" fieldRequired="true"
                                    :fieldLabel="__('modules.tasks.meetingDate')"
                                    :fieldPlaceholder="__('placeholders.date')"
                                    :fieldValue="$defaultStart->format(company()->date_format)"/>
            </div>

            <div class="col-md-4">
                <div class="bootstrap-timepicker timepicker">
                    <x-forms.text fieldId="meeting_start_time" fieldName="start_time" fieldRequired="true"
                                  :fieldLabel="__('modules.tasks.meetingStartTime')"
                                  :fieldPlaceholder="__('placeholders.hours')"
                                  :fieldValue="$defaultStart->format(company()->time_format)"/>
                </div>
            </div>

            <div class="col-md-4">
                <x-forms.select fieldId="meeting_duration" fieldName="duration" fieldRequired="true"
                                :fieldLabel="__('modules.tasks.meetingDuration')">
                    @foreach ($durations as $minutes)
                        <option value="{{ $minutes }}" @selected($minutes == $defaultDuration)>
                            {{ __('modules.tasks.meetingMinutes', ['count' => $minutes]) }}
                        </option>
                    @endforeach
                </x-forms.select>
            </div>

            <div class="col-md-12">
                <x-forms.select fieldId="meeting_attendee_ids" fieldName="attendee_ids[]" :multiple="true" search="true"
                                fieldRequired="true" :fieldLabel="__('modules.tasks.meetingAttendees')">
                    @foreach ($employees as $employee)
                        @continue($employee->id == user()->id)
                        <x-user-option :user="$employee" :selected="in_array($employee->id, $selectedAttendees)"/>
                    @endforeach
                </x-forms.select>
            </div>

            <div class="col-md-6">
                <x-forms.url fieldId="meeting_event_link" fieldName="event_link"
                             :fieldLabel="__('modules.tasks.meetingLink')" fieldPlaceholder="https://meet.google.com/..."
                             :fieldValue="$meeting?->event_link"/>
            </div>

            <div class="col-md-6">
                <x-forms.text fieldId="meeting_where" fieldName="where"
                              :fieldLabel="__('modules.tasks.meetingLocation')"
                              :fieldPlaceholder="__('modules.tasks.meetingLocationPlaceholder')"
                              :fieldValue="$isEdit && !in_array($meeting->where, ['--', __('modules.tasks.meetingOnline')]) ? $meeting->where : ''"/>
            </div>

            <div class="col-md-12 mt-3">
                <x-forms.checkbox fieldId="meeting_send_reminder" fieldName="send_reminder" fieldValue="1"
                                  :fieldLabel="__('modules.tasks.meetingRemind')"
                                  :checked="!$isEdit || $meeting->send_reminder == 'yes'"/>
            </div>
        </div>
    </x-form>
</div>
<div class="modal-footer">
    <x-forms.button-cancel data-dismiss="modal" class="border-0 mr-3">@lang('app.cancel')</x-forms.button-cancel>
    <x-forms.button-primary id="save-meeting" icon="check">@lang('app.save')</x-forms.button-primary>
</div>

<script>
    $('#save-meeting-form .select-picker').selectpicker();

    datepicker('#meeting_date', {
        position: 'bl',
        ...datepickerConfig
    });

    $('#meeting_start_time').timepicker({
        @if (company()->time_format == 'H:i')
            showMeridian: false,
        @endif
    });

    $('#save-meeting').click(function () {
        $.easyAjax({
            url: "{{ $isEdit ? route('task-meetings.update', $meeting->id) : route('task-meetings.store') }}",
            container: '#save-meeting-form',
            type: 'POST',
            blockUI: true,
            disableButton: true,
            buttonSelector: '#save-meeting',
            data: $('#save-meeting-form').serialize(),
            success: function (response) {
                if (response.status == 'success') {
                    $(MODAL_LG).modal('hide');
                    $(document).trigger('meeting:changed', ['{{ $task->id }}']);
                }
            }
        });
    });
</script>
