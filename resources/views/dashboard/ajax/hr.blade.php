<script src="{{ asset('vendor/jquery/frappe-charts.min.iife.js') }}"></script>
<script src="{{ asset('vendor/jquery/Chart.min.js') }}"></script>


{{-- arw-dash scopes public/css/app-custom.css to this screen; arw-stats lets the five figures share a row evenly --}}
<div class="arw-dash">
<div class="arw-stats">
    @if (in_array('leaves', user_modules()) && in_array('total_leaves_approved', $activeWidgets))
        <x-cards.stat accent="indigo" icon="plane-departure" link-id="total-leaves-approved"
            :label="__('modules.dashboard.totalLeavesApproved')" :value="$totalLeavesApproved"
            :info="__('messages.leaveInfo')" />
    @endif

    @if (in_array('employees', user_modules()) && in_array('total_new_employee', $activeWidgets))
        <x-cards.stat accent="emerald" icon="users" link-class="total-employees" :link-data="['status' => 'open']"
            :label="__('app.menu.employees')" :value="$totalEmployee"
            :info="__('messages.totalEmployeeInfo')"
            :sub-value="$totalNewEmployee" :sub-label="__('modules.dashboard.totalNewEmployee')"
            :sub-info="__('messages.newEmployeeInfo')"
            sub-link-class="total-new-employees" :sub-link-data="['status' => 'resolved']" />
    @endif

    @if (in_array('employees', user_modules()) && in_array('total_employee_exits', $activeWidgets))
        <x-cards.stat accent="amber" icon="sign-out-alt" link-id="total-ex-employees"
            :label="__('modules.dashboard.totalEmployeeExits')" :value="$totalEmployeeExits"
            :info="__('messages.employeeExitInfo')" />
    @endif

    @if (in_array('attendance', user_modules()) && in_array('total_today_attendance', $activeWidgets))
        <x-cards.stat accent="sky" icon="calendar-check" :href="route('attendances.index')" link-class="arw-stat__go"
            :label="__('modules.dashboard.totalTodayAttendance')"
            :value="$counts->totalTodayAttendance.'/'.$counts->totalEmployees" />
    @endif

    @if (in_array('attendance', user_modules()) && in_array('average_attendance', $activeWidgets))
        <x-cards.stat accent="violet" icon="fingerprint" :href="route('attendances.index')" link-class="arw-stat__go"
            :label="__('modules.dashboard.averageAttendance')" :value="$averageAttendance" />
    @endif
</div>

