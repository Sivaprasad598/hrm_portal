@php
$addAttendancePermission = user()->permission('add_attendance');
$editAttendancePermission = user()->permission('edit_attendance');
$deleteAttendancePermission = user()->permission('delete_attendance');
@endphp

<div class="modal-header">
    <h5 class="modal-title" id="modelHeading">@lang('app.attendanceDetails')</h5>
    <button type="button"  class="close" data-dismiss="modal" aria-label="Close"><span
            aria-hidden="true">×</span></button>
</div>

<div class="modal-body bg-grey">
    <div class="row">
        <div class="col-md-12 mb-4">
            <x-cards.user :image="$attendance->user->image_url">
                <div class="row">
                    <div class="col-12">
                        <h4 class="card-title f-15 f-w-500 text-darkest-grey mb-0">
                            <a href="{{ route('employees.show', [$attendance->user->id]) }}"
                                class="text-darkest-grey">{{ $attendance->user->name }}  @if(user() && user()->id == $attendance->user->id) <span class='ml-2 badge badge-secondary'> @lang('app.itsYou')</span> @endif </a>

                            @isset($attendance->user->country)
                                <x-flag :country="$attendance->user->country" />
                            @endisset
                        </h4>
                        <p class="mb-0 f-13 text-dark-grey">
                            {{ (!is_null($attendance->user->employeeDetail) && !is_null($attendance->user->employeeDetail->designation)) ? $attendance->user->employeeDetail->designation->name : ' ' }}
                        </p>
                    </div>
                </div>
            </x-cards.user>
        </div>
    </div>

    <div class="row">
        <div class="col-md-6">
            <x-cards.data :title="__('app.date').' - '.$attendanceDate->translatedFormat(company()->date_format) .' ('.$attendanceDate->translatedFormat('l').')'">
                <div class="mb-3">
                    @if ($hasAssignedShift && !empty($attendanceSettings) && !empty($attendanceSettings->shift_name))
                        <p class="mb-0 f-13">{{ __('modules.attendance.shiftLabel') }}: {{ $attendanceSettings->shift_name }}</p>
                        <p class="mb-0 f-13">{{ __('modules.attendance.timing') }}: {{ \Carbon\Carbon::createFromFormat('H:i:s', $attendanceSettings->office_start_time)->translatedFormat(company()->time_format) }} - {{ \Carbon\Carbon::createFromFormat('H:i:s', $attendanceSettings->office_end_time)->translatedFormat(company()->time_format) }}</p>
                        <p class="mb-0 f-13">{{ __('modules.attendance.totalShiftHours') }}: {{ $totalShiftHoursLabel }}</p>
                    @else
                        <p class="mb-0 f-13">{{ __('modules.attendance.shiftLabel') }}: {{ __('modules.attendance.noShiftAssigned') }}</p>
                    @endif
                </div>
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
                    <div class="attendance-summary mb-3">
                        <div class="row">
                            <div class="col-sm-6">
                                <p class="mb-1"><strong>@lang('modules.attendance.firstClockIn'):</strong> {{ $firstClockInTimeFormatted }}</p>
                                <p class="mb-1"><strong>@lang('modules.attendance.lastClockOut'):</strong> {{ $lastClockOutTimeFormatted }}</p>
                                <p class="mb-1"><strong>@lang('modules.attendance.totalSessions'):</strong> {{ $totalSessions }}</p>
                            </div>
                            <div class="col-sm-6">
                                <p class="mb-1"><strong>@lang('modules.attendance.totalTimeInOffice'):</strong> {{ $totalTimeInOffice }}</p>
                                <p class="mb-1"><strong>@lang('modules.attendance.totalTimeOutside'):</strong> {{ $totalTimeOutside }}</p>
                                <p class="mb-1"><strong>@lang('modules.attendance.totalWorkingSpan'):</strong> {{ $totalWorkingSpan }}</p>
                            </div>
                        </div>
                    </div>
                    <div class="border rounded p-3 bg-light">
                        <h6 class="f-13">@lang('modules.attendance.clock_out')</h6>
                        <p class="mb-0">{{ $endTime != '' ? $endTime->translatedFormat(company()->time_format) : '' }}
                            @if (isset($notClockedOut))
                                (@lang('modules.attendance.notClockOut'))
                            @endif
                        </p>
                    </div>
                    <input type="hidden" id="date" value="{{ $attendanceDate }}">

                </div>

                <div class="row mt-3">
                    @isset($dayStatus)
                        <div class="col-6 col-md-3 mb-3">
                            <h6 class="f-13">@lang('modules.attendance.dayStatus')</h6>
                            <p class="mb-0">
                                <span class="badge {{ ['Present' => 'badge-success', 'Half Day' => 'badge-warning', 'Absent' => 'badge-danger'][$dayStatus] ?? 'badge-secondary' }}">{{ $dayStatus }}</span>
                            </p>
                        </div>
                    @endisset
                    <div class="col-6 col-md-3 mb-3">
                        <h6 class="f-13">@lang('modules.attendance.shiftHours')</h6>
                        <p class="mb-0">{{ $shiftHours }}</p>
                    </div>
                    <div class="col-6 col-md-3 mb-3">
                        <h6 class="f-13">@lang('modules.attendance.lateBy')</h6>
                        <p class="mb-0">{{ $lateTime }}</p>
                    </div>
                    <div class="col-6 col-md-3 mb-3">
                        <h6 class="f-13">@lang('modules.attendance.earlyExit')</h6>
                        <p class="mb-0">{{ $earlyExitTime }}</p>
                    </div>
                    <div class="col-6 col-md-3 mb-3">
                        <h6 class="f-13">@lang('modules.attendance.overtime')</h6>
                        <p class="mb-0">{{ $overtime }}</p>
                    </div>
                </div>
            </x-cards.data>
        </div>
        <div class="col-md-6">

            <x-cards.data :title="__('modules.employees.activity')">
                @if ($addAttendancePermission == 'all' && $maxClockIn)
                    <x-slot name="action">
                        <a class="btn-primary rounded f-12 py-1 px-2" href="javascript:;" onclick="addAttendance({{ $attendance->user->id }})" data-attendance-id="{{ $attendance->user->id }}">@lang('app.add')</a>
                    </x-slot>
                @endif

                <div class="recent-activity">
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

                                        @if ($item->clock_in_type == 'biometric')
                                            <i class="fa fa-fingerprint ml-2" data-toggle="tooltip" data-original-title="@lang('modules.module.biometric')"></i>
                                        @endif

                                        @if ($item->latitude != '' && $item->longitude != '')

                                        <a href="https://www.google.com/maps/search/?api=1&query={{ $item->latitude }}%2C{{ $item->longitude }}" target="_blank">
                                            <i class="fa fa-map-marked-alt ml-2"></i> @lang('modules.attendance.showOnMap')</a>
                                        @endif
                                    </p>

                                    {{-- What was captured at the moment of clocking in. --}}
                                    <div class="clock-in-photo-block d-flex align-items-start mb-2">
                                        @php
                                            $photoUrl = \App\Services\Attendance\ClockInCapture::url($item->clock_in_photo ?? null);
                                            $need = $item->photo_requirement ?? 'off';
                                        @endphp

                                        @if ($photoUrl)
                                            <a href="{{ $photoUrl }}" target="_blank" title="Open the full picture">
                                                <img src="{{ $photoUrl }}" alt="Photo taken at clock-in"
                                                     style="width:72px; height:72px; object-fit:cover; border-radius:6px; border:1px solid #e2e8f0">
                                            </a>
                                        @else
                                            <div class="d-flex align-items-center justify-content-center"
                                                 style="width:72px; height:72px; border-radius:6px; background:#f1f5f9;
                                                        border:1px dashed #cbd5e0; color:#94a3b8">
                                                <i class="fa fa-user-slash"></i>
                                            </div>
                                        @endif

                                        <div class="ml-2 f-12" style="line-height:1.5">
                                            @if ($photoUrl)
                                                <span class="text-dark-grey">Photo taken at clock-in</span>
                                            @elseif ($need === 'mandatory')
                                                <span class="text-red">No photo on record, though one was required</span>
                                            @elseif ($need === 'optional')
                                                <span class="text-lightest">No photo — it was optional that day</span>
                                            @else
                                                <span class="text-lightest">No photo — none was asked for that day</span>
                                            @endif

                                            <span class="d-block text-lightest">
                                                @if ($item->latitude != '' && $item->longitude != '')
                                                    <i class="fa fa-map-marker-alt mr-1"></i>
                                                    {{ number_format((float) $item->latitude, 6) }},
                                                    {{ number_format((float) $item->longitude, 6) }}
                                                @else
                                                    <i class="fa fa-map-marker-alt mr-1"></i> No location recorded
                                                @endif
                                            </span>
                                        </div>
                                    </div>
                                </li>
                                <li>
                                    <p class="mb-0">@lang('modules.attendance.clock_out')</p>
                                    <p class="res-activity-time">
                                        <i class="fa fa-clock"></i>
                                        @if (!is_null($item->clock_out_time))
                                            {{ $item->clock_out_time->timezone(company()->timezone)->translatedFormat(company()->date_format . ' ' . company()->time_format) }}

                                            @if ($item->clock_out_time_work_from_type != '')
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
                                            @if ($item->clock_in_type == 'biometric')
                                            <i class="fa fa-fingerprint ml-2" data-toggle="tooltip" data-original-title="@lang('modules.module.biometric')"></i>
                                        @endif
                                        @else
                                            @lang('modules.attendance.notClockOut')
                                        @endif

                                        @if ($item->clock_out_latitude != '' && $item->clock_out_longitude != '')
                                            <a href="https://www.google.com/maps/search/?api=1&query={{ $item->clock_out_latitude }}%2C{{ $item->clock_out_longitude }}" target="_blank">
                                                <i class="fa fa-map-marked-alt ml-2"></i> @lang('modules.attendance.showOnMap')</a>
                                        @endif
                                    </p>

                                    {{-- What was captured on the way out, laid out exactly as
                                         the clock-in block above it. Only shown once there is
                                         a clock-out to describe. --}}
                                    @if (!is_null($item->clock_out_time))
                                        <div class="clock-out-photo-block d-flex align-items-start mb-2">
                                            @php
                                                $outPhotoUrl = \App\Services\Attendance\ClockInCapture::url($item->clock_out_photo ?? null);
                                                $outNeed = $item->clock_out_photo_requirement ?? 'off';
                                            @endphp

                                            @if ($outPhotoUrl)
                                                <a href="{{ $outPhotoUrl }}" target="_blank" title="Open the full picture">
                                                    <img src="{{ $outPhotoUrl }}" alt="Photo taken at clock-out"
                                                         style="width:72px; height:72px; object-fit:cover; border-radius:6px; border:1px solid #e2e8f0">
                                                </a>
                                            @else
                                                <div class="d-flex align-items-center justify-content-center"
                                                     style="width:72px; height:72px; border-radius:6px; background:#f1f5f9;
                                                            border:1px dashed #cbd5e0; color:#94a3b8">
                                                    <i class="fa fa-user-slash"></i>
                                                </div>
                                            @endif

                                            <div class="ml-2 f-12" style="line-height:1.5">
                                                @if ($outPhotoUrl)
                                                    <span class="text-dark-grey">Photo taken at clock-out</span>
                                                @elseif ($outNeed === 'mandatory')
                                                    <span class="text-red">No photo on record, though one was required</span>
                                                @elseif ($outNeed === 'optional')
                                                    <span class="text-lightest">No photo — it was optional that day</span>
                                                @else
                                                    <span class="text-lightest">No photo — none was asked for that day</span>
                                                @endif

                                                <span class="d-block text-lightest">
                                                    @if ($item->clock_out_latitude != '' && $item->clock_out_longitude != '')
                                                        <i class="fa fa-map-marker-alt mr-1"></i>
                                                        {{ number_format((float) $item->clock_out_latitude, 6) }},
                                                        {{ number_format((float) $item->clock_out_longitude, 6) }}
                                                    @else
                                                        <i class="fa fa-map-marker-alt mr-1"></i> No location recorded
                                                    @endif
                                                </span>

                                                @if ($item->clock_out_place)
                                                    <span class="d-block text-lightest">
                                                        <i class="fa fa-building mr-1"></i> {{ $item->clock_out_place }}
                                                    </span>
                                                @endif
                                            </div>
                                        </div>
                                    @endif
                                </li>
                            </ul>

                            <div class="col-md-3 text-right">
                                <div class="dropdown ml-auto comment-action">
                                    @if ($editAttendancePermission == 'all'
                                        || ($addAttendancePermission == 'all')
                                        || ($editAttendancePermission == 'added' && $item->added_by == user()->id)
                                        || ($editAttendancePermission == 'owned' && $attendance->user->id == user()->id)
                                        || ($editAttendancePermission == 'both' && ($item->added_by == user()->id || $attendance->user->id == user()->id))
                                        || $deleteAttendancePermission == 'all'
                                        || ($deleteAttendancePermission == 'added' && $item->added_by == user()->id)
                                        || ($deleteAttendancePermission == 'owned' && $attendance->user->id == user()->id)
                                        || ($deleteAttendancePermission == 'both' && ($item->added_by == user()->id || $attendance->user->id == user()->id))
                                    )
                                    <button
                                        class="btn btn-lg f-14 py-0 text-lightest  rounded  dropdown-toggle"
                                        type="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                        <i class="fa fa-ellipsis-h"></i>
                                    </button>
                                    <div class="dropdown-menu dropdown-menu-right border-grey rounded b-shadow-4 p-0 mr-2"
                                        aria-labelledby="dropdownMenuLink" tabindex="0">

                                        @if ($editAttendancePermission == 'all'
                                            || ($editAttendancePermission == 'added' && $item->added_by == user()->id)
                                            || ($editAttendancePermission == 'owned' && $attendance->user->id == user()->id)
                                            || ($editAttendancePermission == 'both' && ($item->added_by == user()->id || $attendance->user->id == user()->id))
                                            )
                                            <a class="dropdown-item d-block text-dark-grey f-13 py-1 px-3"
                                                href="javascript:;" onclick="editAttendance({{ $item->aId }})"
                                                data-attendance-id="{{ $item->aId }}">@lang('app.edit')</a>
                                        @endif

                                        @if ($deleteAttendancePermission == 'all'
                                            || ($deleteAttendancePermission == 'added' && $item->added_by == user()->id)
                                            || ($deleteAttendancePermission == 'owned' && $attendance->user->id == user()->id)
                                            || ($deleteAttendancePermission == 'both' && ($item->added_by == user()->id || $attendance->user->id == user()->id))
                                            )
                                            <a class="cursor-pointer dropdown-item d-block text-dark-grey f-13 pb-1 px-3"
                                                onclick="deleteAttendance({{ $item->aId }})"
                                                data-attendance-id="{{ $item->aId }}"
                                                href="javascript:;">@lang('app.delete')</a>
                                        @endif
                                    </div>
                                    @endif
                                </div>

                            </div>
                        </div>
                    @endforeach

                </div>
            </x-cards.data>
        </div>
    </div>

