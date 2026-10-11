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
                                fieldId="clock-in-time" :fieldReadOnly="true"
                                :fieldValue="(!is_null($row->clock_in_time)) ? \Carbon\Carbon::createFromFormat('Y-m-d H:i:s', $row->clock_in_time)->timezone(company()->timezone)->translatedFormat(company()->time_format) : ''" />
                        </div>
                    </div>

                    <div class="col-lg-6 col-md-6">
                        <div class="bootstrap-timepicker timepicker">
                            <x-forms.text :fieldLabel="__('modules.attendance.clock_out')"
                                :fieldPlaceholder="__('placeholders.hours')" fieldName="clock_out_time"
                                fieldId="clock-out" :fieldReadOnly="true"
                                :fieldValue="isset($row) && $row->clock_out_time
                                ? \Carbon\Carbon::createFromFormat('Y-m-d H:i:s', $row->clock_out_time)
                                    ->timezone(company()->timezone)
                                    ->translatedFormat(company()->time_format)
                                : ''"/>
                        </div>
                    </div>

                    <div class="col-lg-6 col-md-6">
                        <x-forms.text :fieldLabel="__('modules.attendance.working_from')"
                                    :fieldPlaceholder="__('placeholders.hours')"
                                    fieldName="work_from_type"
                                    fieldId="work_from_type"
                                    :fieldValue="ucfirst($row->working_from)" :fieldReadOnly="true" />
                    </div>

                    @if ($row->other_location)
                        <div class="col-lg-6 col-md-6">
                            <x-forms.text fieldId="other_location" :fieldLabel="__('modules.attendance.otherPlace')" fieldName="other_location" :fieldValue="$row->other_location" :fieldReadOnly="true" >
                            </x-forms.text>
                        </div>
                    @endif

                    @if ($row->reason)
                        <div class="col-lg-12 col-md-12">
                            <x-forms.textarea :fieldLabel="__('app.reason')"
                                              fieldName="reason"
                                              fieldId="reason"
                                              :fieldValue="$row->reason"
                                              :fieldReadOnly="true">
                            </x-forms.textarea>
                        </div>
                    @endif

                    <div class="col-lg-6 col-md-6">
                        <x-forms.text :fieldLabel="__('app.status')"
                                    fieldName="status"
                                    fieldId="status"
                                    :fieldValue="ucfirst($row->status)" :fieldReadOnly="true" />
                    </div>

                    @if ($row->status == 'reject' && $row->reject_reason)
                        <div class="col-lg-12 col-md-12">
                            <x-forms.textarea :fieldLabel="__('clan.attendance.rejectReason')"
                                              fieldName="reject_reason"
                                              fieldId="reject_reason"
                                              :fieldValue="$row->reject_reason"
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
</div>

<script>
    $('.select-picker').selectpicker();

    $(document).ready(function() {
        $('#clock-in-time').attr('readonly', true);
        $('#clock-out').attr('readonly', true);
        $('#work_from_type').attr('readonly', true);
        $('#other_location').attr('readonly', true);
        $('#reason').attr('readonly', true);
        $('#status').attr('readonly', true);
        $('#reject_reason').attr('readonly', true);
    });

</script>