<div class="row">
    @if (in_array('employees', user_modules()) && in_array('department_wise_employee', $activeWidgets))
        <div class="col-sm-12 col-lg-6 mt-3">
            <x-cards.data :title="__('modules.dashboard.departmentWiseEmployee').' <i class=\'fa fa-question-circle\' data-toggle=\'popover\' data-placement=\'top\' data-content=\''.__('messages.dateFilterNotApplied').'\' data-trigger=\'hover\'></i>'">
                @if (array_sum($departmentWiseChart['values']) > 0)
                    <x-slot name="action">
                        <a href="javascript:;" class="text-darkest-grey f-w-500 piechart-full-screen" data-chart-id="task-chart1" data-chart-data="{{ json_encode($departmentWiseChart) }}"><i class="fas fa-expand"></i></a>
                    </x-slot>
                @endif
                <x-pie-chart id="task-chart1" :labels="$departmentWiseChart['labels']"
                    :values="$departmentWiseChart['values']" :colors="$departmentWiseChart['colors'] ?? null" height="300" width="300" />
            </x-cards.data>
        </div>
    @endif

    @if (in_array('employees', user_modules()) && in_array('designation_wise_employee', $activeWidgets))
        <div class="col-sm-12 col-lg-6 mt-3">
            <x-cards.data :title="__('modules.dashboard.designationWiseEmployee').' <i class=\'fa fa-question-circle\' data-toggle=\'popover\' data-placement=\'top\' data-content=\''.__('messages.dateFilterNotApplied').'\' data-trigger=\'hover\'></i>'">
                @if (array_sum($designationWiseChart['values']) > 0)
                    <x-slot name="action">
                        <a href="javascript:;" class="text-darkest-grey f-w-500 piechart-full-screen" data-chart-id="task-chart2" data-chart-data="{{ json_encode($designationWiseChart) }}"><i class="fas fa-expand"></i></a>
                    </x-slot>
                @endif
                <x-pie-chart id="task-chart2" :labels="$designationWiseChart['labels']"
                    :values="$designationWiseChart['values']" :colors="$designationWiseChart['colors'] ?? null" height="300" width="300" />
            </x-cards.data>
        </div>
    @endif

    @if (in_array('employees', user_modules()) && in_array('gender_wise_employee', $activeWidgets))
        <div class="col-sm-12 col-lg-6 mt-3">
            <x-cards.data :title="__('modules.dashboard.genderWiseEmployee').' <i class=\'fa fa-question-circle\' data-toggle=\'popover\' data-placement=\'top\' data-content=\''.__('messages.dateFilterNotApplied').'\' data-trigger=\'hover\'></i>'">
                @if (array_sum($genderWiseChart['values']) > 0)
                    <x-slot name="action">
                        <a href="javascript:;" class="text-darkest-grey f-w-500 piechart-full-screen" data-chart-id="task-chart3" data-chart-data="{{ json_encode($genderWiseChart) }}"><i class="fas fa-expand"></i></a>
                    </x-slot>
                @endif
                <x-pie-chart id="task-chart3" :labels="$genderWiseChart['labels']" :values="$genderWiseChart['values']"
                    :colors="$genderWiseChart['colors'] ?? null" height="300" width="300" />
            </x-cards.data>
        </div>
    @endif

    @if (in_array('employees', user_modules()) && in_array('role_wise_employee', $activeWidgets))
        <div class="col-sm-12 col-lg-6 mt-3">
            <x-cards.data :title="__('modules.dashboard.roleWiseEmployee').' <i class=\'fa fa-question-circle\' data-toggle=\'popover\' data-placement=\'top\' data-content=\''.__('messages.dateFilterNotApplied').'\' data-trigger=\'hover\'></i>'">
                @if (array_sum($roleWiseChart['values']) > 0)
                    <x-slot name="action">
                        <a href="javascript:;" class="text-darkest-grey f-w-500 piechart-full-screen" data-chart-id="task-chart4" data-chart-data="{{ json_encode($roleWiseChart) }}"><i class="fas fa-expand"></i></a>
                    </x-slot>
                @endif
                <x-pie-chart id="task-chart4" :labels="$roleWiseChart['labels']" :values="$roleWiseChart['values']"
                    :colors="$roleWiseChart['colors'] ?? null" height="300" width="300" />
            </x-cards.data>
        </div>
    @endif

    @if (in_array('employees', user_modules()) && in_array('headcount', $activeWidgets))
    <div class="col-sm-12 col-lg-12 mt-3">
        <x-cards.data :title="__('modules.dashboard.headcount').' <i class=\'fa fa-question-circle\' data-toggle=\'popover\' data-placement=\'top\' data-content=\''.__('app.lastTweleveMonths').' \' data-trigger=\'hover\'></i>'">
            <x-bar-chart id="task-chart6" :chartData="$headCountChart" height="300"></x-bar-chart>
        </x-cards.data>
    </div>
    @endif

    @if (in_array('employees', user_modules()) && in_array('joining_vs_attrition', $activeWidgets))
    <div class="col-sm-12 col-lg-12 mt-3">
        <x-cards.data :title="__('modules.dashboard.joiningVsAttrition').' <i class=\'fa fa-question-circle\' data-toggle=\'popover\' data-placement=\'top\' data-content=\''.__('app.lastTweleveMonths').' \' data-trigger=\'hover\'></i>'">
            <x-line-chart id="task-chart5" :chartData="$joiningVsAttritionChart" height="250" multiple="true" />
        </x-cards.data>
    </div>
    @endif


    @if (in_array('leaves', user_modules()) && in_array('leaves_taken', $activeWidgets))
        <div class="col-sm-12 col-lg-6 mt-3">
            <x-cards.data :title="__('modules.dashboard.leavesTaken')" padding="false" otherClasses="h-200">
                <x-table>
                    @forelse ($leavesTaken as $item)
                        <tr>
                            <td class="pl-20">
                                <x-employee :user="$item" />
                            </td>
                            <td class="pr-20 text-right"><span
                                    class="badge badge-light p-2">{{ $item->employeeLeaveCount }}</span></td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="2" class="shadow-none">
                                <x-cards.no-record icon="plane-departure" :message="__('messages.noRecordFound')" />
                            </td>
                        </tr>
                    @endforelse
                </x-table>
            </x-cards.data>
        </div>
    @endif

    @if (in_array('birthday', $activeWidgets))
        <div class="col-sm-12 col-lg-6 mt-3">
            <x-cards.data :title="__('modules.dashboard.birthday')" padding="false" otherClasses="h-200">
                <x-table>
                    @forelse ($birthdays as $birthday)
                        <tr>
                            <td class="pl-20">
                                <x-employee :user="$birthday->user"/>
                            </td>
                            <td>
                            <span class="badge badge-light p-2">
                                <i class="fa fa-birthday-cake"></i>
                                {{ $birthday->date_of_birth->translatedFormat('d M') }}
                            </span>
                            </td>
                            <td class="pr-20" align="right">
                                @php
                                    $currentYear = now(company()->timezone)->year;
                                    // Set the birthday date to the current year for comparison
                                    $dateBirth = $birthday->date_of_birth
                                        ->copy()
                                        ->setTimezone(company()->timezone)
                                        ->setYear($currentYear);

                                    if ($dateBirth->isPast() && !$dateBirth->isToday()) {
                                        $dateBirth = $dateBirth->copy()->addYear();
                                    }

                                    $diffInDays = $dateBirth->diffForHumans(
                                        now(company()->timezone),
                                        [
                                            'syntax' => \Carbon\CarbonInterface::DIFF_RELATIVE_AUTO,
                                            'options' => \Carbon\Carbon::JUST_NOW | \Carbon\Carbon::ONE_DAY_WORDS | \Carbon\Carbon::TWO_DAY_WORDS,
                                        ]
                                    );
                                @endphp

                                @if ($dateBirth->isToday())
                                    <span class="badge badge-light text-success p-2"><i class="fa fa-smile"></i> @lang('app.today')</span>
                                @else
                                    <span class="badge badge-light p-2">{{ $diffInDays }}</span>
                                @endif
                            </td>
                    
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3" class="shadow-none">
                                <x-cards.no-record icon="birthday-cake" :message="__('messages.noRecordFound')" />
                            </td>
                        </tr>
                    @endforelse
                </x-table>
            </x-cards.data>
        </div>
    @endif

    @if (in_array('attendance', user_modules()) && in_array('late_attendance_mark', $activeWidgets))
        <div class="col-sm-12 col-lg-6 mt-3">
            <x-cards.data :title="__('modules.dashboard.lateAttendanceMark')" padding="false" otherClasses="h-200">
                <x-table>
                    @forelse ($lateAttendanceMarks as $item)
                        <tr>
                            <td class="pl-20">
                                <x-employee :user="$item" />
                            </td>
                            <td><span class="badge badge-light p-2">{{ $item->employeeLateCount }}</span></td>
                            <td class="pr-20 text-right">
                                <x-forms.button-secondary icon="eye" data-user-id="{{ $item->id }}" class="view-late-attendance">
                                    @lang('app.view')
                                </x-forms.button-secondary>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="2" class="shadow-none">
                                <x-cards.no-record icon="user-clock" :message="__('messages.noRecordFound')" />
                            </td>
                        </tr>
                    @endforelse
                </x-table>
            </x-cards.data>
        </div>
    @endif

