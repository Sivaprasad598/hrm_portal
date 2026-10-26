<div class="col-lg-12 col-md-12 ntfcn-tab-content-left w-100 p-4 ">
    @method('PUT')
    {{-- Grouped in plain words: the same settings as before (names and ids unchanged). --}}
    <style>
        .att-section { border: 1px solid #eaecf0; border-radius: 8px; padding: 16px 18px 6px; margin-bottom: 16px; }
        .att-section > h4 { font-size: 15px; font-weight: 600; color: #101828; margin: 0 0 2px; }
        .att-section > p.att-hint { font-size: 12px; color: #667085; margin: 0 0 10px; }
    </style>
    <div class="row">
        <div class="col-lg-12">

            {{-- 1. Clocking in --}}
            <div class="att-section">
                <h4>Clocking in</h4>
                <p class="att-hint">How employees mark their own attendance from the web or mobile.</p>
                <div class="row">
                    <div class="col-lg-12 mb-2">
                        <x-forms.checkbox fieldLabel="Employees can clock in and out themselves" fieldName="employee_clock_in_out"
                            fieldId="employee_clock_in_out" fieldValue="yes" :checked="$attendanceSetting->employee_clock_in_out == 'yes'" />
                    </div>
                    <div class="col-lg-12 mb-2">
                        <x-forms.checkbox fieldLabel="Allow clocking in outside shift hours" fieldName="show_clock_in_button"
                            fieldId="show_clock_in_button" fieldValue="yes" :checked="($attendanceSetting->show_clock_in_button == 'yes')"/>
                    </div>
                    <div class="col-lg-12 mb-2">
                        <x-forms.checkbox fieldLabel="Clock them in automatically when they first sign in for the day" fieldName="auto_clock_in"
                            fieldId="auto_clock_in" fieldValue="yes" :checked="$attendanceSetting->auto_clock_in == 'yes'" />
                    </div>
                    <div class="col-lg-4 auto_clock_in_location mb-3 @if ($attendanceSetting->auto_clock_in == 'no') d-none @endif">
                        <x-forms.select fieldLabel="Automatic clock-in counts as working from" fieldName="auto_clock_in_location" fieldId="auto_clock_in_location"
                        fieldRequired="true">
                        <option value="office" {{ $attendanceSetting->auto_clock_in_location == 'office' ? 'selected' : '' }}>
                            @lang('modules.attendance.office')</option>
                        <option value="home" {{ $attendanceSetting->auto_clock_in_location == 'home' ? 'selected' : '' }}>
                            @lang('modules.attendance.home')</option>
                        </x-forms.select>
                    </div>
                </div>
            </div>

            {{-- 2. What a clock-in records --}}
            <div class="att-section">
                <h4>What a clock-in records</h4>
                <p class="att-hint">Proof kept with each clock-in.</p>
                <div class="row">
                    <div class="col-md-5 mb-3">
                        <label class="f-14 text-dark-grey mb-2">Photo</label>
                        <select name="clock_in_photo" id="clock_in_photo" class="form-control f-14">
                            <option value="off" @selected(($attendanceSetting->clock_in_photo ?? 'off') === 'off')>Not needed</option>
                            <option value="optional" @selected(($attendanceSetting->clock_in_photo ?? '') === 'optional')>Ask, but not required</option>
                            <option value="mandatory" @selected(($attendanceSetting->clock_in_photo ?? '') === 'mandatory')>Required to clock in</option>
                        </select>
                    </div>
                    <div class="col-md-5 mb-3">
                        <label class="f-14 text-dark-grey mb-2">Location (GPS)</label>
                        <select name="clock_in_location" id="clock_in_location" class="form-control f-14">
                            <option value="off" @selected(($attendanceSetting->clock_in_location ?? 'off') === 'off')>Not needed</option>
                            <option value="optional" @selected(($attendanceSetting->clock_in_location ?? '') === 'optional')>Ask, but not required</option>
                            <option value="mandatory" @selected(($attendanceSetting->clock_in_location ?? '') === 'mandatory')>Required to clock in</option>
                        </select>
                    </div>
                    <div class="col-lg-12 mb-2">
                        <x-forms.checkbox fieldLabel="Save where they clocked in from" fieldName="save_current_location"
                            fieldId="save_current_location" fieldValue="yes" :checked="$attendanceSetting->save_current_location" />
                    </div>
                    <div class="col-12 mb-3">
                        <span class="f-12 text-dark-grey">Browsers share the camera and location only on a secure (https) site.</span>
                    </div>
                </div>
            </div>

            {{-- 3. Where they can clock in from --}}
            <div class="att-section">
                <h4>Where they can clock in from</h4>
                <p class="att-hint">Leave both off to allow clocking in from anywhere.</p>
                <div class="row">
                    <div class="col-lg-12 mb-2">
                        <x-forms.checkbox fieldLabel="Only near the office (within a distance)" fieldName="radius_check" fieldId="radius_check" fieldValue="yes"
                            :checked="$attendanceSetting->radius_check == 'yes'" />
                    </div>
                    <div class="col-lg-4 @if ($attendanceSetting->radius_check == 'no') d-none @endif " id="radiusBox">
                        <x-forms.number class="mr-0 mr-lg-2 mr-md-2" fieldLabel="Distance from the office (metres)" fieldName="radius" fieldId="radius"
                            :fieldValue="$attendanceSetting->radius" />
                    </div>
                    <div class="col-lg-12 mb-1">
                        <x-forms.checkbox fieldLabel="Only from the office network (approved IP addresses)" fieldName="ip_check" fieldId="ip_check" fieldValue="yes"
                            :checked="$attendanceSetting->ip_check == 'yes'" />
                    </div>
                    <div class="col-lg-12 mb-3 @if ($attendanceSetting->ip_check == 'no') d-none @endif " id="ipBox">
                        <div id="addMoreBox1" class="row">
                            @forelse($ipAddresses as $index => $ipAddress)
                                <div class="col-md-5">
                                    <div class="form-group" id="occasionBox">
                                        <input class="form-control height-35 f-14" type="text" value="{{ $ipAddress }}"
                                            name="ip[{{ $index }}]"
                                            placeholder="{{ __('modules.attendance.ipAddress') }}" />
                                        <div id="errorOccasion"></div>
                                    </div>
                                </div>
                            @empty
                                <div class="col-md-5">
                                    <div class="form-group" id="occasionBox">
                                        <x-forms.text fieldLabel="" :fieldPlaceholder="__('modules.attendance.ipAddress')" fieldName="ip[0]" fieldId="ip[0]" />
                                        <div id="errorOccasion"></div>
                                    </div>
                                </div>
                            @endforelse
                            <div class="col-md-1"></div>
                        </div>
                        <div id="insertBefore"></div>
                        <div class="clearfix"></div>
                        <a href="javascript:;" id="plusButton" class=""><i class="f-12 mr-2 fa fa-plus"></i>
                            @lang('modules.addIpAddress') </a>
                    </div>
                </div>
            </div>

            {{-- 4. Shifts and week --}}
            <div class="att-section">
                <h4>Shifts and week</h4>
                <p class="att-hint">Shift timings themselves are on the Shifts tab.</p>
                <div class="row">
                    <div class="col-lg-12 mb-2">
                        <x-forms.checkbox fieldLabel="Employees can ask to change their shift" fieldName="allow_shift_change"
                            fieldId="allow_shift_change" fieldValue="yes" :checked="$attendanceSetting->allow_shift_change" />
                    </div>
                    <div class="col-lg-4 mb-2">
                        <x-forms.weeks type="select" fieldLabel="Week starts on"
                                       fieldName="week_start_from" fieldId="week_start_from"
                                       fieldRequired="true"
                                       :selectedWeek="$attendanceSetting->week_start_from">
                        </x-forms.weeks>
                    </div>
                </div>
            </div>

            {{-- 5. Reminders and reports --}}
            <div class="att-section">
                <h4>Reminders and reports</h4>
                <div class="row">
                    <div class="col-lg-4">
                        <x-forms.toggle-switch class="mr-0 mr-lg-12" :checked="$attendanceSetting->alert_after_status" fieldLabel="Remind employees who have not clocked in"
                            fieldName="alert_after_status" fieldId="alert_after_status" />
                    </div>
                    <div class="col-lg-4 alert_after_box @if ($attendanceSetting->alert_after_status == 0) d-none @endif">
                        <x-forms.number class="mr-0 mr-lg-2 mr-md-2" fieldLabel="Remind after (minutes past shift start)" fieldName="alert_after"
                            fieldId="alert_after" :fieldValue="$attendanceSetting->alert_after" fieldRequired="true" />
                    </div>
                    <div class="col-lg-12 mb-1">
                        <x-forms.checkbox fieldLabel="Email a monthly attendance report" :popover="__('messages.sendMonthlyReportTooltip')" fieldName="monthly_report" fieldId="monthly_report" fieldValue="1"
                            :checked="$attendanceSetting->monthly_report" />
                    </div>
                    <div class="col-lg-12 mb-3 @if ($attendanceSetting->monthly_report == 0) d-none @endif " id="monthly-report-roles">
                        <x-forms.select fieldLabel="Send it to" fieldName="monthly_report_roles[]" fieldId="monthly_report_roles"
                        fieldRequired="true" multiple="true">
                            @foreach ($roles as $item)
                                <option
                                @if (is_array($monthlyReportRoles) && in_array($item->id, $monthlyReportRoles))
                                    selected
                                @endif
                                value="{{ $item->id }}">{{ $item->display_name }}</option>
                            @endforeach
                        </x-forms.select>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>
<!-- Buttons Start -->
<div class="w-100 border-top-grey">
    <x-setting-form-actions>
        <x-forms.button-primary id="save-form" class="mr-3" icon="check">@lang('app.save')
        </x-forms.button-primary>
    </x-setting-form-actions>
</div>
<!-- Buttons End -->

<script>
    $('#ipBox').on('click', '.delete-ip-field', function() {
        var ipIndex = $(this).data('ip-index');
        $('#addMoreBox' + ipIndex).remove();
    });
    var $insertBefore = $('#insertBefore');
    var $i = {{ count($ipAddresses) }};

    $('#save-form').click(function() {
        $.easyAjax({
            url: "{{ route('attendance-settings.update', $attendanceSetting->id) }}",
            container: '#editSettings',
            disableButton: true,
            blockUI: true,
            buttonSelector: "#save-form",
            type: "POST",
            redirect: true,
            data: $('#editSettings').serialize()
        })
    });

    $('#employee_clock_in_out').click(function() {
        if ($(this).prop("checked") == true) {
            $('#radius_check').removeAttr("disabled");
            $('#ip_check').removeAttr("disabled");
        } else if ($(this).prop("checked") == false) {
            if ($('#radius_check').prop("checked") == true) {
                $('#radius_check').trigger('click');
            }
            if ($('#ip_check').prop("checked") == true) {
                $('#ip_check').trigger('click');
            }

            $('#radius_check').attr("disabled", 'disabled');
            $('#ip_check').attr("disabled", 'disabled');
        }
    });

    $('#radius_check').click(function() {
        $('#radiusBox').toggleClass('d-none');
    });

    $('#ip_check').click(function() {
        $('#ipBox').toggleClass('d-none');
    });

    $('#monthly_report').click(function() {
        $('#monthly-report-roles').toggleClass('d-none');
    });

    // Add More Inputs
    $('#plusButton').click(function() {
        $i = $i + 1;
        var indexs = $i + 1;
        $(`<div id="addMoreBox${indexs}" class="row clearfix"><div class="col-md-5"><div class="form-group"><input class="form-control height-35 f-14" name="ip[${$i}]" type="text" value="" placeholder="@lang('modules.attendance.ipAddress')"/></div></div><div class="col-md-1"><div class="task_view mt-1"> <a href="javascript:;" data-ip-index="${indexs}" class="delete-ip-field task_view_more d-flex align-items-center justify-content-center dropdown-toggle" > <i class="fa fa-trash icons mr-2"></i> @lang('app.delete')</a> </div></div></div>`)
            .insertBefore($insertBefore);
    });

    // Remove fields
    function removeBox(index) {
        $('#addMoreBox' + index).remove();
    }


    $('#alert_after_status').click(function() {
        $('.alert_after_box').toggleClass('d-none');
    })

    $('#auto_clock_in').click(function() {
        $('.auto_clock_in_location').toggleClass('d-none');
    })
</script>
