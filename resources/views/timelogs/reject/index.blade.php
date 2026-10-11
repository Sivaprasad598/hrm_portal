<div class="modal-header">
    <h5 class="modal-title" id="modelHeading">@lang('app.timelogsRejectReason')</h5>
    <button type="button"  class="close" data-dismiss="modal" aria-label="Close"><span
            aria-hidden="true">×</span></button>
</div>
<div class="modal-body">
    <div class="portlet-body">
        <x-form id="followUpForm" method="POST" class="ajax-form">
            <div class="form-body">
                <div class="row">
                    <div class="col-md-12">
                        <div class="form-group my-3">
                            <x-forms.textarea class="mr-0 mr-lg-2 mr-md-2" :fieldLabel="__('app.reason')"
                                fieldName="reason" fieldId="reason" fieldRequired="true">
                            </x-forms.textarea>
                        </div>
                    </div>
                </div>
            </div>
        </x-form>
    </div>
</div>
<div class="modal-footer">
    <x-forms.button-cancel data-dismiss="modal" class="border-0 mr-3">@lang('app.close')</x-forms.button-cancel>
    <x-forms.button-primary id="save-timelog" icon="check">@lang('app.save')</x-forms.button-primary>
</div>

<script>
    // save timelog
    $('#save-timelog').click(function() {
        let url = '{{ route("timelogs.timelog_action") }}';
        let timelogId = '{{ $timelogID }}';
        let userId = $('.timelog-action-reject').data('user-id');
        let reason = $('#reason').val();

        $.easyAjax({
            url: url,
            type: "POST",
            blockUI: true,
            data: {
                'timelogId': timelogId,
                '_token': '{{ csrf_token() }}',
                'reason': reason,
                'userId': userId
            },
            success: function(response) {
                if (response.status == "success") {
                    window.location.reload();
                }
            }
        })
    });
</script>
