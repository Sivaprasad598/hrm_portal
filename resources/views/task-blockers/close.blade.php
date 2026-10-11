<div class="modal-header">
    <h5 class="modal-title">
        {{ $action == 'cancel' ? __('modules.tasks.roadblockCancel') : __('modules.tasks.roadblockResolve') }}
    </h5>
    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">×</span></button>
</div>
<div class="modal-body">
    <x-form id="close-roadblock-form">
        <input type="hidden" name="action" value="{{ $action }}">
        <input type="hidden" name="_method" value="PUT">

        <div class="f-13 text-dark-grey">
            <p class="mb-1">@lang('app.task'): <strong>{{ $blocker->task->heading }}</strong></p>
            <p class="mb-1">@lang('modules.tasks.roadblockRaisedBy'): {{ $blocker->raisedBy?->name ?? '--' }}
                &middot; @lang('modules.tasks.roadblockWaitingOn'): {{ $blocker->blockingUser?->name ?? '--' }}</p>
            <p class="mb-0">@lang('modules.tasks.roadblockNeed'): {!! nl2br(e($blocker->need)) !!}</p>
        </div>

        <x-forms.textarea fieldId="roadblock_note" fieldName="note"
                          :fieldLabel="__('modules.tasks.roadblockResolutionNote')"/>
    </x-form>
</div>
<div class="modal-footer">
    <x-forms.button-cancel data-dismiss="modal" class="border-0 mr-3">@lang('app.close')</x-forms.button-cancel>
    <x-forms.button-primary id="close-roadblock" icon="check">
        {{ $action == 'cancel' ? __('modules.tasks.roadblockCancel') : __('modules.tasks.roadblockResolve') }}
    </x-forms.button-primary>
</div>

<script>
    $('#close-roadblock').click(function () {
        $.easyAjax({
            url: "{{ route('task-blockers.update', $blocker->id) }}",
            container: '#close-roadblock-form',
            type: 'POST',
            blockUI: true,
            disableButton: true,
            buttonSelector: '#close-roadblock',
            data: $('#close-roadblock-form').serialize(),
            success: function (response) {
                if (response.status == 'success') {
                    $(MODAL_LG).modal('hide');
                    $(document).trigger('roadblock:changed', ['{{ $blocker->task_id }}']);
                }
            }
        });
    });
</script>
