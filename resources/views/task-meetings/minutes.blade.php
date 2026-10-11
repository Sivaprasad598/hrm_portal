<div class="modal-header">
    <h5 class="modal-title">@lang('modules.tasks.meetingAddMinutes')</h5>
    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">×</span></button>
</div>
<div class="modal-body">
    <x-form id="meeting-minutes-form">
        <p class="f-13 text-dark-grey mb-0">
            <strong>{{ $meeting->event_name }}</strong> &middot;
            {{ $meeting->start_date_time->copy()->tz(company()->timezone)->translatedFormat(company()->date_format . ' ' . company()->time_format) }}
        </p>

        <x-forms.textarea fieldId="meeting_outcome" fieldName="meeting_outcome" fieldRequired="true"
                          :fieldLabel="__('modules.tasks.meetingOutcome')"/>

        <x-forms.label class="mt-3" fieldId="" :fieldLabel="__('modules.tasks.meetingFollowUpActions')"
                       :popover="__('modules.tasks.meetingFollowUpActionsInfo')"/>

        <div id="meeting-actions">
            <div class="row meeting-action-row mb-2">
                <div class="col-md-6">
                    <input type="text" class="form-control height-35 f-14" name="actions[0][title]"
                           placeholder="@lang('modules.tasks.meetingActionTitle')">
                </div>
                <div class="col-md-3">
                    <select class="form-control height-35 f-14" name="actions[0][assigned_to]">
                        <option value="">@lang('modules.tasks.meetingActionOwner')</option>
                        @foreach ($owners as $owner)
                            <option value="{{ $owner->id }}">{{ $owner->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <input type="text" class="form-control height-35 f-14 meeting-action-due" name="actions[0][due_date]"
                           placeholder="@lang('modules.tasks.meetingActionDue')" autocomplete="off">
                </div>
            </div>
        </div>

        <a href="javascript:;" id="add-meeting-action" class="f-13 text-primary"><i class="fa fa-plus mr-1"></i>@lang('modules.tasks.meetingAddAction')</a>
    </x-form>
</div>
<div class="modal-footer">
    <x-forms.button-cancel data-dismiss="modal" class="border-0 mr-3">@lang('app.cancel')</x-forms.button-cancel>
    <x-forms.button-primary id="save-meeting-minutes" icon="check">@lang('app.save')</x-forms.button-primary>
</div>

<script>
    function initActionDue(input) {
        datepicker(input, {
            position: 'bl',
            ...datepickerConfig
        });
    }

    initActionDue(document.querySelector('.meeting-action-due'));

    let meetingActionIndex = 1;

    $('#add-meeting-action').click(function () {
        let row = $('.meeting-action-row').first().clone();

        row.find('input, select').each(function () {
            $(this).attr('name', $(this).attr('name').replace(/\[\d+\]/, '[' + meetingActionIndex + ']')).val('');
        });

        $('#meeting-actions').append(row);
        initActionDue(row.find('.meeting-action-due')[0]);
        meetingActionIndex++;
    });

    $('#save-meeting-minutes').click(function () {
        $.easyAjax({
            url: "{{ route('task-meetings.store_minutes', $meeting->id) }}",
            container: '#meeting-minutes-form',
            type: 'POST',
            blockUI: true,
            disableButton: true,
            buttonSelector: '#save-meeting-minutes',
            data: $('#meeting-minutes-form').serialize(),
            success: function (response) {
                if (response.status == 'success') {
                    $(MODAL_LG).modal('hide');
                    $(document).trigger('meeting:changed', ['{{ $meeting->task_id }}']);
                }
            }
        });
    });
</script>
