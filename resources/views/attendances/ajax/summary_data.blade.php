@php
$addAttendancePermission = user()->permission('add_attendance');

// Icon/tooltip for manual overrides from the calendar panel's "Assign
// Status" action (stored in $day as "Override:<status>"). Reuses the same
// icon language as the rest of this table where a direct equivalent
// exists (Leave/Day Off/Half Day/Absent/Holiday/Present/WFH/Late); CL, SL
// and LOP get their own icons since this table has no prior convention
// for them.
$overrideMeta = [
    'cl' => ['icon' => 'file-alt text-primary', 'label' => 'Casual Leave (CL)'],
    'sl' => ['icon' => 'file-alt text-success', 'label' => 'Sick Leave (SL)'],
    'lop' => ['icon' => 'exclamation-triangle text-danger', 'label' => 'Loss of Pay (LOP)'],
    'cl-half' => ['icon' => 'file-alt text-primary', 'label' => 'Casual Leave (0.5)'],
    'sl-half' => ['icon' => 'file-alt text-success', 'label' => 'Sick Leave (0.5)'],
    'lop-half' => ['icon' => 'exclamation-triangle text-danger', 'label' => 'Loss of Pay (0.5)'],
    'holiday' => ['icon' => 'star text-warning', 'label' => 'Holiday'],
    'dayoff' => ['icon' => 'calendar-week text-red', 'label' => __('modules.attendance.dayOff')],
    'present' => ['icon' => 'check text-success', 'label' => __('app.present')],
    'absent' => ['icon' => 'times text-lightest', 'label' => __('app.absent')],
    'leave' => ['icon' => 'plane-departure text-red', 'label' => __('app.onLeave')],
    'wfh' => ['icon' => 'home text-primary', 'label' => __('modules.attendance.workFromHomeLegend')],
    'halfday' => ['icon' => 'star-half-alt text-red', 'label' => __('modules.attendance.halfDay')],
    'late' => ['icon' => 'exclamation-circle text-warning', 'label' => 'Late'],
];
// Credit each status contributes toward the Total column — a Half Day (from
// attendance, leave, or a calendar override) counts as 0.5 of a day, not a
// full day, everywhere it can occur.
$overrideCredit = ['present' => 1, 'wfh' => 1, 'late' => 1, 'halfday' => 0.5, 'cl-half' => 0.5, 'sl-half' => 0.5, 'lop-half' => 0.5];
@endphp
<div class="table-responsive">
    <x-table class="table-bordered mt-3 table-hover" headType="thead-light">
        <x-slot name="thead">
            <th class="px-2" style="vertical-align: middle;">@lang('app.employee')</th>
            @for ($i = 1; $i <= $daysInMonth; $i++)
            <th class="pr-2 pl-1 f-11">{{ $i }}
                <br>
                <span class="text-dark-grey f-10">
                    {{ $weekMap[\Carbon\Carbon::parse(\Carbon\Carbon::parse($i . '-' . $month . '-' . $year))->dayOfWeek] }}
                </span></th>
            @endfor
            <th class="px-2 text-center"></th>
            <th class="px-2 text-center"></th>
            <th class="text-right px-2">@lang('app.total')</th>
        </x-slot>

        @foreach ($employeeAttendence as $key => $attendance)
            @php
                $totalPresent = 0;
                $keyParts = explode('#', $key);
                $userId = $keyParts[0];
                $userName = $keyParts[1] ?? '';
            @endphp
            <tr>
                <td class="w-30 px-2"> {!! end($attendance) !!} </td>
                @foreach ($attendance as $key2 => $day)
                    @if ($key2 + 1 <= count($attendance))
                        @php
                            $attendanceDate = \Carbon\Carbon::parse($year.'-'.$month.'-'.$key2);
                        @endphp
                        <td class="px-1">
                            @if ($day == 'Leave')
                            <span data-toggle="tooltip" data-html="true"
                            data-original-title="{{ \Illuminate\Support\Str::limit(strip_tags($leaveReasons[$userId][$key2] ?? ''), 50, '...') }}">
                            <i class="fa fa-plane-departure text-red"></i>
                        </span>
                            @elseif ($day == 'Day Off')
                                <span data-toggle="tooltip" data-original-title="@lang('modules.attendance.dayOff')"><i
                                        class="fa fa-calendar-week text-red"></i></span>
                            @elseif ($day == 'Half Day')
                                @php
                                    $totalPresent = $totalPresent + 0.5;
                                @endphp
                                @if ($attendanceDate->isFuture())
                                    <span data-toggle="tooltip" data-original-title="@lang('modules.attendance.halfDay')"><i
                                        class="fa fa-star-half-alt text-red"></i></span>
                                @else
                                    <a @if ($addAttendancePermission == 'all') href="javascript:;" class="edit-attendance" @endif data-user-id="{{ $userId }}"
                                            data-attendance-date="{{ $key2 }}">
                                        <span data-toggle="tooltip" data-original-title="@lang('modules.attendance.halfDay')"><i
                                                class="fa fa-star-half-alt text-red"></i></span>
                                    </a>
                                @endif
                            @elseif ($day == 'Absent')
                                <a @if ($addAttendancePermission == 'all') href="javascript:;" class="edit-attendance" @endif data-user-id="{{ $userId }}"
                                    data-attendance-date="{{ $key2 }}"><i
                                        class="fa fa-times text-lightest"></i></a>
                            @elseif ($day == 'Holiday')
                                <a href="javascript:;" data-toggle="tooltip" @if(user()->permission('add_attendance') == 'all') class="edit-attendance" @endif
                                    data-original-title="{{ $holidayOccasions[$key2] }}"
                                    data-user-id="{{ $userId }}" data-attendance-date="{{ $key2 }}"><i
                                        class="fa fa-star text-warning"></i></a>
                            @elseif (str_starts_with($day, 'Override:') && isset($overrideMeta[substr($day, 9)]))
                                @php
                                    $overrideKey = substr($day, 9);
                                    $totalPresent = $totalPresent + ($overrideCredit[$overrideKey] ?? 0);
                                @endphp
                                <span data-toggle="tooltip" data-original-title="{{ $overrideMeta[$overrideKey]['label'] }}">
                                    <i class="fa fa-{{ $overrideMeta[$overrideKey]['icon'] }}"></i>
                                </span>
                            @else
                                @if ($day != '-')
                                    @php
                                        // Attendance-derived Half Day never stores the literal string 'Half
                                        // Day' — the controller stores rendered HTML with a star-half-alt
                                        // icon instead (see AttendanceController::summaryData()), so it
                                        // lands here rather than the @elseif above. Detect it the same way
                                        // the icon itself is rendered, so it gets 0.5 credit, not a full day.
                                        $totalPresent = $totalPresent + (str_contains($day, 'star-half-alt') ? 0.5 : 1);
                                    @endphp
                                @endif

                                {!! $day !!}
                            @endif
                        </td>
                    @endif
                @endforeach
                <td class="px-2 text-center">
                    @if (isset($pendingHalfDayDeductions[$userId]))
                        @php $deduction = $pendingHalfDayDeductions[$userId]; @endphp
                        <a href="javascript:;" class="apply-late-half-day" data-user-id="{{ $userId }}"
                            data-toggle="tooltip" data-html="true"
                            data-original-title="{{ $deduction['lateCount'] }} late comings this month ({{ $deduction['threshold'] }} = 1 half day)<br>Click to apply {{ $deduction['pending'] }} pending half-day deduction{{ $deduction['pending'] > 1 ? 's' : '' }}">
                            <i class="fa fa-exclamation-triangle text-warning"></i>
                        </a>
                    @endif
                </td>
                <td class="px-2 text-center">
                    <div class="d-flex align-items-center justify-content-center" style="white-space: nowrap;">
                        <a href="javascript:;" class="view-attendance-calendar" data-user-id="{{ $userId }}"
                            data-user-name="{{ $userName }}"
                            data-toggle="tooltip" data-original-title="@lang('app.viewCalendar')">
                            <i class="fa fa-calendar-alt text-primary"></i>
                        </a>

                        @if (user()->permission('delete_attendance') == 'all')
                            <div class="dropdown ml-1">
                                <a href="javascript:;" class="text-lightest" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                    <i class="fa fa-ellipsis-v"></i>
                                </a>
                                <div class="dropdown-menu dropdown-menu-right border-grey rounded b-shadow-4 p-0">
                                    <a class="dropdown-item d-block text-dark-grey f-13 py-1 px-3 delete-user-month-attendance"
                                        href="javascript:;" data-user-id="{{ $userId }}" data-user-name="{{ $userName }}">
                                        @lang('modules.attendance.deleteMonthData')
                                    </a>
                                </div>
                            </div>
                        @endif
                    </div>
                </td>
                <td class="text-dark f-w-500 text-right attendance-total px-2 w-100">
                    {!! $totalPresent . ' / <span class="text-lightest">' . (count($attendance) - 1) . '</span>' !!}</td>
            </tr>
        @endforeach
    </x-table>
    @include('attendances.ajax.pager')
</div>
