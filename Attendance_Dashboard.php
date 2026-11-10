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

$companyId = isset($_GET['company_id']) ? (int) $_GET['company_id'] : 0;
// Distinct from "no month param was ever passed" (first load, defaults to the real
// current month and shows it selected) — this tracks that the user explicitly hit
// "Clear", so the dropdown should show "Clear" itself as selected instead of
// snapping back to whatever the current month happens to be.
$monthCleared = isset($_GET['cleared']);
$month = isset($_GET['month']) ? (int) $_GET['month'] : (int) date('n');
$year = isset($_GET['year']) ? (int) $_GET['year'] : (int) date('Y');

// A specific Date filter always wins on month/year — otherwise picking a date outside
// whatever month happened to be loaded silently returns zero rows, since the server
// only ever computes one month's worth of records at a time.
$selectedDate = '';
if (!empty($_GET['date']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['date'])) {
    $selectedDate = $_GET['date'];
    $month = (int) substr($selectedDate, 5, 2);
    $year = (int) substr($selectedDate, 0, 4);
}

function adEsc($value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
}

$departments = [];
$records = [];

if ($companyId > 0) {
    try {
        $company = Company::find($companyId);
        $timezone = $company->timezone ?? 'UTC';

        $departments = DB::table('teams')->where('company_id', $companyId)->orderBy('team_name')->pluck('team_name', 'id')->toArray();

        $employees = User::withRole('employee')
            ->join('employee_details', 'employee_details.user_id', '=', 'users.id')
            ->where('users.company_id', $companyId)
            ->select('users.id', 'users.name', 'employee_details.department_id')
            ->get();

        $employeeIds = $employees->pluck('id');
        $daysInMonth = Carbon::createFromDate($year, $month, 1)->daysInMonth;
        $monthStart = Carbon::createFromDate($year, $month, 1, $timezone)->startOfDay();
        $monthEnd = Carbon::createFromDate($year, $month, $daysInMonth, $timezone)->endOfDay();
        $today = Carbon::now($timezone)->startOfDay();

        // Attendance for the month, grouped by user + local calendar day. Worked seconds are
        // summed across every session that day (mirrors AttendanceController::summaryData()),
        // and the shift captured is whichever session's — good enough since almost every day
        // only has one shift in play.
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

        // Day Off, from the shift roster
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

        // Approved leave
        $leaveByUserDay = [];
        Leave::whereIn('user_id', $employeeIds)
            ->whereBetween('leave_date', [$monthStart->toDateString(), $monthEnd->toDateString()])
            ->where('status', 'approved')
            ->get()
            ->each(function ($leave) use (&$leaveByUserDay) {
                $leaveByUserDay[$leave->user_id][(int) Carbon::parse($leave->leave_date)->day] = true;
            });

        // Company-wide holidays (not filtered by department/designation — good enough for a
        // dashboard summary; the main Attendance table is the source of truth for the exact
        // per-employee holiday match).
        $holidaysByDay = [];
        Holiday::where('company_id', $companyId)
            ->whereBetween('date', [$monthStart->toDateString(), $monthEnd->toDateString()])
            ->get()
            ->each(function ($holiday) use (&$holidaysByDay) {
                $holidaysByDay[(int) Carbon::parse($holiday->date)->day] = true;
            });

        foreach ($employees as $employee) {
            $branchName = $departments[$employee->department_id] ?? '—';

            for ($day = 1; $day <= $daysInMonth; $day++) {
                $date = Carbon::createFromDate($year, $month, $day, $timezone)->startOfDay();

                if ($date->greaterThan($today)) {
                    continue;
                }

                if (!empty($dayOffByUserDay[$employee->id][$day])) {
                    $status = 'Day Off';
                } elseif (!empty($holidaysByDay[$day])) {
                    $status = 'Holiday';
                } elseif (!empty($leaveByUserDay[$employee->id][$day])) {
                    $status = 'On Leave';
                } elseif (isset($workedSecondsByUserDay[$employee->id][$day])) {
                    $workedSeconds = $workedSecondsByUserDay[$employee->id][$day];
                    $shift = $shiftByUserDay[$employee->id][$day] ?? null;
                    $fullDaySeconds = $halfDaySeconds = null;

                    if ($shift && !is_null($shift->min_working_hours_full_day) && !is_null($shift->min_working_hours_half_day)) {
                        // Configured shift policy (Attendance Settings > Shift > Add New Shift) —
                        // same rule as AttendanceController::summaryData(): grace period is
                        // subtracted from the required hours to get the real cutoff.
                        $fullDaySeconds = ($shift->min_working_hours_full_day * 3600) - (($shift->min_working_hours_full_day_grace ?? 0) * 60);
                        $halfDaySeconds = ($shift->min_working_hours_half_day * 3600) - (($shift->min_working_hours_half_day_grace ?? 0) * 60);
                    } elseif ($shift) {
                        // No policy configured on this shift — fall back to a percentage of its
                        // total duration, same fallback used on the main Attendance table.
                        $shiftStart = Carbon::parse($shift->office_start_time);
                        $shiftEnd = Carbon::parse($shift->office_end_time);
                        $shiftSeconds = $shiftEnd->lessThanOrEqualTo($shiftStart)
                            ? abs($shiftEnd->copy()->addDay()->diffInSeconds($shiftStart))
                            : abs($shiftEnd->diffInSeconds($shiftStart));
                        $fullDaySeconds = $shiftSeconds * 0.5;
                        $halfDaySeconds = $shiftSeconds * 0.25;
                    }

                    if ($fullDaySeconds !== null && $workedSeconds >= $fullDaySeconds) {
                        $status = 'Present';
                    } elseif ($halfDaySeconds !== null && $workedSeconds >= $halfDaySeconds) {
                        $status = 'Half Day';
                    } else {
                        $status = 'Absent';
                    }
                } else {
                    $status = 'Absent';
                }

                $records[] = [
                    'userId' => $employee->id,
                    'name' => $employee->name,
                    'branch' => $branchName,
                    'status' => $status,
                    'date' => sprintf('%04d-%02d-%02d', $year, $month, $day),
                ];
            }
        }
    } catch (\Throwable $e) {
        $records = [];
        $departments = [];
        $employees = collect();
    }
}

$employeeOptions = isset($employees) ? $employees->sortBy('name')->values() : collect();