</div>
<script>

    function addAttendance(userID) {
            var date = $('#date').val();
            const attendanceDate = date.split("-");
            let dayTime = attendanceDate[2];
            dayTime = dayTime.split(' ');
            let day = dayTime[0];
            let month = attendanceDate[1];
            let year = attendanceDate[0];

            var url = "{{ route('attendances.add-user-attendance', [':userid', ':day', ':month', ':year']) }}";
            url = url.replace(':userid', userID);
            url = url.replace(':day', day);
            url = url.replace(':month', month);
            url = url.replace(':year', year);

            $(MODAL_LG + ' ' + MODAL_HEADING).html('...');
            $.ajaxModal(MODAL_LG, url);
        }

    function deleteAttendance(id) {
        var url = "{{ route('attendances.destroy', ':id') }}";
        url = url.replace(':id', id);
        var token = "{{ csrf_token() }}";

        Swal.fire({
            title: "@lang('messages.sweetAlertTitle')",
            text: "@lang('messages.recoverRecord')",
            icon: 'warning',
            showCancelButton: true,
            focusConfirm: false,
            confirmButtonText: "@lang('messages.confirmDelete')",
            cancelButtonText: "@lang('app.cancel')",
            customClass: {
                confirmButton: 'btn btn-primary mr-3',
                cancelButton: 'btn btn-secondary'
            },
            showClass: {
                popup: 'swal2-noanimation',
                backdrop: 'swal2-noanimation'
            },
            buttonsStyling: false
        }).then((result) => {
            if (result.isConfirmed) {
                $.easyAjax({
                    type: 'POST',
                    url: url,
                    data: {
                        '_token': token,
                        '_method': 'DELETE'
                    },
                    success: function(response) {
                        if (response.status == "success") {
                            showTable();
                            $(MODAL_XL).modal('hide');
                        }
                    }
                });
            }
        });

    }

</script>
