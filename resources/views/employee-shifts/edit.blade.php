<link rel="stylesheet" href="{{ asset('vendor/css/bootstrap-colorpicker.css') }}" />

@if ($employeeShift->shift_type == 'strict')
<style>
    .flexible-timing-field {
        display: none
    }
</style>
@else
<style>
    .strict-timing-field {
        display: none
    }
</style>
@endif


<div class="modal-header">
    <h5 class="modal-title">@lang('app.updateShift')</h5>
    <button type="button" class="close" data-dismiss="modal" aria-hidden="true">×</button>
</div>

<div class="modal-body">
    <div class="portlet-body">
        <x-form id="createTicket" method="PUT" class="ajax-form">
            <div class="form-group">
                <div class="row">
                    <div class="col-lg-12">
                        <h4 class="heading-h4">@lang('modules.attendance.shiftType'): {{ ($employeeShift->shift_type == 'strict') ? __('modules.attendance.strictTiming') : __('modules.attendance.flexibleTiming') }}</h4>
                        <input type="hidden" value="{{ $employeeShift->shift_type }}" name="shift_type">

                        <div class="bootstrap-timepicker">
                            <x-forms.text :fieldLabel="__('modules.attendance.shiftName')" :fieldPlaceholder="__('placeholders.shiftName')" fieldName="shift_name"
                                fieldId="shift_name" fieldRequired="true" :fieldValue="$employeeShift->shift_name" />
                        </div>
                    </div>

                    <div class="col-md-6">
                        <x-forms.text :fieldLabel="__('modules.attendance.shiftShortCode')" fieldName="shift_short_code" fieldId="shift_short_code"
                            :fieldPlaceholder="__('placeholders.shiftShortCode')" :fieldValue="$employeeShift->shift_short_code" fieldRequired="true" />
                    </div>

                    <div class="col-md-6">
                        <div class="form-group my-3">
                            <x-forms.label fieldId="colorselector" fieldRequired="true" :fieldLabel="__('modules.sticky.colors')">
                            </x-forms.label>
                            <x-forms.input-group id="colorpicker">
                                <input type="text" class="form-control height-35 f-14"
                                    placeholder="{{ __('placeholders.colorPicker') }}" name="color"
                                    id="colorselector">

                                <x-slot name="append">
                                    <span class="input-group-text height-35 colorpicker-input-addon"><i></i></span>
                                </x-slot>
                            </x-forms.input-group>
                        </div>
                    </div>

                    <div class="col-lg-4 strict-timing-field">
                        <div class="bootstrap-timepicker">
                            <x-forms.text :fieldLabel="__('modules.attendance.officeStartTime')" :fieldPlaceholder="__('placeholders.hours')" fieldName="office_start_time"
                                fieldId="office_start_time" fieldRequired="true" :fieldValue="\Carbon\Carbon::createFromFormat(
                                    'H:i:s',
                                    $employeeShift->office_start_time,
                                )->translatedFormat(company()->time_format)" />
                        </div>
                    </div>

                    <div class="col-lg-4 strict-timing-field">
                        <div class="bootstrap-timepicker">
                            <x-forms.text :fieldLabel="__('modules.attendance.officeEndTime')" :fieldPlaceholder="__('placeholders.hours')" fieldName="office_end_time"
                                fieldId="office_end_time" fieldRequired="true" :fieldValue="\Carbon\Carbon::createFromFormat('H:i:s', $employeeShift->office_end_time)->format(
                                    company()->time_format,
                                )" />
                        </div>
                    </div>

                    <div class="col-lg-4 flexible-timing-field">
                        <x-forms.number :fieldLabel="__('modules.attendance.totalShiftHours')"  fieldName="total_shift_hours"
                                          fieldId="total_shift_hours" fieldRequired="true" minValue="1" maxValue="23" :fieldValue="$employeeShift->flexible_total_hours" :popover="__('messages.totalShiftHours')" />
                    </div>

                    <div class="col-lg-4 flexible-timing-field">
                        <x-forms.number :fieldLabel="__('modules.attendance.halfdayShiftHours')"  fieldName="halfday_shift_hours"
                                          fieldId="halfday_shift_hours" fieldRequired="true" minValue="1" maxValue="23" :fieldValue="$employeeShift->flexible_half_day_hours" :popover="__('messages.halfdayShiftHours')" />
                    </div>

                    <div class="col-lg-4 flexible-timing-field">
                        <x-forms.number :fieldLabel="__('modules.attendance.autoClockOutTIme')" fieldName="auto_clockout"
                                          fieldId="auto_clock_out_time" fieldRequired="true" minValue="0" :fieldValue="$employeeShift->flexible_auto_clockout" :popover="__('messages.flexibleAutoClockOut')" />
                    </div>

                    <div class="col-lg-4 strict-timing-field">
                        <div class="bootstrap-timepicker">
                            <x-forms.number :fieldLabel="__('modules.attendance.autoClockOutTIme')" :fieldPlaceholder="__('placeholders.hours')" fieldName="auto_clock_out_time"
                                          fieldId="auto_clock_out_time" fieldRequired="true" :fieldValue="$employeeShift->auto_clock_out_time"  minValue="1" maxValue="12" :popover="__('messages.autoClockOut')" />
                        </div>
                    </div>

                    <div class="col-lg-3 strict-timing-field">
                        <div class="bootstrap-timepicker">
                            <x-forms.text :fieldLabel="__('modules.attendance.halfDayMarkTime')" :fieldPlaceholder="__('placeholders.hours')" fieldName="halfday_mark_time"
                                fieldId="halfday_mark_time" fieldRequired="true" :fieldValue="!is_null($employeeShift->halfday_mark_time) ? \Carbon\Carbon::createFromFormat(
                                    'H:i:s',
                                    $employeeShift->halfday_mark_time,
                                )->translatedFormat(company()->time_format) : ''" />
                        </div>
                    </div>

                    <div class="col-lg-3 strict-timing-field">
                        <x-forms.number fieldName="early_clock_in" fieldId="early_clock_in"
                        :fieldLabel="__('modules.attendance.earlyClockIn')" :fieldValue="$employeeShift->early_clock_in"/>
                    </div>

                    <div class="col-lg-3 strict-timing-field">
                        <x-forms.number class="mr-0 mr-lg-2 mr-md-2" :fieldLabel="__('modules.attendance.lateMark')" fieldName="late_mark_duration"
                            fieldId="late_mark_duration" fieldRequired="true" :fieldValue="$employeeShift->late_mark_duration" />
                    </div>

                    <div class="col-lg-3 strict-timing-field">
                        <x-forms.text :fieldLabel="__('modules.attendance.allowedClockInTime')" fieldName="allowed_clock_in_time_display"
                            fieldId="allowed_clock_in_time" fieldPlaceholder="--:--" disabled="disabled" />
                    </div>

                    <div class="col-lg-3 strict-timing-field">
                        <x-forms.number class="mr-0 mr-lg-2 mr-md-2" :fieldLabel="__('modules.attendance.checkininday')" fieldName="clockin_in_day"
                            fieldId="clockin_in_day" fieldRequired="true" :fieldValue="$employeeShift->clockin_in_day" />
                    </div>

                    <div class="col-lg-3 strict-timing-field">
                        <x-forms.number class="mr-0 mr-lg-2 mr-md-2" :fieldLabel="__('modules.attendance.minWorkingHoursFullDay')" fieldName="min_working_hours_full_day"
                            fieldId="min_working_hours_full_day" fieldRequired="true" minValue="0" maxValue="24" :fieldValue="$employeeShift->min_working_hours_full_day" />
                    </div>

                    <div class="col-lg-3 strict-timing-field">
                        <x-forms.number class="mr-0 mr-lg-2 mr-md-2" :fieldLabel="__('modules.attendance.minWorkingHoursFullDayGrace')" fieldName="min_working_hours_full_day_grace"
                            fieldId="min_working_hours_full_day_grace" fieldRequired="true" minValue="0" :fieldValue="$employeeShift->min_working_hours_full_day_grace" />
                    </div>

                    <div class="w-100 strict-timing-field"></div>

                    <div class="col-lg-3 strict-timing-field">
                        <x-forms.number class="mr-0 mr-lg-2 mr-md-2" :fieldLabel="__('modules.attendance.minWorkingHoursHalfDay')" fieldName="min_working_hours_half_day"
                            fieldId="min_working_hours_half_day" fieldRequired="true" minValue="0" maxValue="24" :fieldValue="$employeeShift->min_working_hours_half_day" />
                    </div>

                    <div class="col-lg-3 strict-timing-field">
                        <x-forms.number class="mr-0 mr-lg-2 mr-md-2" :fieldLabel="__('modules.attendance.minWorkingHoursHalfDayGrace')" fieldName="min_working_hours_half_day_grace"
                            fieldId="min_working_hours_half_day_grace" fieldRequired="true" minValue="0" :fieldValue="$employeeShift->min_working_hours_half_day_grace" />
                    </div>

                    <div class="col-lg-12">
                        <div class="form-group my-3">
                            <x-forms.label fieldId="office_open_days" :fieldLabel="__('modules.attendance.officeOpenDays')" fieldRequired="true">
                            </x-forms.label>
                            <div class="d-lg-flex d-sm-block justify-content-between ">
                                <x-forms.weeks fieldName="office_open_days[]" :checkedDays="$openDays"></x-forms.weeks>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </x-form>
    </div>
