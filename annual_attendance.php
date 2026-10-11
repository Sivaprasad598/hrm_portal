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

function aaEsc($value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function aaClassifyLeaveType(string $typeName): ?string
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

/**
 * Full per-status day counts for one employee across one calendar month, plus the
 * "Working Days" denominator and a "presentCredit" figure used for the overall %
 * columns (Present/WFH/Late = full credit, Half Day/half-day-leave = 0.5 credit).
 *
 * Priority per day, same convention used elsewhere in this app (Attendance_Dashboard.php,
 * admin_leave.php): Day Off > Holiday > Leave (CL/SL/LOP/generic On Leave) > attendance-derived
 * (Half Day / WFH / Late / Present) > Absent. WFH and Late are treated as their own primary,
 * mutually-exclusive buckets here (not additive badges) since the Status filter needs to be
 * able to isolate each one on its own — WFH takes priority over Late when a full day was both
 * worked-from-home and a late clock-in.
 */
function aaMonthStatusCounts(array $employeeIds, int $year, int $month, string $timezone, Carbon $today, int $companyId): array
{
    $emptyCounts = [
        'cl' => 0, 'sl' => 0, 'lop' => 0, 'holiday' => 0, 'present' => 0, 'absent' => 0,
        'onleave' => 0, 'wfh' => 0, 'halfday' => 0, 'late' => 0, 'dayoff' => 0,
        'workingDays' => 0, 'presentCredit' => 0.0,
    ];

    $daysInMonth = Carbon::createFromDate($year, $month, 1)->daysInMonth;
    $monthStart = Carbon::createFromDate($year, $month, 1, $timezone)->startOfDay();
    $monthEnd = Carbon::createFromDate($year, $month, $daysInMonth, $timezone)->endOfDay();

    if ($monthStart->greaterThan($today)) {
        return array_fill_keys($employeeIds, $emptyCounts);
    }

    $leaveTypeClassification = [];
    foreach (DB::table('leave_types')->where('company_id', $companyId)->whereNull('deleted_at')->get(['id', 'type_name']) as $lt) {
        $key = aaClassifyLeaveType($lt->type_name);
        if ($key !== null) {
            $leaveTypeClassification[$lt->id] = $key;
        }
    }

    $workedSecondsByUserDay = [];
    $shiftByUserDay = [];
    $firstClockInByUserDay = [];
    $firstSessionWfhByUserDay = [];

    Attendance::whereIn('user_id', $employeeIds)
        ->whereBetween('clock_in_time', [$monthStart->copy()->timezone('UTC'), $monthEnd->copy()->timezone('UTC')])
        ->with('shift')
        ->orderBy('clock_in_time')
        ->get()
        ->each(function ($row) use (&$workedSecondsByUserDay, &$shiftByUserDay, &$firstClockInByUserDay, &$firstSessionWfhByUserDay, $timezone, $year, $month) {
            $local = $row->clock_in_time->copy()->timezone($timezone);

            if ((int) $local->year !== $year || (int) $local->month !== $month) {
                return;
            }

            $day = (int) $local->day;
            $shiftByUserDay[$row->user_id][$day] = $row->shift;

            if (!isset($firstClockInByUserDay[$row->user_id][$day])) {
                $firstClockInByUserDay[$row->user_id][$day] = $local;
                $firstSessionWfhByUserDay[$row->user_id][$day] = $row->work_from_type === 'home';
            }

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
            $leaveByUserDay[$leave->user_id][(int) Carbon::parse($leave->leave_date)->day] = [
                'duration' => $leave->duration,
                'leave_type_id' => $leave->leave_type_id,
            ];
        });

    $holidaysByDay = [];
    Holiday::where('company_id', $companyId)
        ->whereBetween('date', [$monthStart->toDateString(), $monthEnd->toDateString()])
        ->get()
        ->each(function ($holiday) use (&$holidaysByDay) {
            $holidaysByDay[(int) Carbon::parse($holiday->date)->day] = true;
        });

    $counts = [];

    foreach ($employeeIds as $userId) {
        $c = $emptyCounts;

        for ($day = 1; $day <= $daysInMonth; $day++) {
            $date = Carbon::createFromDate($year, $month, $day, $timezone)->startOfDay();

            if ($date->greaterThan($today)) {
                continue;
            }

            if (!empty($dayOffByUserDay[$userId][$day])) {
                $c['dayoff']++;
                continue;
            }

            if (!empty($holidaysByDay[$day])) {
                $c['holiday']++;
                continue;
            }

            $c['workingDays']++;

            if (isset($leaveByUserDay[$userId][$day])) {
                $leave = $leaveByUserDay[$userId][$day];
                $isHalf = $leave['duration'] === 'half day';
                $classification = $leaveTypeClassification[$leave['leave_type_id']] ?? null;

                if ($classification === 'cl') {
                    $c['cl']++;
                } elseif ($classification === 'sl') {
                    $c['sl']++;
                } elseif ($classification === 'lop') {
                    $c['lop']++;
                } else {
                    $c['onleave']++;
                }

                // LOP is unpaid by definition — no attendance credit even though it still
                // counts as a working day above.
                if ($classification !== 'lop') {
                    $c['presentCredit'] += $isHalf ? 0.5 : 1;
                }

                continue;
            }

            if (isset($workedSecondsByUserDay[$userId][$day])) {
                $workedSeconds = $workedSecondsByUserDay[$userId][$day];
                $shift = $shiftByUserDay[$userId][$day] ?? null;
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
                    $isWfh = $firstSessionWfhByUserDay[$userId][$day] ?? false;
                    $isLate = false;

                    if (!$isWfh && $shift && ($shift->shift_type ?? null) === 'strict' && !empty($shift->office_start_time)) {
                        $allowedClockIn = Carbon::parse($date->format('Y-m-d') . ' ' . $shift->office_start_time, $timezone)
                            ->addMinutes((int) ($shift->late_mark_duration ?? 0));
                        $isLate = ($firstClockInByUserDay[$userId][$day] ?? $date)->greaterThan($allowedClockIn);
                    }

                    if ($isWfh) {
                        $c['wfh']++;
                    } elseif ($isLate) {
                        $c['late']++;
                    } else {
                        $c['present']++;
                    }

                    $c['presentCredit'] += 1;
                } elseif ($halfDaySeconds !== null && $workedSeconds >= $halfDaySeconds) {
                    $c['halfday']++;
                    $c['presentCredit'] += 0.5;
                } else {
                    $c['absent']++;
                }

                continue;
            }

            $c['absent']++;
        }

        $counts[$userId] = $c;
    }

    return $counts;
}

