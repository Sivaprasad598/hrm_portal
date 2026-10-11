<style>
    .form-description {
        margin-bottom: -18px;
    }

    .input-radio-button {
        margin-left: -7px;
    }
</style>

<div class="col-lg-12 col-md-12 ntfcn-tab-content-left w-100 p-4">
    @method('PUT')
    <div class="row">

        <div class="col-lg-12">
            <div class="form-group my-2">
                <label class="f-14 text-dark-grey mb-12 w-100"
                for="usr">@lang('clan.attendance.attendanceLogAdjust')</label>
                <div class="d-flex">
                    <x-forms.radio fieldId="adjust_attendance_logs_not_allowed" :fieldLabel="__('clan.attendance.notAllowed')" fieldValue="not_allowed"
                        fieldName="adjust_attendance_logs" :checked="$attendanceSetting->adjust_attendance_logs == 'not_allowed'">
                    </x-forms.radio>
                    <x-forms.radio fieldId="adjust_attendance_logs_missing_swipes" :fieldLabel="__('clan.attendance.onlyMissingSwipes')" fieldValue="missing_swipes"
                        fieldName="adjust_attendance_logs" :checked="$attendanceSetting->adjust_attendance_logs == 'missing_swipes'">
                    </x-forms.radio>
                    <x-forms.radio fieldId="adjust_attendance_logs_all_logs" :fieldLabel="__('clan.attendance.allLogs')" fieldValue="all_logs"
                        fieldName="adjust_attendance_logs" :checked="$attendanceSetting->adjust_attendance_logs == 'all_logs'">
                    </x-forms.radio>
                </div>
            </div>
        </div>

    </div>

        <div class="@if ($attendanceSetting->adjustment_allowed == '0') d-none @endif" id="adjustment_allowed_checkbox">
            <div class="col-lg-12">
                <div class="form-group my-3">
                    <label class="f-14 text-dark-grey mb-12 w-100"
                        for="usr">@lang('clan.attendance.timesAdjustmentAllowed')</label>
                    <div class="d-flex flex-column">
                        <div class="form-check d-flex align-items-center">
                            <x-forms.radio fieldId="times_adjustment_allowed-1" :fieldLabel="__('clan.attendance.last')" fieldValue="1"
                                fieldName="times_adjustment_allowed" :checked="$attendanceSetting?->times_adjustment_allowed == '1'">
                            </x-forms.radio>
                            <div class="input-radio-button mr-2">
                                <input type="number" class="form-control" name="last_days" style="width:60px;" value="{{$attendanceSetting?->last_days}}">
                            </div>
                            <div class="form-description">
                                <p>{{__('clan.attendance.days')}}
                                    <i class="fa fa-question-circle" data-toggle="tooltip" data-original-title="{{__('clan.attendance.lastDaysPopover')}}"></i>
                                </p>
                            </div>

                        </div>
                        <div class="form-check d-flex align-items-center">
                            <x-forms.radio fieldId="times_adjustment_allowed-2" :fieldLabel="__('clan.attendance.adjustmentAllowed')" fieldValue="2"
                                fieldName="times_adjustment_allowed" :checked="$attendanceSetting?->times_adjustment_allowed == '2'">
                            </x-forms.radio>
                            <div class="input-radio-button mr-2">
                                <input type="number" class="form-control" name="adjustment_total_times" style="width:60px;" value="{{$attendanceSetting?->adjustment_total_times}}">
                            </div>
                            <div class="form-description">
                                <p>{{__('clan.attendance.timesIn')}}</p>
                            </div>

                            <div class="ml-2 mr-1">
                                <select class="form-control" name="adjustment_type" style="width:145px;">
                                    <option value="current_week" @if($attendanceSetting?->adjustment_type == 'current_week') selected @endif>{{ __('Current Week') }}</option>
                                    <option value="current_month" @if($attendanceSetting?->adjustment_type == 'current_month') selected @endif>{{ __('Current Month') }}</option>
                                    <option value="current_quarter" @if($attendanceSetting?->adjustment_type == 'current_quarter') selected @endif>{{ __('Current Quarter') }}</option>
                                    <option value="current_year" @if($attendanceSetting?->adjustment_type == 'current_year') selected @endif>{{ __('Current Year') }}</option>
                                </select>
                            </div>
                            <i class="fa fa-question-circle" data-toggle="tooltip" data-original-title="{{__('clan.attendance.adjustmentAllowedPopover')}}"></i>
                        </div>

                        <div class="form-check d-flex align-items-center">

                            <x-forms.radio fieldId="times_adjustment_allowed-3" :fieldLabel="__('clan.attendance.before')" fieldValue="3"
                                fieldName="times_adjustment_allowed" :checked="$attendanceSetting?->times_adjustment_allowed == '3'">
                            </x-forms.radio>

                            <div class="input-radio-button mr-2">
                                <select class="form-control" name="before_day_of_month" style="width:53px;">
                                    @for ($i = 1; $i <= 31; $i++)
                                        <option value="{{ $i }}" {{ $attendanceSetting?->before_day_of_month == $i ? 'selected' : '' }}>{{ $i }}</option>
                                    @endfor
                                </select>
                            </div>
                            <div class="form-description">
                                <p>{{__('clan.attendance.dayCurrentMonth')}}
                                    <i class="fa fa-question-circle" data-toggle="tooltip" data-original-title="{{__('clan.attendance.beforeDaysPopover')}}"></i>
                                </p>

                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class=" w-100 clearfix">
                <div class="col-lg-6">
                    <div class="form-group my-3">
                        <x-forms.select fieldId="attendance_regularize_roles" :fieldLabel="__('clan.attendance.regulariseRequest')"
                            fieldName="attendance_regularize_roles[]" multiple>
                                @foreach ($allRoles as $role)
                                <option value="{{ $role->name }}" @if(in_array($role->name, $selectedRoles)) selected @endif>{{$role->display_name }}</option>
                            @endforeach
                        </x-forms.select>
                    </div>
                </div>
            </div>

    </div>
</div>
<!-- Buttons Start -->
<div class="w-100 border-top-grey">
    <x-setting-form-actions>
        <x-forms.button-primary id="save-regularisation-form" class="mr-3" icon="check">@lang('app.save')
        </x-forms.button-primary>
    </x-setting-form-actions>
</div>
<!-- Buttons End -->

<script>

    $('#save-regularisation-form').click(function() {
        console.log($('#editSettings').serialize())
        @if(isset($attendanceSetting) && $attendanceSetting->id)
        var url = "{{ route('attendance-settings.attendanceRegularisation', $attendanceSetting->id) }}";
        @else
        var url = "{{ route('attendance-settings.attendanceRegularisation', 1) }}";
        @endif
        $.easyAjax({
            url: url,
            container: '#editSettings',
            disableButton: true,
            blockUI: true,
            buttonSelector: "#save-regularisation-form",
            type: "POST",
            redirect: true,
            data: $('#editSettings').serialize()
        })
    });

    $('input[name="adjust_attendance_logs"]').on('change', function() {
        var selectedValue = $(this).val();

        if (selectedValue === 'not_allowed') {
            $('#adjustment_allowed_checkbox').addClass('d-none');
        } else {
            $('#adjustment_allowed_checkbox').removeClass('d-none');
        }
    });

</script>
