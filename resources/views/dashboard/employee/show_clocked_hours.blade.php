<div class="modal-header">
    <h5 class="modal-title" id="modelHeading">@lang('app.attendanceDetails')</h5>
    <button type="button"  class="close" data-dismiss="modal" aria-label="Close"><span
            aria-hidden="true">×</span></button>
</div>

<div class="modal-body bg-grey">

    <div class="row">
        @php
            // Only calculate flexible shift values if shift exists and is flexible
            if (isset($attendance->shift) && $attendance->shift && $attendance->shift->shift_type == 'flexible') {
                $minimumHalfDayMinutes = ((float)$attendance->shift->flexible_half_day_hours * 60);
                $totalMinimumMinutes = ((float)$attendance->shift->flexible_total_hours * 60);
            } else {
                $minimumHalfDayMinutes = 0;
                $totalMinimumMinutes = 0;
            }
            $clockedTotalMinutes = floor((float)$totalTime / 60);
            $clockedTotalHours = $attendance->clock_in_time->timezone(company()->timezone)->diffInHours(now()->timezone(company()->timezone));
        @endphp

        @if (isset($attendance->shift) && $attendance->shift->shift_type == 'flexible')

            @if ($clockedTotalMinutes < $minimumHalfDayMinutes)
                @if($clockedTotalHours < (float)$attendance->shift->flexible_half_day_hours)
                    <div class="col-md-12">
                        <x-alert type="warning">@lang('messages.halfdayHoursNotComplete')</x-alert>
                    </div>
                @endif
            @elseif($clockedTotalMinutes >= $minimumHalfDayMinutes && $clockedTotalMinutes < $totalMinimumMinutes)
                @if($clockedTotalHours >= (float)$attendance->shift->flexible_half_day_hours && $clockedTotalHours < (float)$attendance->shift->flexible_total_hours)
                    <div class="col-md-12">
                        <x-alert type="warning">@lang('messages.willMarkHalfDay')</x-alert>
                    </div>
                @endif
            @endif
        @endif

        <div class="col-md-6">
            <x-cards.data :title="__('app.date').' - '.$attendanceDate->translatedFormat(company()->date_format) .' ('.$attendanceDate->translatedFormat('l').')'">
                <div class="punch-status">
                    <div class="border rounded p-3 mb-3 bg-light">
                        <h6 class="f-13">@lang('modules.attendance.clock_in')</h6>
                        <p class="mb-0">{{ $startTime->translatedFormat(company()->time_format) }}</p>
                    </div>
                    <div class="punch-info">
                        <div class="punch-hours f-13">
                            <span>{{ $totalWorkingSpan }}</span>
                        </div>
                    </div>
                    <div class="attendance-summary mb-3 px-3">
                        <div class="row">
                            <div class="col-sm-6">
                                <p class="mb-1"><strong>@lang('modules.attendance.firstClockIn'):</strong> {{ $firstClockInTimeFormatted }}</p>
                                <p class="mb-1"><strong>@lang('modules.attendance.lastClockOut'):</strong> {{ $lastClockOutTimeFormatted }}</p>
                                <p class="mb-1"><strong>@lang('modules.attendance.totalSessions'):</strong> {{ $totalSessions }}</p>
                            </div>
                            <div class="col-sm-6">
                                <p class="mb-1"><strong>@lang('modules.attendance.totalTimeInOffice'):</strong> {{ $totalTimeFormatted }}</p>
                                <p class="mb-1"><strong>@lang('modules.attendance.totalTimeOutside'):</strong> {{ $totalTimeOutside }}</p>
                                <p class="mb-1"><strong>@lang('modules.attendance.totalWorkingSpan'):</strong> {{ $totalWorkingSpan }}</p>
                            </div>
                        </div>
                    </div>
                    <div class="border rounded p-3 bg-light">
                        <p class="mb-0">{{ $endTime != '' ? $endTime->translatedFormat(company()->time_format) : '' }}
                            @if (isset($notClockedOut))
                                (@lang('modules.attendance.currentTime'))
                            @endif
                        </p>
                    </div>

                </div>
            </x-cards.data>
        </div>
        <div class="col-md-6">

            <x-cards.data :title="__('modules.employees.activity')">

                <div class="recent-activity h-auto">
                    @foreach ($attendanceActivity->reverse() as $item)
                        <div class="row res-activity-box" id="timelogBox{{ $item->aId }}">
                            <ul class="res-activity-list col-md-9">
                                <li>
                                    <p class="mb-0">@lang('modules.attendance.clock_in')
                                        @if (!is_null($item->employee_shift_id))
                                        @if ($item->shift && $item->shift->shift_name != 'Day Off')
                                                <span class="badge badge-info ml-2" style="background-color: {{ $item->shift->color }}">{{ $item->shift->shift_name }}</span>
                                            @else
                                                <span class="badge badge-secondary ml-2" >{{ __('modules.attendance.' . str($attendanceSettings->shift_name)->camel()) }}</span>
                                            @endif
                                        @endif
                                    </p>
                                    <p class="res-activity-time">
                                        <i class="fa fa-clock"></i>
                                        {{ $item->clock_in_time->timezone(company()->timezone)->translatedFormat(company()->date_format . ' ' . company()->time_format) }}

                                        @if ($item->work_from_type != '')
                                            @if ($item->work_from_type == 'other')
                                                <i class="fa fa-map-marker-alt ml-2"></i>
                                                {{ $item->location }} {{ $item->working_from != '' ? '(' . $item->working_from . ')' : ''  }}
                                            @else
                                                <i class="fa fa-map-marker-alt ml-2"></i>
                                                {{ $item->location }} ({{$item->work_from_type}})
                                            @endif
                                        @endif

                                        @if ($item->late == 'yes')
                                            <i class="fa fa-exclamation-triangle ml-2"></i>
                                            @lang('modules.attendance.late')
                                        @endif

                                        @if ($item->half_day == 'yes')
                                            <i class="fa fa-sign-out-alt ml-2"></i>
                                            @lang('modules.attendance.halfDay')
                                            <span>
                                                @if($item->half_day_type == 'first_half')
                                                    ( @lang('modules.leaves.1stHalf') )
                                                @elseif ($item->half_day_type == 'second_half')
                                                    ( @lang('modules.leaves.2ndHalf') )
                                                @else

                                                @endif
                                            </span>
                                        @endif

                                    </p>
                                </li>
                                <li>
                                    <p class="mb-0">@lang('modules.attendance.clock_out')</p>
                                    <p class="res-activity-time">
                                        <i class="fa fa-clock"></i>
                                        @if (!is_null($item->clock_out_time))
                                            {{ $item->clock_out_time->timezone(company()->timezone)->translatedFormat(company()->date_format . ' ' . company()->time_format) }}

                                            @if ($item->clock_out_time_work_from_type != NULL)
                                                @if ($item->clock_out_time_work_from_type == 'other')
                                                    <i class="fa fa-map-marker-alt ml-2"></i>
                                                    {{ $item->clockOutLocation }} {{ $item->clock_out_time_working_from != '' ? '(' . $item->clock_out_time_working_from . ')' : ''  }}
                                                @else
                                                    <i class="fa fa-map-marker-alt ml-2"></i>
                                                    {{ $item->clockOutLocation }} ({{$item->clock_out_time_work_from_type}})
                                                @endif
                                            @endif

                                            @if($item->auto_clock_out)
                                                <i class="fa fa-sign-out-alt ml-2"></i>
                                                @lang('modules.attendance.autoClockOut')
                                            @endif
                                        @else
                                            @lang('modules.attendance.notClockOut')
                                        @endif
                                    </p>
                                </li>
                            </ul>

                        </div>
                    @endforeach

                </div>
            </x-cards.data>
        </div>
    </div>

    <div class="row">
        <div class="col-md-12 mb-2 mt-4">
            <div class="card border-0 b-shadow-4">
                <div class="card-horizontal align-items-center">
                    <div class="card-body border-0 pl-0">
                        <div class="row">
                            <div class="col-12">
                                <div class="col-md-6 float-left">
                                    <x-forms.select fieldId="clock_out_location" :fieldLabel="__('app.location')" fieldName="clock_out_"
                                                    search="true">
                                        @foreach ($location as $locations)
                                            <option @if ($locations->id == $user->employeeDetail->company_address_id) selected
                                                    @endif value="{{ $locations->id }}">
                                                {{ $locations->location }}</option>
                                        @endforeach
                                    </x-forms.select>
                                </div>
                                <div class="col-md-6 float-right">
                                    <x-forms.select fieldId="clock_out_work_from_type" :fieldLabel="__('modules.attendance.working_from')"
                                                    fieldName="clock_out_work_from_type" fieldRequired="true"
                                                    search="true">
                                        <option value="office">@lang('modules.attendance.office')</option>
                                        <option value="home">@lang('modules.attendance.home')</option>
                                        <option value="other">@lang('modules.attendance.other')</option>
                                    </x-forms.select>
                                </div>
                            </div>
                        </div>
                        <div class="col-12" id="other_place" style="display:none">
                            <x-forms.text fieldId="clock_out_working_from" :fieldLabel="__('modules.attendance.otherPlace')"
                                            fieldName="clock_out_working_from" fieldRequired="true">
                            </x-forms.text>
                        </div>

                        {{-- The same photograph and place asked for at clock-in.
                             One pair of settings governs both ends of the day. --}}
                        <div class="col-12">
                            @include('dashboard.employee.capture_panel')
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

</div>

<div class="modal-footer">
    <button type="button" class="mr-3 rounded btn-cancel" data-dismiss="modal">@lang('app.cancel')</button>
    {{-- The id and the capture rules travel on the button so clockOut() can be
         defined once, globally, instead of only on the dashboard page. --}}
    <button type="button" onclick="clockOut()" class="rounded btn-danger"
            id="clock-out-submit"
            data-attendance-id="{{ $attendance->id }}"
            data-photo-rule="{{ $captureRules['photo'] }}"
            data-location-rule="{{ $captureRules['location'] }}"><i
        class="icons icon-login mr-2"></i>@lang('modules.attendance.clock_out')</button>
</div>
<script>

$('.select-picker').selectpicker('refresh');

$(function () {
        $('#clock_out_work_from_type').change(function () {

            ($(this).val() == 'other') ? $('#other_place').show() : $('#other_place').hide();

        });
    });

</script>
