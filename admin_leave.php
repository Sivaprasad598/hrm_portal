<?php
require_once __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

use App\Models\Attendance;
use App\Models\EmployeeShiftSchedule;
use App\Models\Holiday;
use App\Models\Leave;
use App\Models\User;
use App\Scopes\ActiveScope;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Facades\DB;

$companyId = isset($_GET['company_id']) ? (int) $_GET['company_id'] : 0;
$employeeId = isset($_GET['user_id']) ? (int) $_GET['user_id'] : 0;
$month = isset($_GET['month']) ? (int) $_GET['month'] : (int) date('n');
$year = isset($_GET['year']) ? (int) $_GET['year'] : (int) date('Y');

// route() called from this script resolves to the wrong base URL — this
// bypasses Laravel's normal front controller (public/index.php), so it
// can't correctly detect the real /hrm/public prefix. attendances/index.blade.php
// generates these correctly (normal routing) and passes them in; the
// route() fallback here only matters when this file is opened directly.
$attendanceCreateUrl = $_GET['attendance_create_url'] ?? null;
$attendanceEditUrlTemplate = $_GET['attendance_edit_url_template'] ?? null;
$attendancesIndexUrl = $_GET['attendances_index_url'] ?? null;
$leaveCreateUrl = $_GET['leave_create_url'] ?? null;
$leaveEditUrlTemplate = $_GET['leave_edit_url_template'] ?? null;
$shiftMarkUrlTemplate = $_GET['shift_mark_url_template'] ?? null;

function alEsc($value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
}

/**
 * This schema's leave_types table has no `code` column, so CL/SL are
 * identified by matching `type_name` — same heuristic used by
 * leave_management.php / leave_policy.php / AttendanceController.
 */
function alClassifyLeaveType(string $typeName): ?string
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

$allowedStatuses = ['cl', 'sl', 'lop', 'holiday', 'present', 'absent', 'leave', 'wfh', 'halfday', 'late', 'dayoff', 'cl-half', 'sl-half', 'lop-half'];

/**
 * Same CL/SL allocation, usage, carry-forward and LOP algorithm as
 * AttendanceController::buildMonthlyLeaveDashboard() — run sequentially
 * from January up to the target month since carry-forward is cumulative.
 */
function alBuildMonthlyLeaveDashboard(int $companyId, int $userId, int $month, int $year): array
{
    $policy = [
        'cl' => ['monthlyAllocation' => 1.0, 'carryForward' => true, 'halfDayRate' => 2],
        'sl' => ['monthlyAllocation' => 1.0, 'carryForward' => false, 'halfDayRate' => 2],
    ];

    // Allocation/carry-forward now come straight from the Leave Settings
    // (leave_types) page, which is the single source of truth for these
    // rules — no more separate leave_policy table.
    $leaveTypeRows = DB::table('leave_types')
        ->where('company_id', $companyId)
        ->whereNull('deleted_at')
        ->select(['type_name', 'no_of_leaves', 'leavetype', 'monthly_limit', 'unused_leave'])
        ->get();

    foreach ($leaveTypeRows as $leaveTypeRow) {
        $key = alClassifyLeaveType($leaveTypeRow->type_name);

        if ($key === null) {
            continue;
        }

        $noOfLeaves = (float) $leaveTypeRow->no_of_leaves;
        $monthlyLimit = (float) $leaveTypeRow->monthly_limit;

        if ($leaveTypeRow->leavetype === 'monthly') {
            $monthlyAllocation = $noOfLeaves;
        } elseif ($leaveTypeRow->leavetype === 'yearly') {
            $monthlyAllocation = $monthlyLimit > 0 ? $monthlyLimit : round($noOfLeaves / 12, 2);
        } else {
            $monthlyAllocation = 999999; // unlimited
        }

        $policy[$key] = [
            'monthlyAllocation' => $monthlyAllocation,
            'carryForward' => $leaveTypeRow->unused_leave === 'carry forward',
            'halfDayRate' => 2, // matches the app-wide 0.5-day half-day convention (LeaveHelper.php)
        ];
    }

    $monthlyRaw = [];

    for ($m = 1; $m <= $month; $m++) {
        $monthlyRaw[$m] = ['clUsed' => 0, 'slUsed' => 0, 'halfDays' => 0, 'clPending' => 0, 'slPending' => 0];
    }

    $leaveRows = DB::table('leaves as l')
        ->join('leave_types as lt', 'lt.id', '=', 'l.leave_type_id')
        ->where('l.user_id', $userId)
        ->whereYear('l.leave_date', $year)
        ->whereMonth('l.leave_date', '<=', $month)
        ->whereIn('l.status', ['approved', 'pending'])
        ->select(['lt.type_name', 'l.leave_date', 'l.status', 'l.half_day_type'])
        ->get();

    foreach ($leaveRows as $leaveRow) {
        $key = alClassifyLeaveType($leaveRow->type_name);

        // Only CL/SL feed the allocation/carry-forward math below — LOP is
        // tracked purely as CL overflow there, same as before. A real LOP
        // leave record is reflected on the calendar grid itself (via
        // resolveStatusKey), not in this monthly-quota calculation.
        if ($key !== 'cl' && $key !== 'sl') {
            continue;
        }

        $m = (int) date('n', strtotime($leaveRow->leave_date));

        if ($leaveRow->status === 'pending') {
            $monthlyRaw[$m][$key . 'Pending']++;
            continue;
        }

        if (!empty($leaveRow->half_day_type)) {
            $monthlyRaw[$m]['halfDays']++;
        }
        else {
            $monthlyRaw[$m][$key . 'Used']++;
        }
    }

    $carryInCL = 0;
    $dashboard = [];

    for ($m = 1; $m <= $month; $m++) {
        $row = $monthlyRaw[$m];
        $clAllocated = $policy['cl']['monthlyAllocation'];
        $slAllocated = $policy['sl']['monthlyAllocation'];

        $halfToSL = intdiv($row['halfDays'], $policy['sl']['halfDayRate']);
        $totalSLUsed = $row['slUsed'] + $halfToSL;
        $slRemaining = $slAllocated - $totalSLUsed;

        $clDeductionForSL = 0;

        if ($slRemaining < 0) {
            $clDeductionForSL = abs($slRemaining);
            $slRemaining = 0;
        }

        $totalCLUsed = $row['clUsed'] + $clDeductionForSL;
        $clAvailable = $clAllocated + $carryInCL;
        $clRemaining = $clAvailable - $totalCLUsed;

        $lop = 0;

        if ($clRemaining < 0) {
            $lop = abs($clRemaining);
            $clRemaining = 0;
        }

        $thisCarryIn = $carryInCL;
        $carryInCL = ($policy['cl']['carryForward'] && $clRemaining > 0) ? $clRemaining : 0;

        if ($m === $month) {
            $dashboard = [
                'clAllocated' => $clAllocated,
                'slAllocated' => $slAllocated,
                'clUsed' => $row['clUsed'],
                'slUsed' => $row['slUsed'],
                'halfDays' => $row['halfDays'],
                'clPending' => $row['clPending'],
                'slPending' => $row['slPending'],
                'carryForwardCL' => $thisCarryIn,
                'remainingCL' => $clRemaining,
                'remainingSL' => $slRemaining,
                'lop' => $lop,
            ];
        }
    }

    return $dashboard;
}

