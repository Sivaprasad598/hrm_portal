<div class="modal-header">
    <h5 class="modal-title" id="modelHeading">
        @lang('clan.attendance.attendanceRegularisation')
    </h5>
    <button type="button"  class="close" data-dismiss="modal" aria-label="Close"><span
            aria-hidden="true">×</span></button>
</div>
<div class="modal-body">
    <div class="row">
        <div class="col-md-12 mb-4">
            <x-employee :user="$attendanceUser" />
        </div>
    </div>

    <div class="row">
        <div class="col-sm-12">

            <h5 class="f-w-500 f-15 d-flex justify-content-between">{{ __('app.date').' - '.\Carbon\Carbon::parse($date)->translatedFormat(company()->date_format) }} <span class="badge badge-info f-14" style="background-color: {{ $attendanceSettings->color }}">{{ $attendanceSettings->shift_name }}</span></h5>

            <x-form id="attendance-container">


                <input type="hidden" name="attendance_date" value="{{ $date }}">

                <div class="row">
                    <div class="col-lg-6 col-md-6">
                        <div class="bootstrap-timepicker timepicker">
                            <x-forms.text class="a-timepicker"
                                          :fieldLabel="__('modules.attendance.clock_in')"
                                          :fieldPlaceholder="__('placeholders.hours')"
                                          fieldName="clock_in_time"
                                          fieldId="clock-in-time" :fieldReadOnly="true"
                                          :fieldValue="isset($rows) && $rows->clock_in_time
                                              ? \Carbon\Carbon::createFromFormat('Y-m-d H:i:s', $rows->clock_in_time)
                                                  ->timezone(company()->timezone)
                                                  ->translatedFormat(company()->time_format)
                                              : ''" />
                        </div>
                    </div>

                    <div class="col-lg-6 col-md-6">
                        <x-forms.text :fieldLabel="__('modules.attendance.working_from')"
                                        :fieldPlaceholder="__('placeholders.hours')"
                                        fieldName="work_from_type"
                                        fieldId="work_from_type"
                                        :fieldValue="$rows->working_from" :fieldReadOnly="true" />
                    </div>

                    @if ($rows->clock_out_time)
                        <div class="col-lg-6 col-md-6">
                            <div class="bootstrap-timepicker timepicker">
                                <x-forms.text :fieldLabel="__('modules.attendance.clock_out')"
                                            fieldName="clock_out_time"
                                            fieldId="clock-out" :fieldReadOnly="true"
                                            :fieldValue="isset($rows) && $rows->clock_out_time
                                                ? \Carbon\Carbon::createFromFormat('Y-m-d H:i:s', $rows->clock_out_time)
                                                    ->timezone(company()->timezone)
                                                    ->translatedFormat(company()->time_format)
                                                : ''"/>
                            </div>
                        </div>
                    @endif

                    @if ($rows->other_location)
                        <div class="col-lg-6 col-md-6">
                            <x-forms.text fieldId="other_location" :fieldLabel="__('modules.attendance.otherPlace')" fieldName="other_location" :fieldValue="$rows->other_location" :fieldReadOnly="true" >
                            </x-forms.text>
                        </div>
                    @endif

                    @if ($rows->reason)
                        <div class="col-lg-12 col-md-12">
                            <x-forms.textarea :fieldLabel="__('app.reason')"
                                              fieldName="reason"
                                              fieldId="reason"
                                              :fieldValue="$rows->reason"
                                              :fieldReadOnly="true">
                            </x-forms.textarea>
                        </div>
                    @endif
                </div>

                <input type="hidden" name="user_id" id="user_id" value="{{ $attendanceUser->id }}">
            </x-form>
        </div>
    </div>

</div>
<div class="modal-footer">
    <x-forms.button-cancel data-dismiss="modal" class="border-0 mr-3">@lang('app.close')</x-forms.button-cancel>
    @if (user()->id == $rows->user_id || in_array('admin', user_roles()))
        <x-forms.button-primary id="delete-regularisation" icon="times" data-request-id="{{ $rows->id }}">@lang('modules.attendance.deleteRequestChange')</x-forms.button-primary>
    @endif

</div>

<script>
    $('.select-picker').selectpicker();

    $(document).ready(function() {
        $('#clock-in-time').attr('readonly', true);

        $('#clock-out').attr('readonly', true);

        $('#work_from_type').change(function(){
            ($(this).val() == 'other') ? $('#other_place').show() : $('#other_place').hide();
        });

        $('#delete-regularisation').click(function() {
            var requestId = $(this).data('request-id');
            var url = "{{ route('attendances.delete_request', ':id') }}";
            url = url.replace(':id', requestId);

            $.easyAjax({
                url: url,
                type: 'DELETE',
                container: '#attendance-container',
                blockUI: true,
                disableButton: true,
                buttonSelector: "#save-shift",
                data: $('#attendance-container').serialize(),
                success: function(response) {
                    if (response.status == 'success') {
                        $(MODAL_LG).modal('hide');
                        showTable();
                    }
                }
            })
        });
    });

</script>