$statusOptions = [
    'present' => 'Present',
    'absent' => 'Absent',
    'cl' => 'CL',
    'sl' => 'SL',
    'lop' => 'LOP',
    'holiday' => 'Holiday',
    'onleave' => 'On Leave',
    'wfh' => 'WFH',
    'halfday' => 'Half Day',
    'late' => 'Late',
    'dayoff' => 'Day Off',
];

$companyId = isset($_GET['company_id']) ? (int) $_GET['company_id'] : 0;
// Calendar year (Jan–Dec).
$year = isset($_GET['year']) ? (int) $_GET['year'] : (int) date('Y');
$departmentId = isset($_GET['department_id']) && $_GET['department_id'] !== '' ? (int) $_GET['department_id'] : 0;
$employeeIdFilter = isset($_GET['employee_id']) && $_GET['employee_id'] !== '' ? (int) $_GET['employee_id'] : 0;
$statusFilter = isset($_GET['status']) && isset($statusOptions[$_GET['status']]) ? $_GET['status'] : 'present';

$departments = [];
$allEmployeeOptions = [];
$annualRows = [];
$monthlyWorkingDaysRow = [];
$monthColumns = []; // ['label' => 'Apr', 'year' => 2026, 'month' => 4]

if ($companyId > 0) {
    try {
        $company = Company::find($companyId);
        $timezone = $company->timezone ?? 'UTC';
        $today = Carbon::now($timezone)->startOfDay();

        $departments = DB::table('teams')->where('company_id', $companyId)->orderBy('team_name')->pluck('team_name', 'id')->toArray();

        // Full employee list for the searchable dropdown (name + employee_id + card id),
        // independent of the Department filter — matches how the Employee filter behaves
        // elsewhere in this app (Attendance_Dashboard.php).
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
        $employeeIds = $employees->pluck('id')->all();

        for ($m = 1; $m <= 12; $m++) {
            $monthColumns[] = ['label' => Carbon::createFromDate($year, $m, 1)->format('M'), 'year' => $year, 'month' => $m];
        }

        $countsByUserMonth = [];
        $workingDaysTotalByMonth = [];

        foreach ($monthColumns as $col) {
            $totals = aaMonthStatusCounts($employeeIds, $col['year'], $col['month'], $timezone, $today, $companyId);

            $maxWorkingDaysThisMonth = 0;

            foreach ($employeeIds as $userId) {
                $countsByUserMonth[$userId][] = $totals[$userId];
                $maxWorkingDaysThisMonth = max($maxWorkingDaysThisMonth, $totals[$userId]['workingDays']);
            }

            $workingDaysTotalByMonth[] = $maxWorkingDaysThisMonth;
        }

        $monthlyWorkingDaysRow = $workingDaysTotalByMonth;

        foreach ($employees as $employee) {
            $monthsCounts = $countsByUserMonth[$employee->id] ?? array_fill(0, 12, [
                'workingDays' => 0, 'present' => 0, 'wfh' => 0, 'late' => 0, 'dayoff' => 0,
                'halfday' => 0, 'cl' => 0, 'sl' => 0, 'lop' => 0, 'holiday' => 0,
            ]);

            // Total Attendance Value (TL) per month — same formula as Total_Monthly_Attendance.php.
            // CL/SL are paid leave so they count toward attendance; LOP (unpaid) does not.
            $monthsTL = array_map(function ($m) {
                return ($m['present'] ?? 0)
                     + ($m['holiday'] ?? 0)
                     + ($m['dayoff'] ?? 0)
                     + (($m['halfday'] ?? 0) * 0.5)
                     + ($m['cl'] ?? 0)
                     + ($m['sl'] ?? 0);
            }, $monthsCounts);

            $totalWorking = array_sum(array_map(fn ($m) => $m['workingDays'], $monthsCounts));
            $totalDaysPresent = array_sum($monthsTL);
            $percentage = $totalWorking > 0 ? round(($totalDaysPresent / $totalWorking) * 100, 2) : 0.0;

            $annualRows[] = [
                'id' => $employee->id,
                'name' => $employee->name,
                'months' => $monthsTL,
                'totalPresent' => $totalDaysPresent,
                'totalWorking' => $totalWorking,
                'percentage' => $percentage,
            ];
        }

        if (isset($_GET['export']) && in_array($_GET['export'], ['pdf', 'excel'], true) && !empty($annualRows)) {
            require_once __DIR__ . '/export_helpers.php';

            $fmtNum = fn ($v) => (fmod($v, 1) === 0.0) ? (int) $v : $v;

            $exportRows = [];
            $exportRows[] = [[
                'text' => 'ANNUAL ATTENDANCE SUMMARY — Total Attendance Value (' . $year . ')',
                'bg' => 'FFE600', 'bold' => true, 'colspan' => 2 + 12 + 3,
            ]];

            $header = [['text' => 'Roll No.', 'bg' => 'C9D7EA', 'bold' => true], ['text' => 'Employee Name', 'bg' => 'C9D7EA', 'bold' => true, 'align' => 'left']];
            foreach ($monthColumns as $col) {
                $header[] = ['text' => $col['label'], 'bg' => 'C9D7EA', 'bold' => true];
            }
            $header[] = ['text' => 'Total Days Present', 'bg' => 'C9D7EA', 'bold' => true];
            $header[] = ['text' => 'Total Working Days', 'bg' => 'C9D7EA', 'bold' => true];
            $header[] = ['text' => '%', 'bg' => 'C9D7EA', 'bold' => true];
            $exportRows[] = $header;

            $workingDaysRow = [['text' => 'Total Monthly Working Days', 'bg' => 'E7EEF7', 'bold' => true, 'colspan' => 2, 'align' => 'left']];
            foreach ($monthlyWorkingDaysRow as $wd) {
                $workingDaysRow[] = ['text' => $wd, 'bg' => 'E7EEF7', 'bold' => true];
            }
            $workingDaysRow[] = ['text' => array_sum($monthlyWorkingDaysRow), 'bg' => 'E7EEF7', 'bold' => true];
            $workingDaysRow[] = ['text' => array_sum($monthlyWorkingDaysRow), 'bg' => 'E7EEF7', 'bold' => true];
            $workingDaysRow[] = ['text' => '100%', 'bg' => 'E7EEF7', 'bold' => true];
            $exportRows[] = $workingDaysRow;

            foreach ($annualRows as $i => $row) {
                $bodyRow = [
                    ['text' => $i + 1],
                    ['text' => $row['name'], 'bold' => true, 'align' => 'left', 'color' => '0B2A5B'],
                ];
                foreach ($row['months'] as $m) {
                    $bodyRow[] = ['text' => $fmtNum($m), 'color' => '0B2A5B'];
                }
                $bodyRow[] = ['text' => $fmtNum($row['totalPresent']), 'color' => '008000', 'bold' => true];
                $bodyRow[] = ['text' => $row['totalWorking'], 'color' => '0056D6', 'bold' => true];
                $bodyRow[] = ['text' => number_format($row['percentage'], 2), 'color' => '008000', 'bold' => true];
                $exportRows[] = $bodyRow;
            }

            reportExportDispatch($_GET['export'], 'Annual_Attendance_Summary_' . $year, 'ANNUAL ATTENDANCE SUMMARY — ' . $year, $exportRows, [0, 0, 1600, 700]);
        }
    } catch (\Throwable $e) {
        $departments = [];
        $annualRows = [];
    }
}

