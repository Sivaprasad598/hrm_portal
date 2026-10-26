<?php
$companyId = isset($_GET['company_id']) ? (int) $_GET['company_id'] : 0;
$currentUserId = isset($_GET['user_id']) ? (int) $_GET['user_id'] : 0;

function leaveMgmtEsc($value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
}

/**
 * This schema's leave_types table has no `code` column, so CL/SL/Marriage
 * are identified by matching `type_name` — same heuristic used by the
 * leave_policy migration seed and leave_policy.php.
 */
function classifyLeaveType(string $typeName): ?string
{
    $name = strtolower(trim($typeName));

    if (str_starts_with($name, 'casual')) {
        return 'cl';
    }

    if (str_starts_with($name, 'sick')) {
        return 'sl';
    }

    if (str_starts_with($name, 'marriage')) {
        return 'marriage';
    }

    return null;
}

$departments = [];
$employees = [];
$leavesByUser = [];

$monthNames = [
    'January', 'February', 'March', 'April', 'May', 'June',
    'July', 'August', 'September', 'October', 'November', 'December',
];

$policy = [
    'cl' => ['monthlyAllocation' => 1, 'carryForward' => true, 'halfDayRate' => 2, 'fixedDays' => 0],
    'sl' => ['monthlyAllocation' => 1, 'carryForward' => false, 'halfDayRate' => 2, 'fixedDays' => 0],
    'marriage' => ['monthlyAllocation' => 0, 'carryForward' => false, 'halfDayRate' => 2, 'fixedDays' => 7],
];

if ($companyId > 0) {
    try {
        require_once __DIR__ . '/vendor/autoload.php';
        $app = require __DIR__ . '/bootstrap/app.php';
        $kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
        $kernel->bootstrap();

        $db = \Illuminate\Support\Facades\DB::connection();

        $departments = $db->table('teams')
            ->where('company_id', $companyId)
            ->orderBy('team_name')
            ->get(['id', 'team_name'])
            ->toArray();

        $employees = $db->table('users as u')
            ->join('employee_details as ed', 'ed.user_id', '=', 'u.id')
            ->leftJoin('teams as t', 't.id', '=', 'ed.department_id')
            ->leftJoin('designations as d', 'd.id', '=', 'ed.designation_id')
            ->where('u.company_id', $companyId)
            ->orderBy('u.name')
            ->get(['u.id', 'u.name', 'ed.employee_card_id', 'ed.department_id', 't.team_name as department_name', 'd.name as designation_name'])
            ->toArray();

        // Allocation/carry-forward now come straight from the Leave Settings
        // (leave_types) page, which is the single source of truth for these
        // rules — no more separate leave_policy table.
        $leaveTypeRows = $db->table('leave_types')
            ->where('company_id', $companyId)
            ->whereNull('deleted_at')
            ->select(['type_name', 'no_of_leaves', 'leavetype', 'monthly_limit', 'unused_leave'])
            ->get();

        foreach ($leaveTypeRows as $leaveTypeRow) {
            $key = classifyLeaveType($leaveTypeRow->type_name);

            if ($key === null) {
                continue;
            }

            $noOfLeaves = (float) $leaveTypeRow->no_of_leaves;
            $monthlyLimit = (float) $leaveTypeRow->monthly_limit;

            // The exact monthly share (8 a year is 0.666.. a month, not 0.67):
            // rounded, twelve months of it came to 8.04. Rounding is for display.
            if ($leaveTypeRow->leavetype === 'monthly') {
                $monthlyAllocation = $noOfLeaves;
                $yearlyAllocation = $noOfLeaves * 12;
            } elseif ($leaveTypeRow->leavetype === 'yearly') {
                $monthlyAllocation = $monthlyLimit > 0 ? $monthlyLimit : $noOfLeaves / 12;
                $yearlyAllocation = $noOfLeaves;
            } else {
                $monthlyAllocation = 999999; // unlimited
                $yearlyAllocation = null;
            }

            $policy[$key] = [
                'monthlyAllocation' => $monthlyAllocation,
                'yearlyAllocation' => $yearlyAllocation,
                'carryForward' => $leaveTypeRow->unused_leave === 'carry forward',
                'halfDayRate' => 2, // matches the app-wide 0.5-day half-day convention (LeaveHelper.php)
                'fixedDays' => $noOfLeaves,
            ];
        }

        // Real leave requests for this company/year, grouped per employee per
        // month, so the "Monthly Leave Summary" table reflects actual leaves
        // people have applied for instead of demo numbers.
        $currentYear = (int) date('Y');

        $leaveRows = $db->table('leaves as l')
            ->join('leave_types as lt', 'lt.id', '=', 'l.leave_type_id')
            ->where('l.company_id', $companyId)
            ->whereYear('l.leave_date', $currentYear)
            ->whereIn('l.status', ['approved', 'pending'])
            ->select(['l.user_id', 'lt.type_name', 'l.leave_date', 'l.status', 'l.half_day_type'])
            ->get();

        foreach ($leaveRows as $leaveRow) {
            $typeKey = classifyLeaveType($leaveRow->type_name);

            // The monthly table only tracks CL/SL; Marriage is a separate one-time badge.
            if ($typeKey === null || $typeKey === 'marriage') {
                continue;
            }

            $monthIndex = ((int) date('n', strtotime($leaveRow->leave_date))) - 1;

            if ($monthIndex < 0 || $monthIndex > 11) {
                continue;
            }

            $rowUserId = (int) $leaveRow->user_id;

            if (!isset($leavesByUser[$rowUserId])) {
                $leavesByUser[$rowUserId] = array_map(function ($name) {
                    return ['month' => $name, 'clUsed' => 0, 'slUsed' => 0, 'halfDays' => 0, 'clPending' => 0, 'slPending' => 0, 'lop' => 0];
                }, $monthNames);
            }

            if ($leaveRow->status === 'pending') {
                $leavesByUser[$rowUserId][$monthIndex][$typeKey . 'Pending']++;
                continue;
            }

            // approved
            if (!empty($leaveRow->half_day_type)) {
                $leavesByUser[$rowUserId][$monthIndex]['halfDays']++;
            }
            else {
                $leavesByUser[$rowUserId][$monthIndex][$typeKey . 'Used']++;
            }
        }
    }
    catch (Throwable $e) {
        $departments = [];
        $employees = [];
        $leavesByUser = [];
    }
}

