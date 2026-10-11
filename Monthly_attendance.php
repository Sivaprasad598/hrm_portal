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

function maEsc($value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
}

/**
 * Same CL/SL/LOP classification convention used by admin_leave.php — leave types are matched
 * by name prefix rather than a fixed ID, since leave type IDs aren't guaranteed stable across
 * companies/environments.
 */
function maClassifyLeaveType(string $typeName): ?string
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
$employeeRows = [];
$monthLabel = '';
$monthNames = ['', 'January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
$daysInMonth = 31;
$weekDayLabels = [];
$titleLabel = '';

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

        for ($d = 1; $d <= $daysInMonth; $d++) {
            $weekDayLabels[$d] = Carbon::createFromDate($year, $month, $d, $timezone)->format('D');
        }

        // Leave type classification for this company (id => 'cl'/'sl'/'lop'), same convention
        // as admin_leave.php — anything not matched stays generic ("TL" bucket below).
        $leaveTypeClassification = [];
        foreach (DB::table('leave_types')->where('company_id', $companyId)->whereNull('deleted_at')->get(['id', 'type_name']) as $lt) {
            $key = maClassifyLeaveType($lt->type_name);
            if ($key !== null) {
                $leaveTypeClassification[$lt->id] = $key;
            }
        }

        // Worked seconds + shift captured per user/day (mirrors Attendance_Dashboard.php).
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

        // Day Off, from the shift roster.
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

        // Approved leave, keyed by user/day, keeping the leave_type_id for CL/SL/LOP classification.
        $leaveByUserDay = [];
        Leave::whereIn('user_id', $employeeIds)
            ->whereBetween('leave_date', [$monthStart->toDateString(), $monthEnd->toDateString()])
            ->where('status', 'approved')
            ->get()
            ->each(function ($leave) use (&$leaveByUserDay) {
                $leaveByUserDay[$leave->user_id][(int) Carbon::parse($leave->leave_date)->day] = $leave->leave_type_id;
            });

        // Company-wide holidays.
        $holidaysByDay = [];
        Holiday::where('company_id', $companyId)
            ->whereBetween('date', [$monthStart->toDateString(), $monthEnd->toDateString()])
            ->get()
            ->each(function ($holiday) use (&$holidaysByDay) {
                $holidaysByDay[(int) Carbon::parse($holiday->date)->day] = true;
            });

        foreach ($employees as $employee) {
            $days = [];
            $cl = $sl = $lop = $halfDayCount = $totalLeave = 0;
            $presentCount = $holidayCount = $dayOffCount = 0;

            for ($day = 1; $day <= $daysInMonth; $day++) {
                $date = Carbon::createFromDate($year, $month, $day, $timezone)->startOfDay();

                if ($date->greaterThan($today)) {
                    $days[$day] = ['code' => '', 'class' => ''];
                    continue;
                }

                if (!empty($dayOffByUserDay[$employee->id][$day])) {
                    $dayOffCount++;
                    $days[$day] = ['code' => $weekDayLabels[$day], 'class' => 'weekoff'];
                    continue;
                }

                if (!empty($holidaysByDay[$day])) {
                    $holidayCount++;
                    $days[$day] = ['code' => 'H', 'class' => 'weekoff'];
                    continue;
                }

                if (isset($leaveByUserDay[$employee->id][$day])) {
                    $leaveTypeId = $leaveByUserDay[$employee->id][$day];
                    $classification = $leaveTypeClassification[$leaveTypeId] ?? null;

                    if ($classification === 'cl') {
                        $cl++;
                        $days[$day] = ['code' => 'CL', 'class' => 'cl-cell'];
                    } elseif ($classification === 'sl') {
                        $sl++;
                        $days[$day] = ['code' => 'SL', 'class' => 'sl-cell'];
                    } elseif ($classification === 'lop') {
                        $lop++;
                        $days[$day] = ['code' => 'LOP', 'class' => 'lop-cell'];
                    } else {
                        $totalLeave++;
                        $days[$day] = ['code' => 'L', 'class' => 'leave-cell'];
                    }

                    continue;
                }

                if (isset($workedSecondsByUserDay[$employee->id][$day])) {
                    $workedSeconds = $workedSecondsByUserDay[$employee->id][$day];
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
                        $presentCount++;
                        $days[$day] = ['code' => 'P', 'class' => 'present'];
                    } elseif ($halfDaySeconds !== null && $workedSeconds >= $halfDaySeconds) {
                        $halfDayCount++;
                        $days[$day] = ['code' => '0.5', 'class' => 'halfday'];
                    } else {
                        $days[$day] = ['code' => 'A', 'class' => 'absent'];
                    }

                    continue;
                }

                $days[$day] = ['code' => 'A', 'class' => 'absent'];
            }

            $employeeRows[] = [
                'id' => $employee->id,
                'name' => $employee->name,
                'days' => $days,
                'cl' => $cl,
                'sl' => $sl,
                'lop' => $lop,
                'halfDayCount' => $halfDayCount,
                'totalLeave' => $totalLeave,
                'presentCount' => $presentCount,
                'holidayCount' => $holidayCount,
                'dayOffCount' => $dayOffCount,
                'totalAttendance' => $presentCount + $holidayCount + $dayOffCount + ($halfDayCount * 0.5),
            ];
        }

        $titleLabel = $departmentId > 0 ? ($departments[$departmentId] ?? 'Department') : ($company->name ?? 'All Departments');

        if (isset($_GET['export']) && in_array($_GET['export'], ['pdf', 'excel'], true) && !empty($employeeRows)) {
            require_once __DIR__ . '/export_helpers.php';

            // Same colours as the on-screen day-cell classes (present/absent/weekoff/etc).
            $classColors = [
                'present' => ['bg' => 'E6FFE6', 'color' => '008000'],
                'absent' => ['bg' => 'FFE6E6', 'color' => 'FF0000'],
                'weekoff' => ['bg' => 'F2F2F2', 'color' => '666666'],
                'halfday' => ['bg' => 'FFF3CD', 'color' => 'D97706'],
                'leave-cell' => ['bg' => 'FDEAF6', 'color' => 'A239B8'],
                'cl-cell' => ['bg' => 'EEF0FF', 'color' => '4E73DF'],
                'sl-cell' => ['bg' => 'E6FFF2', 'color' => '1CC88A'],
                'lop-cell' => ['bg' => 'FDECEB', 'color' => 'C00000'],
                '' => ['bg' => null, 'color' => null],
            ];

            $exportRows = [];
            $exportRows[] = [[
                'text' => strtoupper($titleLabel), 'bg' => 'FFE600', 'bold' => true,
                'colspan' => 2 + $daysInMonth + 9,
            ]];

            $header = [
                ['text' => 'S.No', 'bg' => 'D9E8FB', 'bold' => true],
                ['text' => 'Name of the Employee', 'bg' => 'D9E8FB', 'bold' => true, 'align' => 'left'],
            ];
            for ($d = 1; $d <= $daysInMonth; $d++) {
                $header[] = ['text' => $d . ' ' . $weekDayLabels[$d], 'bg' => 'D9E8FB', 'bold' => true];
            }
            foreach (['CL', 'SL', 'LOP', '0.5', 'TL', 'Present', 'Holiday [H]', 'Day Off', 'Total Attendance'] as $h) {
                $header[] = ['text' => $h, 'bg' => 'FFF2CC', 'bold' => true];
            }
            $exportRows[] = $header;

            foreach ($employeeRows as $i => $row) {
                $bodyRow = [
                    ['text' => $i + 1],
                    ['text' => $row['name'], 'bold' => true, 'align' => 'left'],
                ];
                for ($d = 1; $d <= $daysInMonth; $d++) {
                    $colors = $classColors[$row['days'][$d]['class']] ?? ['bg' => null, 'color' => null];
                    $bodyRow[] = ['text' => $row['days'][$d]['code'], 'bg' => $colors['bg'], 'color' => $colors['color'], 'bold' => true];
                }
                $bodyRow[] = ['text' => $row['cl'], 'color' => '7030A0', 'bold' => true];
                $bodyRow[] = ['text' => $row['sl'], 'color' => 'FF9900', 'bold' => true];
                $bodyRow[] = ['text' => $row['lop'], 'color' => 'C00000', 'bold' => true];
                $bodyRow[] = ['text' => $row['halfDayCount']];
                $bodyRow[] = ['text' => $row['totalLeave'], 'color' => '008000', 'bold' => true];
                $bodyRow[] = ['text' => $row['presentCount'], 'bg' => 'E6FFE6', 'color' => '008000', 'bold' => true];
                $bodyRow[] = ['text' => $row['holidayCount'], 'bg' => 'F2F2F2', 'color' => '666666', 'bold' => true];
                $bodyRow[] = ['text' => $row['dayOffCount'], 'bg' => 'F2F2F2', 'color' => '666666', 'bold' => true];
                $bodyRow[] = ['text' => rtrim(rtrim(number_format($row['totalAttendance'], 1), '0'), '.'), 'color' => '008000', 'bold' => true];
                $exportRows[] = $bodyRow;
            }

            $safeName = preg_replace('/[^A-Za-z0-9_-]/', '_', $titleLabel . '_' . $monthLabel);
            reportExportDispatch($_GET['export'], 'Monthly_Attendance_' . $safeName, 'MONTHLY ATTENDANCE — ' . $monthLabel, $exportRows, [0, 0, 2600, 900]);
        }
    } catch (\Throwable $e) {
        $departments = [];
        $employeeRows = [];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Monthly Attendance Sheet</title>
    <style>
        * { box-sizing: border-box; }
        body {
            font-family: Arial, sans-serif;
            background: #f5f5f5;
            margin: 20px;
        }

        .top-controls {
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            margin-bottom: 15px;
            flex-wrap: wrap;
            gap: 14px;
        }

        .top-controls select {
            padding: 6px 10px;
            border-radius: 4px;
            border: 1px solid #999;
        }

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

        .btn-export-pdf {
            background: #dc3545;
            color: #fff;
        }

        .btn-export-excel {
            background: #1d6f42;
            color: #fff;
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

        .attendance-container {
            background: #fff;
            border: 1px solid #ccc;
            overflow-x: auto;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }

        table {
            border-collapse: collapse;
            min-width: 1400px;
            width: 100%;
            text-align: center;
        }

        th, td {
            border: 1px solid #999;
            padding: 6px 8px;
            font-size: 13px;
        }

        .title-row th {
            background: #ffe600;
            color: #000;
            font-size: 18px;
            font-weight: bold;
            padding: 10px;
        }

        .header {
            background: #d9e8fb;
            font-weight: bold;
        }

        .name-col {
            min-width: 180px;
            text-align: left;
            font-weight: bold;
        }

        .present { background: #e6ffe6; color: green; font-weight: bold; }
        .absent { background: #ffe6e6; color: red; font-weight: bold; }
        .weekoff { background: #f2f2f2; color: #666; font-weight: bold; }
        .halfday { background: #fff3cd; color: #d97706; font-weight: bold; }
        .leave-cell { background: #fdeaf6; color: #a239b8; font-weight: bold; }
        .cl-cell { background: #eef0ff; color: #4e73df; font-weight: bold; }
        .sl-cell { background: #e6fff2; color: #1cc88a; font-weight: bold; }
        .lop-cell { background: #fdeceb; color: #c00000; font-weight: bold; }

        .total-head { background: #fff2cc; font-weight: bold; }
        .lop { color: #c00000; font-weight: bold; }
        .sl { color: #ff9900; font-weight: bold; }
        .cl { color: #7030a0; font-weight: bold; }
        .tl { color: #008000; font-weight: bold; }

        .no-data { padding: 40px; text-align: center; color: #888; }

        .legend-box {
            width: 100%;
            box-sizing: border-box;
            margin-top: 15px;
            padding: 12px 16px;
            background: #f2f2f2;
            border-radius: 6px;
            font-size: 13px;
            display: flex;
            flex-wrap: wrap;
            gap: 6px 4px;
        }

        .legend-box b { color: #333; }
        .legend-box .sep { color: #bbb; margin: 0 4px; }
        .legend-box .legend-p { color: green; font-weight: bold; }
        .legend-box .legend-a { color: red; font-weight: bold; }
        .legend-box .legend-cl { color: #7030a0; font-weight: bold; }
        .legend-box .legend-sl { color: #ff9900; font-weight: bold; }
        .legend-box .legend-lop { color: #c00000; font-weight: bold; }
        .legend-box .legend-half { color: #d97706; font-weight: bold; }
        .legend-box .legend-tl { color: #008000; font-weight: bold; }

        .formula-box {
            width: 100%;
            box-sizing: border-box;
            margin-top: 8px;
            padding: 10px 16px;
            background: #fdf3d7;
            border: 1px solid #f0dfa0;
            border-radius: 6px;
            font-size: 13px;
            font-weight: bold;
            color: #6b5a15;
        }
    </style>
</head>
<body>

<div class="top-controls">
    <div class="top-controls-left">
        <div class="control-group">
            <label>Department</label>
            <select id="departmentSelect">
                <option value="">All Departments</option>
                <?php foreach ($departments as $id => $name): ?>
                    <option value="<?php echo (int) $id; ?>" <?php echo ($id === $departmentId) ? 'selected' : ''; ?>><?php echo maEsc($name); ?></option>
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
                                data-search="<?php echo maEsc(strtolower($emp->name . ' ' . $emp->employee_card_id . ' ' . $emp->employee_id)); ?>">
                                <?php echo maEsc($emp->name); ?>
                                <span class="card-id"><?php echo maEsc($emp->employee_card_id ?: $emp->employee_id); ?></span>
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
    <div style="display:flex; align-items:flex-end; gap:14px;">
        <div class="export-buttons">
            <?php
                $exportQuery = $_GET;
                unset($exportQuery['export']);
            ?>
            <a class="btn-export btn-export-pdf" href="Monthly_attendance.php?<?php echo maEsc(http_build_query(array_merge($exportQuery, ['export' => 'pdf']))); ?>" target="_blank">PDF</a>
            <a class="btn-export btn-export-excel" href="Monthly_attendance.php?<?php echo maEsc(http_build_query(array_merge($exportQuery, ['export' => 'excel']))); ?>">Excel</a>
        </div>
        <div><b><?php echo maEsc($monthLabel); ?></b></div>
    </div>
</div>

<div class="attendance-container">
    <?php if (empty($employeeRows)): ?>
        <p class="no-data">No employees found for this selection.</p>
    <?php else: ?>
        <table>
            <tr class="title-row">
                <th colspan="<?php echo 2 + $daysInMonth + 9; ?>"><?php echo strtoupper(maEsc($titleLabel)); ?></th>
            </tr>

            <tr class="header">
                <th>S.No</th>
                <th class="name-col">Name of the Employee</th>
                <?php for ($d = 1; $d <= $daysInMonth; $d++): ?>
                    <th><?php echo $d . '-' . date('M', mktime(0, 0, 0, $month, 1, $year)); ?><br><?php echo $weekDayLabels[$d]; ?></th>
                <?php endfor; ?>
                <th class="total-head">CL</th>
                <th class="total-head">SL</th>
                <th class="total-head">LOP</th>
                <th class="total-head">0.5</th>
                <th class="total-head">TL</th>
                <th class="total-head">Present</th>
                <th class="total-head">Holiday [H]</th>
                <th class="total-head">Day Off</th>
                <th class="total-head">Total Attendance</th>
            </tr>

            <?php foreach ($employeeRows as $i => $row): ?>
                <tr>
                    <td><?php echo $i + 1; ?></td>
                    <td class="name-col"><?php echo maEsc($row['name']); ?></td>
                    <?php for ($d = 1; $d <= $daysInMonth; $d++): ?>
                        <td class="<?php echo maEsc($row['days'][$d]['class']); ?>"><?php echo maEsc($row['days'][$d]['code']); ?></td>
                    <?php endfor; ?>
                    <td class="cl"><?php echo $row['cl']; ?></td>
                    <td class="sl"><?php echo $row['sl']; ?></td>
                    <td class="lop"><?php echo $row['lop']; ?></td>
                    <td><?php echo $row['halfDayCount']; ?></td>
                    <td class="tl"><?php echo $row['totalLeave']; ?></td>
                    <td class="present"><?php echo $row['presentCount']; ?></td>
                    <td class="weekoff"><?php echo $row['holidayCount']; ?></td>
                    <td class="weekoff"><?php echo $row['dayOffCount']; ?></td>
                    <td class="tl"><?php echo rtrim(rtrim(number_format($row['totalAttendance'], 1), '0'), '.'); ?></td>
                </tr>
            <?php endforeach; ?>
        </table>
    <?php endif; ?>
</div>

<div class="legend-box">
    <span><span class="legend-p">P</span> = Present</span>
    <span class="sep">|</span>
    <span><span class="legend-a">A</span> = Absent</span>
    <span class="sep">|</span>
    <span><span class="legend-cl">CL</span> = Casual Leave</span>
    <span class="sep">|</span>
    <span><span class="legend-sl">SL</span> = Sick Leave</span>
    <span class="sep">|</span>
    <span><span class="legend-lop">LOP</span> = Loss of Pay</span>
    <span class="sep">|</span>
    <span><span class="legend-half">0.5</span> = Half Day</span>
    <span class="sep">|</span>
    <span><span class="legend-tl">TL</span> = Total Days Present</span>
</div>
<div class="formula-box">
    TOTAL = Total Present Days + Holiday + Total Day Offs + (0.5 &times; Half Day)
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