$fyLabel = $companyId > 0 ? (string) $year : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Annual Attendance Summary</title>

    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: Arial, Helvetica, sans-serif;
            background: #f1f1f1;
            padding: 20px;
        }

        .top-controls {
            display: flex;
            justify-content: space-between;
            align-items: center;
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
            font-weight: 600;
            color: #333;
        }

        .top-controls select {
            padding: 6px 10px;
            border-radius: 4px;
            border: 1px solid #999;
            min-width: 160px;
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

        .employee-picker-empty {
            padding: 8px 10px;
            font-size: 13px;
            color: #999;
        }

        .attendance-wrapper {
            background: #ffffff;
            border: 1px solid #d5d5d5;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            overflow-x: auto;
        }

        .page-title {
            background: #ffe600;
            color: #000;
            text-align: center;
            padding: 16px;
            border-radius: 6px;
            margin-bottom: 20px;
            font-size: 32px;
            font-weight: 700;
            text-transform: uppercase;
        }

        table {
            width: 100%;
            min-width: 1400px;
            border-collapse: collapse;
            background: #fff;
        }

        thead th {
            background: #c9d7ea;
            color: #000;
            padding: 12px 10px;
            border: 1px solid #b8c4d3;
            text-align: center;
            font-size: 14px;
            font-weight: 700;
        }

        tbody td {
            padding: 12px 10px;
            border: 1px solid #d0d0d0;
            text-align: center;
            font-size: 14px;
            background: #ffffff;
        }

        tbody tr:hover td {
            background: #f5f9ff;
            transition: 0.2s;
        }

        .name-col {
            text-align: left;
            font-weight: 700;
            min-width: 180px;
            color: #0b2a5b;
        }

        .working-days-row td {
            background: #e7eef7;
            color: #0b2a5b;
            font-weight: 700;
        }

        .month-value {
            color: #0b2a5b;
            font-weight: 600;
        }

        .total-present { color: #008000; font-weight: 700; }
        .total-working { color: #0056d6; font-weight: 700; }
        .percentage { color: #008000; font-weight: 700; }

        .no-data { padding: 40px; text-align: center; color: #888; }

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

        @media(max-width:768px) {
            body { padding: 10px; }
            .page-title { font-size: 22px; padding: 12px; }
            thead th, tbody td { padding: 8px 6px; font-size: 12px; }
        }
    </style>
</head>

<body>

    <div class="top-controls">
        <div class="top-controls-left">
            <div class="control-group">
                <label for="departmentSelect">Department</label>
                <select id="departmentSelect">
                    <option value="">All Departments</option>
                    <?php foreach ($departments as $id => $name): ?>
                        <option value="<?php echo (int) $id; ?>" <?php echo ($id === $departmentId) ? 'selected' : ''; ?>><?php echo aaEsc($name); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="control-group">
                <label for="employeePickerInput">Employee</label>
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
                                    data-search="<?php echo aaEsc(strtolower($emp->name . ' ' . $emp->employee_card_id . ' ' . $emp->employee_id)); ?>">
                                    <?php echo aaEsc($emp->name); ?>
                                    <span class="card-id"><?php echo aaEsc($emp->employee_card_id ?: $emp->employee_id); ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="control-group">
                <label for="statusSelect">Status</label>
                <select id="statusSelect">
                    <?php foreach ($statusOptions as $key => $label): ?>
                        <option value="<?php echo aaEsc($key); ?>" <?php echo ($key === $statusFilter) ? 'selected' : ''; ?>><?php echo aaEsc($label); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div style="display:flex; align-items:flex-end; gap:14px;">
            <div class="export-buttons">
                <?php
                    $exportQuery = $_GET;
                    unset($exportQuery['export']);
                ?>
                <a class="btn-export btn-export-pdf" href="annual_attendance.php?<?php echo aaEsc(http_build_query(array_merge($exportQuery, ['export' => 'pdf']))); ?>" target="_blank">PDF</a>
                <a class="btn-export btn-export-excel" href="annual_attendance.php?<?php echo aaEsc(http_build_query(array_merge($exportQuery, ['export' => 'excel']))); ?>">Excel</a>
            </div>
            <div><b>Year: <?php echo aaEsc($fyLabel); ?></b></div>
        </div>
    </div>

    <div class="attendance-wrapper">

        <div class="page-title">
            Annual Attendance Summary — <?php echo aaEsc($statusOptions[$statusFilter]); ?>
        </div>

        <?php if (empty($annualRows)): ?>
            <p class="no-data">No employees found for this selection.</p>
        <?php else: ?>
            <table>

                <thead>
                    <tr>
                        <th>Roll No.</th>
                        <th>Employee Name</th>
                        <?php foreach ($monthColumns as $col): ?>
                            <th><?php echo aaEsc($col['label']); ?></th>
                        <?php endforeach; ?>
                        <th>Total Days Present</th>
                        <th>Total Working Days</th>
                        <th>%</th>
                    </tr>
                </thead>

                <tbody>

                    <tr class="working-days-row">
                        <td colspan="2">Total Monthly Working Days</td>
                        <?php foreach ($monthlyWorkingDaysRow as $wd): ?>
                            <td class="month-value"><?php echo $wd; ?></td>
                        <?php endforeach; ?>
                        <td class="total-working"><?php echo array_sum($monthlyWorkingDaysRow); ?></td>
                        <td class="total-working"><?php echo array_sum($monthlyWorkingDaysRow); ?></td>
                        <td class="percentage">100%</td>
                    </tr>

                    <?php foreach ($annualRows as $i => $row): ?>
                        <tr>
                            <td><?php echo $i + 1; ?></td>
                            <td class="name-col"><?php echo aaEsc($row['name']); ?></td>
                            <?php foreach ($row['months'] as $m): ?>
                                <td><?php echo (fmod($m, 1) === 0.0) ? (int) $m : $m; ?></td>
                            <?php endforeach; ?>
                            <td class="total-present"><?php echo (fmod($row['totalPresent'], 1) === 0.0) ? (int) $row['totalPresent'] : $row['totalPresent']; ?></td>
                            <td class="total-working"><?php echo $row['totalWorking']; ?></td>
                            <td class="percentage"><?php echo number_format($row['percentage'], 2); ?></td>
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

        document.getElementById('statusSelect').addEventListener('change', function () {
            reloadWith('status', this.value);
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
                var anyVisible = false;
                options.forEach(function (opt) {
                    var haystack = opt.dataset.id === '' ? 'all employees' : (opt.dataset.search || '');
                    var visible = haystack.indexOf(term) !== -1;
                    opt.style.display = visible ? '' : 'none';
                    if (visible) {
                        anyVisible = true;
                    }
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