</div>
<div class="modal-footer">
    <x-forms.button-cancel data-dismiss="modal" class="border-0 mr-3">@lang('app.cancel')</x-forms.button-cancel>
    <x-forms.button-primary id="save-employee-shift" icon="check">@lang('app.save')</x-forms.button-primary>
</div>

<script>
    $('#colorpicker').colorpicker({
        "color": "{{ $employeeShift->color }}"
    });

    $('#office_end_time, #office_start_time, #halfday_mark_time').timepicker({
        @if (company()->time_format == 'H:i')
            showMeridian: false,
        @endif
    });

    // ===== Allowed Clock-In Time = Shift Start Time + Late mark after (minutes) =====
    // Purely a display convenience (never submitted, not stored) — the same
    // calculation the backend already uses to decide Present vs Late on clock-in.
    (function () {
        function parseTimeToMinutes(value) {
            if (!value) return null;
            value = value.trim();

            var meridianMatch = value.match(/^(\d{1,2}):(\d{2})\s*(AM|PM)$/i);
            if (meridianMatch) {
                var h = parseInt(meridianMatch[1], 10) % 12;
                var m = parseInt(meridianMatch[2], 10);
                if (meridianMatch[3].toUpperCase() === 'PM') h += 12;
                return h * 60 + m;
            }

            var hm = value.match(/^(\d{1,2}):(\d{2})$/);
            if (hm) {
                return parseInt(hm[1], 10) * 60 + parseInt(hm[2], 10);
            }

            return null;
        }

        function pad(n) {
            return (n < 10 ? '0' : '') + n;
        }

        function formatMinutesLikeInput(totalMinutes, sampleValue) {
            totalMinutes = ((totalMinutes % 1440) + 1440) % 1440;
            var h24 = Math.floor(totalMinutes / 60);
            var m = totalMinutes % 60;

            if (/AM|PM/i.test(sampleValue || '')) {
                var period = h24 >= 12 ? 'PM' : 'AM';
                var h12 = h24 % 12;
                if (h12 === 0) h12 = 12;
                return pad(h12) + ':' + pad(m) + ' ' + period;
            }

            return pad(h24) + ':' + pad(m);
        }

        function recalcAllowedClockIn() {
            var startVal = $('#office_start_time').val();
            var lateMins = parseInt($('#late_mark_duration').val(), 10);
            if (isNaN(lateMins) || lateMins < 0) lateMins = 0;

            var startMins = parseTimeToMinutes(startVal);
            if (startMins === null) {
                $('#allowed_clock_in_time').val('');
                return;
            }

            $('#allowed_clock_in_time').val(formatMinutesLikeInput(startMins + lateMins, startVal));
        }

        $('#office_start_time').on('changeTime.timepicker change input', recalcAllowedClockIn);
        $('#late_mark_duration').on('input change', recalcAllowedClockIn);

        recalcAllowedClockIn();
    })();

    // save type
    $('#save-employee-shift').click(function() {
        $.easyAjax({
            url: "{{ route('employee-shifts.update', $employeeShift->id) }}",
            container: '#createTicket',
            type: "POST",
            blockUI: true,
            blockUI: '#save-employee-shift',
            disableButton: true,
            buttonSelector: '#save-signature',
            data: $('#createTicket').serialize(),
            success: function(response) {
                if (response.status == "success") {
                    window.location.reload();
                }
            }
        })
    });

    setTimeout(function(){
        $('[data-toggle="popover"]').popover();
    }, 300);
</script>
