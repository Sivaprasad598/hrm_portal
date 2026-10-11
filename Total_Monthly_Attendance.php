<?php
require_once __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

use App\Models\Attendance;
use App\Models\Company;
use App\Models\EmployeeShiftSchedule;
use App\Models\Holiday;
use App\Models\Leave;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

function tmaEsc($value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function tmaClassifyLeaveType(string $typeName): ?string
{
    $name = strtolower(trim($typeName));

    if (str_starts_with($name, 'casual')) {
        return 'cl';
    }

    if (str_starts_with($name, 'sick')) {
        return 'sl';
    }

    if ($name === 'lop') {
        return 'lop';
    }

    return null;
}

$companyId = isset($_GET['company_id']) ? (int) $_GET['company_id'] : 0;
$month = isset($_GET['month']) ? (int) $_GET['month'] : (int) date('n');
$year = isset($_GET['year']) ? (int) $_GET['year'] : (int) date('Y');
$departmentId = isset($_GET['department_id']) && $_GET['department_id'] !== '' ? (int) $_GET['department_id'] : 0;
$employeeIdFilter = isset($_GET['employee_id']) && $_GET['employee_id'] !== '' ? (int) $_GET['employee_id'] : 0;

$departments = [];
$allEmployeeOptions = [];
$summaryRows = [];
$monthLabel = '';
$monthNames = ['', 'January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];

if ($companyId > 0) {
    try {
        $company = Company::find($companyId);
        $timezone = $company->timezone ?? 'UTC';

        $departments = DB::table('teams')->where('company_id', $companyId)->orderBy('team_name')->pluck('team_name', 'id')->toArray();

        // Full employee list for the searchable dropdown (name + employee_id + card id),
        // independent of the Department filter.
        $allEmployeeOptions = User::withRole('employee')
            ->join('employee_details', 'employee_details.user_id', '=', 'users.id')
            ->where('users.company_id', $companyId)
            ->select('users.id', 'users.name', 'employee_details.employee_id', 'employee_details.employee_card_id')
            ->orderBy('users.name')
            ->get();

        $employeesQuery = User::withRole('employee')
            ->join('employee_details', 'employee_details.user_id', '=', 'users.id')
            ->where('users.company_id', $companyId)
            ->select('users.id', 'users.name', 'employee_details.department_id');

        if ($departmentId > 0) {
            $employeesQuery->where('employee_details.department_id', $departmentId);
        }

        if ($employeeIdFilter > 0) {
            $employeesQuery->where('users.id', $employeeIdFilter);
        }

        $employees = $employeesQuery->orderBy('users.name')->get();
        $employeeIds = $employees->pluck('id');

        $daysInMonth = Carbon::createFromDate($year, $month, 1)->daysInMonth;
        $monthStart = Carbon::createFromDate($year, $month, 1, $timezone)->startOfDay();
        $monthEnd = Carbon::createFromDate($year, $month, $daysInMonth, $timezone)->endOfDay();
        $today = Carbon::now($timezone)->startOfDay();
        $monthLabel = $monthStart->translatedFormat('F Y');

        $leaveTypeClassification = [];
        foreach (DB::table('leave_types')->where('company_id', $companyId)->whereNull('deleted_at')->get(['id', 'type_name']) as $lt) {
            $key = tmaClassifyLeaveType($lt->type_name);
            if ($key !== null) {
                $leaveTypeClassification[$lt->id] = $key;
            }
        }

        $workedSecondsByUserDay = [];
        $shiftByUserDay = [];

        Attendance::whereIn('user_id', $employeeIds)
            ->whereBetween('clock_in_time', [$monthStart->copy()->timezone('UTC'), $monthEnd->copy()->timezone('UTC')])
            ->with('shift')
            ->get()
            ->each(function ($row) use (&$workedSecondsByUserDay, &$shiftByUserDay, $timezone, $year, $month) {
                $local = $row->clock_in_time->copy()->timezone($timezone);

                if ((int) $local->year !== $year || (int) $local->month !== $month) {
                    return;
                }

                $day = (int) $local->day;
                $shiftByUserDay[$row->user_id][$day] = $row->shift;

                if ($row->clock_out_time) {
                    $seconds = abs($row->clock_out_time->diffInSeconds($row->clock_in_time));
                    $workedSecondsByUserDay[$row->user_id][$day] = ($workedSecondsByUserDay[$row->user_id][$day] ?? 0) + $seconds;
                }
            });

        $dayOffByUserDay = [];
        EmployeeShiftSchedule::whereIn('user_id', $employeeIds)
            ->whereBetween('date', [$monthStart->toDateString(), $monthEnd->toDateString()])
            ->with('shift')
            ->get()
            ->each(function ($schedule) use (&$dayOffByUserDay) {
                if ($schedule->shift && $schedule->shift->shift_name === 'Day Off') {
                    $dayOffByUserDay[$schedule->user_id][(int) Carbon::parse($schedule->date)->day] = true;
                }
            });

        $leaveByUserDay = [];
        Leave::whereIn('user_id', $employeeIds)
            ->whereBetween('leave_date', [$monthStart->toDateString(), $monthEnd->toDateString()])
            ->where('status', 'approved')
            ->get()
            ->each(function ($leave) use (&$leaveByUserDay) {
                $leaveByUserDay[$leave->user_id][(int) Carbon::parse($leave->leave_date)->day] = $leave->leave_type_id;
            });

        $holidaysByDay = [];
        Holiday::where('company_id', $companyId)
            ->whereBetween('date', [$monthStart->toDateString(), $monthEnd->toDateString()])
            ->get()
            ->each(function ($holiday) use (&$holidaysByDay) {
                $holidaysByDay[(int) Carbon::parse($holiday->date)->day] = true;
            });

        foreach ($employees as $employee) {
            $present = $absent = $leaveDays = $holidayDays = $cl = $sl = $lop = $dayOffDays = $halfDayCount = 0;
            $presentFullDays = 0; // pure full-day count, without the +0.5 half-day credit already folded into $present
            $workedSecondsTotal = 0;

            for ($day = 1; $day <= $daysInMonth; $day++) {
                $date = Carbon::createFromDate($year, $month, $day, $timezone)->startOfDay();

                if ($date->greaterThan($today)) {
                    continue;
                }

                if (!empty($dayOffByUserDay[$employee->id][$day])) {
                    $dayOffDays++;
                    continue;
                }

                if (!empty($holidaysByDay[$day])) {
                    $holidayDays++;
                    continue;
                }

                if (isset($leaveByUserDay[$employee->id][$day])) {
                    $leaveDays++;
                    $classification = $leaveTypeClassification[$leaveByUserDay[$employee->id][$day]] ?? null;

                    if ($classification === 'cl') {
                        $cl++;
                    } elseif ($classification === 'sl') {
                        $sl++;
                    } elseif ($classification === 'lop') {
                        $lop++;
                    }

                    continue;
                }

                if (isset($workedSecondsByUserDay[$employee->id][$day])) {
                    $workedSeconds = $workedSecondsByUserDay[$employee->id][$day];
                    $workedSecondsTotal += $workedSeconds;
                    $shift = $shiftByUserDay[$employee->id][$day] ?? null;
                    $fullDaySeconds = $halfDaySeconds = null;

                    if ($shift && !is_null($shift->min_working_hours_full_day) && !is_null($shift->min_working_hours_half_day)) {
                        $fullDaySeconds = ($shift->min_working_hours_full_day * 3600) - (($shift->min_working_hours_full_day_grace ?? 0) * 60);
                        $halfDaySeconds = ($shift->min_working_hours_half_day * 3600) - (($shift->min_working_hours_half_day_grace ?? 0) * 60);
                    } elseif ($shift) {
                        $shiftStart = Carbon::parse($shift->office_start_time);
                        $shiftEnd = Carbon::parse($shift->office_end_time);
                        $shiftSeconds = $shiftEnd->lessThanOrEqualTo($shiftStart)
                            ? abs($shiftEnd->copy()->addDay()->diffInSeconds($shiftStart))
                            : abs($shiftEnd->diffInSeconds($shiftStart));
                        $fullDaySeconds = $shiftSeconds * 0.5;
                        $halfDaySeconds = $shiftSeconds * 0.25;
                    }

                    if ($fullDaySeconds !== null && $workedSeconds >= $fullDaySeconds) {
                        $present++;
                        $presentFullDays++;
                    } elseif ($halfDaySeconds !== null && $workedSeconds >= $halfDaySeconds) {
                        $present += 0.5;
                        $halfDayCount++;
                    } else {
                        $absent++;
                    }

                    continue;
                }

                $absent++;
            }

            $summaryRows[] = [
                'id' => $employee->id,
                'name' => $employee->name,
                'present' => $present,
                'absent' => $absent,
                'leave' => $leaveDays,
                'halfday' => $halfDayCount,
                'holiday' => $holidayDays,
                'cl' => $cl,
                'sl' => $sl,
                'lop' => $lop,
                'dayoff' => $dayOffDays,
                // Total Attendance Value (TL) — same formula as Annual Attendance. CL/SL are paid
                // leave so they count toward attendance; LOP (unpaid) does not.
                'totalAttendance' => $presentFullDays + $holidayDays + $dayOffDays + ($halfDayCount * 0.5) + $cl + $sl,
                'hours' => round($workedSecondsTotal / 3600, 1),
            ];
        }

        if (isset($_GET['export']) && in_array($_GET['export'], ['pdf', 'excel'], true) && !empty($summaryRows)) {
            require_once __DIR__ . '/export_helpers.php';

            $exportRows = [];
            $exportRows[] = [[
                'text' => 'MONTHLY ATTENDANCE SUMMARY — ' . $monthLabel, 'bg' => 'FFE600', 'bold' => true, 'colspan' => 13,
            ]];

            $exportRows[] = array_map(fn ($h) => ['text' => $h, 'bg' => 'D9E8FB', 'bold' => true], [
                'S.No', 'Employee Name', 'Total Present', 'Total Absent', 'Total Leaves', 'Total Half Days',
                'Total Holidays', 'Total CL Used', 'Total SL Used', 'Total LOP', 'Total Day Offs', 'Total Attendance',
                'Total Working Hours',
            ]);

            foreach ($summaryRows as $i => $row) {
                $exportRows[] = [
                    ['text' => $i + 1],
                    ['text' => $row['name'], 'bold' => true, 'align' => 'left'],
                    ['text' => $row['present'], 'color' => '008000', 'bold' => true],
                    ['text' => $row['absent'], 'color' => 'FF0000', 'bold' => true],
                    ['text' => $row['leave'], 'color' => 'FF9800', 'bold' => true],
                    ['text' => $row['halfday'], 'color' => 'D97706', 'bold' => true],
                    ['text' => $row['holiday'], 'color' => '666666', 'bold' => true],
                    ['text' => $row['cl']],
                    ['text' => $row['sl']],
                    ['text' => $row['lop'], 'color' => 'C00000', 'bold' => true],
                    ['text' => $row['dayoff'], 'color' => '6C757D', 'bold' => true],
                    ['text' => rtrim(rtrim(number_format($row['totalAttendance'], 1), '0'), '.'), 'bg' => 'FFF2CC', 'color' => '008000', 'bold' => true],
                    ['text' => $row['hours'] . ' hrs', 'color' => '0066CC', 'bold' => true],
                ];
            }

            $safeName = preg_replace('/[^A-Za-z0-9_-]/', '_', $monthLabel);
            reportExportDispatch($_GET['export'], 'Monthly_Attendance_Summary_' . $safeName, 'MONTHLY ATTENDANCE SUMMARY — ' . $monthLabel, $exportRows);
        }
    } catch (\Throwable $e) {
        $departments = [];
        $summaryRows = [];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Monthly Attendance Summary</title>
    <style>
        * { box-sizing: border-box; }
        body {
            font-family: Arial, sans-serif;
            background: #f4f6f9;
            margin: 20px;
        }

        .container {
            background: #fff;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            overflow-x: auto;
        }

        h2 {
            text-align: center;
            background: #ffe600;
            padding: 12px;
            margin-top: 0;
            border-radius: 6px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 1400px;
        }

        th, td {
            border: 1px solid #ccc;
            padding: 10px;
            text-align: center;
            font-size: 14px;
        }

        th {
            background: #d9e8fb;
            color: #000;
        }

        .name-col {
            text-align: left;
            min-width: 180px;
            font-weight: bold;
        }

        .present { color: green; font-weight: bold; }
        .absent { color: red; font-weight: bold; }
        .leave { color: #ff9800; font-weight: bold; }
        .halfday { color: #d97706; font-weight: bold; }
        .holiday { color: #666; font-weight: bold; }
        .lop { color: #c00000; font-weight: bold; }
        .hours { color: #0066cc; font-weight: bold; }

        select {
            width: 180px;
            padding: 5px;
            border-radius: 4px;
            border: 1px solid #999;
        }

        .top-controls {
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            margin-bottom: 15px;
            flex-wrap: wrap;
            gap: 14px;
        }

        .top-controls-left {
            display: flex;
            align-items: flex-end;
            gap: 14px;
            flex-wrap: wrap;
        }

        .control-group {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .control-group label {
            font-size: 12px;
            font-weight: 700;
        }

        .dayoff { color: #6c757d; font-weight: bold; }
        .totalattendance { color: #008000; font-weight: bold; background: #fff2cc; }

        .export-buttons {
            display: flex;
            gap: 8px;
        }

        .btn-export {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 14px;
            border-radius: 4px;
            font-size: 13px;
            font-weight: 700;
            text-decoration: none;
            border: 1px solid transparent;
        }

        .btn-export-pdf { background: #dc3545; color: #fff; }
        .btn-export-excel { background: #1d6f42; color: #fff; }

        .no-data { padding: 40px; text-align: center; color: #888; }

        /* ---- Searchable Employee dropdown ---- */
        .employee-picker {
            position: relative;
            min-width: 240px;
        }

        .employee-picker-input {
            padding: 6px 10px;
            border-radius: 4px;
            border: 1px solid #999;
            width: 100%;
            cursor: pointer;
            background: #fff;
        }

        .employee-picker-panel {
            display: none;
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            z-index: 20;
            background: #fff;
            border: 1px solid #999;
            border-top: none;
            border-radius: 0 0 4px 4px;
            max-height: 280px;
            overflow-y: auto;
            box-shadow: 0 4px 10px rgba(0,0,0,0.12);
        }

        .employee-picker-panel.open {
            display: block;
        }

        .employee-picker-search {
            width: 100%;
            padding: 8px 10px;
            border: none;
            border-bottom: 1px solid #ddd;
            outline: none;
            font-size: 13px;
        }

        .employee-picker-option {
            padding: 8px 10px;
            font-size: 13px;
            cursor: pointer;
        }

        .employee-picker-option:hover,
        .employee-picker-option.highlighted {
            background: #eef4ff;
        }

        .employee-picker-option .card-id {
            color: #777;
            font-size: 11px;
        }
    </style>
</head>
<body>

<div class="container">
    <h2>MONTHLY ATTENDANCE SUMMARY — <?php echo tmaEsc($monthLabel); ?></h2>

    <div class="top-controls">
        <div class="top-controls-left">
            <div class="control-group">
                <label>Department</label>
                <select id="departmentSelect">
                    <option value="">All Departments</option>
                    <?php foreach ($departments as $id => $name): ?>
                        <option value="<?php echo (int) $id; ?>" <?php echo ($id === $departmentId) ? 'selected' : ''; ?>><?php echo tmaEsc($name); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="control-group">
                <label>Employee</label>
                <div class="employee-picker" id="employeePicker">
                    <input type="text" class="employee-picker-input" id="employeePickerInput" readonly
                        placeholder="All Employees" autocomplete="off" />
                    <div class="employee-picker-panel" id="employeePickerPanel">
                        <input type="text" class="employee-picker-search" id="employeePickerSearch"
                            placeholder="Search by name or card ID…" autocomplete="off" />
                        <div id="employeePickerOptions">
                            <div class="employee-picker-option" data-id="">All Employees</div>
                            <?php foreach ($allEmployeeOptions as $emp): ?>
                                <div class="employee-picker-option" data-id="<?php echo (int) $emp->id; ?>"
                                    data-search="<?php echo tmaEsc(strtolower($emp->name . ' ' . $emp->employee_card_id . ' ' . $emp->employee_id)); ?>">
                                    <?php echo tmaEsc($emp->name); ?>
                                    <span class="card-id"><?php echo tmaEsc($emp->employee_card_id ?: $emp->employee_id); ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="control-group">
                <label>Month</label>
                <select id="monthSelect">
                    <?php for ($m = 1; $m <= 12; $m++): ?>
                        <option value="<?php echo $m; ?>" <?php echo ($m === $month) ? 'selected' : ''; ?>><?php echo $monthNames[$m]; ?></option>
                    <?php endfor; ?>
                </select>
            </div>

            <div class="control-group">
                <label>Year</label>
                <select id="yearSelect">
                    <?php for ($y = $year - 5; $y <= $year + 1; $y++): ?>
                        <option value="<?php echo $y; ?>" <?php echo ($y === $year) ? 'selected' : ''; ?>><?php echo $y; ?></option>
                    <?php endfor; ?>
                </select>
            </div>
        </div>

        <div class="export-buttons">
            <?php
                $exportQuery = $_GET;
                unset($exportQuery['export']);
            ?>
            <a class="btn-export btn-export-pdf" href="Total_Monthly_Attendance.php?<?php echo tmaEsc(http_build_query(array_merge($exportQuery, ['export' => 'pdf']))); ?>" target="_blank">PDF</a>
            <a class="btn-export btn-export-excel" href="Total_Monthly_Attendance.php?<?php echo tmaEsc(http_build_query(array_merge($exportQuery, ['export' => 'excel']))); ?>">Excel</a>
        </div>
    </div>

    <?php if (empty($summaryRows)): ?>
        <p class="no-data">No employees found for this selection.</p>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>S.No</th>
                    <th class="name-col">Employee Name</th>
                    <th>Total Present</th>
                    <th>Total Absent</th>
                    <th>Total Leaves</th>
                    <th>Total Half Days</th>
                    <th>Total Holidays</th>
                    <th>Total CL Used</th>
                    <th>Total SL Used</th>
                    <th>Total LOP</th>
                    <th>Total Day Offs</th>
                    <th>Total Attendance</th>
                    <th>Total Working Hours</th>
                </tr>
            </thead>

            <tbody>
                <?php foreach ($summaryRows as $i => $row): ?>
                    <tr>
                        <td><?php echo $i + 1; ?></td>
                        <td class="name-col"><?php echo tmaEsc($row['name']); ?></td>
                        <td class="present"><?php echo $row['present']; ?></td>
                        <td class="absent"><?php echo $row['absent']; ?></td>
                        <td class="leave"><?php echo $row['leave']; ?></td>
                        <td class="halfday"><?php echo $row['halfday']; ?></td>
                        <td class="holiday"><?php echo $row['holiday']; ?></td>
                        <td><?php echo $row['cl']; ?></td>
                        <td><?php echo $row['sl']; ?></td>
                        <td class="lop"><?php echo $row['lop']; ?></td>
                        <td class="dayoff"><?php echo $row['dayoff']; ?></td>
                        <td class="totalattendance"><?php echo rtrim(rtrim(number_format($row['totalAttendance'], 1), '0'), '.'); ?></td>
                        <td class="hours"><?php echo $row['hours']; ?> hrs</td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<script>
    function reloadWith(param, value) {
        var url = new URL(window.location.href);
        if (value) {
            url.searchParams.set(param, value);
        } else {
            url.searchParams.delete(param);
        }
        window.location.href = url.toString();
    }

    document.getElementById('departmentSelect').addEventListener('change', function () {
        reloadWith('department_id', this.value);
    });

    document.getElementById('monthSelect').addEventListener('change', function () {
        reloadWith('month', this.value);
    });

    document.getElementById('yearSelect').addEventListener('change', function () {
        reloadWith('year', this.value);
    });

    // ---- Searchable Employee dropdown ----
    (function () {
        var picker = document.getElementById('employeePicker');
        var input = document.getElementById('employeePickerInput');
        var panel = document.getElementById('employeePickerPanel');
        var search = document.getElementById('employeePickerSearch');
        var options = Array.prototype.slice.call(document.querySelectorAll('.employee-picker-option'));
        var currentEmployeeId = <?php echo json_encode((string) $employeeIdFilter); ?>;

        function setInputLabel() {
            var match = options.find(function (opt) { return opt.dataset.id === currentEmployeeId; });
            input.value = match ? match.textContent.trim() : 'All Employees';
        }

        function openPanel() {
            panel.classList.add('open');
            search.value = '';
            filterOptions('');
            search.focus();
        }

        function closePanel() {
            panel.classList.remove('open');
        }

        function filterOptions(term) {
            term = term.toLowerCase();
            options.forEach(function (opt) {
                var haystack = opt.dataset.id === '' ? 'all employees' : (opt.dataset.search || '');
                opt.style.display = haystack.indexOf(term) !== -1 ? '' : 'none';
            });
        }

        input.addEventListener('click', function () {
            if (panel.classList.contains('open')) {
                closePanel();
            } else {
                openPanel();
            }
        });

        search.addEventListener('input', function () {
            filterOptions(this.value);
        });

        options.forEach(function (opt) {
            opt.addEventListener('click', function () {
                reloadWith('employee_id', opt.dataset.id);
            });
        });

        document.addEventListener('click', function (e) {
            if (!picker.contains(e.target)) {
                closePanel();
            }
        });

        setInputLabel();
    })();
</script>

</body>
</html>