</div>
</div>{{-- /arw-dash --}}

<script>

    $('#save-dashboard-widget').click(function() {
        $.easyAjax({
            url: "{{ route('dashboard.widget', 'admin-hr-dashboard') }}",
            container: '#dashboardWidgetForm',
            blockUI: true,
            type: "POST",
            redirect: true,
            data: $('#dashboardWidgetForm').serialize(),
            success: function() {
                window.location.reload();
            }
        })
    });

    $('body').on('click', '#total-leaves-approved', function() {
        var dateRange = getDateRange();

        var url = `{{ route('leaves.index') }}`;
        string = `?status=approved&start=${dateRange.startDate}&end=${dateRange.endDate}`;
        url += string;

        window.location.href = url;
    });

    $('body').on('click', '.total-new-employees', function() {
        var dateRange = getDateRange();
        var url = `{{ route('employees.index') }}`;

        string = `?startDate=${dateRange.startDate}&endDate=${dateRange.endDate}`;
        url += string;

        window.location.href = url;
    });

    $('body').on('click', '.total-employees', function() {
        var dateRange = getDateRange();
        var url = `{{ route('employees.index') }}`;
        window.location.href = url;
    });

    $('body').on('click', '#total-ex-employees', function() {
        var dateRange = getDateRange();
        var url = `{{ route('employees.index') }}`;

        string = `?EmployeeType=ex_employee&lastStartDate=${dateRange.startDate}&lastEndDate=${dateRange.endDate}`;
        url += string;

        window.location.href = url;
    });

    $('body').on('click', '.view-late-attendance', function() {
        var empId = $(this).data('user-id');
        var dateRange = getDateRange();
        var url = `{{ route('attendances.index') }}`;

        var string = `?employee_id=${empId}&late=yes`;
        url += string;

        window.location.href = url;
    });

    function getDateRange() {
        var dateRange = $('#datatableRange2').data('daterangepicker');
        var startDate = dateRange.startDate.format('{{ company()->moment_date_format }}');
        var endDate = dateRange.endDate.format('{{ company()->moment_date_format }}');

        startDate = encodeURIComponent(startDate);
        endDate = encodeURIComponent(endDate);

        var data = [];
        data['startDate'] = startDate;
        data['endDate'] = endDate;

        return data;
    }

</script>