// ── Save request (AJAX POST, form-encoded, from the Assign Status modal) ──
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');

    $postedCompanyId = isset($_POST['company_id']) ? (int) $_POST['company_id'] : $companyId;
    $postedUserId = isset($_POST['user_id']) ? (int) $_POST['user_id'] : 0;
    $postedDate = (string) ($_POST['date'] ?? '');
    $postedStatus = (string) ($_POST['status'] ?? '');

    if ($postedCompanyId <= 0 || $postedUserId <= 0 || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $postedDate)) {
        http_response_code(422);
        echo json_encode(['success' => false, 'message' => 'Invalid request.']);
        exit;
    }

    if ($postedStatus !== '' && !in_array($postedStatus, $allowedStatuses, true)) {
        http_response_code(422);
        echo json_encode(['success' => false, 'message' => 'Invalid status.']);
        exit;
    }

    try {
        if ($postedStatus === '') {
            DB::table('attendance_day_overrides')
                ->where('user_id', $postedUserId)
                ->where('override_date', $postedDate)
                ->delete();
        }
        else {
            DB::table('attendance_day_overrides')->updateOrInsert(
                ['user_id' => $postedUserId, 'override_date' => $postedDate],
                [
                    'company_id' => $postedCompanyId,
                    'status' => $postedStatus,
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        }

        echo json_encode(['success' => true]);
    }
    catch (Throwable $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Could not save. Please try again.']);
    }

    exit;
}

// ── Normal page load ──
$calendarUser = null;
$dateWiseData = [];
$leadingBlanks = 0;
$dashboard = ['clAllocated' => 0, 'slAllocated' => 0, 'clUsed' => 0, 'slUsed' => 0, 'halfDays' => 0, 'clPending' => 0, 'slPending' => 0, 'carryForwardCL' => 0, 'remainingCL' => 0, 'remainingSL' => 0, 'lop' => 0];
$timezone = config('app.timezone');
$defaultShift = null;

if ($companyId > 0 && $employeeId > 0 && $month >= 1 && $month <= 12 && $year > 0) {
    $calendarUser = User::withoutGlobalScope(ActiveScope::class)->find($employeeId);

    if ($calendarUser) {
        $startDate = Carbon::createFromDate($year, $month, 1)->startOfMonth()->startOfDay();
        $endDate = $startDate->copy()->endOfMonth()->endOfDay();

        $company = DB::table('companies')->find($companyId);
        $timezone = $company->timezone ?? config('app.timezone');

        $queryStart = Carbon::createFromDate($year, $month, 1)->startOfMonth();
        $officeStartTimeDB = Carbon::createFromFormat('Y-m-d H:i:s', $queryStart, $timezone);
        $queryStart = $queryStart->copy()->subMinutes($officeStartTimeDB->offset / 60);

        $queryEnd = Carbon::createFromDate($year, $month, 1)->endOfMonth();
        $officeEndTimeDB = Carbon::createFromFormat('Y-m-d H:i:s', $queryEnd, $timezone);
        $queryEnd = $queryEnd->copy()->subMinutes($officeEndTimeDB->offset / 60);

        $attendances = Attendance::without('company')
            ->where('user_id', $employeeId)
            ->whereBetween('clock_in_time', [$queryStart, $queryEnd])
            ->orderBy('clock_in_time')
            ->get();

        $holidays = Holiday::getHolidayByDates($startDate, $endDate, $employeeId)->keyBy('holiday_date');

        $leaves = Leave::where('user_id', $employeeId)
            ->where('leave_date', '>=', $startDate)
            ->where('leave_date', '<=', $endDate)
            ->where('status', 'approved')
            ->select('id', 'leave_date', 'reason', 'duration', 'leave_type_id')
            ->get()->keyBy(function ($leave) {
                return Carbon::parse($leave->leave_date)->toDateString();
            });

        $shiftSchedules = EmployeeShiftSchedule::with('shift')
            ->where('user_id', $employeeId)
            ->whereBetween('date', [$startDate->toDateString(), $endDate->toDateString()])
            ->get()
            ->keyBy(function ($schedule) {
                return Carbon::parse($schedule->date)->toDateString();
            });

        $dayOffs = $shiftSchedules->filter(function ($schedule) {
            return $schedule->shift && $schedule->shift->shift_name === 'Day Off';
        });

        $overrides = DB::table('attendance_day_overrides')
            ->where('user_id', $employeeId)
            ->whereBetween('override_date', [$startDate->toDateString(), $endDate->toDateString()])
            ->get()
            ->keyBy(function ($row) {
                return Carbon::parse($row->override_date)->toDateString();
            });

        $attendancesByDate = [];

        foreach ($attendances as $attendance) {
            $dateKey = $attendance->clock_in_time->timezone($timezone)->toDateString();
            $attendancesByDate[$dateKey][] = $attendance;
        }

        foreach (CarbonPeriod::create($startDate->copy()->startOfDay(), $endDate->copy()->startOfDay()) as $date) {
            $dateKey = $date->toDateString();

            $dateWiseData[$dateKey] = [
                'date' => $date->copy(),
                'is_future' => $date->isFuture(),
                'attendance' => $attendancesByDate[$dateKey] ?? null,
                'holiday' => $holidays->get($dateKey),
                'leave' => $leaves->get($dateKey),
                'day_off' => $dayOffs->get($dateKey),
                'shift' => $shiftSchedules->get($dateKey),
                'override' => optional($overrides->get($dateKey))->status,
            ];
        }

        $leadingBlanks = $startDate->dayOfWeek;
        $dashboard = alBuildMonthlyLeaveDashboard($companyId, $employeeId, $month, $year) ?: $dashboard;
    }
}

// Resolved once here so both the calendar's real-leave classification below
// and the "Assign Status" JS (which needs these ids to open Create Leave /
// Update Shift pre-filled) share the same source of truth.
$clLeaveTypeId = 0;
$slLeaveTypeId = 0;
$lopLeaveTypeId = 0;
$dayOffShiftId = 0;
$leaveTypeClassification = [];

if ($companyId > 0) {
    foreach (DB::table('leave_types')->where('company_id', $companyId)->whereNull('deleted_at')->get(['id', 'type_name']) as $leaveTypeRow) {
        $key = alClassifyLeaveType($leaveTypeRow->type_name);

        if ($key === null) {
            continue;
        }

        $leaveTypeClassification[$leaveTypeRow->id] = $key;

        if ($key === 'cl') {
            $clLeaveTypeId = $leaveTypeRow->id;
        } elseif ($key === 'sl') {
            $slLeaveTypeId = $leaveTypeRow->id;
        } elseif ($key === 'lop') {
            $lopLeaveTypeId = $leaveTypeRow->id;
        }
    }

    $dayOffShift = DB::table('employee_shifts')->where('company_id', $companyId)->where('shift_name', 'Day Off')->first();
    $dayOffShiftId = $dayOffShift->id ?? 0;

    $attendanceSettingRow = DB::table('attendance_settings')->where('company_id', $companyId)->first();
    $defaultShift = $attendanceSettingRow
        ? DB::table('employee_shifts')->where('id', $attendanceSettingRow->default_employee_shift)->first()
        : null;
}

// Icons for the 7 core statuses match the main Attendance grid's legend
// exactly (fa fa-star / fa-calendar-week / fa-check / fa-home /
// fa-star-half-alt / fa-times / fa-plane-departure) — colored via the
// existing .status-* CSS classes below, same as before.
$statusMeta = [
    'holiday' => ['icon' => '<i class="fa fa-star"></i>', 'class' => 'status-holiday'],
    'dayoff' => ['icon' => '<i class="fa fa-calendar-week"></i>', 'class' => 'status-dayoff'],
    'present' => ['icon' => '<i class="fa fa-check"></i>', 'class' => 'status-present'],
    'wfh' => ['icon' => '<i class="fa fa-home"></i>', 'class' => 'status-wfh'],
    'halfday' => ['icon' => '<i class="fa fa-star-half-alt"></i>', 'class' => 'status-halfday'],
    'late' => ['icon' => '🟡', 'class' => 'status-late'],
    'absent' => ['icon' => '<i class="fa fa-times"></i>', 'class' => 'status-absent'],
    'leave' => ['icon' => '<i class="fa fa-plane-departure"></i>', 'class' => 'status-leave'],
    'cl' => ['icon' => 'CL', 'class' => 'status-cl'],
    'sl' => ['icon' => 'SL', 'class' => 'status-sl'],
    'lop' => ['icon' => '⚠️', 'class' => 'status-lop'],
    'cl-half' => ['icon' => 'CL (0.5)', 'class' => 'status-cl-half'],
    'sl-half' => ['icon' => 'SL (0.5)', 'class' => 'status-sl-half'],
    'lop-half' => ['icon' => 'LOP (0.5)', 'class' => 'status-lop-half'],
];

$resolveStatusKey = function ($dateData) use ($leaveTypeClassification) {
    if (!empty($dateData['override'])) {
        return $dateData['override'];
    }

    // A scheduled Day Off from the Shift Roster (employee_shift_schedules) wins over a
    // stray clock-in that day — same precedence as AttendanceController::summaryData().
    // Checking attendance first (as this used to) let a single punch on a day the roster
    // says the employee wasn't due in silently replace the Day Off marker with
    // Present/Half Day/WFH.
    if (!empty($dateData['day_off'])) {
        return 'dayoff';
    }

    if (!empty($dateData['attendance']) && empty($dateData['leave'])) {
        $attendance = $dateData['attendance'][0];

        if (!empty($attendance->half_day) && $attendance->half_day == 'yes') {
            return 'halfday';
        }

        if (!empty($attendance->work_from_type) && $attendance->work_from_type === 'home') {
            return 'wfh';
        }

        // Late no longer resolves to its own center status — Present/Half
        // Day/WFH stays the center icon, and lateness shows as the LC badge
        // (see $resolveLateBadge) layered on top instead, additive not
        // replacing, per the Late Coming Indicator spec.
        return 'present';
    }

    if (!empty($dateData['leave'])) {
        $leaveTypeId = $dateData['leave']->leave_type_id ?? null;

        return ($leaveTypeId && isset($leaveTypeClassification[$leaveTypeId])) ? $leaveTypeClassification[$leaveTypeId] : 'leave';
    }

    if (!empty($dateData['holiday'])) {
        return 'holiday';
    }

    if ($dateData['is_future']) {
        return null;
    }

    return 'absent';
};

// LC badge: additive to whatever the center status resolves to (Present,
// Half Day, WFH) — driven by comparing the real clock-in time against the
// shift's Allowed Clock-In Time (start + late mark minutes), not the
// stored `late` column. Admin-entered attendance (e.g. via this calendar's
// "Assign Status" → Present) doesn't auto-compute `late` from the clock-in
// time it saves — only the employee self-clock-in path does — so trusting
// the column alone would silently miss lates on admin-entered records.
$resolveLateBadge = function ($dateData) use ($timezone, $defaultShift) {
    // A scheduled Day Off has no "on time" to be late against — a stray clock-in
    // on a day the roster says the employee wasn't due in shouldn't be flagged
    // late, same precedence Day Off already gets over the center status above.
    if (empty($dateData['attendance']) || !empty($dateData['day_off'])) {
        return false;
    }

    $attendance = $dateData['attendance'][0];

    $shift = (!empty($dateData['shift']) && $dateData['shift']->shift) ? $dateData['shift']->shift : $defaultShift;

    if (!$shift || ($shift->shift_type ?? null) !== 'strict' || empty($shift->office_start_time)) {
        // No fixed start time to compare against (flexible shift, or no
        // shift resolvable at all) — fall back to whatever was recorded.
        return !empty($attendance->late) && $attendance->late == 'yes';
    }

    $clockIn = Carbon::parse($attendance->clock_in_time, 'UTC')->timezone($timezone);
    $allowedClockIn = Carbon::parse($clockIn->format('Y-m-d') . ' ' . $shift->office_start_time, $timezone)
        ->addMinutes((int) ($shift->late_mark_duration ?? 0));

    return $clockIn->gt($allowedClockIn);
};

// Top-left shift label: only shown when the Shift Roster has an explicit
// assignment for that specific date (no fallback to a company default).
$resolveShiftLabel = function ($dateData) {
    if (empty($dateData['shift']) || empty($dateData['shift']->shift)) {
        return null;
    }

    $shift = $dateData['shift']->shift;

    return $shift->shift_short_code ?: $shift->shift_name;
};

$monthNames = ['', 'January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
$monthLabel = ($monthNames[$month] ?? '') . ' ' . $year;
$weekDayLabels = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= alEsc($calendarUser->name ?? 'Attendance Calendar') ?></title>

    <!-- Font Awesome for Icons — matches the icon set used on the main
         Attendance grid (resources/views/attendances/*.blade.php), so the
         calendar's Holiday/Day Off/Present/WFH/Half Day/Absent/Leave
         markers read as the same symbols in both places. -->
    <link
    rel="stylesheet"
    href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css"
    />

    <style>
        /* ===== RESET & BASE ===== */
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            background-color: #f4f7fa; color: #1a202c; padding: 30px; position: relative;
        }
        .container { max-width: 1200px; margin: 0 auto; }

        /* ===== HEADER ===== */
        .header-title { font-size: 28px; font-weight: 700; color: #2c5282; margin-bottom: 5px; }
        .header-sub { font-size: 16px; font-weight: 600; color: #1a202c; margin-bottom: 2px; }
        .header-date { font-size: 14px; color: #718096; }

        /* ===== DASHBOARD HEADER ROW ===== */
        .dashboard-row {
            display: flex; justify-content: space-between; align-items: flex-start;
            margin-top: 25px; margin-bottom: 20px; flex-wrap: wrap; gap: 15px;
        }
        .dashboard-title {
            font-size: 18px; font-weight: 700; color: #1a202c;
            display: flex; align-items: center; gap: 10px;
        }
        .dashboard-title span { color: #2b6cb0; }

        .legend {
            display: flex; flex-wrap: wrap; gap: 12px; font-size: 12px; color: #4a5568; align-items: center;
        }
        .legend-item { display: flex; align-items: center; gap: 4px; }
        .legend-item .shape { display: inline-block; }
        .shape.square { width: 10px; height: 10px; background: #e53e3e; border-radius: 1px; }
        .shape.half-circle { width: 10px; height: 10px; border-radius: 50%; background: #ed8936; clip-path: inset(0 50% 0 0); }
        .shape.circle { width: 10px; height: 10px; border-radius: 50%; background: #ecc94b; }

        /* ===== STATS CARDS ===== */
        .stats-row { display: flex; flex-wrap: wrap; gap: 10px; margin-bottom: 30px; }
        .card {
            flex: 1 1 80px; min-width: 70px; background: #ffffff; border-radius: 6px;
            padding: 12px 5px; box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            text-align: center; display: flex; flex-direction: column; align-items: center; justify-content: center;
        }
        .card-icon { font-size: 20px; margin-bottom: 4px; }
        .card-label { font-size: 9px; font-weight: 700; text-transform: uppercase; color: #718096; line-height: 1.2; letter-spacing: 0.5px; }
        .card-value { font-size: 16px; font-weight: 700; color: #1a202c; margin-top: 6px; transition: 0.2s; }
        .card.blue .card-icon { color: #3182ce; } .card.green .card-icon { color: #38a169; }
        .card.yellow .card-icon { color: #d69e2e; } .card.red .card-icon { color: #e53e3e; }
        .card.teal .card-icon { color: #319795; } .card.purple .card-icon { color: #805ad5; }
        .card.pink .card-icon { color: #fc8181; }

        /* ===== CALENDAR ===== */
        .days-row {
            display: grid; grid-template-columns: repeat(7, 1fr);
            text-align: center; font-size: 14px; font-weight: 700; color: #1a202c;
            margin-bottom: 10px; padding: 0 5px;
        }
        .dates-grid { display: grid; grid-template-columns: repeat(7, 1fr); gap: 8px; }
        .date-box {
            background: #ebf0f6; border: 1px solid #d2dce6; border-radius: 4px;
            padding: 15px 0; text-align: center; display: flex; flex-direction: column;
            align-items: center; justify-content: center; min-height: 60px;
            cursor: pointer; transition: all 0.2s ease; position: relative;
        }
        .date-box:hover { background: #e2e8f0; }
        .date-box.selected { border: 2px solid #2b6cb0; box-shadow: 0 0 0 1px #2b6cb0; }
        .date-num { font-size: 16px; font-weight: 700; color: #1a202c; }

        /* ===== SHIFT LABEL (top-left) & LC BADGE (top-right) ===== */
        .shift-label {
            position: absolute; top: 3px; left: 4px;
            font-size: 9px; font-weight: 600; color: #4a5568;
            max-width: calc(100% - 30px); overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
        }
        .lc-badge {
            position: absolute; top: 3px; right: 3px;
            background-color: #FFA500; color: #fff;
            border-radius: 4px; font-size: 10px; font-weight: bold;
            padding: 1px 4px; line-height: 1.4;
        }

        /* ===== EDIT PENCIL ICON ===== */
        .date-status {
            display: flex; align-items: center; justify-content: center; gap: 4px;
            font-size: 16px; margin-top: 4px; line-height: 1;
        }
        .edit-icon {
            font-size: 11px; cursor: pointer; opacity: 0;
            transition: opacity 0.2s ease; color: #718096; border-radius: 4px; padding: 0 2px;
        }
        .edit-icon:hover { background: #cbd5e0; color: #1a202c; }
        .date-box:hover .edit-icon { opacity: 1; }

        /* Status Styles */
        .status-cl { color: #3182ce; font-weight: 700; font-size: 14px; }
        .status-sl { color: #38a169; font-weight: 700; font-size: 14px; }
        .status-lop { color: #fc8181; font-weight: 700; font-size: 14px; }
        .status-present { color: #38a169; font-weight: bold; }
        .status-absent { color: #e53e3e; font-weight: bold; }
        .status-leave { color: #e53e3e; }
        .status-holiday { color: #ecc94b; }
        .status-wfh { color: #3182ce; }
        .status-halfday { color: #ed8936; }
        .status-late { color: #ecc94b; }
        .status-dayoff { color: #e53e3e; }

        /* Half Status Styles */
        .status-cl-half { color: #3182ce; font-weight: 700; font-size: 12px; }
        .status-sl-half { color: #38a169; font-weight: 700; font-size: 12px; }
        .status-lop-half { color: #fc8181; font-weight: 700; font-size: 12px; }

        /* ===== FLOATING ACTION BUTTON ===== */
        .floating-btn {
            position: fixed; bottom: 30px; right: 30px;
            background: #2b6cb0; color: white; border: none;
            padding: 12px 24px; border-radius: 30px; font-size: 16px; font-weight: 600;
            box-shadow: 0 4px 12px rgba(43, 108, 176, 0.3); cursor: pointer;
            display: none; align-items: center; gap: 8px; transition: all 0.2s ease;
            z-index: 500;
        }
        .floating-btn.revert-btn { background: #e53e3e; box-shadow: 0 4px 12px rgba(229, 62, 62, 0.3); }
        .floating-btn.revert-btn:hover { background: #c53030; transform: scale(1.02); }
        .floating-btn:hover { background: #2c5282; transform: scale(1.02); }
        .floating-btn.visible { display: flex; }

        /* ===== MODALS ===== */
        .modal-overlay {
            position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0, 0, 0, 0.3); backdrop-filter: blur(2px);
            z-index: 1000; display: flex; align-items: center; justify-content: center;
            opacity: 0; pointer-events: none; transition: opacity 0.2s ease;
        }
        .modal-overlay.active { opacity: 1; pointer-events: auto; }
        .modal-content {
            background: #ffffff; padding: 25px; border-radius: 12px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.2); max-width: 500px; width: 90%;
            display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px;
            transform: scale(0.95); transition: transform 0.2s ease;
        }
        .modal-overlay.active .modal-content { transform: scale(1); }

        .modal-header { grid-column: 1 / -1; text-align: center; font-weight: 700; margin-bottom: 10px; border-bottom: 1px solid #e2e8f0; padding-bottom: 10px; font-size: 16px; color: #2d3748; }
        .modal-header.sub { border-bottom: none; padding-bottom: 0; margin-bottom: 5px; font-size: 14px; color: #718096; }

        .modal-option {
            background: #f7fafc; padding: 12px 8px; border-radius: 8px; border: 1px solid #e2e8f0;
            cursor: pointer; display: flex; flex-direction: column; align-items: center; gap: 4px;
            transition: all 0.1s ease; font-size: 12px; color: #4a5568;
        }
        .modal-option:hover { background: #edf2f7; border-color: #a0aec0; transform: translateY(-1px); }
        .modal-option-icon { font-size: 22px; }

        .modal-cancel {
            grid-column: 1 / -1; margin-top: 10px; background: #edf2f7; border: none;
            padding: 10px; border-radius: 8px; cursor: pointer; color: #4a5568; font-weight: 600;
        }
        .modal-cancel:hover { background: #e2e8f0; }

        /* Revert Modal Special Style */
        .modal-option.danger { background: #fee2e2; border-color: #f87171; color: #991b1b; }
        .modal-option.danger:hover { background: #fecaca; }

        .al-loading { text-align: center; padding: 60px 0; color: #718096; }

        /* Responsive */
        @media (max-width: 768px) {
            .dashboard-row { flex-direction: column; align-items: flex-start; }
            .card { min-width: 60px; flex-basis: 40px; } .stats-row { gap: 6px; }
            .modal-content { max-width: 300px; grid-template-columns: repeat(2, 1fr); }
        }
    </style>
</head>
<body>

<?php if (!$calendarUser): ?>
    <div class="al-loading">Employee not found.</div>
<?php else: ?>

<div class="container">
    <!-- Header -->
    <div class="header-title"><?= alEsc($calendarUser->name) ?></div>
    <div class="header-sub"><?= alEsc($calendarUser->name) ?></div>
    <div class="header-date"><?= alEsc($monthLabel) ?></div>

    <!-- Dashboard Title & Legend -->
    <div class="dashboard-row">
        <div class="dashboard-title"><span>📅</span> Monthly Dashboard — <?= alEsc($monthNames[$month] ?? '') ?></div>
        <div class="legend">
            <div class="legend-item"><i class="fa fa-star" style="color:#ecc94b;"></i> Holiday</div>
            <div class="legend-item"><i class="fa fa-calendar-week" style="color:#e53e3e;"></i> Day Off</div>
            <div class="legend-item"><i class="fa fa-check" style="color:#38a169;"></i> Present</div>
            <div class="legend-item"><i class="fa fa-home" style="color:#3182ce;"></i> Work from home</div>
            <div class="legend-item"><i class="fa fa-star-half-alt" style="color:#ed8936;"></i> Half Day</div>
            <div class="legend-item"><span class="shape circle"></span> Late</div>
            <div class="legend-item"><i class="fa fa-times" style="color:#e53e3e;"></i> Absent</div>
            <div class="legend-item"><i class="fa fa-plane-departure" style="color:#e53e3e;"></i> On Leave</div>
            <div class="legend-item" style="font-weight:bold; color:#3182ce;">CL</div>
            <div class="legend-item" style="font-weight:bold; color:#38a169;">SL</div>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="stats-row">
        <div class="card blue"><div class="card-icon">💼</div><div class="card-label">CL<br>ALLOCATED</div><div class="card-value" id="cl-allocated"><?= alEsc($dashboard['clAllocated']) ?></div></div>
        <div class="card green"><div class="card-icon">📅</div><div class="card-label">SL<br>ALLOCATED</div><div class="card-value" id="sl-allocated"><?= alEsc($dashboard['slAllocated']) ?></div></div>
        <div class="card yellow"><div class="card-icon">🟡</div><div class="card-label">CL USED</div><div class="card-value" id="cl-used"><?= alEsc($dashboard['clUsed']) ?></div></div>
        <div class="card red"><div class="card-icon">🔴</div><div class="card-label">SL USED</div><div class="card-value" id="sl-used"><?= alEsc($dashboard['slUsed']) ?></div></div>
        <div class="card teal"><div class="card-icon">◐</div><div class="card-label">HALF-DAYS</div><div class="card-value"><?= alEsc($dashboard['halfDays']) ?></div></div>
        <div class="card yellow"><div class="card-icon">⏳</div><div class="card-label">CL PENDING</div><div class="card-value"><?= alEsc($dashboard['clPending']) ?></div></div>
        <div class="card yellow"><div class="card-icon">⏳</div><div class="card-label">SL PENDING</div><div class="card-value"><?= alEsc($dashboard['slPending']) ?></div></div>
        <div class="card purple"><div class="card-icon">📅</div><div class="card-label">CARRY FWD<br>CL</div><div class="card-value"><?= alEsc($dashboard['carryForwardCL']) ?></div></div>
        <div class="card green"><div class="card-icon">🟢</div><div class="card-label">REMAINING<br>CL</div><div class="card-value"><?= alEsc($dashboard['remainingCL']) ?></div></div>
        <div class="card blue"><div class="card-icon">👤</div><div class="card-label">REMAINING<br>SL</div><div class="card-value"><?= alEsc($dashboard['remainingSL']) ?></div></div>
        <div class="card pink"><div class="card-icon">⚠️</div><div class="card-label">LOP</div><div class="card-value" id="lop-count"><?= alEsc($dashboard['lop']) ?></div></div>
    </div>

    <!-- Calendar Grid -->
    <div class="days-row">
        <?php foreach ($weekDayLabels as $label): ?>
            <div><?= alEsc($label) ?></div>
        <?php endforeach; ?>
    </div>
    <div class="dates-grid">
        <?php for ($i = 0; $i < $leadingBlanks; $i++): ?>
            <div class="date-box"></div>
        <?php endfor; ?>

        <?php foreach ($dateWiseData as $dateKey => $dateData): ?>
            <?php
                $statusKey = $resolveStatusKey($dateData);
                $shiftLabel = $resolveShiftLabel($dateData);
                $showLateBadge = $resolveLateBadge($dateData);
            ?>
            <div class="date-box" data-date="<?= alEsc($dateKey) ?>"
                data-attendance-id="<?= alEsc(!empty($dateData['attendance']) ? $dateData['attendance'][0]->id : '') ?>"
                data-leave-id="<?= alEsc(!empty($dateData['leave']) ? $dateData['leave']->id : '') ?>">
                <?php if ($shiftLabel): ?>
                    <div class="shift-label"><?= alEsc($shiftLabel) ?></div>
                <?php endif; ?>
                <?php if ($showLateBadge): ?>
                    <div class="lc-badge">LC</div>
                <?php endif; ?>
                <div class="date-num"><?= alEsc($dateData['date']->day) ?></div>
                <?php if ($statusKey): ?>
                    <div class="date-status <?= alEsc($statusMeta[$statusKey]['class']) ?>"><?= $statusMeta[$statusKey]['icon'] ?> <span class="edit-icon">✏️</span></div>
                <?php else: ?>
                    <div class="date-status">-</div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- ===== FLOATING ACTION BUTTON ===== -->
<button class="floating-btn" id="bulkActionBtn">
    ✨ Merge Selected <span id="selectedCount"></span>
</button>

<!-- ===== SINGLE ASSIGN MODAL ===== -->
<div class="modal-overlay" id="singleModal">
    <div class="modal-content">
        <div class="modal-header">Assign Status to Selected Date</div>
        <div class="modal-option" data-status="cl"><span class="modal-option-icon">📄</span> CL</div>
        <div class="modal-option" data-status="sl"><span class="modal-option-icon">📄</span> SL</div>
        <div class="modal-option" data-status="lop"><span class="modal-option-icon">⚠️</span> LOP</div>
        <div class="modal-option" data-status="holiday"><span class="modal-option-icon">★</span> Holiday</div>
        <div class="modal-option" data-status="present"><span class="modal-option-icon">✓</span> Present</div>
        <div class="modal-option" data-status="absent"><span class="modal-option-icon">×</span> Absent</div>
        <div class="modal-option" data-status="leave"><span class="modal-option-icon">🚩</span> On Leave</div>
        <div class="modal-option" data-status="wfh"><span class="modal-option-icon">🏠</span> WFH</div>
        <div class="modal-option" data-status="halfday"><span class="modal-option-icon">◐</span> Half Day</div>
        <div class="modal-option" data-status="late"><span class="modal-option-icon">🟡</span> Late</div>
        <div class="modal-option" data-status="dayoff"><span class="modal-option-icon">⬛</span> Day Off</div>
        <div class="modal-option" data-status="clear"><span class="modal-option-icon">🗑️</span> Clear Status</div>
        <button class="modal-cancel" id="singleCancelBtn">Cancel</button>
    </div>
</div>

<!-- ===== MERGE HALF DAYS MODAL ===== -->
<div class="modal-overlay" id="mergeModal">
    <div class="modal-content" style="grid-template-columns: repeat(1, 1fr);">
        <div class="modal-header">Merge 2 Half Days into 1 Full Day</div>
        <div class="modal-header sub" style="font-weight: 400; margin-bottom: 15px;">
            You are merging <strong>2 half-days</strong> into a single day of leave.
            (0.5 + 0.5 = 1 Day Used)
        </div>

        <div class="modal-option" data-merge="cl" style="grid-column: 1;">
            <span class="modal-option-icon" style="color:#3182ce;">📄</span>
            Merge as Casual Leave (CL)
        </div>
        <div class="modal-option" data-merge="sl" style="grid-column: 1;">
            <span class="modal-option-icon" style="color:#38a169;">📄</span>
            Merge as Sick Leave (SL)
        </div>
        <div class="modal-option" data-merge="lop" style="grid-column: 1;">
            <span class="modal-option-icon" style="color:#fc8181;">⚠️</span>
            Merge as Loss of Pay (LOP)
        </div>
        <button class="modal-cancel" id="mergeCancelBtn">Cancel</button>
    </div>
</div>

<!-- ===== REVERT MERGE HALF DAYS MODAL ===== -->
<div class="modal-overlay" id="revertModal">
    <div class="modal-content" style="grid-template-columns: repeat(1, 1fr);">
        <div class="modal-header">↩️ Revert Merged 0.5 Days</div>
        <div class="modal-header sub" style="font-weight: 400; margin-bottom: 15px;">
            Revert these two 0.5 days back to standard <strong>Half Days (◐)</strong>.
            This will automatically <strong>subtract 1</strong> from your used leave stats.
        </div>

        <div class="modal-option danger" data-revert="true" style="grid-column: 1;">
            <span class="modal-option-icon" style="color:#e53e3e;">↩️</span>
            Yes, Revert to Half Days
        </div>
        <button class="modal-cancel" id="revertCancelBtn">Cancel</button>
    </div>
</div>

<script>
(function () {
    var COMPANY_ID = <?= (int) $companyId ?>;
    var USER_ID = <?= (int) $employeeId ?>;
    var USER_NAME = <?= json_encode($calendarUser->name ?? '') ?>;
    var MONTH = <?= (int) $month ?>;
    var YEAR = <?= (int) $year ?>;
    var CL_LEAVE_TYPE_ID = <?= (int) $clLeaveTypeId ?>;
    var SL_LEAVE_TYPE_ID = <?= (int) $slLeaveTypeId ?>;
    var LOP_LEAVE_TYPE_ID = <?= (int) $lopLeaveTypeId ?>;
    var DAY_OFF_SHIFT_ID = <?= (int) $dayOffShiftId ?>;
    var ATTENDANCE_CREATE_URL = <?= json_encode($attendanceCreateUrl ?? route('attendances.create')) ?>;
    var ATTENDANCE_EDIT_URL_TEMPLATE = <?= json_encode($attendanceEditUrlTemplate ?? route('attendances.edit', ':id')) ?>;
    var ATTENDANCES_INDEX_URL = <?= json_encode($attendancesIndexUrl ?? route('attendances.index')) ?>;
    var LEAVE_CREATE_URL = <?= json_encode($leaveCreateUrl ?? route('leaves.create')) ?>;
    var LEAVE_EDIT_URL_TEMPLATE = <?= json_encode($leaveEditUrlTemplate ?? route('leaves.edit', ':id')) ?>;
    var SHIFT_MARK_URL_TEMPLATE = <?= json_encode($shiftMarkUrlTemplate ?? route('shifts.mark', [':userid', ':day', ':month', ':year'])) ?>;

    var dateBoxes = document.querySelectorAll('.date-box[data-date]');
    var singleModal = document.getElementById('singleModal');
    var mergeModal = document.getElementById('mergeModal');
    var revertModal = document.getElementById('revertModal');
    var singleCancelBtn = document.getElementById('singleCancelBtn');
    var mergeCancelBtn = document.getElementById('mergeCancelBtn');
    var revertCancelBtn = document.getElementById('revertCancelBtn');
    var bulkActionBtn = document.getElementById('bulkActionBtn');
    var statusOptions = document.querySelectorAll('#singleModal .modal-option');
    var mergeOptions = document.querySelectorAll('#mergeModal .modal-option[data-merge]');
    var revertOptions = document.querySelectorAll('#revertModal .modal-option[data-revert]');

    var selectedDateElements = [];

    function reloadPage() {
        window.location.reload();
    }

    function saveDayStatus(dateStr, status) {
        var body = new URLSearchParams();
        body.set('company_id', COMPANY_ID);
        body.set('user_id', USER_ID);
        body.set('date', dateStr);
        body.set('status', status || '');

        return fetch(window.location.pathname + window.location.search, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: body.toString()
        }).then(function (res) { return res.json(); });
    }

    function withSavingState(promises) {
        bulkActionBtn.disabled = true;

        Promise.all(promises)
            .catch(function () {
                alert('Could not save. Please try again.');
            })
            .then(function () {
                reloadPage();
            });
    }

    // ===== TOGGLE DATE SELECTION =====
    dateBoxes.forEach(function (box) {
        box.addEventListener('click', function (e) {
            if (e.target.closest('.edit-icon')) return;

            var index = selectedDateElements.indexOf(box);
            if (index > -1) {
                box.classList.remove('selected');
                selectedDateElements.splice(index, 1);
            } else {
                box.classList.add('selected');
                selectedDateElements.push(box);
            }
            updateBulkButton();
        });
    });

    // ===== EDIT ICON DIRECT CLICK =====
    document.addEventListener('click', function (e) {
        if (e.target.closest('.edit-icon')) {
            e.stopPropagation();
            var targetBox = e.target.closest('.date-box');

            selectedDateElements.forEach(function (el) { el.classList.remove('selected'); });
            selectedDateElements = [];

            targetBox.classList.add('selected');
            selectedDateElements.push(targetBox);
            openSingleModal();
        }
    });

    // ===== UPDATE FLOATING BUTTON =====
    function updateBulkButton() {
        var count = selectedDateElements.length;
        if (count === 0) {
            bulkActionBtn.classList.remove('visible');
        } else if (count === 1) {
            bulkActionBtn.className = 'floating-btn visible';
            bulkActionBtn.innerHTML = '📝 Assign Status <span id="selectedCount">(1)</span>';
            bulkActionBtn.onclick = openSingleModal;
        } else if (count === 2) {
            var isHalfDay = selectedDateElements.every(function (el) {
                var statusDiv = el.querySelector('.date-status');
                // Half Day now renders as a Font Awesome <i> icon (no text glyph in
                // innerText), so detect it via the status-halfday class instead.
                return statusDiv && statusDiv.classList.contains('status-halfday');
            });

            var isHalfMerged = selectedDateElements.every(function (el) {
                var statusDiv = el.querySelector('.date-status');
                return statusDiv && (statusDiv.innerText.indexOf('CL (0.5)') !== -1 || statusDiv.innerText.indexOf('SL (0.5)') !== -1 || statusDiv.innerText.indexOf('LOP (0.5)') !== -1);
            });

            if (isHalfDay) {
                bulkActionBtn.className = 'floating-btn visible';
                bulkActionBtn.innerHTML = '✨ Merge 0.5 + 0.5 <span id="selectedCount">(2)</span>';
                bulkActionBtn.onclick = openMergeModal;
            } else if (isHalfMerged) {
                bulkActionBtn.className = 'floating-btn revert-btn visible';
                bulkActionBtn.innerHTML = '↩️ Revert 0.5 + 0.5 <span id="selectedCount">(2)</span>';
                bulkActionBtn.onclick = openRevertModal;
            } else {
                bulkActionBtn.className = 'floating-btn visible';
                bulkActionBtn.innerHTML = '📝 Bulk Assign <span id="selectedCount">(2)</span>';
                bulkActionBtn.onclick = openSingleModal;
            }
        } else {
            bulkActionBtn.className = 'floating-btn visible';
            bulkActionBtn.innerHTML = '📝 Bulk Assign <span id="selectedCount">(' + count + ')</span>';
            bulkActionBtn.onclick = openSingleModal;
        }
    }

    // ===== MODAL OPENERS =====
    function openSingleModal() {
        if (selectedDateElements.length === 0) return;
        singleModal.classList.add('active');
    }

    function openMergeModal() {
        if (selectedDateElements.length !== 2) return;
        mergeModal.classList.add('active');
    }

    function openRevertModal() {
        if (selectedDateElements.length !== 2) return;
        revertModal.classList.add('active');
    }

    // ===== CLOSE MODAL LOGIC =====
    function closeModal(modalEl) {
        modalEl.classList.remove('active');
        selectedDateElements.forEach(function (el) { el.classList.remove('selected'); });
        selectedDateElements = [];
        updateBulkButton();
    }

    singleCancelBtn.onclick = function () { closeModal(singleModal); };
    mergeCancelBtn.onclick = function () { closeModal(mergeModal); };
    revertCancelBtn.onclick = function () { closeModal(revertModal); };
    [singleModal, mergeModal, revertModal].forEach(function (modal) {
        modal.addEventListener('click', function (e) {
            if (e.target === modal) closeModal(modal);
        });
    });

    // ===== OPEN REAL FORMS (Present/WFH/Late/Half Day/On Leave/CL/SL/LOP/Day Off) =====
    // These create genuine attendances/leaves/employee_shift_schedules rows
    // instead of only a display override, by opening the app's own forms
    // (Mark Attendance, Create Leave, Update Shift) in the parent page —
    // this calendar runs in an <iframe>, same-origin, so window.parent's
    // jQuery/modal system can be driven directly. Selector strings below
    // mirror the `const` route/modal constants in layouts/app.blade.php —
    // `const` bindings aren't reachable across frames via window, so
    // they're duplicated here rather than read off window.parent.
    var parentWin = window.parent;
    var RIGHT_MODAL_CONTENT_SEL = '#right-modal-content';
    var RIGHT_MODAL_TITLE_SEL = '#right-modal-title';
    var RIGHT_MODAL_SEL = '#task-detail-1';
    var MODAL_DEFAULT_SEL = '#myModalDefault';

    function openInRightPanel(url) {
        parentWin.$.easyAjax({
            url: url,
            blockUI: true,
            container: RIGHT_MODAL_SEL,
            historyPush: true,
            success: function (response) {
                if (response.status == 'success') {
                    parentWin.$(RIGHT_MODAL_CONTENT_SEL).html(response.html);
                    parentWin.$(RIGHT_MODAL_TITLE_SEL).html(response.title);
                }
            }
        });
    }

    // Mark Attendance / Create Leave save by navigating/reloading the
    // parent page — this flag tells it to reopen this calendar afterwards.
    function rememberToReopenCalendar() {
        parentWin.sessionStorage.setItem('reopenCalendar', JSON.stringify({
            userId: USER_ID,
            userName: USER_NAME,
            month: MONTH,
            year: YEAR
        }));
    }

    function openMarkAttendance(dateStr, extraParams, attendanceId) {
        var params = new URLSearchParams();
        params.set('default_assign', USER_ID);
        params.set('date', dateStr);
        for (var key in extraParams) {
            params.set(key, extraParams[key]);
        }

        rememberToReopenCalendar();

        var baseUrl = attendanceId ? ATTENDANCE_EDIT_URL_TEMPLATE.replace(':id', attendanceId) : ATTENDANCE_CREATE_URL;
        openInRightPanel(baseUrl + '?' + params.toString());
    }

    function openCreateLeave(dateStr, leaveTypeId, leaveId) {
        var params = new URLSearchParams();
        params.set('default_assign', USER_ID);
        params.set('date', dateStr);
        params.set('status', 'approved');
        params.set('redirectUrl', ATTENDANCES_INDEX_URL);
        if (leaveTypeId) {
            params.set('leave_type_id', leaveTypeId);
        }

        rememberToReopenCalendar();

        var baseUrl = leaveId ? LEAVE_EDIT_URL_TEMPLATE.replace(':id', leaveId) : LEAVE_CREATE_URL;
        openInRightPanel(baseUrl + '?' + params.toString());
    }

    function openUpdateShift(dateStr, employeeShiftId) {
        var parts = dateStr.split('-');
        var url = SHIFT_MARK_URL_TEMPLATE
            .replace(':userid', USER_ID)
            .replace(':day', parts[2])
            .replace(':month', parts[1])
            .replace(':year', parts[0]);
        url += '?employee_shift_id=' + employeeShiftId;

        // Doesn't navigate the parent page on save (unlike the two above),
        // so no reopen flag needed — window.loadData on the attendances
        // page (attendances/index.blade.php) refreshes this calendar in
        // place once the shift modal's own success handler fires.
        parentWin.$.ajaxModal(MODAL_DEFAULT_SEL, url);
    }

    var FORM_STATUS_HANDLERS = {
        present: function (d, aId) { openMarkAttendance(d, {}, aId); },
        wfh: function (d, aId) { openMarkAttendance(d, { work_from_type: 'home' }, aId); },
        late: function (d, aId) { openMarkAttendance(d, { late: 'yes' }, aId); },
        halfday: function (d, aId) { openMarkAttendance(d, { half_day: 'yes' }, aId); },
        absent: function (d, aId, lId) { openCreateLeave(d, 0, lId); },
        leave: function (d, aId, lId) { openCreateLeave(d, 0, lId); },
        cl: function (d, aId, lId) { openCreateLeave(d, CL_LEAVE_TYPE_ID, lId); },
        sl: function (d, aId, lId) { openCreateLeave(d, SL_LEAVE_TYPE_ID, lId); },
        lop: function (d, aId, lId) { openCreateLeave(d, LOP_LEAVE_TYPE_ID, lId); },
        dayoff: function (d) { openUpdateShift(d, DAY_OFF_SHIFT_ID); }
    };

    // ===== SINGLE / BULK ASSIGN LOGIC =====
    statusOptions.forEach(function (option) {
        option.addEventListener('click', function () {
            var status = option.dataset.status === 'clear' ? '' : option.dataset.status;
            var dates = selectedDateElements.map(function (el) { return el.dataset.date; });
            // Snapshotted before closeModal() clears selectedDateElements —
            // an already-assigned date has a real attendance/leave record,
            // so the pencil edits that record instead of creating a new one.
            var existingAttendanceId = selectedDateElements.length === 1 ? selectedDateElements[0].dataset.attendanceId : '';
            var existingLeaveId = selectedDateElements.length === 1 ? selectedDateElements[0].dataset.leaveId : '';

            closeModal(singleModal);

            // Holiday and Clear Status, plus any multi-date bulk selection
            // (the real forms below only make sense for one date at a
            // time), keep saving straight to the override table.
            if (dates.length === 1 && FORM_STATUS_HANDLERS[status]) {
                var dateToOpen = dates[0];

                // A leftover override from before this date was ever
                // assigned through a real form would otherwise keep
                // winning over the new attendance/leave/shift record (the
                // override always takes priority when displaying status),
                // making the edit look like it silently didn't take.
                saveDayStatus(dateToOpen, '').then(function () {
                    FORM_STATUS_HANDLERS[status](dateToOpen, existingAttendanceId, existingLeaveId);
                }).catch(function () {
                    FORM_STATUS_HANDLERS[status](dateToOpen, existingAttendanceId, existingLeaveId);
                });
                return;
            }

            withSavingState(dates.map(function (d) { return saveDayStatus(d, status); }));
        });
    });

    // ===== MERGE HALF-DAYS LOGIC =====
    mergeOptions.forEach(function (option) {
        option.addEventListener('click', function () {
            var mergeType = option.dataset.merge;
            var dates = selectedDateElements.map(function (el) { return el.dataset.date; });

            closeModal(mergeModal);
            withSavingState(dates.map(function (d) { return saveDayStatus(d, mergeType + '-half'); }));
        });
    });

    // ===== REVERT MERGED HALF-DAYS LOGIC =====
    revertOptions.forEach(function (option) {
        option.addEventListener('click', function () {
            var dates = selectedDateElements.map(function (el) { return el.dataset.date; });

            closeModal(revertModal);
            withSavingState(dates.map(function (d) { return saveDayStatus(d, 'halfday'); }));
        });
    });
})();
</script>

<?php endif; ?>

</body>
</html>