$recordsJson = json_encode($records);
$departmentsJson = json_encode(array_values($departments));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Attendance Dashboard</title>

    <!-- Font Awesome for Icons -->
    <link
    rel="stylesheet"
    href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css"
    />

    <style>
        /* ---------- RESET & BASE ---------- */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        body {
            background: #f0f4f8;
            min-height: 100vh;
            padding: 20px;
        }

        /* ---------- DASHBOARD WRAPPER ---------- */
        .dashboard {
            width: 100%;
            min-height: calc(100vh - 40px);
            background: #ffffff;
            border-radius: 28px;
            box-shadow: 0 20px 60px rgba(0, 20, 40, 0.12);
            padding: 32px 36px 42px;
            transition: all 0.2s;
        }

        /* ---------- HEADER ---------- */
        .dashboard-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 16px 24px;
            margin-bottom: 30px;
        }

        .dashboard-header h1 {
            font-size: 26px;
            color: #182848;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .dashboard-header h1 i {
            color: #4e73df;
        }

        .header-date {
            display: flex;
            align-items: center;
            gap: 8px;
            color: #6c7a94;
            font-size: 14px;
            background: #f0f4f8;
            padding: 8px 16px;
            border-radius: 12px;
        }

        /* ---------- TABS ---------- */
        .dashboard-tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 24px;
        }

        .dashboard-tab {
            border: none;
            background: #f0f4f8;
            color: #6c7a94;
            font-weight: 600;
            font-size: 14px;
            padding: 10px 20px;
            border-radius: 12px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: background 0.15s, color 0.15s;
        }

        .dashboard-tab.active {
            background: #4e73df;
            color: #fff;
        }

        .tab-pane {
            display: none;
        }

        .tab-pane.active {
            display: block;
        }

        .particular-placeholder {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 18px 20px;
            border-radius: 14px;
            background: #eef1f6;
            color: #6c7a94;
            font-size: 14px;
        }

        /* ---------- FILTERS ---------- */
        .filters {
            display: flex;
            flex-wrap: wrap;
            gap: 18px 24px;
            margin-bottom: 28px;
            padding-bottom: 24px;
            border-bottom: 1px solid #eef1f6;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .filter-group label {
            font-size: 12px;
            font-weight: 600;
            color: #6c7a94;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .filter-group select,
        .filter-group input {
            border: 1px solid #e0e5ec;
            border-radius: 10px;
            padding: 9px 12px;
            font-size: 14px;
            color: #182848;
            min-width: 170px;
            background: #fff;
        }

        /* ---------- STATS GRID ---------- */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 20px;
            margin-bottom: 32px;
        }

        .stat-card {
            display: flex;
            align-items: center;
            gap: 16px;
            padding: 20px;
            border-radius: 20px;
            background: #f8f9fc;
            border: 1px solid #eef1f6;
        }

        .stat-icon {
            width: 52px;
            height: 52px;
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            color: #fff;
        }

        .stat-icon.present { background: #1cc88a; }
        .stat-icon.absent { background: #e74a3b; }
        .stat-icon.halfday { background: #f6c23e; }
        .stat-icon.total { background: #4e73df; }
        .stat-icon.leave { background: #a239b8; }
        .stat-icon.working { background: #f7941d; }
        .stat-icon.holiday { background: #6f42c1; }
        .stat-icon.dayoff { background: #858796; }

        /* Particular Employee Dashboard has 8 tiles instead of 4 — narrower minmax and
           tighter padding/icon size so more fit per row instead of wrapping to one per line. */
        .particular-section .stats-grid {
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 14px;
            margin-bottom: 0;
        }

        .particular-section .stat-card {
            padding: 14px;
            gap: 12px;
            border-radius: 16px;
        }

        .particular-section .stat-icon {
            width: 40px;
            height: 40px;
            border-radius: 12px;
            font-size: 16px;
        }

        .particular-section .stat-number {
            font-size: 20px;
        }

        .particular-section .stat-label {
            font-size: 12px;
        }

        .stat-number {
            font-size: 26px;
            font-weight: 700;
            color: #182848;
            line-height: 1.1;
        }

        .stat-label {
            font-size: 13px;
            color: #6c7a94;
            margin-top: 4px;
        }

        /* ---------- DETAILS SECTION ---------- */
        .details-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 14px;
            margin-bottom: 16px;
        }

        .details-header-left {
            display: flex;
            align-items: center;
            gap: 20px;
            flex-wrap: wrap;
        }

        .details-header-left h3 {
            font-size: 17px;
            color: #182848;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .search-wrapper {
            position: relative;
        }

        .search-wrapper i {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: #a5adc0;
            font-size: 13px;
        }

        .search-wrapper input {
            padding: 9px 12px 9px 32px;
            border: 1px solid #e0e5ec;
            border-radius: 10px;
            font-size: 14px;
            min-width: 220px;
        }

        .details-header-right {
            display: flex;
            align-items: center;
            gap: 14px;
            font-size: 13px;
            color: #6c7a94;
        }

        .status-filter-select {
            border: 1px solid #e0e5ec;
            border-radius: 10px;
            padding: 8px 12px;
            font-size: 13px;
            color: #182848;
            background: #fff;
        }

        .table-wrap {
            overflow-x: auto;
            border: 1px solid #eef1f6;
            border-radius: 16px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 14px;
        }

        thead th {
            text-align: left;
            padding: 12px 16px;
            background: #f8f9fc;
            color: #6c7a94;
            font-weight: 600;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.03em;
            border-bottom: 1px solid #eef1f6;
        }

        tbody td {
            padding: 12px 16px;
            border-bottom: 1px solid #f3f5f9;
            color: #182848;
        }

        tbody tr:last-child td {
            border-bottom: none;
        }

        .emp-cell {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .employee-avatar {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            background: #4e73df;
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            font-weight: 700;
            flex-shrink: 0;
        }

        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        .status-badge.present { background: #e3f9f0; color: #1cc88a; }
        .status-badge.absent { background: #fdecea; color: #e74a3b; }
        .status-badge.halfday { background: #fef6e0; color: #d4a017; }
        .status-badge.dayoff { background: #eef1f6; color: #6c7a94; }
        .status-badge.holiday { background: #eef0ff; color: #4e73df; }
        .status-badge.onleave { background: #fbeefc; color: #a239b8; }

        .no-results td {
            text-align: center;
            padding: 40px 16px;
            color: #a5adc0;
        }

        .no-results i {
            font-size: 24px;
            display: block;
            margin-bottom: 10px;
        }
    </style>
    <!-- Epic theme over the page's own styles: the app's font and the colours chosen in
         Settings > Theme Settings (gold in Black & Gold). Shown as the Attendance > Dashboard tab. -->
    <link rel="stylesheet" href="<?php echo asset('css/epic/fonts.css'); ?>">
    <?php if (function_exists('view')) { echo view('sections.epic-colors')->render(); } ?>
    <style>
        :root { --epic-blue: #b8892e; --epic-green: #cd6e4f; --epic-dark: #111111; --epic-line: #e8e9e9; --epic-muted: #878a99; --epic-text: #212529; --epic-page: #f3f3f9; }
        * { font-family: 'Plus Jakarta Sans', 'Segoe UI', Arial, sans-serif; }
        .fas, .far, .fa { font-family: 'Font Awesome 6 Free' !important; }
        body { background: var(--epic-page); padding: 0; }
        .dashboard { min-height: 0; border-radius: 10px; box-shadow: 0 1px 2px rgba(56, 65, 74, .15); padding: 0 0 24px; }
        /* No overflow clipping: an open dropdown must reach past the card */
        .dashboard-header { border-radius: 10px 10px 0 0; }
        .dashboard.on-report .filters { display: none; }
        .tab-pane iframe { border-radius: 8px; }

        /* Header: a dark band with a gold glow, like the app's dashboard cards */
        .dashboard-header { margin: 0; padding: 18px 24px; color: #fff;
            background: radial-gradient(420px 160px at 0% 0%, color-mix(in srgb, var(--epic-blue) 40%, transparent), transparent 70%), var(--epic-dark); }
        .dashboard-header h1 { font-size: 18px; font-weight: 700; color: #fff; }
        .dashboard-header h1 i { color: var(--epic-blue); }
        .header-date { background: rgba(255, 255, 255, .08); color: rgba(255, 255, 255, .85); border: 1px solid rgba(255, 255, 255, .14); border-radius: 999px; font-size: 13px; }
        .header-date i { color: var(--epic-blue); }

        /* Tabs: underlined, as the app's page tabs */
        .dashboard-tabs { gap: 4px; margin: 0 0 20px; padding: 0 24px; border-bottom: 1px solid var(--epic-line); overflow-x: auto; }
        .dashboard-tab { background: none; border-radius: 0; color: var(--epic-muted); font-weight: 500; padding: 14px 14px 12px; border-bottom: 2px solid transparent; white-space: nowrap; }
        .dashboard-tab:hover { color: var(--epic-text); }
        .dashboard-tab.active { background: none; color: var(--epic-blue); border-bottom-color: var(--epic-blue); font-weight: 600; }
        .dashboard-tab i { opacity: .8; }

        .tab-pane, .filters, .particular-placeholder { margin-left: 24px; margin-right: 24px; }
        .tab-pane .filters, .tab-pane .tab-pane { margin-left: 0; margin-right: 0; }

        /* Filters: one row */
        .filters { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 12px 14px; padding-bottom: 18px; margin-bottom: 20px; border-bottom: 1px solid var(--epic-line); }
        .filter-group label { color: var(--epic-muted); font-size: 11px; letter-spacing: .06em; text-transform: uppercase; }
        .filter-group label i { color: var(--epic-blue); }
        .filter-group select, .filter-group input, .status-filter-select, .search-wrapper input { border: 1px solid var(--epic-line); border-radius: 6px; color: var(--epic-text); height: 38px; padding: 6px 10px; }
        .filter-group select:focus, .filter-group input:focus, .status-filter-select:focus, .search-wrapper input:focus { outline: none; border-color: var(--epic-blue); box-shadow: 0 0 0 3px color-mix(in srgb, var(--epic-blue) 18%, transparent); }

        /* Stat cards: white, a thin accent bar, the figure large */
        .stat-card { background: #fff; border: 1px solid var(--epic-line); border-radius: 10px; position: relative; overflow: hidden; transition: box-shadow .15s, transform .15s; }
        .stat-card:hover { box-shadow: 0 6px 18px rgba(16, 24, 40, .08); transform: translateY(-1px); }
        .stat-card::before { content: ''; position: absolute; left: 0; top: 0; bottom: 0; width: 3px; background: var(--card-tone, var(--epic-blue)); }
        .stat-icon { border-radius: 10px; background: color-mix(in srgb, var(--card-tone, var(--epic-blue)) 14%, #fff) !important; color: var(--card-tone, var(--epic-blue)) !important; }
        .stat-card:has(.stat-icon.present) { --card-tone: #12b76a; }
        .stat-card:has(.stat-icon.absent) { --card-tone: #f04438; }
        .stat-card:has(.stat-icon.halfday) { --card-tone: #f79009; }
        .stat-card:has(.stat-icon.dayoff) { --card-tone: #667085; }
        .stat-card:has(.stat-icon.holiday) { --card-tone: var(--epic-blue); }
        .stat-card:has(.stat-icon.total) { --card-tone: var(--epic-dark); }
        .stat-card:has(.stat-icon.leave) { --card-tone: #2e90fa; }
        .stat-card:has(.stat-icon.working) { --card-tone: var(--epic-green); }
        .stat-number { color: var(--epic-text); font-weight: 700; }
        .stat-label { color: var(--epic-muted); }

        /* Employee details table */
        .details-header-left h3 { color: var(--epic-text); font-size: 16px; }
        .details-header-left h3 i { color: var(--epic-blue); }
        .table-wrap { border: 1px solid var(--epic-line); border-radius: 10px; }
        .table-wrap th { background: #fafafa; color: var(--epic-muted); font-size: 11px; letter-spacing: .06em; }
        .table-wrap td { color: var(--epic-text); }
        .table-wrap tbody tr:hover td { background: color-mix(in srgb, var(--epic-blue) 5%, #fff); }
        .employee-avatar { background: color-mix(in srgb, var(--epic-blue) 16%, #fff) !important; color: var(--epic-blue) !important; font-weight: 700; }
        .status-badge.holiday { background: color-mix(in srgb, var(--epic-blue) 14%, #fff); color: color-mix(in srgb, var(--epic-blue) 80%, #000); }
        .status-badge.onleave { background: #eff8ff; color: #175cd3; }

        /* Compact: a slimmer header band, every card the same smaller widget */
        .dashboard-header { padding: 10px 18px !important; }
        .dashboard-header h1 { font-size: 16px !important; gap: 10px !important; }
        .header-date { padding: 5px 12px !important; font-size: 12px !important; }
        .dashboard-tab { padding: 11px 12px 9px !important; font-size: 13px !important; }
        .dashboard-tabs { margin-bottom: 14px !important; }
        .filters { padding-bottom: 14px !important; margin-bottom: 14px !important; }
        .stats-grid, .particular-section .stats-grid { gap: 12px !important; margin-bottom: 16px !important; grid-template-columns: repeat(auto-fit, minmax(170px, 1fr)) !important; }
        .stat-card, .particular-section .stat-card { padding: 12px 14px !important; min-height: 0 !important; border-radius: 6px !important; }
        .stat-number, .particular-section .stat-number { font-size: 20px !important; }
        .stat-label, .particular-section .stat-label { font-size: 10.5px !important; margin-bottom: 4px !important; }
        .stat-icon, .particular-section .stat-icon { width: 34px !important; height: 34px !important; min-width: 34px !important; font-size: 14px !important; border-radius: 6px !important; }
        .chart-card { padding: 12px 14px !important; }
        .chart-head { font-size: 13px !important; margin-bottom: 6px !important; }
        .chart-box { height: 220px !important; }
        .chart-box-dept { height: 260px !important; }
        .details-header { margin-bottom: 10px !important; }
        /* Room between the header band, the tabs and what is under them */
        .dashboard-tabs { padding: 8px 24px 0 !important; gap: 6px !important; margin-bottom: 18px !important; }
        .dashboard-tab { padding: 12px 16px 11px !important; }
        .filters { padding-top: 2px; }

        /* Reports: three report cards to choose from, the open one in gold */
        .report-picker { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 12px; margin: 0 0 16px; }
        .report-option { display: flex; align-items: center; gap: 12px; text-align: left; padding: 12px 14px; cursor: pointer;
            background: #fff; border: 1px solid var(--epic-line); border-radius: 8px; transition: border-color .15s, box-shadow .15s; font: inherit; color: var(--epic-text); }
        .report-option:hover { border-color: color-mix(in srgb, var(--epic-blue) 50%, #fff); }
        .report-option.active { border-color: var(--epic-blue); box-shadow: 0 0 0 3px color-mix(in srgb, var(--epic-blue) 15%, transparent); background: color-mix(in srgb, var(--epic-blue) 4%, #fff); }
        .report-option b { display: block; font-size: 13.5px; font-weight: 600; }
        .report-option small { display: block; font-size: 11.5px; color: var(--epic-muted); margin-top: 2px; line-height: 1.35; }
        .report-option__icon { width: 36px; height: 36px; min-width: 36px; border-radius: 8px; display: inline-flex; align-items: center; justify-content: center;
            background: color-mix(in srgb, var(--epic-blue) 12%, #fff); color: var(--epic-blue); font-size: 15px; }
        .report-option.active .report-option__icon { background: var(--epic-blue); color: #fff; }
        .report-loading { display: none; align-items: center; justify-content: center; gap: 10px; padding: 40px 0; color: var(--epic-muted); font-size: 13px; }
        .report-loading i { color: var(--epic-blue); }
        #tab-reports > .tab-pane.active { display: block; }
        @media (max-width: 767px) { .report-picker { grid-template-columns: 1fr; } }

        .search-wrapper { position: relative; }
        .search-wrapper input { padding-left: 34px !important; }

        /* Cards as the app's widgets: the label on top, the figure under it, the icon to the right */
        .stats-grid { gap: 16px; }
        .stat-card { display: flex; flex-direction: row-reverse; justify-content: space-between; align-items: flex-start; gap: 12px;
            padding: 18px 20px; border: 0; border-radius: 6px; box-shadow: 0 1px 2px rgba(56, 65, 74, .15); background: #fff; }
        .stat-card::before { display: none; }
        .stat-card:hover { box-shadow: 0 6px 20px rgba(56, 65, 74, .14); }
        .stat-info { display: flex; flex-direction: column-reverse; min-width: 0; }
        .stat-label { font-size: 11.5px; font-weight: 600; letter-spacing: .06em; text-transform: uppercase; color: var(--epic-muted); margin: 0 0 8px; white-space: nowrap; }
        .stat-number { font-size: 26px; line-height: 1.1; font-weight: 600; color: var(--epic-text); }
        .stat-icon { width: 42px; height: 42px; min-width: 42px; border-radius: 8px; font-size: 17px; }
        .particular-section .stat-card { padding: 16px 18px; }

        /* Charts */
        .charts-grid { display: grid; grid-template-columns: 2fr 1fr; gap: 16px; margin: 4px 0 26px; }
        .chart-card { background: #fff; border-radius: 6px; box-shadow: 0 1px 2px rgba(56, 65, 74, .15); padding: 16px 18px; min-width: 0; }
        .chart-full { grid-column: 1 / -1; }
        .chart-head { font-size: 14px; font-weight: 600; color: var(--epic-text); margin-bottom: 10px; display: flex; align-items: center; gap: 8px; }
        .chart-head i { color: var(--epic-blue); }
        .chart-note { margin-left: auto; font-size: 11.5px; font-weight: 400; color: var(--epic-muted); }
        .chart-box { position: relative; height: 260px; }
        .chart-box-dept { height: 300px; }
        @media (max-width: 991px) { .charts-grid { grid-template-columns: 1fr; } }

        /* Pager under a table */
        .pager { display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 10px; padding: 12px 2px 0; font-size: 13px; color: var(--epic-muted); }
        .pager-btns { display: flex; gap: 4px; align-items: center; }
        .pager button { min-width: 34px; height: 34px; padding: 0 10px; border: 1px solid var(--epic-line); background: #fff; color: var(--epic-text); border-radius: 6px; cursor: pointer; font-size: 13px; }
        .pager button:hover:not(:disabled) { border-color: var(--epic-blue); color: var(--epic-blue); }
        .pager button.active { background: var(--epic-blue); border-color: var(--epic-blue); color: #fff; font-weight: 600; }
        .pager button:disabled { opacity: .4; cursor: default; }
        .pager-gap { padding: 0 4px; }

        /* Searchable dropdowns (Choices) in the theme */
        .filter-group .choices { margin: 0; font-size: 14px; }
        .filter-group .choices__inner { min-height: 38px; padding: 4px 8px !important; border: 1px solid var(--epic-line); border-radius: 6px; background: #fff; }
        .filter-group .choices.is-focused .choices__inner, .filter-group .choices.is-open .choices__inner { border-color: var(--epic-blue); box-shadow: 0 0 0 3px color-mix(in srgb, var(--epic-blue) 18%, transparent); }
        .filter-group .choices__list--single { padding: 4px 16px 4px 2px; }
        .filter-group .choices[data-type*=select-one]::after { border-color: var(--epic-muted) transparent transparent; right: 12px; }
        .filter-group .choices__list--dropdown { border: 1px solid var(--epic-line); border-radius: 6px; box-shadow: 0 8px 24px rgba(16, 24, 40, .12); z-index: 20; }
        .filter-group .choices__list--dropdown .choices__item--selectable.is-highlighted { background: color-mix(in srgb, var(--epic-blue) 12%, #fff); }
        .filter-group .choices__input { font-size: 13px; border-bottom: 1px solid var(--epic-line); }
        .search-wrapper i { left: 12px; color: var(--epic-muted); }
        ::-webkit-scrollbar { width: 12px; height: 12px; background: #eceef1; }
        ::-webkit-scrollbar-thumb { background-color: #9aa1ab; border: 2px solid #eceef1; border-radius: 8px; }
        ::-webkit-scrollbar-thumb:hover { background-color: var(--epic-blue); }
    </style>
</head>
<body>

    <div class="dashboard">

        <!-- ====== HEADER ====== -->
        <header class="dashboard-header">
            <h1>
                <i class="fas fa-clipboard-check"></i>
                Attendance Dashboard
            </h1>
            <div class="header-date">
                <i class="fas fa-calendar-alt"></i>
                <span id="currentDisplayDate">Loading…</span>
            </div>
        </header>

        <!-- ====== TABS ====== -->
        <div class="dashboard-tabs">
            <button type="button" class="dashboard-tab active" id="tab-btn-monthly" data-tab="monthly">
                <i class="fas fa-calendar-alt"></i> Monthly Dashboard
            </button>
            <button type="button" class="dashboard-tab" id="tab-btn-particular" data-tab="particular">
                <i class="fas fa-user-circle"></i> Particular Employee Dashboard
            </button>
            <button type="button" class="dashboard-tab" id="tab-btn-reports" data-tab="reports">
                <i class="fas fa-file-alt"></i> Reports
            </button>
        </div>

        <!-- ====== FILTERS (shared by both tabs) ====== -->
        <section class="filters">

            <!-- Department Dropdown -->
            <div class="filter-group">
                <label for="branchSelect">
                    <i class="fas fa-building"></i> Department
                </label>
                <select id="branchSelect">
                    <option value="all">All Departments</option>
                    <?php foreach ($departments as $name): ?>
                        <option value="<?php echo adEsc($name); ?>"><?php echo adEsc($name); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Employee Dropdown -->
            <div class="filter-group">
                <label for="employeeSelect">
                    <i class="fas fa-user"></i> Employee
                </label>
                <select id="employeeSelect">
                    <option value="all">All Employees</option>
                    <?php foreach ($employeeOptions as $emp): ?>
                        <option value="<?php echo (int) $emp->id; ?>"><?php echo adEsc($emp->name); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Date Picker -->
            <div class="filter-group">
                <label for="datePicker">
                    <i class="fas fa-calendar-day"></i> Date
                </label>
                <input type="date" id="datePicker" value="<?php echo adEsc($selectedDate); ?>" />
            </div>

            <!-- Month Dropdown -->
            <div class="filter-group">
                <label for="monthSelect">
                    <i class="fas fa-calendar-alt"></i> Month
                </label>
                <select id="monthSelect">
                    <option value="" <?php echo $monthCleared ? 'selected' : ''; ?>>Clear</option>
                    <?php
                    $monthNames = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
                    foreach ($monthNames as $index => $label):
                    ?>
                        <option value="<?php echo $index; ?>" <?php echo (!$monthCleared && $index + 1 === $month) ? 'selected' : ''; ?>><?php echo $label; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Year Dropdown -->
            <div class="filter-group">
                <label for="yearSelect">
                    <i class="fas fa-calendar-year"></i> Year
                </label>
                <select id="yearSelect">
                    <?php for ($y = $year - 5; $y <= $year + 1; $y++): ?>
                        <option value="<?php echo $y; ?>" <?php echo ($y === $year) ? 'selected' : ''; ?>><?php echo $y; ?></option>
                    <?php endfor; ?>
                </select>
            </div>

        </section>

        <!-- ====== MONTHLY DASHBOARD TAB ====== -->
        <div class="tab-pane active" id="tab-monthly">

        <!-- ====== STATS CARDS ====== -->
        <section class="stats-grid" id="statsGrid">
            <div class="stat-card">
                <div class="stat-icon present"><i class="fas fa-user-check"></i></div>
                <div class="stat-info">
                    <div class="stat-number" id="presentCount">0</div>
                    <div class="stat-label">Present</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon absent"><i class="fas fa-user-times"></i></div>
                <div class="stat-info">
                    <div class="stat-number" id="absentCount">0</div>
                    <div class="stat-label">Absent</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon halfday"><i class="fas fa-user-clock"></i></div>
                <div class="stat-info">
                    <div class="stat-number" id="halfdayCount">0</div>
                    <div class="stat-label">Half Day</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon dayoff"><i class="fas fa-calendar-week"></i></div>
                <div class="stat-info">
                    <div class="stat-number" id="dayoffCount">0</div>
                    <div class="stat-label">Day Off</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon holiday"><i class="fas fa-star"></i></div>
                <div class="stat-info">
                    <div class="stat-number" id="holidayCount">0</div>
                    <div class="stat-label">Holidays</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon total"><i class="fas fa-users"></i></div>
                <div class="stat-info">
                    <div class="stat-number" id="totalCount">0</div>
                    <div class="stat-label">Total Employees</div>
                </div>
            </div>
        </section>

        <!-- ====== CHARTS (follow the filters) ====== -->
        <section class="charts-grid">
            <div class="chart-card chart-wide">
                <div class="chart-head"><i class="fas fa-chart-bar"></i> Daily attendance</div>
                <div class="chart-box"><canvas id="chartDaily"></canvas></div>
            </div>
            <div class="chart-card">
                <div class="chart-head"><i class="fas fa-chart-pie"></i> Status split</div>
                <div class="chart-box"><canvas id="chartStatus"></canvas></div>
            </div>
            <div class="chart-card chart-full">
                <div class="chart-head"><i class="fas fa-building"></i> Attendance rate by department <span class="chart-note">present + half days, of the working days</span></div>
                <div class="chart-box chart-box-dept"><canvas id="chartDept"></canvas></div>
            </div>
        </section>

        <!-- ====== DETAIL SECTION ====== -->
        <section class="details-section">
            <div class="details-header">

                <!-- LEFT: heading + search -->
                <div class="details-header-left">
                    <h3><i class="fas fa-list-ul"></i> Employee Details</h3>

                    <!-- SEARCH BAR -->
                    <div class="search-wrapper">
                        <i class="fas fa-search"></i>
                        <input
                        type="text"
                        id="searchInput"
                        placeholder="Search employee..."
                        autocomplete="off"
                        />
                    </div>
                </div>

                <!-- RIGHT: status filter + record count -->
                <div class="details-header-right">
                    <select id="statusSelect" class="status-filter-select">
                        <option value="all">All Status</option>
                        <option value="Present">Present</option>
                        <option value="Absent">Absent</option>
                        <option value="Half Day">Half Day</option>
                        <option value="Day Off">Day Off</option>
                        <option value="Holiday">Holiday</option>
                        <option value="On Leave">On Leave</option>
                    </select>
                    <span id="recordCount">0 records</span>
                </div>

            </div>

            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Employee</th>
                            <th>Department</th>
                            <th>Status</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody id="employeeTableBody">
                        <!-- rows injected by JS -->
                    </tbody>
                </table>
            </div>
        </section>

        </div>
        <!-- ====== END MONTHLY DASHBOARD TAB ====== -->

        <!-- ====== PARTICULAR EMPLOYEE DASHBOARD TAB ====== -->
        <div class="tab-pane" id="tab-particular">
        <section class="details-section particular-section">
            <div class="details-header">
                <div class="details-header-left">
                    <h3><i class="fas fa-user-circle"></i> Particular Employee Dashboard</h3>
                </div>
            </div>

            <p id="particularNoSelection" class="particular-placeholder" style="display:none;">
                <i class="fas fa-info-circle"></i> Select a specific employee from the Employee filter above to view their dashboard.
            </p>

            <section class="stats-grid" id="particularStatsGrid">
                <div class="stat-card">
                    <div class="stat-icon present"><i class="fas fa-user-check"></i></div>
                    <div class="stat-info">
                        <div class="stat-number" id="particularPresentCount">0</div>
                        <div class="stat-label">Present</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon absent"><i class="fas fa-user-times"></i></div>
                    <div class="stat-info">
                        <div class="stat-number" id="particularAbsentCount">0</div>
                        <div class="stat-label">Absent</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon leave"><i class="fas fa-plane-departure"></i></div>
                    <div class="stat-info">
                        <div class="stat-number" id="particularLeaveCount">0</div>
                        <div class="stat-label">Leaves</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon halfday"><i class="fas fa-user-clock"></i></div>
                    <div class="stat-info">
                        <div class="stat-number" id="particularHalfdayCount">0</div>
                        <div class="stat-label">Half Days</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon working"><i class="fas fa-briefcase"></i></div>
                    <div class="stat-info">
                        <div class="stat-number" id="particularWorkingCount">0</div>
                        <div class="stat-label">Working Days</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon holiday"><i class="fas fa-star"></i></div>
                    <div class="stat-info">
                        <div class="stat-number" id="particularHolidayCount">0</div>
                        <div class="stat-label">Holidays</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon dayoff"><i class="fas fa-calendar-week"></i></div>
                    <div class="stat-info">
                        <div class="stat-number" id="particularDayOffCount">0</div>
                        <div class="stat-label">Day Off</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon total"><i class="fas fa-calendar-alt"></i></div>
                    <div class="stat-info">
                        <div class="stat-number" id="particularTotalCount">0</div>
                        <div class="stat-label">Total Days</div>
                    </div>
                </div>
            </section>
        </section>

        <section class="details-section" id="particularDetailsSection">
            <div class="details-header">
                <div class="details-header-left">
                    <h3><i class="fas fa-list-ul"></i> Employee Details</h3>

                    <div class="search-wrapper">
                        <i class="fas fa-search"></i>
                        <input
                        type="text"
                        id="particularSearchInput"
                        placeholder="Search status or date..."
                        autocomplete="off"
                        />
                    </div>
                </div>

                <div class="details-header-right">
                    <span id="particularRecordCount">0 records</span>
                </div>
            </div>

            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Employee</th>
                            <th>Department</th>
                            <th>Status</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody id="particularEmployeeTableBody">
                        <!-- rows injected by JS -->
                    </tbody>
                </table>
            </div>
        </section>
        </div>
        <!-- ====== END PARTICULAR EMPLOYEE DASHBOARD TAB ====== -->

        <!-- ====== REPORTS TAB: pick a report, it opens under the choice ====== -->
        <div class="tab-pane" id="tab-reports">
        <div class="report-picker">
            <button type="button" class="report-option active" data-report="total-monthly">
                <span class="report-option__icon"><i class="fas fa-list-ol"></i></span>
                <span><b>Monthly Summary</b><small>Totals per employee: present, absent, leave, LOP, hours</small></span>
            </button>
            <button type="button" class="report-option" data-report="monthly-sheet">
                <span class="report-option__icon"><i class="fas fa-table"></i></span>
                <span><b>Day-wise Sheet</b><small>Every employee, every day of the month, P / A / ½</small></span>
            </button>
            <button type="button" class="report-option" data-report="annual">
                <span class="report-option__icon"><i class="fas fa-calendar-week"></i></span>
                <span><b>Annual Summary</b><small>Month by month for the year, with the attendance %</small></span>
            </button>
        </div>

        <!-- Monthly Summary -->
        <div class="tab-pane" id="tab-total-monthly">
            <iframe id="iframe-total-monthly" data-src="Total_Monthly_Attendance.php?company_id=<?php echo (int) $companyId; ?>&month=<?php echo (int) $month; ?>&year=<?php echo (int) $year; ?>"
                style="width:100%; min-height: 120px; border: 0;" title="Total Monthly Attendance"></iframe>
        </div>

        <!-- Day-wise Sheet -->
        <div class="tab-pane" id="tab-monthly-sheet">
            <iframe id="iframe-monthly-sheet" data-src="Monthly_attendance.php?company_id=<?php echo (int) $companyId; ?>&month=<?php echo (int) $month; ?>&year=<?php echo (int) $year; ?>"
                style="width:100%; min-height: 120px; border: 0;" title="Monthly Attendance"></iframe>
        </div>

        <!-- Annual Summary -->
        <div class="tab-pane" id="tab-annual">
            <iframe id="iframe-annual" data-src="annual_attendance.php?company_id=<?php echo (int) $companyId; ?>&year=<?php echo (int) $year; ?>"
                style="width:100%; min-height: 120px; border: 0;" title="Annual Attendance"></iframe>
        </div>
        <p class="report-loading" id="reportLoading"><i class="fas fa-circle-notch fa-spin"></i> Preparing the report… a month for every employee takes a few seconds</p>
        </div>

    </div>

    <link rel="stylesheet" href="<?php echo asset('vendor/css/choices.css'); ?>">
    <script src="<?php echo asset('vendor/jquery/Chart.min.js'); ?>"></script>
    <script src="<?php echo asset('vendor/jquery/choices.js'); ?>"></script>
    <script>
        // ============================================================
        //  REAL DATA — computed server-side from the attendances,
        //  leaves, holidays and shift roster tables for the selected month.
        // ============================================================
        const ALL_RECORDS = <?php echo $recordsJson; ?>;

        // ============================================================
        //  DASHBOARD STATE
        // ============================================================
        let allRecords = ALL_RECORDS;
        let filteredRecords = [];
        let searchQuery = '';
        let particularSearchQuery = '';

        // DOM refs
        const branchSelect = document.getElementById('branchSelect');
        const employeeSelect = document.getElementById('employeeSelect');
        const datePicker = document.getElementById('datePicker');
        const monthSelect = document.getElementById('monthSelect');
        const yearSelect = document.getElementById('yearSelect');
        const statusSelect = document.getElementById('statusSelect');
        const searchInput = document.getElementById('searchInput');
        const presentCountEl = document.getElementById('presentCount');
        const absentCountEl = document.getElementById('absentCount');
        const halfdayCountEl = document.getElementById('halfdayCount');
        const dayoffCountEl = document.getElementById('dayoffCount');
        const holidayCountEl = document.getElementById('holidayCount');
        const totalCountEl = document.getElementById('totalCount');
        const tableBody = document.getElementById('employeeTableBody');
        const recordCountEl = document.getElementById('recordCount');
        const currentDisplayDate = document.getElementById('currentDisplayDate');

        // Particular Employee Dashboard refs — reuses the shared Employee filter above,
        // it no longer has its own separate selector.
        const particularNoSelection = document.getElementById('particularNoSelection');
        const particularStatsGrid = document.getElementById('particularStatsGrid');
        const particularPresentCountEl = document.getElementById('particularPresentCount');
        const particularAbsentCountEl = document.getElementById('particularAbsentCount');
        const particularLeaveCountEl = document.getElementById('particularLeaveCount');
        const particularHalfdayCountEl = document.getElementById('particularHalfdayCount');
        const particularWorkingCountEl = document.getElementById('particularWorkingCount');
        const particularHolidayCountEl = document.getElementById('particularHolidayCount');
        const particularDayOffCountEl = document.getElementById('particularDayOffCount');
        const particularTotalCountEl = document.getElementById('particularTotalCount');
        const particularDetailsSection = document.getElementById('particularDetailsSection');
        const particularSearchInput = document.getElementById('particularSearchInput');
        const particularRecordCountEl = document.getElementById('particularRecordCount');
        const particularTableBody = document.getElementById('particularEmployeeTableBody');

        // ============================================================
        //  FILTER LOGIC (including search) — operates on the current
        //  month's real records; changing month/year reloads the page
        //  with new data instead (see reloadForMonthYear()).
        // ============================================================
        function getFilteredRecords() {
            const branch = branchSelect.value;
            const employeeId = employeeSelect.value;
            const dateVal = datePicker.value;
            const status = statusSelect.value;
            const query = searchQuery.trim().toLowerCase();

            return allRecords.filter(rec => {
                if (branch !== 'all' && rec.branch !== branch) return false;

                if (employeeId !== 'all' && String(rec.userId) !== employeeId) return false;

                if (dateVal && rec.date !== dateVal) return false;

                if (status !== 'all' && rec.status !== status) return false;

                if (query) {
                    const nameMatch = rec.name.toLowerCase().includes(query);
                    const branchMatch = rec.branch.toLowerCase().includes(query);
                    const statusMatch = rec.status.toLowerCase().includes(query);
                    if (!nameMatch && !branchMatch && !statusMatch) return false;
                }

                return true;
            });
        }

        // ============================================================
        //  UPDATE DASHBOARD
        // ============================================================
        function updateDashboard() {
            filteredRecords = getFilteredRecords();

            let present = 0, absent = 0, halfday = 0, dayOff = 0, holiday = 0;
            filteredRecords.forEach(rec => {
                if (rec.status === 'Present') present++;
                else if (rec.status === 'Absent') absent++;
                else if (rec.status === 'Half Day') halfday++;
                else if (rec.status === 'Day Off') dayOff++;
                else if (rec.status === 'Holiday') holiday++;
            });

            presentCountEl.textContent = present;
            absentCountEl.textContent = absent;
            halfdayCountEl.textContent = halfday;
            dayoffCountEl.textContent = dayOff;
            holidayCountEl.textContent = holiday;
            // "Total Employees" is a headcount, not a row count — filteredRecords has one row
            // per employee per day, so counting rows directly (248 for a full month) is wrong;
            // count distinct employees represented in the current filter instead.
            totalCountEl.textContent = new Set(filteredRecords.map(rec => rec.userId)).size;

            renderTable(filteredRecords, tableBody);
            updateCharts(filteredRecords);
            recordCountEl.textContent = `${filteredRecords.length} record${filteredRecords.length !== 1 ? 's' : ''}`;

            const dateVal = datePicker.value;
            if (dateVal) {
                const parts = dateVal.split('-');
                const d = new Date(parseInt(parts[0]), parseInt(parts[1]) - 1, parseInt(parts[2]));
                currentDisplayDate.textContent = d.toLocaleDateString('en-US', {
                    weekday: 'short', year: 'numeric', month: 'short', day: 'numeric'
                });
            } else {
                const month = parseInt(monthSelect.value, 10);
                const year = parseInt(yearSelect.value, 10);
                const d = new Date(year, month);
                currentDisplayDate.textContent = d.toLocaleDateString('en-US', { month: 'long', year: 'numeric' });
            }
        }

        // ============================================================
        //  CHARTS (Chart.js) - daily trend, status split, department rate
        // ============================================================
        const css = getComputedStyle(document.documentElement);
        const tone = (name, fallback) => (css.getPropertyValue(name) || '').trim() || fallback;
        const COLORS = {
            'Present': '#12b76a', 'Half Day': '#f79009', 'Absent': '#f04438', 'On Leave': '#2e90fa',
            'Day Off': '#98a2b3', 'Holiday': tone('--epic-blue', '#b8892e')
        };
        const charts = {};

        if (window.Chart) {
            Chart.defaults.font.family = "'Plus Jakarta Sans', 'Segoe UI', Arial, sans-serif";
            Chart.defaults.color = '#878a99';
            Chart.defaults.plugins.legend.labels.boxWidth = 10;
            Chart.defaults.plugins.legend.labels.boxHeight = 10;
        }

        function drawChart(id, config) {
            if (!window.Chart) return;
            if (charts[id]) {
                charts[id].data = config.data;
                charts[id].update();
                return;
            }
            charts[id] = new Chart(document.getElementById(id), config);
        }

        function updateCharts(records) {
            // Daily: present, half day, absent, on leave per date
            const days = [...new Set(records.map(r => r.date))].sort();
            const byDay = {};
            records.forEach(r => {
                byDay[r.date] = byDay[r.date] || {};
                byDay[r.date][r.status] = (byDay[r.date][r.status] || 0) + 1;
            });
            const dailyStatuses = ['Present', 'Half Day', 'On Leave', 'Absent'];
            drawChart('chartDaily', {
                type: 'bar',
                data: {
                    labels: days.map(d => parseInt(d.slice(8), 10)),
                    datasets: dailyStatuses.map(st => ({
                        label: st, data: days.map(d => (byDay[d] || {})[st] || 0),
                        backgroundColor: COLORS[st], borderRadius: 3, maxBarThickness: 22
                    }))
                },
                options: {
                    maintainAspectRatio: false,
                    plugins: { legend: { position: 'top', align: 'end' }, tooltip: { callbacks: { title: items => days[items[0].dataIndex] } } },
                    scales: { x: { stacked: true, grid: { display: false } }, y: { stacked: true, beginAtZero: true, grid: { color: '#f0f1f3' } } }
                }
            });

            // Status split
            const splitStatuses = ['Present', 'Half Day', 'On Leave', 'Absent', 'Day Off', 'Holiday'];
            const counts = splitStatuses.map(st => records.filter(r => r.status === st).length);
            drawChart('chartStatus', {
                type: 'doughnut',
                data: { labels: splitStatuses, datasets: [{ data: counts, backgroundColor: splitStatuses.map(st => COLORS[st]), borderWidth: 2, borderColor: '#fff' }] },
                options: { maintainAspectRatio: false, cutout: '68%', plugins: { legend: { position: 'bottom' } } }
            });

            // Department: (present + half days / 2) of the working days, best first, ten at most
            const dept = {};
            records.forEach(r => {
                if (r.status === 'Day Off' || r.status === 'Holiday') return;
                const d = dept[r.branch] = dept[r.branch] || { worked: 0, days: 0 };
                d.days++;
                if (r.status === 'Present') d.worked++;
                else if (r.status === 'Half Day') d.worked += 0.5;
            });
            const rows = Object.entries(dept).filter(([, v]) => v.days > 0)
                .map(([name, v]) => [name || '—', Math.round(v.worked / v.days * 1000) / 10])
                .sort((a, b) => b[1] - a[1]).slice(0, 10);
            drawChart('chartDept', {
                type: 'bar',
                data: { labels: rows.map(r => r[0]), datasets: [{ label: 'Attendance %', data: rows.map(r => r[1]), backgroundColor: tone('--epic-blue', '#b8892e'), borderRadius: 4, maxBarThickness: 18 }] },
                options: {
                    indexAxis: 'y', maintainAspectRatio: false,
                    plugins: { legend: { display: false }, tooltip: { callbacks: { label: c => c.parsed.x + '%' } } },
                    scales: { x: { beginAtZero: true, max: 100, ticks: { callback: v => v + '%' }, grid: { color: '#f0f1f3' } }, y: { grid: { display: false } } }
                }
            });
        }

        // ============================================================
        //  PARTICULAR EMPLOYEE DASHBOARD — a monthly summary for whichever
        //  employee is picked in the shared Employee filter above. Only
        //  reacts to Employee/Month/Year — Department/Date/search only
        //  make sense for the multi-employee Monthly Dashboard table.
        // ============================================================
        function updateParticularDashboard() {
            const employeeId = employeeSelect.value;

            if (employeeId === 'all') {
                particularNoSelection.style.display = 'flex';
                particularStatsGrid.style.display = 'none';
                particularDetailsSection.style.display = 'none';
                return;
            }

            particularNoSelection.style.display = 'none';
            particularStatsGrid.style.display = 'grid';
            particularDetailsSection.style.display = 'block';

            const empRecords = allRecords.filter(rec => String(rec.userId) === employeeId);

            let present = 0, absent = 0, leave = 0, halfday = 0, dayOff = 0, holiday = 0;
            empRecords.forEach(rec => {
                if (rec.status === 'Present') present++;
                else if (rec.status === 'Absent') absent++;
                else if (rec.status === 'On Leave') leave++;
                else if (rec.status === 'Half Day') halfday++;
                else if (rec.status === 'Day Off') dayOff++;
                else if (rec.status === 'Holiday') holiday++;
            });

            const totalDays = empRecords.length;
            const workingDays = totalDays - dayOff - holiday;

            particularPresentCountEl.textContent = present;
            particularAbsentCountEl.textContent = absent;
            particularLeaveCountEl.textContent = leave;
            particularHalfdayCountEl.textContent = halfday;
            particularWorkingCountEl.textContent = workingDays;
            particularHolidayCountEl.textContent = holiday;
            particularDayOffCountEl.textContent = dayOff;
            particularTotalCountEl.textContent = totalDays;

            // Employee Details table for this tab — same per-day rows as the Monthly Dashboard
            // table would show if filtered to this one employee, plus its own status/date search.
            const query = particularSearchQuery.trim().toLowerCase();
            const searchedRecords = query
                ? empRecords.filter(rec => rec.status.toLowerCase().includes(query) || rec.date.includes(query))
                : empRecords;

            renderTable(searchedRecords, particularTableBody);
            particularRecordCountEl.textContent = `${searchedRecords.length} record${searchedRecords.length !== 1 ? 's' : ''}`;
        }

        // ============================================================
        //  RENDER TABLE
        // ============================================================
        const PAGE_SIZE = 25;

        // A table shows its records a page at a time, with the pager under it
        function renderTable(records, targetTbody) {
            targetTbody._records = records;
            renderPage(targetTbody, 1);
        }

        function renderPage(targetTbody, page) {
            const all = targetTbody._records || [];
            const pages = Math.max(1, Math.ceil(all.length / PAGE_SIZE));
            page = Math.min(Math.max(1, page), pages);
            const from = (page - 1) * PAGE_SIZE;
            renderRows(all.slice(from, from + PAGE_SIZE), targetTbody, from);

            const wrap = targetTbody.closest('.table-wrap');
            let pager = wrap.nextElementSibling;
            if (!pager || !pager.classList.contains('pager')) {
                pager = document.createElement('div');
                pager.className = 'pager';
                wrap.after(pager);
            }
            if (all.length <= PAGE_SIZE) {
                pager.innerHTML = '';
                return;
            }

            const nums = [];
            for (let i = 1; i <= pages; i++) {
                if (i === 1 || i === pages || Math.abs(i - page) <= 1) nums.push(i);
                else if (nums[nums.length - 1] !== '…') nums.push('…');
            }
            pager.innerHTML = `<span class="pager-info">Showing ${from + 1}–${Math.min(from + PAGE_SIZE, all.length)} of ${all.length}</span>
                <span class="pager-btns">
                    <button type="button" data-page="${page - 1}" ${page === 1 ? 'disabled' : ''}><i class="fas fa-chevron-left"></i></button>
                    ${nums.map(n => n === '…' ? '<span class="pager-gap">…</span>' : `<button type="button" data-page="${n}" class="${n === page ? 'active' : ''}">${n}</button>`).join('')}
                    <button type="button" data-page="${page + 1}" ${page === pages ? 'disabled' : ''}><i class="fas fa-chevron-right"></i></button>
                </span>`;
            pager.querySelectorAll('button[data-page]').forEach(b => b.addEventListener('click', () => renderPage(targetTbody, parseInt(b.dataset.page, 10))));
        }

        function renderRows(records, targetTbody, offset) {
            if (records.length === 0) {
                targetTbody.innerHTML = `
                        <tr class="no-results">
                            <td colspan="5">
                                <i class="fas fa-inbox"></i>
                                No matching employees found
                            </td>
                        </tr>
                    `;
                return;
            }

            let html = '';
            records.forEach((rec, index) => {
                // Two letters at most (first and last name): long names gave "SSCVRRB", which spilled over the name
                const parts = String(rec.name || '').trim().split(/\s+/).filter(Boolean);
                const initials = ((parts[0] || '')[0] || '').concat(parts.length > 1 ? parts[parts.length - 1][0] : '').toUpperCase();
                const statusClass = rec.status.toLowerCase().replace(' ', '');
                html += `
                        <tr>
                            <td>${offset + index + 1}</td>
                            <td>
                                <div class="emp-cell">
                                    <span class="employee-avatar">${initials}</span>
                                    ${rec.name}
                                </div>
                            </td>
                            <td>${rec.branch}</td>
                            <td><span class="status-badge ${statusClass}">${rec.status}</span></td>
                            <td>${rec.date}</td>
                        </tr>
                    `;
            });
            targetTbody.innerHTML = html;
        }

        // ============================================================
        //  Month/Year change — the server only computed data for one
        //  month, so switching reloads this same page with new params
        //  instead of filtering client-side.
        // ============================================================
        function reloadForMonthYear() {
            const url = new URL(window.location.href);

            // Picking a month/year explicitly supersedes any specific date that was
            // pinning the page to a different month.
            url.searchParams.delete('date');

            if (monthSelect.value === '') {
                // "Clear" — drop both params so the server falls back to its own
                // defaults (the real current month/year) instead of pinning to one.
                // The "cleared" marker is what lets the dropdown show "Clear" itself
                // as selected on reload, instead of snapping back to that real month.
                url.searchParams.set('cleared', '1');
                url.searchParams.delete('month');
                url.searchParams.delete('year');
            } else {
                url.searchParams.delete('cleared');
                url.searchParams.set('month', parseInt(monthSelect.value, 10) + 1);
                url.searchParams.set('year', yearSelect.value);
            }

            window.location.href = url.toString();
        }

        // ============================================================
        //  Date change — reload with month/year matching the picked date, since
        //  the server only ever computes records for the currently loaded month.
        //  Without this, picking a date outside that month silently shows nothing.
        // ============================================================
        function reloadForDate() {
            const url = new URL(window.location.href);

            if (datePicker.value === '') {
                url.searchParams.delete('date');
            } else {
                url.searchParams.delete('cleared');
                const [y, m] = datePicker.value.split('-');
                url.searchParams.set('date', datePicker.value);
                url.searchParams.set('month', parseInt(m, 10));
                url.searchParams.set('year', y);
            }

            window.location.href = url.toString();
        }

        // ============================================================
        //  TAB SWITCHING
        // ============================================================
        const tabButtons = document.querySelectorAll('.dashboard-tab');
        const tabPanes = {
            monthly: document.getElementById('tab-monthly'),
            particular: document.getElementById('tab-particular'),
            reports: document.getElementById('tab-reports')
        };

        // The three report tabs are iframes to separate PHP files — lazy-load each one's
        // src only the first time its tab is opened, so switching tabs doesn't pay for
        // fetching all three reports up front.
        const lazyIframeIds = {
            'total-monthly': 'iframe-total-monthly',
            'monthly-sheet': 'iframe-monthly-sheet',
            annual: 'iframe-annual'
        };

        // Same-origin report iframes have no fixed height of their own — grow them to fit
        // their full content so the report isn't clipped into an inner scrollbar under the
        // page's own scrollbar. min-height in the inline style stays as the floor for short
        // reports (e.g. a filtered single-employee view).
        function autoResizeIframe(iframe) {
            try {
                const doc = iframe.contentWindow.document;
                const height = Math.max(doc.documentElement.scrollHeight, doc.body.scrollHeight);
                iframe.style.height = height + 'px';
            } catch (e) {
                // Cross-origin or not yet loaded — the min-height fallback still applies.
            }
        }

        Object.values(lazyIframeIds).forEach(iframeId => {
            const iframe = document.getElementById(iframeId);
            if (iframe) {
                iframe.addEventListener('load', () => autoResizeIframe(iframe));
            }
        });

        const reportLoading = document.getElementById('reportLoading');
        let currentReport = 'total-monthly';

        // Open one of the three reports inside the Reports tab (each loads the first time it is opened)
        function showReport(key) {
            currentReport = key;
            document.querySelectorAll('.report-option').forEach(o => o.classList.toggle('active', o.dataset.report === key));
            Object.keys(lazyIframeIds).forEach(k => document.getElementById('tab-' + k).classList.toggle('active', k === key));

            const iframe = document.getElementById(lazyIframeIds[key]);
            if (!iframe.src) {
                // The message stays until *this* report is in: another report finishing
                // meanwhile must not clear it (that left an empty block for ~30s)
                iframe.addEventListener('load', () => {
                    iframe.dataset.loaded = '1';
                    autoResizeIframe(iframe);
                    if (currentReport === key) reportLoading.style.display = 'none';
                }, { once: true });
                iframe.src = iframe.dataset.src;
            }
            reportLoading.style.display = iframe.dataset.loaded ? 'none' : 'flex';
            if (iframe.dataset.loaded) autoResizeIframe(iframe);
        }

        document.querySelectorAll('.report-option').forEach(o => o.addEventListener('click', () => showReport(o.dataset.report)));

        tabButtons.forEach(btn => {
            btn.addEventListener('click', () => {
                tabButtons.forEach(b => b.classList.remove('active'));
                btn.classList.add('active');

                Object.values(tabPanes).forEach(pane => pane.classList.remove('active'));
                tabPanes[btn.dataset.tab].classList.add('active');

                // The reports have filters of their own; the shared row is for the two dashboards
                const onReports = btn.dataset.tab === 'reports';
                document.querySelector('.dashboard').classList.toggle('on-report', onReports);
                if (onReports) {
                    showReport(currentReport);
                }
            });
        });

        // ============================================================
        //  EVENT LISTENERS
        // ============================================================
        branchSelect.addEventListener('change', updateDashboard);
        employeeSelect.addEventListener('change', () => {
            updateDashboard();
            updateParticularDashboard();
        });
        datePicker.addEventListener('change', reloadForDate);
        monthSelect.addEventListener('change', reloadForMonthYear);
        yearSelect.addEventListener('change', reloadForMonthYear);
        statusSelect.addEventListener('change', updateDashboard);

        searchInput.addEventListener('input', (e) => {
            searchQuery = e.target.value;
            updateDashboard();
        });

        particularSearchInput.addEventListener('input', (e) => {
            particularSearchQuery = e.target.value;
            updateParticularDashboard();
        });

        // ============================================================
        //  BOOTSTRAP
        // ============================================================
        if (window.Choices) {
            [branchSelect, employeeSelect, monthSelect, yearSelect].forEach(el => new Choices(el, {
                searchEnabled: true, shouldSort: false, itemSelectText: '', searchResultLimit: 30,
                searchPlaceholderValue: 'Type to search…', position: 'bottom'
            }));
        }

        searchQuery = '';
        searchInput.value = '';
        updateDashboard();
        updateParticularDashboard();
    </script>
</body>
</html>
