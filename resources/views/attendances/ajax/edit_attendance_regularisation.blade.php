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
                            <x-forms.text class="a-timepicker" :fieldLabel="__('modules.attendance.clock_in')"
                                :fieldPlaceholder="__('placeholders.hours')" fieldName="clock_in_time"
                                fieldId="clock-in-time" fieldRequired="true"
                                :fieldValue="(!is_null($row->clock_in_time)) ? \Carbon\Carbon::createFromFormat('Y-m-d H:i:s', $row->clock_in_time)->timezone(company()->timezone)->translatedFormat(company()->time_format) : ''" />
                        </div>
                    </div>

                    <div class="col-lg-6 col-md-6">
                        <div class="bootstrap-timepicker timepicker">
                            <x-forms.text :fieldLabel="__('modules.attendance.clock_out')"
                                :fieldPlaceholder="__('placeholders.hours')" fieldName="clock_out_time"
                                fieldId="clock-out" :fieldValue="isset($row) && $row->clock_out_time
                                ? \Carbon\Carbon::createFromFormat('Y-m-d H:i:s', $row->clock_out_time)
                                    ->timezone(company()->timezone)
                                    ->translatedFormat(company()->time_format)
                                : ''"/>
                        </div>
                    </div>

                    <div class="col-lg-6 col-md-6">
                        <x-forms.select fieldId="working_from" :fieldLabel="__('modules.attendance.working_from')" fieldName="working_from" fieldRequired="true" search="true">
                            <option @if ($row->working_from == 'office') selected @endif value="office">@lang('modules.attendance.office')</option>
                            <option @if ($row->working_from == 'home') selected @endif value="home">@lang('modules.attendance.home')</option>
                            <option @if ($row->working_from == 'other') selected @endif value="other">@lang('modules.attendance.other')</option>
                        </x-forms.select>
                    </div>

                    <div class="col-lg-12 col-md-12">
                        <x-forms.textarea :fieldLabel="__('app.reason')"
                                          fieldName="reason"
                                          fieldId="reason"
                                          :fieldRequired="true"
                                          :fieldValue="$row->reason ?? ''"
                                          fieldPlaceholder="Enter reason for attendance regularisation">
                        </x-forms.textarea>
                    </div>

                    <div class="col-lg-6 col-md-6" id="other_place" style="display:none">
                        <x-forms.text fieldId="other_location" :fieldLabel="__('modules.attendance.otherPlace')" fieldName="other_location" fieldRequired="true" :fieldValue="$row->other_location"></x-forms.text>
                    </div>

                </div>

                <input type="hidden" name="user_id" id="user_id" value="{{ $attendanceUser->id }}">
            </x-form>
        </div>
    </div>

</div>
<div class="modal-footer">
    <x-forms.button-cancel data-dismiss="modal" class="border-0 mr-3">@lang('app.close')</x-forms.button-cancel>
    <x-forms.button-primary id="save-attendance-regularisation" icon="check">@lang('app.save')</x-forms.button-primary>
</div>

<script>
    $('.select-picker').selectpicker();

    $(document).ready(function() {

        $('#clock-in-time').timepicker({
            @if(company()->time_format == 'H:i')
            showMeridian: false,
            @endif
            minuteStep: 1
        });
        $('#clock-out').timepicker({
            @if(company()->time_format == 'H:i')
            showMeridian: false,
            @endif
            minuteStep: 1,
            defaultTime: false
        });

        if ($('#working_from').val() == 'other') {
            $('#other_place').show();
        }

        $('#working_from').change(function(){
            if ($(this).val() == 'other') {
                $('#other_place').show();
            } else {
                $('#other_place').hide();
            }
        });

        $('#save-attendance-regularisation').click(function () {

            var url = "{{route('attendances.update_attendance_regularisation', $row->id)}}";
            $.easyAjax({
                url: url,
                type: "POST",
                container: '#attendance-container',
                blockUI: true,
                disableButton: true,
                buttonSelector: "#save-attendance-regularisation",
                data: $('#attendance-container').serialize(),
                success: function (response) {
                    if(response.status == 'success'){
                        $(MODAL_XL).modal('hide');
                        $(MODAL_LG).modal('hide');
                        showTable();
                    }
                }
            })
        });
    });

</script>
