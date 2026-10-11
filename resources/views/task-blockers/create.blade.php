<div class="modal-header">
    <h5 class="modal-title">@lang('modules.tasks.raiseRoadblock')</h5>
    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">×</span></button>
</div>
<div class="modal-body">
    <x-form id="save-roadblock-form">
        <input type="hidden" name="task_id" value="{{ $task->id }}">

        <p class="f-13 text-dark-grey mb-0">
            @lang('app.task'): <strong>{{ $task->heading }}</strong>
            @if ($task->task_short_code) <span class="text-lightest">#{{ $task->task_short_code }}</span> @endif
        </p>

        <div class="row">
            <div class="col-md-12">
                <x-forms.textarea fieldId="roadblock_need" fieldName="need" fieldRequired="true"
                                  :fieldLabel="__('modules.tasks.roadblockNeed')"
                                  :fieldPlaceholder="__('modules.tasks.roadblockNeedPlaceholder')"/>
            </div>

            <div class="col-md-12">
                <x-forms.select fieldId="roadblock_user_ids" fieldName="user_ids[]" :multiple="true" search="true"
                                fieldRequired="true" :fieldLabel="__('modules.tasks.roadblockWaitingOn')">
                    @foreach ($employees as $employee)
                        <x-user-option :user="$employee"/>
                    @endforeach
                </x-forms.select>
            </div>

            <div class="col-md-6">
                <x-forms.datepicker fieldId="roadblock_needed_by" fieldName="needed_by"
                                    :fieldLabel="__('modules.tasks.roadblockNeededBy')"
                                    :fieldPlaceholder="__('placeholders.date')"/>
            </div>

            <div class="col-md-6">
                <x-forms.label class="my-3" fieldId="" :fieldLabel="__('modules.tasks.roadblockUrgency')"/>
                <div class="d-flex">
                    <x-forms.radio fieldId="roadblock_urgency_normal" :fieldLabel="__('modules.tasks.roadblockNormal')"
                                   fieldName="urgency" fieldValue="normal" checked="true"/>
                    <x-forms.radio fieldId="roadblock_urgency_urgent" :fieldLabel="'🚩 ' . __('modules.tasks.roadblockUrgent')"
                                   fieldName="urgency" fieldValue="urgent"/>
                </div>
            </div>

            <div class="col-md-12">
                <x-forms.select fieldId="roadblock_linked_task_id" fieldName="linked_task_id" search="true"
                                :fieldLabel="__('modules.tasks.roadblockLinkedTask')"
                                :popover="__('modules.tasks.roadblockLinkedTaskInfo')">
                    <option value="">--</option>
                </x-forms.select>
            </div>
        </div>
    </x-form>
</div>
<div class="modal-footer">
    <x-forms.button-cancel data-dismiss="modal" class="border-0 mr-3">@lang('app.cancel')</x-forms.button-cancel>
    <x-forms.button-primary id="save-roadblock" icon="check">@lang('app.save')</x-forms.button-primary>
</div>

<script>
    $('#save-roadblock-form .select-picker').selectpicker();

    datepicker('#roadblock_needed_by', {
        position: 'bl',
        minDate: new Date(),
        ...datepickerConfig
    });

    // Offer the open tasks of the people being waited on
    $('#roadblock_user_ids').on('changed.bs.select', function () {
        $.easyAjax({
            url: "{{ route('task-blockers.user_tasks') }}",
            type: 'GET',
            data: {user_ids: $(this).val(), task_id: '{{ $task->id }}'},
            success: function (response) {
                $('#roadblock_linked_task_id').html(response.data).selectpicker('refresh');
            }
        });
    });

    $('#save-roadblock').click(function () {
        $.easyAjax({
            url: "{{ route('task-blockers.store') }}",
            container: '#save-roadblock-form',
            type: 'POST',
            blockUI: true,
            disableButton: true,
            buttonSelector: '#save-roadblock',
            data: $('#save-roadblock-form').serialize(),
            success: function (response) {
                if (response.status == 'success') {
                    $(MODAL_LG).modal('hide');
                    $(document).trigger('roadblock:changed', ['{{ $task->id }}']);
                }
            }
        });
    });
</script>