// Marriage leave's "monthly allocation" isn't meaningful (one-time entitlement),
// same override as leave_policy.php.
$policy['marriage']['monthlyAllocation'] = 0;

$policyJson = json_encode($policy);
$leavesByUserJson = json_encode($leavesByUser);

$employeesJson = json_encode(array_map(function ($employee) {
    return [
        'id' => (int) $employee->id,
        'name' => (string) $employee->name,
        'card' => (string) ($employee->employee_card_id ?? ''),
        'departmentId' => $employee->department_id !== null ? (int) $employee->department_id : null,
        'departmentName' => (string) ($employee->department_name ?? ''),
        'designationName' => (string) ($employee->designation_name ?? ''),
    ];
}, $employees));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Employee Leave Dashboard — Policy Logic</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />
    <link href="https://fonts.googleapis.com/css2?family=Inter:ital,wght@0,300;0,400;0,500;0,600;0,700;0,800;1,400&display=swap" rel="stylesheet" />
    <style>
        /* ───────────────────────────── RESET & BASE ───────────────────────────── */
        *,
        *::before,
        *::after {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --primary: #2563eb;
            --primary-dark: #1d4ed8;
            --primary-light: #dbeafe;
            --secondary: #0ea5e9;
            --accent-amber: #f59e0b;
            --accent-amber-light: #fef3c7;
            --success: #10b981;
            --success-light: #d1fae5;
            --danger: #ef4444;
            --danger-light: #fee2e2;
            --purple: #8b5cf6;
            --purple-light: #ede9fe;
            --gray-50: #f8fafc;
            --gray-100: #f1f5f9;
            --gray-200: #e2e8f0;
            --gray-300: #cbd5e1;
            --gray-400: #94a3b8;
            --gray-500: #64748b;
            --gray-600: #475569;
            --gray-700: #334155;
            --gray-800: #1e293b;
            --gray-900: #0f172a;
            --shadow-sm: 0 1px 2px rgba(0, 0, 0, 0.05);
            --shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.07), 0 2px 4px -1px rgba(0, 0, 0, 0.04);
            --shadow-md: 0 10px 15px -3px rgba(0, 0, 0, 0.08), 0 4px 6px -2px rgba(0, 0, 0, 0.03);
            --shadow-lg: 0 20px 25px -5px rgba(0, 0, 0, 0.08), 0 10px 10px -5px rgba(0, 0, 0, 0.02);
            --radius: 12px;
            --radius-sm: 8px;
            --transition: 0.25s cubic-bezier(0.4, 0, 0.2, 1);
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: var(--gray-100);
            color: var(--gray-800);
            padding: 24px;
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: flex-start;
        }

        .dashboard {
            width: 100%;
            margin: 0 auto;
        }

        /* ───────────────────────────── HEADER ───────────────────────────── */
        .dashboard-header {
            background: white;
            border-radius: var(--radius);
            padding: 24px 32px;
            box-shadow: var(--shadow);
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 28px;
            border: 1px solid rgba(226, 232, 240, 0.6);
        }

        .header-left {
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .header-left .logo-icon {
            width: 48px;
            height: 48px;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            border-radius: var(--radius-sm);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 22px;
            flex-shrink: 0;
        }

        .header-title h1 {
            font-size: 22px;
            font-weight: 700;
            color: var(--gray-900);
            letter-spacing: -0.3px;
        }

        .header-title p {
            font-size: 14px;
            color: var(--gray-500);
            font-weight: 400;
            margin-top: 2px;
        }

        .header-right {
            display: flex;
            align-items: center;
            gap: 20px;
            flex-wrap: wrap;
        }

        .employee-filters {
            display: flex;
            align-items: center;
            gap: 10px;
            flex: 1 1 auto;
            flex-wrap: wrap;
        }

        .filter-select {
            appearance: none;
            background: var(--gray-50) url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='6'%3E%3Cpath d='M0 0l5 6 5-6z' fill='%2364748b'/%3E%3C/svg%3E") no-repeat right 12px center;
            border: 1px solid var(--gray-200);
            border-radius: var(--radius-sm);
            padding: 8px 30px 8px 12px;
            font-size: 13px;
            font-weight: 500;
            color: var(--gray-700);
            cursor: pointer;
            transition: var(--transition);
            width: 180px;
            flex: 0 0 auto;
        }

        .filter-select:hover,
        .filter-select:focus {
            border-color: var(--primary);
            outline: none;
        }

        .user-select-wrap {
            display: flex;
            align-items: center;
            gap: 8px;
            flex: 1 1 auto;
            min-width: 0;
        }

        .user-search-box {
            position: relative;
            flex: 1 1 200px;
            min-width: 160px;
            max-width: 240px;
        }

        .user-search-box i {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--gray-400);
            font-size: 12px;
            pointer-events: none;
        }

        .user-search-box input {
            width: 100%;
            border: 1px solid var(--gray-200);
            border-radius: var(--radius-sm);
            background: var(--gray-50);
            padding: 8px 12px 8px 30px;
            font-size: 13px;
            font-family: inherit;
            color: var(--gray-700);
            transition: var(--transition);
        }

        .user-search-box input:focus {
            border-color: var(--primary);
            outline: none;
            background: white;
        }

        .user-select-wrap .filter-select {
            width: 220px;
            flex: 1 1 220px;
            max-width: 260px;
        }

        @media (max-width: 768px) {
            .employee-filters,
            .user-select-wrap {
                width: 100%;
            }

            .filter-select,
            .user-search-box,
            .user-select-wrap .filter-select {
                flex: 1 1 100%;
                width: 100%;
                max-width: none;
            }
        }

        .employee-badge {
            display: flex;
            align-items: center;
            gap: 14px;
            background: var(--gray-50);
            padding: 8px 16px 8px 14px;
            border-radius: 50px;
            border: 1px solid var(--gray-200);
        }

        .employee-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 16px;
            flex-shrink: 0;
        }

        .employee-meta {
            display: flex;
            flex-direction: column;
        }

        .employee-meta .name {
            font-weight: 600;
            font-size: 14px;
            color: var(--gray-900);
        }

        .employee-meta .detail {
            font-size: 12px;
            color: var(--gray-500);
        }

        .header-actions {
            display: flex;
            gap: 8px;
        }

        .header-actions button {
            background: var(--gray-50);
            border: 1px solid var(--gray-200);
            border-radius: var(--radius-sm);
            padding: 8px 12px;
            font-size: 13px;
            font-weight: 500;
            color: var(--gray-700);
            cursor: pointer;
            transition: var(--transition);
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .header-actions button:hover {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
        }

        .header-actions button i {
            font-size: 14px;
        }

        /* ───────────────────────────── SUMMARY CARDS ───────────────────────────── */
        .summary-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
            gap: 14px;
            margin-bottom: 28px;
        }

        .summary-card {
            background: white;
            border-radius: var(--radius);
            padding: 16px 18px 14px;
            box-shadow: var(--shadow);
            border: 1px solid rgba(226, 232, 240, 0.6);
            transition: var(--transition);
            display: flex;
            align-items: center;
            gap: 12px;
            cursor: default;
        }

        .summary-card:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow-md);
        }

        .summary-card .icon-wrap {
            width: 42px;
            height: 42px;
            border-radius: var(--radius-sm);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            flex-shrink: 0;
        }

        .summary-card .icon-wrap.blue {
            background: var(--primary-light);
            color: var(--primary);
        }
        .summary-card .icon-wrap.green {
            background: var(--success-light);
            color: var(--success);
        }
        .summary-card .icon-wrap.amber {
            background: var(--accent-amber-light);
            color: var(--accent-amber);
        }
        .summary-card .icon-wrap.red {
            background: var(--danger-light);
            color: var(--danger);
        }
        .summary-card .icon-wrap.purple {
            background: var(--purple-light);
            color: var(--purple);
        }
        .summary-card .icon-wrap.gray {
            background: var(--gray-200);
            color: var(--gray-600);
        }
        .summary-card .icon-wrap.teal {
            background: #ccfbf1;
            color: #0d9488;
        }

        .summary-card .info {
            flex: 1;
            min-width: 0;
        }

        .summary-card .info .label {
            font-size: 11px;
            font-weight: 500;
            color: var(--gray-500);
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }

        .summary-card .info .value {
            font-size: 20px;
            font-weight: 700;
            color: var(--gray-900);
            line-height: 1.3;
            margin-top: 1px;
        }

        .summary-card .info .value .sub {
            font-size: 12px;
            font-weight: 400;
            color: var(--gray-400);
            margin-left: 4px;
        }

        .summary-card .info .value .lop-badge {
            font-size: 13px;
            font-weight: 700;
            color: var(--danger);
            background: var(--danger-light);
            padding: 0 10px;
            border-radius: 50px;
            margin-left: 4px;
        }

        /* ───────────────────────────── LEAVE TYPES ROW ───────────────────────────── */
        .leave-types {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
            margin-bottom: 28px;
        }

        .leave-type-card {
            background: white;
            border-radius: var(--radius);
            padding: 18px 22px;
            box-shadow: var(--shadow);
            border: 1px solid rgba(226, 232, 240, 0.6);
            display: flex;
            align-items: center;
            gap: 16px;
            transition: var(--transition);
            cursor: default;
            position: relative;
            overflow: hidden;
        }

        .leave-type-card::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 4px;
            height: 100%;
            border-radius: 0 2px 2px 0;
        }

        .leave-type-card.cl::after {
            background: var(--primary);
        }
        .leave-type-card.sl::after {
            background: var(--success);
        }
        .leave-type-card.marriage::after {
            background: var(--purple);
        }
        .leave-type-card.lop::after {
            background: var(--danger);
        }

        .leave-type-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }

        .leave-type-card .lt-icon {
            width: 44px;
            height: 44px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            flex-shrink: 0;
        }

        .leave-type-card.cl .lt-icon {
            background: var(--primary-light);
            color: var(--primary);
        }
        .leave-type-card.sl .lt-icon {
            background: var(--success-light);
            color: var(--success);
        }
        .leave-type-card.marriage .lt-icon {
            background: var(--purple-light);
            color: var(--purple);
        }
        .leave-type-card.lop .lt-icon {
            background: var(--danger-light);
            color: var(--danger);
        }

        .leave-type-card .lt-info {
            flex: 1;
        }

        .leave-type-card .lt-info .lt-name {
            font-weight: 600;
            font-size: 15px;
            color: var(--gray-900);
        }

        .leave-type-card .lt-info .lt-desc {
            font-size: 13px;
            color: var(--gray-500);
            margin-top: 1px;
        }

        .leave-type-card .lt-badge {
            font-size: 12px;
            font-weight: 600;
            padding: 4px 12px;
            border-radius: 50px;
            background: var(--gray-100);
            color: var(--gray-600);
            white-space: nowrap;
        }

        .leave-type-card.cl .lt-badge {
            background: var(--primary-light);
            color: var(--primary);
        }
        .leave-type-card.sl .lt-badge {
            background: var(--success-light);
            color: var(--success);
        }
        .leave-type-card.marriage .lt-badge {
            background: var(--purple-light);
            color: var(--purple);
        }
        .leave-type-card.lop .lt-badge {
            background: var(--danger-light);
            color: var(--danger);
        }

        /* ───────────────────────────── TABLE SECTION ───────────────────────────── */
        .table-section {
            background: white;
            border-radius: var(--radius);
            padding: 20px 0 4px 0;
            box-shadow: var(--shadow);
            border: 1px solid rgba(226, 232, 240, 0.6);
            overflow: hidden;
        }

        .table-header {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            padding: 0 24px 16px 24px;
        }

        .table-header h2 {
            font-size: 17px;
            font-weight: 600;
            color: var(--gray-900);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .table-header h2 i {
            color: var(--primary);
        }

        .table-header-controls {
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
        }

        .table-header-controls .month-filter {
            width: 150px;
        }

        .table-header .table-actions {
            display: flex;
            gap: 8px;
        }

        .table-header .table-actions button {
            background: var(--gray-50);
            border: 1px solid var(--gray-200);
            border-radius: var(--radius-sm);
            padding: 6px 14px;
            font-size: 13px;
            font-weight: 500;
            color: var(--gray-600);
            cursor: pointer;
            transition: var(--transition);
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .table-header .table-actions button:hover {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
        }

        .table-wrapper {
            overflow-x: auto;
            padding: 0 4px 4px 4px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
            min-width: 800px;
        }

        table thead th {
            background: var(--gray-50);
            color: var(--gray-600);
            font-weight: 600;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.4px;
            padding: 10px 10px;
            text-align: left;
            border-bottom: 2px solid var(--gray-200);
            white-space: nowrap;
        }

        table tbody td {
            padding: 10px 10px;
            border-bottom: 1px solid var(--gray-100);
            color: var(--gray-700);
            white-space: nowrap;
        }

        table tbody tr {
            transition: var(--transition);
        }

        table tbody tr:hover {
            background: var(--gray-50);
        }

        table tbody tr:last-child td {
            border-bottom: none;
        }

        table .month-name {
            font-weight: 600;
            color: var(--gray-900);
        }

        table .badge-pending {
            background: var(--accent-amber-light);
            color: var(--accent-amber);
            padding: 2px 10px;
            border-radius: 50px;
            font-size: 11px;
            font-weight: 600;
            display: inline-block;
        }

        table .badge-used {
            background: var(--danger-light);
            color: var(--danger);
            padding: 2px 10px;
            border-radius: 50px;
            font-size: 11px;
            font-weight: 600;
            display: inline-block;
        }

        table .badge-available {
            background: var(--success-light);
            color: var(--success);
            padding: 2px 10px;
            border-radius: 50px;
            font-size: 11px;
            font-weight: 600;
            display: inline-block;
        }

        table .badge-lop {
            background: var(--danger-light);
            color: var(--danger);
            padding: 2px 10px;
            border-radius: 50px;
            font-size: 11px;
            font-weight: 700;
            display: inline-block;
        }

        table .badge-half {
            background: #fef3c7;
            color: #d97706;
            padding: 2px 10px;
            border-radius: 50px;
            font-size: 11px;
            font-weight: 600;
            display: inline-block;
        }

        .table-note {
            font-size: 13px;
            color: var(--gray-500);
            padding: 10px 24px 14px 24px;
            border-top: 1px solid var(--gray-100);
            margin-top: 8px;
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
        }

        .table-note i {
            color: var(--primary);
            font-size: 14px;
        }

        .table-note .highlight {
            font-weight: 600;
            color: var(--primary);
        }

        .table-note .highlight-danger {
            font-weight: 600;
            color: var(--danger);
        }

        /* ───────────────────────────── MONTHLY DASHBOARD ───────────────────────────── */
        .monthly-dashboard-section {
            margin-bottom: 28px;
        }

        .monthly-dashboard-body {
            padding: 4px 24px 20px 24px;
        }

        .monthly-dashboard-body .summary-grid {
            margin-bottom: 0;
        }

        /* ───────────────────────────── RESPONSIVE ───────────────────────────── */
        @media (max-width: 1024px) {
            .summary-grid {
                grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
            }
        }

        @media (max-width: 768px) {
            body {
                padding: 16px;
            }

            .dashboard-header {
                padding: 18px 20px;
                flex-direction: column;
                align-items: stretch;
                gap: 14px;
            }

            .header-left {
                gap: 12px;
            }

            .header-title h1 {
                font-size: 18px;
            }

            .header-right {
                flex-wrap: wrap;
                justify-content: space-between;
            }

            .employee-badge {
                padding: 6px 14px 6px 12px;
            }

            .employee-avatar {
                width: 34px;
                height: 34px;
                font-size: 14px;
            }

            .header-actions button {
                padding: 6px 10px;
                font-size: 12px;
            }

            .summary-grid {
                grid-template-columns: repeat(3, 1fr);
                gap: 10px;
            }

            .summary-card {
                padding: 12px 12px 10px;
                flex-direction: column;
                align-items: flex-start;
                gap: 6px;
            }

            .summary-card .icon-wrap {
                width: 34px;
                height: 34px;
                font-size: 14px;
            }

            .summary-card .info .value {
                font-size: 17px;
            }

            .summary-card .info .label {
                font-size: 10px;
            }

            .leave-types {
                grid-template-columns: 1fr 1fr;
            }

            .table-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
                padding: 0 16px 12px 16px;
            }

            table {
                font-size: 12px;
                min-width: 680px;
            }

            table thead th,
            table tbody td {
                padding: 8px 8px;
            }
        }

        @media (max-width: 480px) {
            .summary-grid {
                grid-template-columns: 1fr 1fr;
                gap: 8px;
            }

            .leave-types {
                grid-template-columns: 1fr;
            }

            .summary-card .info .value {
                font-size: 15px;
            }

            .dashboard-header {
                padding: 14px 16px;
            }

            .header-title h1 {
                font-size: 16px;
            }

            .header-title p {
                font-size: 12px;
            }

            .employee-meta .name {
                font-size: 13px;
            }

            .employee-meta .detail {
                font-size: 11px;
            }

            .header-actions button {
                font-size: 11px;
                padding: 5px 8px;
            }

            .header-actions button span {
                display: none;
            }

            table {
                font-size: 11px;
                min-width: 560px;
            }

            table thead th,
            table tbody td {
                padding: 6px 6px;
            }
        }

        @media (max-width: 380px) {
            .summary-grid {
                grid-template-columns: 1fr 1fr;
                gap: 6px;
            }

            .summary-card {
                padding: 8px 8px 8px;
            }

            .summary-card .icon-wrap {
                width: 28px;
                height: 28px;
                font-size: 12px;
            }

            .summary-card .info .value {
                font-size: 13px;
            }

            .summary-card .info .label {
                font-size: 9px;
            }
        }

        .table-wrapper::-webkit-scrollbar {
            height: 6px;
        }

        .table-wrapper::-webkit-scrollbar-track {
            background: var(--gray-100);
            border-radius: 4px;
        }

        .table-wrapper::-webkit-scrollbar-thumb {
            background: var(--gray-300);
            border-radius: 4px;
        }

        .table-wrapper::-webkit-scrollbar-thumb:hover {
            background: var(--gray-400);
        }

        .text-muted {
            color: var(--gray-400);
        }

        .fw-600 {
            font-weight: 600;
        }
    </style>
</head>
<body>

    <div class="dashboard">

        <!-- ═══════════════ HEADER ═══════════════ -->
        <header class="dashboard-header">
            <div class="header-left">
                <div class="logo-icon">
                    <i class="fas fa-calendar-check"></i>
                </div>
                <div class="header-title">
                    <h1>Leave Management</h1>
                    <p>Employee Leave Dashboard — Policy Logic</p>
                </div>
            </div>
            <div class="header-right">
                <div class="employee-filters">
                    <select id="departmentFilter" class="filter-select">
                        <option value="">All Departments</option>
                        <?php foreach ($departments as $department): ?>
                            <option value="<?php echo (int) $department->id; ?>"><?php echo leaveMgmtEsc($department->team_name); ?></option>
                        <?php endforeach; ?>
                    </select>

                    <div class="user-select-wrap">
                        <div class="user-search-box">
                            <i class="fas fa-search"></i>
                            <input type="text" id="userSearch" placeholder="Search name or card ID" autocomplete="off">
                        </div>
                        <select id="userFilter" class="filter-select">
                            <option value="">Select Employee</option>
                            <?php foreach ($employees as $employee): ?>
                                <option value="<?php echo (int) $employee->id; ?>" data-department="<?php echo (int) ($employee->department_id ?? 0); ?>">
                                    <?php echo leaveMgmtEsc($employee->name . (empty($employee->employee_card_id) ? '' : ' (' . $employee->employee_card_id . ')')); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="employee-badge">
                    <div class="employee-avatar" id="employeeAvatar">JD</div>
                    <div class="employee-meta">
                        <span class="name" id="employeeName">John Doe</span>
                        <span class="detail" id="employeeDetail">EMP-2024-001 &bull; Engineering &bull; Sr. Software Engineer</span>
                    </div>
                </div>
                <div class="header-actions">
                    <button><i class="fas fa-file-export"></i> <span>Export</span></button>
                    <button><i class="fas fa-plus"></i> <span>Apply</span></button>
                </div>
            </div>
        </header>

        <!-- ═══════════════ SUMMARY CARDS ═══════════════ -->
        <section class="summary-grid" id="summaryGrid">
            <!-- populated by JS -->
        </section>

        <!-- ═══════════════ LEAVE TYPES ═══════════════ -->
        <section class="leave-types" id="leaveTypes">
            <!-- populated by JS -->
        </section>

        <!-- ═══════════════ MONTHLY DASHBOARD ═══════════════ -->
        <section class="table-section monthly-dashboard-section">
            <div class="table-header">
                <h2><i class="fas fa-calendar-day"></i> Monthly Dashboard — <span id="monthlyDashboardLabel">—</span></h2>
            </div>
            <div class="monthly-dashboard-body">
                <div class="summary-grid" id="monthlySummaryGrid">
                    <!-- populated by JS -->
                </div>
            </div>
        </section>

        <!-- ═══════════════ TABLE ═══════════════ -->
        <section class="table-section">
            <div class="table-header">
                <h2><i class="fas fa-table"></i> Monthly Leave Summary — Policy Applied</h2>
                <div class="table-header-controls">
                    <select id="monthFilter" class="filter-select month-filter">
                        <option value="">All Months</option>
                    </select>
                    <div class="table-actions">
                        <button><i class="fas fa-download"></i> Download</button>
                        <button><i class="fas fa-print"></i> Print</button>
                    </div>
                </div>
            </div>
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>Month</th>
                            <th>CL Alloc.</th>
                            <th>SL Alloc.</th>
                            <th>CL Used</th>
                            <th>SL Used</th>
                            <th>Half‑Days</th>
                            <th>CL Pending</th>
                            <th>SL Pending</th>
                            <th>Carry Fwd CL</th>
                            <th>Remaining CL</th>
                            <th>Remaining SL</th>
                            <th>LOP</th>
                        </tr>
                    </thead>
                    <tbody id="leaveTableBody">
                        <!-- populated by JS -->
                    </tbody>
                </table>
            </div>
            <div class="table-note">
                <i class="fas fa-info-circle"></i>
                <span id="policyNote"><!-- populated by JS from leave_policy --></span>
            </div>
        </section>

    </div>

    <script>
        // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
        //  DEPARTMENT / EMPLOYEE FILTERS
        // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

        const employeesData = <?php echo $employeesJson ?: '[]'; ?>;
        const CURRENT_USER_ID = <?php echo (int) $currentUserId; ?>;

        // Real allocation / carry-forward / half-day rules from the leave_policy table.
        const POLICY = <?php echo $policyJson; ?>;

        const departmentFilter = document.getElementById('departmentFilter');
        const userSearch = document.getElementById('userSearch');
        const userFilter = document.getElementById('userFilter');

        function escapeHtml(value) {
            const div = document.createElement('div');
            div.textContent = value == null ? '' : String(value);
            return div.innerHTML;
        }

        function renderUserOptions() {
            const departmentId = departmentFilter.value;
            const query = userSearch.value.trim().toLowerCase();
            const previousValue = userFilter.value;

            const filtered = employeesData.filter(function(employee) {
                const matchesDepartment = !departmentId || String(employee.departmentId) === departmentId;
                const matchesQuery = !query
                    || (employee.name || '').toLowerCase().includes(query)
                    || (employee.card || '').toLowerCase().includes(query);

                return matchesDepartment && matchesQuery;
            });

            const options = filtered.map(function(employee) {
                const label = employee.card ? `${employee.name} (${employee.card})` : employee.name;
                return `<option value="${employee.id}" data-department="${employee.departmentId ?? ''}">${escapeHtml(label)}</option>`;
            });

            userFilter.innerHTML = '<option value="">Select Employee</option>' + options.join('');

            if (filtered.some(function(employee) { return String(employee.id) === previousValue; })) {
                userFilter.value = previousValue;
            }
        }

        if (departmentFilter && userSearch && userFilter) {
            departmentFilter.addEventListener('change', renderUserOptions);
            userSearch.addEventListener('input', renderUserOptions);
            renderUserOptions();
        }

        // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
        //  EMPLOYEE BADGE — reflects whichever employee is selected
        //  in the user dropdown.
        // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

        const employeeAvatar = document.getElementById('employeeAvatar');
        const employeeNameEl = document.getElementById('employeeName');
        const employeeDetailEl = document.getElementById('employeeDetail');

        function initialsOf(name) {
            const parts = (name || '').trim().split(/\s+/).filter(Boolean);

            if (!parts.length) {
                return '--';
            }

            if (parts.length === 1) {
                return parts[0].slice(0, 2).toUpperCase();
            }

            return (parts[0][0] + parts[parts.length - 1][0]).toUpperCase();
        }

        function updateEmployeeBadge(employee) {
            if (!employeeAvatar || !employeeNameEl || !employeeDetailEl) {
                return;
            }

            if (!employee) {
                employeeAvatar.textContent = '--';
                employeeNameEl.textContent = 'Select an employee';
                employeeDetailEl.textContent = '';
                return;
            }

            employeeAvatar.textContent = initialsOf(employee.name);
            employeeNameEl.textContent = employee.name || '--';
            employeeDetailEl.textContent = [employee.card, employee.departmentName, employee.designationName]
                .filter(Boolean)
                .join(' • ');
        }

        if (userFilter) {
            userFilter.addEventListener('change', function() {
                const selected = employeesData.find(function(employee) {
                    return String(employee.id) === userFilter.value;
                });

                updateEmployeeBadge(selected || null);
                renderForEmployee(Number(userFilter.value) || 0);
            });
        }

        // Default the badge + user dropdown to whoever is currently logged in.
        if (CURRENT_USER_ID && userFilter) {
            const currentEmployee = employeesData.find(function(employee) {
                return employee.id === CURRENT_USER_ID;
            });

            if (currentEmployee) {
                userFilter.value = String(currentEmployee.id);
                updateEmployeeBadge(currentEmployee);
            }
        }

        // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
        //  REAL LEAVE DATA — per employee, per month, pulled from the
        //  `leaves` table (grouped server-side by leave_date's month).
        // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

        const MONTH_NAMES = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
        const LEAVES_BY_USER = <?php echo $leavesByUserJson ?: '{}'; ?>;

        function emptyMonthlyRows() {
            return MONTH_NAMES.map(name => ({ month: name, clUsed: 0, slUsed: 0, halfDays: 0, clPending: 0, slPending: 0, lop: 0 }));
        }

        function getRawDataFor(userId) {
            const rows = LEAVES_BY_USER[String(userId)];
            return Array.isArray(rows) && rows.length ? rows : emptyMonthlyRows();
        }

        // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
        //  COMPUTE WITH POLICY LOGIC
        // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

        // Allocation, carry-forward, and half-day conversion all come from
        // the leave_policy table (POLICY), not hardcoded constants.

        function computeMonthlyData(rawData) {
            let carryForwardCL = 0;

            return rawData.map((row) => {
                // Carry-in: unused CL brought forward from the previous month.
                // (Captured before this iteration overwrites carryForwardCL with
                // this month's own carry-out — otherwise "Carry Fwd CL" and
                // "Remaining CL" end up showing the same figure for every row.)
                const carryInCL = carryForwardCL;

                // 1. Monthly allocation (from leave_policy)
                const clAllocated = POLICY.cl.monthlyAllocation;
                const slAllocated = POLICY.sl.monthlyAllocation;

                // 2. Half-days → SL conversion (rate from leave_policy)
                const halfDays = row.halfDays || 0;
                const halfToSL = Math.floor(halfDays / POLICY.sl.halfDayRate);
                const remainingHalf = halfDays % POLICY.sl.halfDayRate;

                // 3. Total SL used = direct SL used + half-day conversion
                let totalSLUsed = row.slUsed + halfToSL;

                // 4. Determine SL available = allocated (no carry forward)
                let slAvailable = slAllocated;
                let slRemaining = slAvailable - totalSLUsed;

                // 5. If SL is negative, we need to deduct from CL
                let clDeductionForSL = 0;
                let lopDays = row.lop || 0;

                if (slRemaining < 0) {
                    // Need to cover the deficit from CL
                    clDeductionForSL = Math.abs(slRemaining);
                    slRemaining = 0;
                }

                // 6. CL used = direct CL used + CL deducted for SL deficit
                let totalCLUsed = row.clUsed + clDeductionForSL;

                // 7. CL available = allocated + carry forward (carry-in)
                let clAvailable = clAllocated + carryInCL;

                // 8. CL remaining = clAvailable - totalCLUsed
                let clRemaining = clAvailable - totalCLUsed;

                // 9. If CL remaining is negative, convert to LOP
                let lopFromCL = 0;
                if (clRemaining < 0) {
                    lopFromCL = Math.abs(clRemaining);
                    clRemaining = 0;
                    lopDays += lopFromCL;
                }

                // 10. Carry forward for next month = CL remaining (unused CL),
                // only when the policy has carry-forward enabled for CL.
                const clToCarry = (POLICY.cl.carryForward && clRemaining > 0) ? clRemaining : 0;
                carryForwardCL = clToCarry;

                // 11. Total pending = CL pending + SL pending
                const totalPending = row.clPending + row.slPending;

                return {
                    month: row.month,
                    clAllocated,
                    slAllocated,
                    clUsed: row.clUsed,
                    slUsed: row.slUsed,
                    halfDays: row.halfDays || 0,
                    halfToSL,
                    remainingHalf,
                    clPending: row.clPending,
                    slPending: row.slPending,
                    carryForwardCL: carryInCL,
                    clAvailable,
                    slAvailable,
                    clRemaining,
                    slRemaining,
                    clDeductionForSL,
                    lop: lopDays,
                    totalPending,
                    totalCLUsed,
                    totalSLUsed,
                };
            });
        }

        // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
        //  COMPUTE TOTALS FOR SUMMARY CARDS
        // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

        function computeTotals(monthlyData) {
            const totals = monthlyData.reduce((acc, row) => {
                acc.totalCLAllocated += row.clAllocated;
                acc.totalSLAllocated += row.slAllocated;
                acc.totalCLUsed += row.clUsed;
                acc.totalSLUsed += row.slUsed;
                acc.totalHalfDays += row.halfDays;
                acc.totalLOP += row.lop;
                acc.totalPending += row.totalPending;
                acc.totalCLDeduction += row.clDeductionForSL || 0;
                return acc;
            }, {
                totalCLAllocated: 0,
                totalSLAllocated: 0,
                totalCLUsed: 0,
                totalSLUsed: 0,
                totalHalfDays: 0,
                totalLOP: 0,
                totalPending: 0,
                totalCLDeduction: 0,
            });

            // CL/SL Remaining and Carry Fwd CL are running balances, not
            // additive quantities — summing them across 12 months would
            // wildly inflate the figure. The correct "current" value is
            // simply whatever the most recent month's balance is.
            const lastRow = monthlyData[monthlyData.length - 1];
            totals.totalCLRemaining = lastRow && lastRow.clRemaining > 0 ? lastRow.clRemaining : 0;
            totals.totalSLRemaining = lastRow && lastRow.slRemaining > 0 ? lastRow.slRemaining : 0;
            totals.totalCarryForward = lastRow ? lastRow.carryForwardCL : 0;

            return totals;
        }

        // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
        //  RENDER SUMMARY CARDS
        // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

        function renderSummary(totals) {
            const summaryConfig = [{
                icon: 'fa-calendar-alt',
                color: 'blue',
                label: 'Total CL (alloc.)',
                value: POLICY.cl.yearlyAllocation ?? totals.totalCLAllocated,
                sub: 'for the year'
            }, {
                icon: 'fa-calendar-check',
                color: 'green',
                label: 'Total SL (alloc.)',
                value: POLICY.sl.yearlyAllocation ?? totals.totalSLAllocated,
                sub: 'for the year'
            }, {
                icon: 'fa-clock',
                color: 'amber',
                label: 'CL Used',
                value: totals.totalCLUsed,
                sub: 'taken'
            }, {
                icon: 'fa-clock',
                color: 'red',
                label: 'SL Used',
                value: totals.totalSLUsed,
                sub: 'taken'
            }, {
                icon: 'fa-clock',
                color: 'teal',
                label: 'Half‑Days Used',
                value: totals.totalHalfDays,
                sub: 'total'
            }, {
                icon: 'fa-boxes',
                color: 'purple',
                label: 'Carry Fwd CL',
                value: totals.totalCarryForward,
                sub: 'accumulated'
            }, {
                icon: 'fa-hourglass-half',
                color: 'amber',
                label: 'Pending',
                value: totals.totalPending,
                sub: 'requests'
            }, {
                icon: 'fa-exclamation-triangle',
                color: 'red',
                label: 'LOP Days',
                value: totals.totalLOP,
                sub: 'unpaid'
            }, ];

            const summaryGrid = document.getElementById('summaryGrid');
            summaryGrid.innerHTML = summaryConfig.map(item => `
                    <div class="summary-card">
                        <div class="icon-wrap ${item.color}">
                            <i class="fas ${item.icon}"></i>
                        </div>
                        <div class="info">
                            <div class="label">${item.label}</div>
                            <div class="value">${num(item.value)} <span class="sub">${item.sub}</span></div>
                        </div>
                    </div>
                `).join('');
        }

        // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
        //  RENDER LEAVE TYPES
        // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

        function renderLeaveTypes(totals) {
            const leaveTypesConfig = [{
                cls: 'cl',
                icon: 'fa-user-check',
                name: 'Casual Leave (CL)',
                desc: `${POLICY.cl.carryForward ? 'Carries forward' : 'No carry forward'} • ${num(POLICY.cl.monthlyAllocation)} per month`,
                badge: `${num(totals.totalCLRemaining)} remaining`
            }, {
                cls: 'sl',
                icon: 'fa-user-md',
                name: 'Sick Leave (SL)',
                desc: `${POLICY.sl.carryForward ? 'Carries forward' : 'No carry forward'} • ${num(POLICY.sl.monthlyAllocation)} per month`,
                badge: `${num(totals.totalSLRemaining)} remaining`
            }, {
                cls: 'marriage',
                icon: 'fa-ring',
                name: 'Marriage Leave',
                desc: `${POLICY.marriage.fixedDays} Days — Paid Leave (one‑time)`,
                badge: `${POLICY.marriage.fixedDays} days`
            }, {
                cls: 'lop',
                icon: 'fa-exclamation-circle',
                name: 'Loss of Pay (LOP)',
                desc: 'Unpaid leave when balances exhausted',
                badge: `${num(totals.totalLOP)} days`
            }, ];

            const leaveTypesContainer = document.getElementById('leaveTypes');
            leaveTypesContainer.innerHTML = leaveTypesConfig.map(item => `
                    <div class="leave-type-card ${item.cls}">
                        <div class="lt-icon">
                            <i class="fas ${item.icon}"></i>
                        </div>
                        <div class="lt-info">
                            <div class="lt-name">${item.name}</div>
                            <div class="lt-desc">${item.desc}</div>
                        </div>
                        <span class="lt-badge">${item.badge}</span>
                    </div>
                `).join('');
        }

        // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
        //  POLICY FOOTNOTE — reflects the real leave_policy values,
        //  doesn't depend on which employee is selected.
        // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

        const policyNote = document.getElementById('policyNote');

        if (policyNote) {
            policyNote.innerHTML = `
                <span class="highlight">CL</span> ${POLICY.cl.carryForward ? 'carries forward (unused accumulates)' : 'does <strong>not</strong> carry forward'}.
                <span class="highlight">SL</span> ${POLICY.sl.carryForward ? 'carries forward' : 'does <strong>not</strong> carry forward — resets to ' + num(POLICY.sl.monthlyAllocation) + ' each month'}.
                <span class="highlight">${POLICY.sl.halfDayRate} Half‑Days</span> = 1 SL. If SL exhausted, deduct from CL → then Carry Fwd CL → else <span class="highlight-danger">LOP</span>.
                <span class="highlight">Marriage Leave</span>: ${POLICY.marriage.fixedDays} days paid (one‑time).
            `;
        }

        // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
        //  RENDER TABLE
        // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

        // Every figure shown: at most two decimals, no floating-point tail (8.04000000000001).
        function num(value) {
            const n = Number(value);
            return Number.isFinite(n) ? Math.round(n * 100) / 100 : value;
        }

        function badgeHtml(value, type) {
            value = num(value);
            if (type === 'pending' && value > 0) return `<span class="badge-pending">${value}</span>`;
            if (type === 'used' && value > 0) return `<span class="badge-used">${value}</span>`;
            if (type === 'available' && value > 0) return `<span class="badge-available">${value}</span>`;
            if (type === 'lop' && value > 0) return `<span class="badge-lop">${value}</span>`;
            if (type === 'half' && value > 0) return `<span class="badge-half">${value}</span>`;
            return value;
        }

        const tbody = document.getElementById('leaveTableBody');
        let currentMonthlyData = [];

        function renderTable(filterMonth) {
            const rows = filterMonth
                ? currentMonthlyData.filter(row => row.month === filterMonth)
                : currentMonthlyData;

            tbody.innerHTML = rows.map(row => `
                <tr>
                    <td class="month-name">${row.month}</td>
                    <td>${badgeHtml(row.clAllocated, 'available')}</td>
                    <td>${badgeHtml(row.slAllocated, 'available')}</td>
                    <td>${badgeHtml(row.clUsed, 'used')}</td>
                    <td>${badgeHtml(row.slUsed, 'used')}</td>
                    <td>${badgeHtml(row.halfDays, 'half')}</td>
                    <td>${badgeHtml(row.clPending, 'pending')}</td>
                    <td>${badgeHtml(row.slPending, 'pending')}</td>
                    <td>${badgeHtml(row.carryForwardCL, 'available')}</td>
                    <td>${badgeHtml(row.clRemaining, 'available')}</td>
                    <td>${badgeHtml(row.slRemaining, 'available')}</td>
                    <td>${badgeHtml(row.lop, 'lop')}</td>
                </tr>
            `).join('') || `<tr><td colspan="12" style="text-align:center; color:var(--gray-400); padding:20px;">No data for this month.</td></tr>`;
        }

        // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
        //  MONTHLY DASHBOARD — same card style as the annual summary,
        //  but scoped to a single month for the selected employee.
        // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

        const monthlyDashboardLabel = document.getElementById('monthlyDashboardLabel');
        const monthlySummaryGrid = document.getElementById('monthlySummaryGrid');

        function currentRealMonthName() {
            return MONTH_NAMES[new Date().getMonth()];
        }

        function renderMonthlyDashboard() {
            if (!monthlySummaryGrid) {
                return;
            }

            const selectedMonth = (monthFilter && monthFilter.value) || currentRealMonthName();
            const row = currentMonthlyData.find(r => r.month === selectedMonth);

            if (monthlyDashboardLabel) {
                monthlyDashboardLabel.textContent = selectedMonth;
            }

            if (!row) {
                monthlySummaryGrid.innerHTML = '';
                return;
            }

            const config = [
                { icon: 'fa-calendar-alt', color: 'blue', label: 'CL Allocated', value: row.clAllocated, sub: 'this month' },
                { icon: 'fa-calendar-check', color: 'green', label: 'SL Allocated', value: row.slAllocated, sub: 'this month' },
                { icon: 'fa-clock', color: 'amber', label: 'CL Used', value: row.clUsed, sub: 'taken' },
                { icon: 'fa-clock', color: 'red', label: 'SL Used', value: row.slUsed, sub: 'taken' },
                { icon: 'fa-clock', color: 'teal', label: 'Half‑Days', value: row.halfDays, sub: 'taken' },
                { icon: 'fa-hourglass-half', color: 'amber', label: 'CL Pending', value: row.clPending, sub: 'requests' },
                { icon: 'fa-hourglass-half', color: 'amber', label: 'SL Pending', value: row.slPending, sub: 'requests' },
                { icon: 'fa-boxes', color: 'purple', label: 'Carry Fwd CL', value: row.carryForwardCL, sub: 'brought in' },
                { icon: 'fa-check-circle', color: 'green', label: 'Remaining CL', value: row.clRemaining, sub: 'available' },
                { icon: 'fa-check-circle', color: 'blue', label: 'Remaining SL', value: row.slRemaining, sub: 'available' },
                { icon: 'fa-exclamation-triangle', color: 'red', label: 'LOP', value: row.lop, sub: 'unpaid' },
            ];

            monthlySummaryGrid.innerHTML = config.map(item => `
                    <div class="summary-card">
                        <div class="icon-wrap ${item.color}">
                            <i class="fas ${item.icon}"></i>
                        </div>
                        <div class="info">
                            <div class="label">${item.label}</div>
                            <div class="value">${num(item.value)} <span class="sub">${item.sub}</span></div>
                        </div>
                    </div>
                `).join('');
        }

        // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
        //  RENDER EVERYTHING FOR A GIVEN EMPLOYEE
        // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

        function renderForEmployee(userId) {
            const rawData = getRawDataFor(userId);
            currentMonthlyData = computeMonthlyData(rawData);

            const totals = computeTotals(currentMonthlyData);
            renderSummary(totals);
            renderLeaveTypes(totals);
            renderTable(monthFilter ? monthFilter.value : '');
            renderMonthlyDashboard();
        }

        // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
        //  MONTH FILTER — beside "Monthly Leave Summary"
        // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

        const monthFilter = document.getElementById('monthFilter');

        if (monthFilter) {
            monthFilter.innerHTML = '<option value="">All Months</option>' +
                MONTH_NAMES.map(name => `<option value="${name}">${name}</option>`).join('');

            monthFilter.addEventListener('change', function() {
                renderTable(monthFilter.value);
                renderMonthlyDashboard();
            });
        }

        renderForEmployee(CURRENT_USER_ID || (employeesData[0] ? employeesData[0].id : 0));
    </script>

</body>
</html>
