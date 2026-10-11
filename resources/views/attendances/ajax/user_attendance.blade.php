@php
    $attendanceArray = [];

    foreach ($totalRequestCheck as $totalRequest) {
        $dt = \Carbon\Carbon::parse($totalRequest->date);
        $attendanceArray[$totalRequest->user_id][$dt->format('Y-m-d')] = $totalRequest;
    }

    $attendanceArrays = [];

    foreach ($totalRequestCheck as $totalRequests) {
        if ($totalRequests->status == 'pending') {
            $dts = \Carbon\Carbon::parse($totalRequests->date);
            $attendanceArrays[$totalRequests->user_id][$dts->format('Y-m-d')] = $totalRequests;
        }
    }

@endphp

@forelse ($dateWiseData as $key => $dateData)
    @php
        $currentDate = \Carbon\Carbon::parse($key);
        $today = \Carbon\Carbon::now()->copy();
        $endDateofMonth = $currentDate->copy()->endOfMonth();
        $startDateofAdjustment = $today->copy()->subDays($lastDays);
        $startDateOfMonth = $currentDate->copy()->startOfMonth();
        // For times_adjustment_allowed == '3', we need current month's start and end dates
        $currentMonthStart = $today->copy()->startOfMonth();
        $currentMonthEnd = $today->copy()->endOfMonth();
        // Calculate before_day_of_month date safely
        $beforeDayOfMonth = null;
        if (isset($attendanceSettings->before_day_of_month) && $attendanceSettings->before_day_of_month) {
            try {
                $day = (int) $attendanceSettings->before_day_of_month;
                $beforeDayOfMonth = $today->copy()->startOfMonth()->setDay($day);
            } catch (\Exception $e) {
                // If day doesn't exist in month (e.g., 31 in February), use last day of month
                $beforeDayOfMonth = $today->copy()->endOfMonth();
            }
        }

        $requestForDate = $attendanceArray[$userId][$key] ?? null;
 
        // Determine if a request exists and its status
        $matchingRequest = !is_null($requestForDate);
        $requestStatus = $requestForDate->status ?? null;


    @endphp
    <input type="hidden" value="{{$userId}}" id="userId" name="userId">
    <input type="hidden" value="{{$currentDate}}" id="attendance-date" name="attendance_date">
    @if (isset($dateData['attendance']) && ($dateData['attendance'] == true) && $dateData['leave'] != true)
        <tr>
            <td>
                <div class="media-body">
                    <h5 class="mb-0 f-13">{{ $currentDate->translatedFormat(company()->date_format) }}
                    </h5>
                    <p class="mb-0 f-13 text-dark-grey">
                        <label class="badge badge-secondary">{{ $currentDate->translatedFormat('l') }}</label>
                    </p>
                </div>
            </td>
            <td>
                <div class="media-body">
                    <span class="badge badge-success">@lang('modules.attendance.present')</span><br>
                        @if ($attendanceSettings?->adjust_attendance_logs == 'all_logs' && $attendanceSettings?->adjustment_allowed == '1')
                            @if ($attendanceSettings?->times_adjustment_allowed == '1')
                                    @if ($currentDate->between($startDateofAdjustment, $today) && ($currentDate->isSameDay($employeeJoiningDate) || $currentDate->greaterThan($employeeJoiningDate)))
                                        @if ($matchingRequest)
                                            @if ($requestStatus == 'pending')
                                                <a data-date="{{ $currentDate->format('Y-m-d') }}" class="request-pending f-13"><i class="bi bi-link-45deg"></i>@lang('clan.attendance.requestPending')</a>
                                            @endif
                                        @else
                                            <a data-date="{{ $currentDate->format('Y-m-d') }}" class="request-regularise f-13"><i class="bi bi-link-45deg"></i>@lang('clan.attendance.requestRegularise')</a>
                                        @endif
                                    @endif

                                @elseif ($attendanceSettings?->times_adjustment_allowed == '2')
                                    @if ($currentDate->between($checkStartDate, $checkEndDate) && $conditionWiseCount < $attendanceSettings?->adjustment_total_times && ($currentDate->isSameDay($employeeJoiningDate) || $currentDate->greaterThan($employeeJoiningDate)))
                                        @if ($matchingRequest)
                                            @if ($requestStatus == 'pending')
                                                <a data-date="{{ $currentDate->format('Y-m-d') }}" class="request-pending f-13"><i class="bi bi-link-45deg"></i>@lang('clan.attendance.requestPending')</a>
                                            @endif
                                        @else
                                                <a data-date="{{ $currentDate->format('Y-m-d') }}" class="request-regularise f-13"><i class="bi bi-link-45deg"></i>@lang('clan.attendance.requestRegularise')</a>
                                        @endif
                                    @endif

                                @elseif ($attendanceSettings?->times_adjustment_allowed == '3' && $beforeDayOfMonth)
                                    @if ($currentDate->between($currentMonthStart, $beforeDayOfMonth) && $today->between($currentMonthStart, $currentMonthEnd) && ($currentDate->isSameDay($employeeJoiningDate) || $currentDate->greaterThan($employeeJoiningDate)))
                                        @if ($matchingRequest)
                                        @if ($requestStatus == 'pending')
                                                <a data-date="{{ $currentDate->format('Y-m-d') }}" class="request-pending f-13"><i class="bi bi-link-45deg"></i>@lang('clan.attendance.requestPending')</a>
                                            @endif
                                        @else
                                                <a data-date="{{ $currentDate->format('Y-m-d') }}" class="request-regularise f-13"><i class="bi bi-link-45deg"></i>@lang('clan.attendance.requestRegularise')</a>
                                        @endif
                                    @endif
                                @else

                                @if ($matchingRequest)
                                    @if ($requestStatus == 'pending')
                                        <a data-date="{{ $currentDate->format('Y-m-d') }}" class="request-pending f-13"><i class="bi bi-link-45deg"></i>@lang('clan.attendance.requestPending')</a>
                                    @endif
                                @else
                                        <a data-date="{{ $currentDate->format('Y-m-d') }}" class="request-regularise f-13"><i class="bi bi-link-45deg"></i>@lang('clan.attendance.requestRegularise')</a>
                                @endif
                            @endif
                        @endif
                </div>
            </td>
            <td colspan="2">
                <x-table class="mb-0 rounded table table-bordered table-hover">
                    @foreach ($dateData['attendance'] as $attendance)
                        <tr>
                            <td width="50%">
                                {{ $attendance->clock_in_time->timezone(company()->timezone)->translatedFormat(company()->time_format) }}

                                @if ($attendance->late == 'yes')
                                    <span class="text-dark-grey"><i class="fa fa-exclamation-triangle ml-2"></i>
                                    @lang('modules.attendance.late')</span>
                                @endif

                                @if ($attendance->half_day == 'yes')
                                    <span class="text-dark-grey"><i class="fa fa-sign-out-alt ml-2"></i>
                                    @lang('modules.attendance.halfDay')</span>
                                    <span>
                                        @if($attendance->half_day_type == 'first_half')
                                            ( @lang('modules.leaves.1stHalf') )
                                        @elseif ($attendance->half_day_type == 'second_half')
                                            ( @lang('modules.leaves.2ndHalf') )
                                        @else

                                        @endif
                                    </span>
                                @endif

                                @if ($attendance->work_from_type != '')
                                    @if ($attendance->work_from_type == 'other')
                                        <i class="fa fa-map-marker-alt ml-2"></i>
                                        {{ $attendance->location }} ({{$attendance->working_from}})
                                    @else
                                        <i class="fa fa-map-marker-alt ml-2"></i>
                                        {{ $attendance->location }} ({{$attendance->work_from_type}})
                                    @endif
                                @endif
                            </td>
                            <td width="50%">
                                @if (!is_null($attendance->clock_out_time))
                                    {{ $attendance->clock_out_time->timezone(company()->timezone)->translatedFormat(company()->time_format) }}
                                    @if($attendance->auto_clock_out)
                                        <i class="fa fa-sign-out-alt ml-2"></i>
                                        @lang('modules.attendance.autoClockOut')
                                    @endif
                                @else - @endif
                            </td>
                        </tr>
                    @endforeach
                </x-table>
            </td>
            <td>
                {{ $attendance->totalTime($attendance->clock_in_time, $attendance->clock_in_time, $attendance->user_id) }}
            </td>
            <td class="text-right pb-2 pr-20">
                <x-forms.button-secondary icon="search" class="view-attendance"
                    data-attendance-id="{{ $attendance->aId }}">
                    @lang('app.details')
                </x-forms.button-secondary>
            </td>

        </tr>
    @else
        <tr>
            <td>
                <div class="media-body">
                    <h5 class="mb-0 f-13">{{ $currentDate->translatedFormat(company()->date_format) }}
                    </h5>
                    <p class="mb-0 f-13 text-dark-grey">
                        <span class="badge badge-secondary">{{ $currentDate->translatedFormat('l') }}</span>
                    </p>
                </div>
            </td>
            <td>
                @if (isset($dateData['day_off']) && ($dateData['day_off'] == true))
                    <label class="badge badge-info">@lang('modules.attendance.dayOff')</label>
                @elseif (!$dateData['holiday'] && !$dateData['leave'])
                    <div class="media-body">
                        <label class="badge badge-danger">@lang('modules.attendance.absent')</label><br>
                            @if ($attendanceSettings?->adjust_attendance_logs != 'not_allowed' && $attendanceSettings?->adjustment_allowed == '1')
                                @if ($attendanceSettings?->times_adjustment_allowed == '1')
                                    @if ($currentDate->between($startDateofAdjustment, $today) && ($currentDate->isSameDay($employeeJoiningDate) || $currentDate->greaterThan($employeeJoiningDate)))
                                        @if ($matchingRequest)
                                            @if ($requestStatus == 'pending')
                                                <a data-date="{{ $currentDate->format('Y-m-d') }}" class="request-pending f-13"><i class="bi bi-link-45deg"></i>@lang('clan.attendance.requestPending')</a>
                                            @endif
                                        @else
                                                <a data-date="{{ $currentDate->format('Y-m-d') }}" class="request-regularise f-13"><i class="bi bi-link-45deg"></i>@lang('clan.attendance.requestRegularise')</a>
                                        @endif
                                    @endif

                                @elseif ($attendanceSettings?->times_adjustment_allowed == '2')
                                    @if ($currentDate->between($checkStartDate, $checkEndDate) && $conditionWiseCount < $attendanceSettings?->adjustment_total_times && ($currentDate->isSameDay($employeeJoiningDate) || $currentDate->greaterThan($employeeJoiningDate)))
                                        @if ($matchingRequest)
                                            @if ($requestStatus == 'pending')
                                                <a data-date="{{ $currentDate->format('Y-m-d') }}" class="request-pending f-13"><i class="bi bi-link-45deg"></i>@lang('clan.attendance.requestPending')</a>
                                            @endif
                                        @else
                                                <a data-date="{{ $currentDate->format('Y-m-d') }}" class="request-regularise f-13"><i class="bi bi-link-45deg"></i>@lang('clan.attendance.requestRegularise')</a>
                                        @endif
                                    @endif

                                @elseif ($attendanceSettings?->times_adjustment_allowed == '3' && $beforeDayOfMonth)
                                    @if ($currentDate->between($currentMonthStart, $beforeDayOfMonth) && $today->between($currentMonthStart, $currentMonthEnd) && ($currentDate->isSameDay($employeeJoiningDate) || $currentDate->greaterThan($employeeJoiningDate)))
                                        @if ($matchingRequest)
                                            @if ($requestStatus == 'pending')
                                                <a data-date="{{ $currentDate->format('Y-m-d') }}" class="request-pending f-13"><i class="bi bi-link-45deg"></i>@lang('clan.attendance.requestPending')</a>
                                            @endif
                                        @else
                                                <a data-date="{{ $currentDate->format('Y-m-d') }}" class="request-regularise f-13"><i class="bi bi-link-45deg"></i>@lang('clan.attendance.requestRegularise')</a>
                                        @endif
                                    @endif
                                @endif
                            @endif
                    </div>
                @elseif($dateData['leave'])
                    @if ($dateData['leave']['duration'] == 'half day')
                        <label class="badge badge-primary">@lang('modules.attendance.leave')</label><br><br>
                        <label class="badge badge-warning">@lang('modules.attendance.halfDay')</label>
                    @else
                        <label class="badge badge-primary">@lang('modules.attendance.leave')</label>
                    @endif
                @else
                    <label class="badge badge-secondary">@lang('modules.attendance.holiday')</label>
                @endif
            </td>
            @if (isset($dateData['attendance']) && ($dateData['attendance'] == true))
                <td colspan="2">
                        <x-table class="mb-0 rounded table table-bordered table-hover">
                                @foreach ($dateData['attendance'] as $attendance)
                                    <tr>
                                        <td width="50%">
                                            {{ $attendance->clock_in_time->timezone(company()->timezone)->translatedFormat(company()->time_format) }}

                                            @if ($attendance->late == 'yes')
                                                <span class="text-dark-grey"><i class="fa fa-exclamation-triangle ml-2"></i>
                                                @lang('modules.attendance.late')</span>
                                            @endif

                                            @if ($attendance->half_day == 'yes')
                                                <span class="text-dark-grey"><i class="fa fa-sign-out-alt ml-2"></i>
                                                @lang('modules.attendance.halfDay')</span>
                                            @endif

                                            @if ($attendance->work_from_type != '')
                                                @if ($attendance->work_from_type == 'other')
                                                    <i class="fa fa-map-marker-alt ml-2"></i>
                                                    {{ $attendance->location }} ({{$attendance->working_from}})
                                                @else
                                                    <i class="fa fa-map-marker-alt ml-2"></i>
                                                    {{ $attendance->location }} ({{$attendance->work_from_type}})
                                                @endif
                                            @endif
                                        </td>
                                        <td width="50%">
                                            @if (!is_null($attendance->clock_out_time))
                                                {{ $attendance->clock_out_time->timezone(company()->timezone)->translatedFormat(company()->time_format) }}
                                            @else - @endif
                                        </td>
                                    </tr>
                                @endforeach
                        </x-table>
                </td>
                <td>{{ $attendance->totalTime($attendance->clock_in_time, $attendance->clock_in_time, $attendance->user_id) }}</td>
                <td class="text-right pb-2 pr-20">
                    <x-forms.button-secondary icon="search" class="view-attendance"
                        data-attendance-id="{{ $attendance->aId }}">
                        @lang('app.details')
                    </x-forms.button-secondary>
                </td>
            @else
                <td colspan="2">
                    <table width="100%">
                        <tr>
                            <td width="50%">-</td>
                            <td width="50%">-</td>
                        </tr>
                    </table>
                </td>
                <td>-</td>
                <td>-</td>
            @endif
        </tr>
    @endif
@empty
    <tr>
        <td colspan="6">
            <x-cards.no-record icon="calendar" :message="__('messages.noRecordFound')" />
        </td>
    </tr>
@endforelse
