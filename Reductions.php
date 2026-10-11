<?php
/**
 * Payroll Calculator · All Inputs
 * Server-side (PHP) port of Reductions.html — every figure on the slip is
 * computed in PHP on form submit; the page posts back to itself.
 */

// ---------------------------------------------------------------------------
// Include database config (Laravel style)
// ---------------------------------------------------------------------------
$dbConfig = @include __DIR__ . '/config/database.php';
$dbConn = $dbConfig['connections']['mysql'] ?? null;

// Where this page and the payslip page live. Served through the application's
// authenticated route the addresses are /account/legacy/..., so the page's own
// links, form action and AJAX calls are built here rather than hard-coded;
// run directly (tests, CLI) they fall back to the file names.
$selfUrl = function_exists('route') ? route('legacy.page', ['page' => 'reductions']) : 'Reductions.php';
$payslipsUrl = function_exists('route') ? route('legacy.page', ['page' => 'payslips']) : 'payslips.php';
$csrfField = function_exists('csrf_token')
    ? '<input type="hidden" name="_token" value="' . htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') . '">'
    : '';

if (isset($_GET['ajax']) && $_GET['ajax'] === 'lookup_employee' && !empty($_GET['card_id'])) {
    header('Content-Type: application/json');
    $response = ['success' => false, 'message' => 'No active employee found with that code. Someone who has left cannot be paid from here.'];

    if ($dbConn) {
        try {
            $dsn = "mysql:host={$dbConn['host']};port={$dbConn['port']};dbname={$dbConn['database']};charset={$dbConn['charset']}";
            $pdo = new PDO($dsn, $dbConn['username'], isset($dbConn['password']) ? $dbConn['password'] : '', [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);

            $cardId = trim($_GET['card_id']);
            $stmt = $pdo->prepare(
                "SELECT u.name AS employee_name,
                        ed.employee_card_id,
                        ed.employee_id,
                        ed.user_id,
                        ed.designation_id,
                        ed.joining_date,
                        u.pan_number AS pan,
                        u.employee_ctc AS ctc,
                        u.basic_for_payroll,
                        u.monthly_tds,
                        d.name AS designation_name
                 FROM employee_details ed
                 LEFT JOIN users u ON u.id = ed.user_id
                 LEFT JOIN designations d ON d.id = ed.designation_id
                 WHERE (ed.employee_card_id = :cardId
                    OR ed.employee_id = :cardId
                    OR ed.user_id = :cardId
                    OR u.id = :cardId)
                 -- Somebody who has left, or whose account is disabled, is not
                 -- paid from this screen; their final settlement is handled
                 -- through off-boarding.
                 AND u.status = 'active'
                 AND ed.employment_status <> 'off_boarded'
                 LIMIT 1"
            );
            $stmt->execute([':cardId' => $cardId]);
            $employee = $stmt->fetch();

            if ($employee) {
                $response = [
                    'success' => true,
                    'user_id' => (int) $employee['user_id'],
                    'employee_name' => $employee['employee_name'] ?? '',
                    'employee_card_id' => $employee['employee_card_id'] ?: ($employee['employee_id'] ?? ($employee['user_id'] ?? '')),
                    'designation_name' => $employee['designation_name'] ?? '',
                    'pan' => $employee['pan'] ?? '',
                    'joining_date' => $employee['joining_date'] ?? '',
                    'ctc' => (float) ($employee['ctc'] ?? 0),
                    'basic_for_payroll' => $employee['basic_for_payroll'] !== null ? (float) $employee['basic_for_payroll'] : null,
                    // Income tax is a figure per employee, set by the accountant
                    // on the Pay Elements screen rather than typed in here.
                    'monthly_tds' => (float) ($employee['monthly_tds'] ?? 0),
                ];
            }
        } catch (Exception $e) {
            $response['message'] = 'Lookup failed';
        }
    } else {
        $response['message'] = 'Database config not found';
    }

    echo json_encode($response);
    exit;
}

// ---------------------------------------------------------------------------
// Real Working Days / Days Present for an employee + month, fetched from the
// same attendance data the Attendance grid shows — Working Days and Days
// Present here used to just sit at whatever was hardcoded/leftover from the
// last payslip generated for ANY employee (see $DEFAULTS below), completely
// disconnected from actual attendance.
// ---------------------------------------------------------------------------
if (isset($_GET['ajax']) && $_GET['ajax'] === 'lookup_attendance' && !empty($_GET['user_id']) && !empty($_GET['month']) && !empty($_GET['year'])) {
    header('Content-Type: application/json');
    $response = ['success' => false, 'message' => 'Could not compute attendance'];

    if ($dbConn) {
        try {
            $dsn = "mysql:host={$dbConn['host']};port={$dbConn['port']};dbname={$dbConn['database']};charset={$dbConn['charset']}";
            $pdo = new PDO($dsn, $dbConn['username'], isset($dbConn['password']) ? $dbConn['password'] : '', [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);

            $userId = (int) $_GET['user_id'];
            $month = (int) $_GET['month'];
            $year = (int) $_GET['year'];

            $companyStmt = $pdo->prepare(
                "SELECT ed.company_id, c.timezone FROM employee_details ed
                 JOIN companies c ON c.id = ed.company_id
                 WHERE ed.user_id = :uid LIMIT 1"
            );
            $companyStmt->execute([':uid' => $userId]);
            $company = $companyStmt->fetch();

            if (!$company) {
                throw new Exception('Employee/company not found');
            }

            $timezone = $company['timezone'] ?: 'UTC';
            $tz = new DateTimeZone($timezone);
            $utcTz = new DateTimeZone('UTC');

            $daysInMonth = (int) (new DateTime(sprintf('%04d-%02d-01', $year, $month)))->format('t');
            $today = new DateTime('now', $tz);

            // How payroll counts days. Pay is net ÷ Working Days × Days Present, so:
            //   Working Days  = every calendar day of the month that has happened
            //                   (the whole month once it is over);
            //   Days Present  = the days among them that are paid: days worked
            //                   (half for a half day), week-offs, holidays and paid
            //                   leave (CL, SL, EL, ML ...).
            // Absence, LOP leave and days before joining / after leaving earn nothing,
            // so each costs exactly one day's pay (LOP Days = Working − Present).
            $employeeStmt = $pdo->prepare("SELECT joining_date, last_date FROM employee_details WHERE user_id = :uid LIMIT 1");
            $employeeStmt->execute([':uid' => $userId]);
            $employment = $employeeStmt->fetch() ?: [];
            $joinedOn = !empty($employment['joining_date']) ? substr($employment['joining_date'], 0, 10) : null;
            $leftOn = !empty($employment['last_date']) ? substr($employment['last_date'], 0, 10) : null;

            // The shift for a day: the roster's, else the company default shift —
            // the same fallback the Attendance screens use.
            $shiftColumns = "es.shift_name, es.office_open_days, es.min_working_hours_full_day, es.min_working_hours_full_day_grace,
                             es.min_working_hours_half_day, es.min_working_hours_half_day_grace, es.office_start_time, es.office_end_time";
            $rosterStmt = $pdo->prepare(
                "SELECT DAY(ess.date) AS d, $shiftColumns FROM employee_shift_schedules ess
                 JOIN employee_shifts es ON es.id = ess.employee_shift_id
                 WHERE ess.user_id = :uid AND MONTH(ess.date) = :m AND YEAR(ess.date) = :y"
            );
            $rosterStmt->execute([':uid' => $userId, ':m' => $month, ':y' => $year]);
            $rosterByDay = [];
            foreach ($rosterStmt->fetchAll() as $row) {
                $rosterByDay[(int) $row['d']] = $row;
            }

            $defaultShiftStmt = $pdo->prepare(
                "SELECT $shiftColumns FROM attendance_settings ats
                 JOIN employee_shifts es ON es.id = ats.default_employee_shift
                 WHERE ats.company_id = :cid LIMIT 1"
            );
            $defaultShiftStmt->execute([':cid' => $company['company_id']]);
            $defaultShift = $defaultShiftStmt->fetch() ?: null;

            // Company-wide holidays for the month.
            $holidayStmt = $pdo->prepare(
                "SELECT DAY(date) AS d FROM holidays WHERE company_id = :cid AND MONTH(date) = :m AND YEAR(date) = :y"
            );
            $holidayStmt->execute([':cid' => $company['company_id'], ':m' => $month, ':y' => $year]);
            $holidayDays = array_map('intval', array_column($holidayStmt->fetchAll(), 'd'));

            // Which leave is unpaid. The leave code decides where one is set (LOP is
            // unpaid; CL, SL, EL, ML and any other code are paid); a type without a
            // code is unpaid if it is named "LOP" or marked unpaid on the leave type.
            try {
                $leaveTypeStmt = $pdo->prepare(
                    "SELECT id, type_name, code, paid FROM leave_types WHERE company_id = :cid"
                );
                $leaveTypeStmt->execute([':cid' => $company['company_id']]);
            } catch (Throwable $noCodeColumn) {
                // A copy that predates the leave code: names only.
                $leaveTypeStmt = $pdo->prepare(
                    "SELECT id, type_name, NULL AS code, paid FROM leave_types WHERE company_id = :cid"
                );
                $leaveTypeStmt->execute([':cid' => $company['company_id']]);
            }
            $unpaidLeaveType = [];
            foreach ($leaveTypeStmt->fetchAll() as $row) {
                $code = strtoupper(trim((string) ($row['code'] ?? '')));
                $unpaidLeaveType[(int) $row['id']] = $code !== ''
                    ? $code === 'LOP'
                    : (strtolower(trim($row['type_name'])) === 'lop' || (string) $row['paid'] === '0');
            }

            // Approved leave per day. A day can hold two half-day leaves.
            $leaveStmt = $pdo->prepare(
                "SELECT DAY(leave_date) AS d, duration, leave_type_id FROM leaves
                 WHERE user_id = :uid AND status = 'approved' AND MONTH(leave_date) = :m AND YEAR(leave_date) = :y"
            );
            $leaveStmt->execute([':uid' => $userId, ':m' => $month, ':y' => $year]);
            $leaveByDay = [];
            foreach ($leaveStmt->fetchAll() as $row) {
                $day = (int) $row['d'];
                $portion = $row['duration'] === 'half day' ? 0.5 : 1.0;
                $kind = ($unpaidLeaveType[(int) $row['leave_type_id']] ?? false) ? 'unpaid' : 'paid';
                $leaveByDay[$day]['covered'] = min(1.0, ($leaveByDay[$day]['covered'] ?? 0) + $portion);
                $leaveByDay[$day][$kind] = ($leaveByDay[$day][$kind] ?? 0) + $portion;
            }

            // Worked seconds per local calendar day, summed across every session that
            // day — mirrors AttendanceController::summaryData().
            $monthStartLocal = new DateTime(sprintf('%04d-%02d-01 00:00:00', $year, $month), $tz);
            $monthEndLocal = (clone $monthStartLocal)->modify('last day of this month')->setTime(23, 59, 59);
            $monthStartUtc = (clone $monthStartLocal)->setTimezone($utcTz)->format('Y-m-d H:i:s');
            $monthEndUtc = (clone $monthEndLocal)->setTimezone($utcTz)->format('Y-m-d H:i:s');

            $attStmt = $pdo->prepare(
                "SELECT clock_in_time, clock_out_time FROM attendances
                 WHERE user_id = :uid AND clock_in_time BETWEEN :start AND :end"
            );
            $attStmt->execute([':uid' => $userId, ':start' => $monthStartUtc, ':end' => $monthEndUtc]);

            $workedSecondsByDay = [];
            foreach ($attStmt->fetchAll() as $row) {
                if (!$row['clock_out_time']) {
                    continue;
                }

                $clockInUtc = new DateTime($row['clock_in_time'], $utcTz);
                $clockInLocal = (clone $clockInUtc)->setTimezone($tz);
                $day = (int) $clockInLocal->format('j');
                $clockOutUtc = new DateTime($row['clock_out_time'], $utcTz);
                $seconds = abs($clockOutUtc->getTimestamp() - $clockInUtc->getTimestamp());
                $workedSecondsByDay[$day] = ($workedSecondsByDay[$day] ?? 0) + $seconds;
            }

            // Credit for time worked in a day: 1, 0.5 or 0 by the shift's thresholds.
            $attendanceCredit = function ($worked, $shift) {
                if (!$shift || $worked <= 0) {
                    return 0.0;
                }

                $fullDaySeconds = $halfDaySeconds = null;

                if ($shift['min_working_hours_full_day'] !== null && $shift['min_working_hours_half_day'] !== null) {
                    $fullDaySeconds = ($shift['min_working_hours_full_day'] * 3600)
                        - (($shift['min_working_hours_full_day_grace'] ?? 0) * 60);
                    $halfDaySeconds = ($shift['min_working_hours_half_day'] * 3600)
                        - (($shift['min_working_hours_half_day_grace'] ?? 0) * 60);
                } elseif ($shift['office_start_time'] && $shift['office_end_time']) {
                    $s = strtotime($shift['office_start_time']);
                    $e = strtotime($shift['office_end_time']);
                    $shiftSeconds = $e > $s ? ($e - $s) : ($e + 86400 - $s);
                    $fullDaySeconds = $shiftSeconds * 0.5;
                    $halfDaySeconds = $shiftSeconds * 0.25;
                }

                if ($fullDaySeconds !== null && $worked >= $fullDaySeconds) {
                    return 1.0;
                }

                return ($halfDaySeconds !== null && $worked >= $halfDaySeconds) ? 0.5 : 0.0;
            };

            $breakdown = ['worked' => 0.0, 'week_off' => 0, 'holiday' => 0, 'paid_leave' => 0.0,
                'lop_leave' => 0.0, 'absent' => 0.0, 'not_employed' => 0];
            $workingDays = 0;
            $daysPresent = 0.0;

            for ($day = 1; $day <= $daysInMonth; $day++) {
                $dateLocal = new DateTime(sprintf('%04d-%02d-%02d', $year, $month, $day), $tz);

                // Only days up to and including today have happened.
                if ($dateLocal > $today) {
                    continue;
                }

                $workingDays++;
                $date = $dateLocal->format('Y-m-d');

                $roster = $rosterByDay[$day] ?? null;
                $shift = $roster ?: $defaultShift;

                // Outside employment, week-offs, holidays and leave are not paid.
                // Time actually punched still is: attendance before the joining
                // date usually means the joining date was entered wrong.
                if (($joinedOn && $date < $joinedOn) || ($leftOn && $date > $leftOn)) {
                    $worked = $attendanceCredit($workedSecondsByDay[$day] ?? 0, $shift);
                    $breakdown['worked'] += $worked;
                    $breakdown['not_employed'] += 1 - $worked;
                    $daysPresent += $worked;
                    continue;
                }

                // A rostered Day Off, or with no roster entry, a day the default
                // shift's office is closed (Saturday and Sunday on General Shift).
                $openDays = $defaultShift
                    ? array_map('intval', json_decode((string) $defaultShift['office_open_days'], true) ?: [])
                    : [];
                $weekOff = $roster
                    ? $roster['shift_name'] === 'Day Off'
                    : ($defaultShift !== null && !in_array((int) $dateLocal->format('N'), $openDays, true));

                if ($weekOff) {
                    $breakdown['week_off']++;
                    $daysPresent++;
                    continue;
                }

                if (in_array($day, $holidayDays, true)) {
                    $breakdown['holiday']++;
                    $daysPresent++;
                    continue;
                }

                $leave = $leaveByDay[$day] ?? ['covered' => 0.0];
                $paidLeave = (float) ($leave['paid'] ?? 0);

                // Work counts only for the part of the day not on leave, so a
                // half-day leave plus a half day worked makes a full day.
                $worked = min(1.0 - $leave['covered'], $attendanceCredit($workedSecondsByDay[$day] ?? 0, $shift));

                $breakdown['paid_leave'] += $paidLeave;
                $breakdown['lop_leave'] += (float) ($leave['unpaid'] ?? 0);
                $breakdown['worked'] += $worked;
                $breakdown['absent'] += max(0.0, 1.0 - $leave['covered'] - $worked);
                $daysPresent += min(1.0, $paidLeave + $worked);
            }

            // Overtime, a bonus, an advance being recovered: what is held for
            // this month on the Pay Elements screen. Read only — an instalment
            // is drawn when the payslip is actually issued, not by looking.
            $extras = [];
            $rich = [];

            try {
                if (class_exists(\App\Services\Payroll\PayPeriod::class)) {
                    $elementCycle = \App\Services\Payroll\PayPeriod::forMonth($month, $year);
                    $elementStmt = $pdo->prepare(
                        'SELECT id, kind, label, amount, payslip_id FROM payslip_components
                          WHERE user_id = :uid AND period_start = :start
                          ORDER BY id'
                    );
                    $elementStmt->execute([':uid' => $userId, ':start' => $elementCycle->start->toDateString()]);

                    foreach ($elementStmt->fetchAll() as $element) {
                        $row = [
                            'id' => (int) $element['id'],
                            'kind' => $element['kind'],
                            'label' => $element['label'],
                            'amount' => (float) $element['amount'],
                            'payslip_id' => $element['payslip_id'] ? (int) $element['payslip_id'] : null,
                        ];

                        $rich[] = $row;

                        // The preview only lists what is still to be paid.
                        if ($row['payslip_id'] === null) {
                            $extras[] = $row;
                        }
                    }
                }
            } catch (Throwable $elementError) {
                // An older copy without these tables simply shows none.
            }

            $response = [
                'success' => true,
                'working_days' => $workingDays,
                'days_present' => $daysPresent,
                'breakdown' => $breakdown,
                'extras' => $extras,
                'extra_rows' => $rich,
            ];
        } catch (Exception $e) {
            $response['message'] = 'Attendance lookup failed';
        }
    } else {
        $response['message'] = 'Database config not found';
    }

    echo json_encode($response);
    exit;
}

// When embedded in the Payslips page's "Payslip Calculation" tab (?compact=1),
// show only the Generate Payslip form — the detailed Basic%/HRA%/CA/ESI/PF
// configuration and salary-slip preview stay available when this page is
// opened directly, just hidden in that compact embed.
$compactMode = isset($_GET['compact']) && $_GET['compact'] == '1';

// ---------------------------------------------------------------------------
// Defaults
// ---------------------------------------------------------------------------
$DEFAULTS = [
    'gross'        => 60000,
    'basicPct'     => 30,
    'hraPct'       => 50,
    'caThreshold'  => 20000,
    'caHigh'       => 1600,
    'caLow'        => 800,
    'pt'           => 200,
    'esiThreshold' => 20000,
    'esiPct'       => 0.4,
    'pfPct'        => 12,
    // Statutory settings: PF is taken on basic up to this ceiling (0 = all of
    // basic), the employer's ESI share is larger than the employee's, and
    // professional tax follows salary bands where they are configured.
    'pfWageCeiling' => 15000,
    'esiEmployerPct' => 3.25,
    'ptSlabs'      => null,
    'workingDays'  => 22,
    'daysPresent'  => 22,
];

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

/** Indian digit grouping: 1234567 → 12,34,567 */
function indian_number($value)
{
    $value = (int) round((float) $value);
    $sign  = $value < 0 ? '-' : '';
    $s     = (string) abs($value);

    if (strlen($s) <= 3) {
        return $sign . $s;
    }

    $last3 = substr($s, -3);
    $rest  = substr($s, 0, -3);
    $rest  = preg_replace('/\B(?=(\d{2})+(?!\d))/', ',', $rest);

    return $sign . $rest . ',' . $last3;
}

/** ₹ 12,34,567 */
function inr($value)
{
    return '₹ ' . indian_number($value);
}

/** Trim trailing zeros off a percentage: 0.40 → 0.4, 12.00 → 12 */
function pct($value)
{
    $s = number_format((float) $value, 2, '.', '');
    $s = rtrim(rtrim($s, '0'), '.');

    return $s === '' || $s === '-' ? '0' : $s;
}

// Guarded: the framework defines the same helper when this page is served
// through its authenticated route, and redeclaring it would be fatal.
if (!function_exists('e')) {
    function e($value)
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}

/** Read a numeric field from the request, falling back to the default. */
function field($key, $default, $useDefaults)
{
    if ($useDefaults || !isset($_POST[$key]) || trim($_POST[$key]) === '') {
        return (float) $default;
    }

    return is_numeric($_POST[$key]) ? (float) $_POST[$key] : (float) $default;
}

// ---------------------------------------------------------------------------
// Fetch Latest Payroll Data from Database
// ---------------------------------------------------------------------------
$DB_DEFAULTS = $DEFAULTS; // Start with hardcoded defaults
$latestRecordId = null;
$successMessage = null;

if ($dbConn) {
    try {
        $dsn = "mysql:host={$dbConn['host']};port={$dbConn['port']};dbname={$dbConn['database']};charset={$dbConn['charset']}";
        $pdo = new PDO($dsn, $dbConn['username'], isset($dbConn['password']) ? $dbConn['password'] : '', [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);

        // Delete the current saved calculation (🗑 Delete button) before
        // re-fetching "latest", so the form immediately reverts to its
        // blank/default Save state on this same page load.
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && isset($_POST['delete_calculation']) && !empty($_POST['calc_id'])) {
            $deleteStmt = $pdo->prepare("DELETE FROM payroll_calculations WHERE id = :id");
            $deleteStmt->execute([':id' => (int) $_POST['calc_id']]);
            $successMessage = 'Payroll deleted successfully';
        }

        // Fetch latest payroll calculation record
        $stmt = $pdo->query("SELECT * FROM payroll_calculations ORDER BY id DESC LIMIT 1");
        $latestRecord = $stmt->fetch();
        $latestRecordId = $latestRecord['id'] ?? null;

        if ($latestRecord) {
            // Override defaults with database values
            $DB_DEFAULTS = [
                'gross'        => (float) ($latestRecord['gross'] ?? $DEFAULTS['gross']),
                'basicPct'     => (float) ($latestRecord['basic_pct'] ?? $DEFAULTS['basicPct']),
                'hraPct'       => (float) ($latestRecord['hra_pct'] ?? $DEFAULTS['hraPct']),
                'caThreshold'  => (float) ($latestRecord['ca_threshold'] ?? $DEFAULTS['caThreshold']),
                'caHigh'       => (float) ($latestRecord['ca_high'] ?? $DEFAULTS['caHigh']),
                'caLow'        => (float) ($latestRecord['ca_low'] ?? $DEFAULTS['caLow']),
                'pt'           => (float) ($latestRecord['pt'] ?? $DEFAULTS['pt']),
                'esiThreshold' => (float) ($latestRecord['esi_threshold'] ?? $DEFAULTS['esiThreshold']),
                'esiPct'       => (float) ($latestRecord['esi_pct'] ?? $DEFAULTS['esiPct']),
                'pfPct'        => (float) ($latestRecord['pf_pct'] ?? $DEFAULTS['pfPct']),
                'pfWageCeiling' => (float) ($latestRecord['pf_wage_ceiling'] ?? $DEFAULTS['pfWageCeiling']),
                'esiEmployerPct' => (float) ($latestRecord['esi_employer_pct'] ?? $DEFAULTS['esiEmployerPct']),
                'ptSlabs'      => $latestRecord['pt_slabs'] ?? $DEFAULTS['ptSlabs'],
                // Working Days / Days Present are attendance for whichever employee +
                // month the last save happened to be for (or 0/0, when nobody was
                // looked up yet) — not a sane default for the next person who opens
                // this tool fresh. Keep them pinned to $DEFAULTS instead of carrying
                // that leftover value forward; a 0 here trips the "must be 1-31"
                // validation error and blanks out the entire salary slip.
                'workingDays'  => $DEFAULTS['workingDays'],
                'daysPresent'  => $DEFAULTS['daysPresent'],
            ];
        }
    } catch (Exception $e) {
        // If database query fails, use hardcoded defaults
        $DB_DEFAULTS = $DEFAULTS;
    }
}

// ---------------------------------------------------------------------------
// Input
// ---------------------------------------------------------------------------
$useDefaults = isset($_POST['reset_defaults'])
    || (isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : 'GET') !== 'POST';

$in = [
    'gross'        => field('gross', $DB_DEFAULTS['gross'], $useDefaults),
    'basicPct'     => field('basicPct', $DB_DEFAULTS['basicPct'], $useDefaults),
    'hraPct'       => field('hraPct', $DB_DEFAULTS['hraPct'], $useDefaults),
    'caThreshold'  => field('caThreshold', $DB_DEFAULTS['caThreshold'], $useDefaults),
    'caHigh'       => field('caHigh', $DB_DEFAULTS['caHigh'], $useDefaults),
    'caLow'        => field('caLow', $DB_DEFAULTS['caLow'], $useDefaults),
    'pt'           => field('pt', $DB_DEFAULTS['pt'], $useDefaults),
    'esiThreshold' => field('esiThreshold', $DB_DEFAULTS['esiThreshold'], $useDefaults),
    'esiPct'       => field('esiPct', $DB_DEFAULTS['esiPct'], $useDefaults),
    'pfPct'        => field('pfPct', $DB_DEFAULTS['pfPct'], $useDefaults),
    'pfWageCeiling' => field('pfWageCeiling', $DB_DEFAULTS['pfWageCeiling'], $useDefaults),
    'esiEmployerPct' => field('esiEmployerPct', $DB_DEFAULTS['esiEmployerPct'], $useDefaults),
    'ptSlabs'      => $DB_DEFAULTS['ptSlabs'],
    'workingDays'  => field('workingDays', $DB_DEFAULTS['workingDays'], $useDefaults),
    'daysPresent'  => field('daysPresent', $DB_DEFAULTS['daysPresent'], $useDefaults),
];

// ---------------------------------------------------------------------------
// Validation
// ---------------------------------------------------------------------------
$error = null;

if ($in['gross'] <= 0) {
    $error = '⚠️ Enter a valid positive Gross salary';
} elseif ($in['workingDays'] < 1 || $in['workingDays'] > 31) {
    $error = '⚠️ Working days must be 1‑31';
} elseif ($in['daysPresent'] < 0 || $in['daysPresent'] > 31) {
    $error = '⚠️ Days present must be 0‑31';
}

// ---------------------------------------------------------------------------
// Payroll calculation
// ---------------------------------------------------------------------------
/**
 * Professional tax for a salary, from the configured bands.
 *
 * Bands are [{upto: 15000, amount: 0}, {upto: 20000, amount: 150},
 * {upto: null, amount: 200}] — the first band the salary falls within wins, and
 * a band with no upper limit covers everything above. With no bands configured
 * the flat figure is used, which is how this worked before.
 */
function pt_for_salary(float $gross, $slabs, float $flat): float
{
    if (is_string($slabs)) {
        $slabs = json_decode($slabs, true);
    }

    if (!is_array($slabs) || $slabs === []) {
        return $flat;
    }

    foreach ($slabs as $slab) {
        $upto = $slab['upto'] ?? null;

        if ($upto === null || $upto === '' || $gross <= (float) $upto) {
            return (float) ($slab['amount'] ?? $flat);
        }
    }

    return $flat;
}

function calculate_payroll(array $in)
{
    $gross = $in['gross'];
    $basic = $gross * ($in['basicPct'] / 100);
    $hra   = $basic * ($in['hraPct'] / 100);
    // Above the threshold, not at it: the formula sheet reads IF(Gross > 20000, ...).
    $ca    = $gross > $in['caThreshold'] ? $in['caHigh'] : $in['caLow'];

    $others = $gross - ($basic + $hra + $ca);
    // At or below the threshold: IF(Gross <= 21000, ...).
    $esi    = $gross <= $in['esiThreshold'] ? $gross * ($in['esiPct'] / 100) : 0.0;

    // Provident fund is taken on basic up to the wage ceiling (15,000 by the
    // usual rule); a ceiling of 0 means the whole of basic, as before.
    $ceiling = (float) ($in['pfWageCeiling'] ?? 0);
    $pfWages = $ceiling > 0 ? min($basic, $ceiling) : $basic;
    $pf      = $pfWages * ($in['pfPct'] / 100);

    // The employer pays its own, larger, share of ESI; its PF matches the
    // employee's. Both are part of CTC, not deductions from pay.
    $employerEsi = $esi > 0 ? $gross * ((float) ($in['esiEmployerPct'] ?? $in['esiPct']) / 100) : 0.0;
    $employerPf  = $pf;

    // Professional tax by salary band, where bands are configured.
    $pt = pt_for_salary($gross, $in['ptSlabs'] ?? null, $in['pt']);

    $in['pt'] = $pt;
    $totalDeductions = $pt + $esi + $pf;
    $netSalary       = $gross - $totalDeductions;
    $perDay          = $in['workingDays'] > 0 ? $netSalary / $in['workingDays'] : 0.0;
    $attendanceNet   = $perDay * $in['daysPresent'];

    // Running balance, stepped in the order the deduction sections appear on
    // the slip (ESI → PT → PF). The last step equals the net salary.
    $afterEsi = $gross - $esi;
    $afterPt  = $afterEsi - $in['pt'];
    $afterPf  = $afterPt - $pf;

    // Second, independent chain: how gross is carved up into its components.
    // What remains after CA is exactly Others, the balancing figure.
    $allocAfterBasic = $gross - $basic;
    $allocAfterHra   = $allocAfterBasic - $hra;
    $allocAfterCa    = $allocAfterHra - $ca;

    // Basic + HRA + CA before the balancing figure. If this already exceeds
    // gross, Others turns negative and the percentages need lowering.
    $fixedComponents = $basic + $hra + $ca;
    $overAllocated   = $fixedComponents - $gross;

    // Attendance pro-rating. Negative unpaid days mean extra days worked.
    $unpaidDays   = $in['workingDays'] - $in['daysPresent'];
    $unpaidAmount = $perDay * $unpaidDays;

    return [
        'gross'           => $gross,
        'basic'           => $basic,
        'hra'             => $hra,
        'ca'              => $ca,
        'others'          => $others,
        'pt'              => $pt,
        'esi'             => $esi,
        'pf'              => $pf,
        'pfWages'         => $pfWages,
        'employerPf'      => $employerPf,
        'employerEsi'     => $employerEsi,
        'totalDeductions' => $totalDeductions,
        'netSalary'       => $netSalary,
        'perDay'          => $perDay,
        'attendanceNet'   => $attendanceNet,
        'esiApplies'      => $gross <= $in['esiThreshold'],
        'caIsHigh'        => $gross > $in['caThreshold'],
        'afterEsi'        => $afterEsi,
        'afterPf'         => $afterPf,
        'afterPt'         => $afterPt,
        'allocAfterBasic' => $allocAfterBasic,
        'allocAfterHra'   => $allocAfterHra,
        'allocAfterCa'    => $allocAfterCa,
        'fixedComponents' => $fixedComponents,
        'overAllocated'   => $overAllocated,
        'unpaidDays'      => $unpaidDays,
        'unpaidAmount'    => $unpaidAmount,
    ];
}

$d = calculate_payroll($in);

// Computed early (not just inline where the full slip renders) so the compact "Attendance"
// summary — shown below the Generate Payslip form — can use it too.
$warning = '';
if ($error === null) {
    if ($in['daysPresent'] > $in['workingDays']) {
        $warning = '<span style="color:#b36b2a; background:#ffeedb; padding:0.1rem 1rem; border-radius:2rem; font-size:0.75rem;">⚠️ Days present &gt; working days</span>';
    }
    if ($in['daysPresent'] === 0) {
        $warning = '<span style="color:#b33c4a; background:#ffe6e9; padding:0.1rem 1rem; border-radius:2rem; font-size:0.75rem;">⚠️ 0 days present → net pay = 0</span>';
    }
}

// ---------------------------------------------------------------------------
// Save to database (if no error and form was submitted)
// ---------------------------------------------------------------------------
if ($error === null && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && isset($_POST['calculate'])) {
    if ($dbConn) {
        try {
            $dsn = "mysql:host={$dbConn['host']};port={$dbConn['port']};dbname={$dbConn['database']};charset={$dbConn['charset']}";
            $pdo = new PDO($dsn, $dbConn['username'], $dbConn['password'] ?? '', [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);

            // A calc_id means we're editing the already-saved calculation
            // (✏️ Update button) — update that row in place instead of
            // inserting a new one each time.
            $editingId = !empty($_POST['calc_id']) ? (int) $_POST['calc_id'] : null;

            $params = [
                ':gross'           => $in['gross'],
                ':basicPct'        => $in['basicPct'],
                ':hraPct'          => $in['hraPct'],
                ':caThreshold'     => $in['caThreshold'],
                ':caHigh'          => $in['caHigh'],
                ':caLow'           => $in['caLow'],
                ':pt'              => $in['pt'],
                ':esiThreshold'    => $in['esiThreshold'],
                ':esiPct'          => $in['esiPct'],
                ':pfPct'           => $in['pfPct'],
                ':pfWageCeiling'   => $in['pfWageCeiling'],
                ':esiEmployerPct'  => $in['esiEmployerPct'],
                ':ptSlabs'         => is_array($in['ptSlabs']) ? json_encode($in['ptSlabs']) : $in['ptSlabs'],
                ':periodStartDay'  => max(1, min(28, (int) ($_POST['periodStartDay'] ?? ($latestRecord['period_start_day'] ?? 1)))),
                ':workingDays'     => $in['workingDays'],
                ':daysPresent'     => $in['daysPresent'],
                ':basic'           => $d['basic'],
                ':hra'             => $d['hra'],
                ':ca'              => $d['ca'],
                ':others'          => $d['others'],
                ':esi'             => $d['esi'],
                ':pf'              => $d['pf'],
                ':totalDeductions' => $d['totalDeductions'],
                ':netSalary'       => $d['netSalary'],
                ':perDay'          => $d['perDay'],
                ':attendanceNet'   => $d['attendanceNet'],
            ];

            if ($editingId) {
                $stmt = $pdo->prepare("
                    UPDATE payroll_calculations SET
                        gross = :gross, basic_pct = :basicPct, hra_pct = :hraPct,
                        ca_threshold = :caThreshold, ca_high = :caHigh, ca_low = :caLow,
                        pt = :pt, esi_threshold = :esiThreshold, esi_pct = :esiPct, pf_pct = :pfPct,
                        pf_wage_ceiling = :pfWageCeiling, esi_employer_pct = :esiEmployerPct,
                        pt_slabs = :ptSlabs, period_start_day = :periodStartDay,
                        working_days = :workingDays, days_present = :daysPresent,
                        basic = :basic, hra = :hra, ca = :ca, others = :others, esi = :esi, pf = :pf,
                        total_deductions = :totalDeductions, net_salary = :netSalary,
                        per_day = :perDay, attendance_net = :attendanceNet
                    WHERE id = :id
                ");
                $params[':id'] = $editingId;
                $stmt->execute($params);
                $latestRecordId = $editingId;
                $successMessage = 'Payroll updated successfully';
            } else {
                $stmt = $pdo->prepare("
                    INSERT INTO payroll_calculations (
                        gross, basic_pct, hra_pct, ca_threshold, ca_high, ca_low,
                        pt, esi_threshold, esi_pct, pf_pct,
                        pf_wage_ceiling, esi_employer_pct, pt_slabs, period_start_day,
                        working_days, days_present,
                        basic, hra, ca, others, esi, pf,
                        total_deductions, net_salary, per_day, attendance_net
                    ) VALUES (
                        :gross, :basicPct, :hraPct, :caThreshold, :caHigh, :caLow,
                        :pt, :esiThreshold, :esiPct, :pfPct,
                        :pfWageCeiling, :esiEmployerPct, :ptSlabs, :periodStartDay,
                        :workingDays, :daysPresent,
                        :basic, :hra, :ca, :others, :esi, :pf,
                        :totalDeductions, :netSalary, :perDay, :attendanceNet
                    )
                ");
                $stmt->execute($params);
                $latestRecordId = (int) $pdo->lastInsertId();
            }
        } catch (PDOException $e) {
            error_log('Payroll save failed: ' . $e->getMessage());
            // Optionally show a user-friendly message:
            // $error = '⚠️ Could not save to database.';
        }
    } else {
        error_log('Database config not found – skipping save.');
    }
}

/**
 * Footer printed at the end of every section: what that section took out of the
 * running balance, and what is left afterwards.
 *
 * $tone — 'deduct' for take-home deductions (ESI / PF / PT),
 *         'alloc'  for gross carve-up components (Basic / HRA / CA / Others),
 *         'net'    for the attendance pro-rating.
 */
function section_footer($openLabel, $opening, $minusLabel, $amount, $closeLabel, $tone = 'deduct')
{
    $closing = $opening - $amount;
    $isAdd   = $amount < 0;
    ?>
    <div class="deduct-footer tone-<?= e($tone) ?>">
        <div class="slip-row"><span class="label"><?= e($openLabel) ?></span><span class="value"><?= e(inr($opening)) ?></span></div>
        <div class="slip-row">
            <span class="label"><?= e($minusLabel) ?></span>
            <span class="value <?= $isAdd ? 'plus' : 'minus' ?>"><?= $isAdd ? '+' : '−' ?> <?= e(inr(abs($amount))) ?></span>
        </div>
        <div class="deduct-rule"></div>
        <div class="slip-row"><span class="label"><strong><?= e($closeLabel) ?></strong></span><span class="value"><strong><?= e(inr($closing)) ?></strong></span></div>
    </div>
    <?php
}

/** Take-home chain: ESI → PF → PT, each reducing the payable gross. */
function deduction_footer($label, $opening, $amount)
{
    section_footer(
        "Gross before {$label}", $opening,
        "Less: {$label} deduction", $amount,
        "Gross after {$label}", 'deduct'
    );
}

/** Carve-up chain: Basic → HRA → CA → Others, splitting gross into components. */
function allocation_footer($label, $opening, $amount)
{
    section_footer(
        "Unallocated gross before {$label}", $opening,
        "Less: {$label}", $amount,
        "Unallocated gross after {$label}", 'alloc'
    );
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Payroll Calculator · All Inputs</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: system-ui, 'Segoe UI', Roboto, sans-serif;
        }
        body {
            background: #f0f4fa;
            min-height: 100vh;
            padding: 2rem 1rem;
            display: flex;
            justify-content: center;
            align-items: center;
        }
        .card {
            max-width: 1100px;
            width: 100%;
            background: white;
            border-radius: 2rem;
            box-shadow: 0 20px 40px -12px rgba(0,20,40,0.25);
            padding: 2rem 2rem 2.5rem;
        }
        h1 {
            font-weight: 600;
            font-size: 1.8rem;
            color: #0b2b4a;
            border-bottom: 2px solid #e6edf5;
            padding-bottom: 1rem;
            margin-bottom: 1.8rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            flex-wrap: wrap;
        }
        h1 small {
            font-size: 1rem;
            font-weight: 400;
            color: #3d5f7a;
            margin-left: 0.3rem;
        }
        .grid-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2rem;
        }
        @media (max-width: 860px) {
            .grid-2 {
                grid-template-columns: 1fr;
                gap: 2rem;
            }
        }
        .input-panel {
            background: #f9fbfd;
            padding: 1.5rem 1.8rem;
            border-radius: 1.5rem;
            border: 1px solid #e9eef3;
        }
        .input-panel h3 {
            font-size: 1.2rem;
            font-weight: 600;
            color: #1d3b57;
            margin-bottom: 1.2rem;
        }
        .section-title {
            font-weight: 600;
            color: #1d4a6e;
            font-size: 0.9rem;
            margin: 1.2rem 0 0.6rem 0;
            border-bottom: 1px solid #d5dee8;
            padding-bottom: 0.3rem;
        }
        .input-row-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0.8rem;
        }
        @media (max-width: 500px) {
            .input-row-2 {
                grid-template-columns: 1fr;
            }
        }
        .input-group {
            display: flex;
            flex-direction: column;
            gap: 0.2rem;
            margin-bottom: 0.6rem;
        }
        .input-group label {
            font-weight: 500;
            font-size: 0.8rem;
            color: #2c4a66;
        }
        .input-group label .hint {
            font-weight: 400;
            color: #6b88a2;
            font-size: 0.7rem;
        }
        .input-group input {
            padding: 0.5rem 0.8rem;
            border: 1px solid #d5dee8;
            border-radius: 0.8rem;
            font-size: 0.95rem;
            background: white;
            transition: 0.15s;
            width: 100%;
        }
        .input-group input:focus {
            outline: 2px solid #2b6a9e;
            border-color: transparent;
        }
        .action-buttons {
            display: flex;
            flex-wrap: wrap;
            gap: 0.8rem;
            margin: 1.5rem 0 0.5rem;
        }
        .btn {
            background: #e5edf6;
            border: none;
            padding: 0.65rem 1.6rem;
            border-radius: 2rem;
            font-weight: 600;
            font-size: 0.9rem;
            color: #1d3b57;
            cursor: pointer;
            transition: 0.15s;
            border: 1px solid transparent;
            flex: 1 0 auto;
        }
        .btn-primary {
            background: #1d4b75;
            color: white;
            border-color: #1d4b75;
        }
        .btn-primary:hover {
            background: #123a5c;
            transform: scale(1.01);
        }
        .btn-outline {
            background: transparent;
            border: 1px solid #b8ccde;
        }
        .btn-outline:hover {
            background: #e5edf6;
        }
        .slip-panel {
            background: white;
        }
        .slip-header {
            display: flex;
            justify-content: space-between;
            align-items: baseline;
            margin-bottom: 1.2rem;
            flex-wrap: wrap;
        }
        .slip-header h3 {
            font-weight: 600;
            color: #1d3b57;
            font-size: 1.3rem;
        }
        .slip-header .badge {
            background: #ddeeff;
            padding: 0.3rem 1rem;
            border-radius: 30px;
            font-size: 0.8rem;
            font-weight: 600;
            color: #00558b;
        }
        .salary-slip {
            background: #fafcff;
            border-radius: 1.4rem;
            border: 1px solid #e2eaf2;
            padding: 1.4rem 1.6rem 1.8rem;
        }
        .slip-row {
            display: flex;
            justify-content: space-between;
            padding: 0.4rem 0;
            border-bottom: 1px dashed #e2eaf2;
            font-size: 0.95rem;
        }
        .slip-row:last-child {
            border-bottom: none;
        }
        .slip-row .label {
            color: #3a5c78;
        }
        .slip-row .value {
            font-weight: 600;
            color: #0a263b;
        }
        .slip-row.total {
            border-top: 2px solid #b8ccde;
            border-bottom: none;
            padding-top: 0.8rem;
            margin-top: 0.3rem;
            font-size: 1.05rem;
        }
        .slip-row.total .value {
            color: #004e8c;
            font-size: 1.1rem;
        }
        .highlight-net {
            background: #e1effa;
            border-radius: 1rem;
            padding: 0.4rem 1rem;
            margin-top: 0.8rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .highlight-net .label {
            font-weight: 600;
            color: #004e8c;
        }
        .highlight-net .value {
            font-weight: 700;
            font-size: 1.2rem;
            color: #003f6b;
        }
        .attendance-box {
            background: #e9f2fa;
            border-radius: 1rem;
            padding: 0.3rem 1rem;
            margin-top: 0.6rem;
            display: flex;
            justify-content: space-between;
            flex-wrap: wrap;
            align-items: center;
        }
        .per-day-tag {
            background: #d5e6f5;
            padding: 0.1rem 1.2rem;
            border-radius: 30px;
            font-weight: 600;
            font-size: 0.9rem;
            color: #004274;
        }
        .footnote {
            font-size: 0.78rem;
            color: #6b87a0;
            margin-top: 1.2rem;
            border-top: 1px solid #e2eaf2;
            padding-top: 0.9rem;
            text-align: center;
        }
        .error-msg {
            color: #b33c4a;
            background: #ffe6e9;
            padding: 0.3rem 1rem;
            border-radius: 3rem;
            font-size: 0.8rem;
            display: inline-block;
            margin-top: 0.3rem;
        }
        .success-msg {
            color: #1e7b45;
            background: #e3f7ea;
            padding: 0.3rem 1rem;
            border-radius: 3rem;
            font-size: 0.8rem;
            display: inline-block;
            margin-bottom: 0.6rem;
        }
        .inline-hint {
            font-size: 0.7rem;
            color: #58748c;
            margin-top: 0.1rem;
        }
        /* shows which CA slab the current gross falls into */
        .slab-note {
            background: #e9f2fa;
            border-left: 3px solid #2b6a9e;
            border-radius: 0.5rem;
            padding: 0.35rem 0.7rem;
            font-size: 0.78rem;
            color: #1d4a6e;
        }
        .slip-section-title {
            font-weight: 600;
            color: #1d3b57;
            margin-bottom: 0.2rem;
            font-size: 0.95rem;
        }
        /* mirrors .section-title from the input panel so both columns read alike */
        .slip-head {
            font-weight: 600;
            color: #1d4a6e;
            font-size: 0.9rem;
            margin: 1.1rem 0 0.4rem 0;
            border-bottom: 1px solid #d5dee8;
            padding-bottom: 0.3rem;
        }
        .slip-row .sub {
            font-weight: 400;
            color: #6b88a2;
            font-size: 0.72rem;
        }
        .slip-row .value.minus {
            color: #b33c4a;
        }
        .slip-row .value.plus {
            color: #1d7a4a;
        }
        /* worked arithmetic with the entered amounts substituted in */
        .calc-line {
            font-family: 'Consolas', 'Courier New', monospace;
            font-size: 0.76rem;
            color: #2c4a66;
            background: #eef4fa;
            border-radius: 0.4rem;
            padding: 0.25rem 0.6rem;
            margin: 0.15rem 0 0.3rem;
            text-align: right;
        }
        .calc-line strong {
            color: #004e8c;
        }
        /* stacked working, one arithmetic step per line */
        .calc-line .step {
            padding: 0.05rem 0;
        }
        .calc-line .step.result {
            border-top: 1px solid #b8ccde;
            margin-top: 0.15rem;
            padding-top: 0.15rem;
        }
        /* ties the allocation chain to the Others figure it produces */
        .carry-note {
            font-size: 0.72rem;
            color: #3f8f6b;
            padding: 0.1rem 0.2rem 0.2rem;
            text-align: right;
        }
        .carry-note strong {
            color: #1d6b4d;
        }
        /* hand-off form that posts the calculated figures to payslips.php */
        .payslip-form {
            max-width: 760px;
            margin: 1.2rem auto 0;
            background: #ffffff;
            border: 1px solid #e2eaf2;
            border-radius: 1.6rem;
            padding: 2rem 2.2rem 2.2rem;
            box-shadow: 0 16px 40px -20px rgba(11, 43, 74, 0.3);
        }
        .payslip-form-logo {
            text-align: center;
            margin-bottom: 1.4rem;
            padding-bottom: 1.4rem;
            border-bottom: 1px solid #eef2f7;
        }
        .payslip-form-logo img {
            width: 100%;
            height: auto;
            max-width: 100%;
        }
        .payslip-form-title {
            font-weight: 700;
            color: #1d4a6e;
            font-size: 1.2rem;
            text-align: center;
            letter-spacing: 0.01em;
            margin-bottom: 1.3rem;
        }
        .payslip-form .input-group label {
            font-weight: 600;
            color: #1d3b57;
        }
        .payslip-form input[type="text"],
        .payslip-form select {
            padding: 0.6rem 0.9rem;
            border: 1px solid #d5dee8;
            border-radius: 0.8rem;
            font-size: 0.9rem;
            background: white;
            width: 100%;
            transition: 0.15s;
        }
        .payslip-form select {
            cursor: pointer;
        }
        .payslip-form input[type="text"]:focus,
        .payslip-form select:focus {
            outline: 2px solid #2b6a9e;
            border-color: transparent;
            box-shadow: 0 0 0 4px rgba(43, 106, 158, 0.12);
        }
        .payslip-form .btn-primary {
            box-shadow: 0 10px 24px -10px rgba(29, 75, 117, 0.6);
        }
        .alloc-warn {
            background: #ffeedb;
            border-left: 3px solid #b36b2a;
            border-radius: 0.5rem;
            padding: 0.35rem 0.7rem;
            font-size: 0.78rem;
            color: #8a4f1c;
            margin-top: 0.3rem;
        }
        /* footer accent per chain: deductions vs gross allocation vs attendance */
        .deduct-footer.tone-alloc {
            background: #f4f9f6;
            border-left-color: #3f8f6b;
        }
        .deduct-footer.tone-net {
            background: #eef4fb;
            border-left-color: #7a5cc0;
        }
        /* running "gross after deduction" block closing each deduction section */
        .deduct-footer {
            background: #f2f7fe;
            border-left: 3px solid #2b6a9e;
            border-radius: 0.6rem;
            padding: 0.2rem 0.7rem 0.35rem;
            margin: 0.5rem 0 0.2rem;
        }
        .deduct-footer .slip-row {
            border-bottom: none;
            padding: 0.15rem 0;
            font-size: 0.85rem;
        }
        .deduct-rule {
            border-top: 1px solid #b8ccde;
            margin: 0.15rem 0;
        }
        /* final Gross − Total Deductions = Gross After Deductions */
        .grand-summary {
            background: #e9f2fa;
            border: 1px solid #c5d8ed;
            border-radius: 0.9rem;
            padding: 0.35rem 0.9rem 0.5rem;
            margin-top: 0.9rem;
        }
        .grand-summary .slip-row {
            border-bottom: none;
            padding: 0.2rem 0;
        }
        .grand-summary .slip-row:last-child .value {
            color: #004e8c;
            font-size: 1.05rem;
        }
        .others-highlight {
            background: #f5f9ff;
            border-left: 3px solid #2b6a9e;
            padding-left: 0.5rem;
            margin: 0.2rem 0;
        }
        .separator-line {
            border-top: 2px solid #d1dfec;
            margin: 0.3rem 0 0.2rem 0;
        }

        /* ---------- Salary Preview Styles ---------- */
        #salaryPreview {
            margin-top: 1.5rem;
            border-top: 2px solid #d5dee8;
            padding-top: 1.2rem;
        }
        .preview-title {
            font-weight: 600;
            font-size: 1.1rem;
            color: #1d4a6e;
            margin-bottom: 0.6rem;
        }
        .preview-employee {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0.3rem 1.5rem;
            background: #f5f9ff;
            padding: 0.5rem 1rem;
            border-radius: 0.8rem;
            margin-bottom: 0.8rem;
            font-size: 0.9rem;
        }
        .preview-employee span strong {
            color: #0b2b4a;
        }
        .preview-ctc {
            background: #e9f2fa;
            padding: 0.3rem 1rem;
            border-radius: 0.6rem;
            display: flex;
            justify-content: space-between;
            flex-wrap: wrap;
            margin-bottom: 0.8rem;
            font-weight: 500;
        }
        .preview-slip {
            background: #fafcff;
            border: 1px solid #e2eaf2;
            border-radius: 1rem;
            padding: 0.8rem 1.2rem;
        }
        .preview-slip .slip-row {
            font-size: 0.85rem;
            padding: 0.2rem 0;
        }
        .preview-slip .slip-row.total {
            font-size: 0.95rem;
        }
        .preview-slip .slip-row .value {
            font-weight: 600;
        }
        .preview-slip .slip-head {
            font-size: 0.8rem;
            margin: 0.5rem 0 0.2rem;
        }
        .preview-slip .highlight-net {
            padding: 0.2rem 0.8rem;
            margin-top: 0.4rem;
        }
        .preview-slip .highlight-net .value {
            font-size: 1rem;
        }
    </style>
</head>
<body>

<?php if ($error === null && $compactMode): ?>
<!-- ========== GENERATE PAYSLIP FORM — compact view only (?compact=1) ==========
     The full/direct view hides this and shows only the detailed configuration
     below; the compact embed (Payslip Calculation tab) shows only this.
     No outer .card wrapper here — .payslip-form is its own self-contained card
     (background/border/shadow/rounding), so wrapping it in another .card just
     added a second layer of white padding around it. -->
    <form class="payslip-form" method="post" action="<?= e($payslipsUrl) ?>" id="payslipForm">
        <?= $csrfField ?>
        <div class="payslip-form-logo">
            <img src="<?= function_exists('asset') ? asset('img/arowana_full_logo_white_bg.png') : '../img/arowana_full_logo_white_bg.png' ?>" alt="Arowana" />
        </div>
        <div class="payslip-form-title">🧾 Generate Payslip</div>


        <!-- Employee details (display only, readonly) -->
        <div class="input-row-2">
            <div class="input-group">
                <label>Employee Card ID</label>
                <!-- FIX: removed readonly so user can type -->
                <input type="text" id="employee_card_id" name="emp_id" value="<?= e(isset($_POST['emp_id']) ? $_POST['emp_id'] : '') ?>" placeholder="ACPL0000" autocomplete="off" />
            </div>
            <div class="input-group">
                <label>Employee name</label>
                <input type="text" id="emp_name" name="emp_name" readonly value="<?= e(isset($_POST['emp_name']) ? $_POST['emp_name'] : '') ?>" placeholder="Full name" />
            </div>
        </div>
        <div class="input-row-2">
            <div class="input-group">
                <label>Designation</label>
                <input type="text" id="emp_designation" name="emp_designation" readonly value="<?= e(isset($_POST['emp_designation']) ? $_POST['emp_designation'] : '') ?>" placeholder="Role" />
            </div>
            <div class="input-group">
                <label>PAN No</label>
                <input type="text" id="emp_pan" name="emp_pan" readonly value="<?= e(isset($_POST['emp_pan']) ? $_POST['emp_pan'] : '') ?>" placeholder="ABCDE1234F" />
            </div>
        </div>
        <div class="input-row-2">
            <div class="input-group">
                <label>Month</label>
                <select id="payslip_month" name="month"></select>
            </div>
            <div class="input-group">
                <label>Year</label>
                <select id="payslip_year" name="year"></select>
            </div>
        </div>
        <input type="hidden" id="employee_joining_date" name="joining_date" value="" />
        <input type="hidden" id="period" name="period" value="<?= e(isset($_POST['period']) && $_POST['period'] !== '' ? $_POST['period'] : date('F - Y')) ?>" />

        <!-- Hidden fields for payroll data (will be set by JavaScript) -->
        <input type="hidden" name="basic_pct" id="hf_basic_pct" value="<?= e($in['basicPct']) ?>" />
        <input type="hidden" name="hra_pct" id="hf_hra_pct" value="<?= e($in['hraPct']) ?>" />
        <input type="hidden" name="ca_threshold" id="hf_ca_threshold" value="<?= e($in['caThreshold']) ?>" />
        <input type="hidden" name="ca_high" id="hf_ca_high" value="<?= e($in['caHigh']) ?>" />
        <input type="hidden" name="ca_low" id="hf_ca_low" value="<?= e($in['caLow']) ?>" />
        <input type="hidden" name="esi_threshold" id="hf_esi_threshold" value="<?= e($in['esiThreshold']) ?>" />
        <input type="hidden" name="esi_pct" id="hf_esi_pct" value="<?= e($in['esiPct']) ?>" />
        <input type="hidden" name="pf_pct" id="hf_pf_pct" value="<?= e($in['pfPct']) ?>" />
        <input type="hidden" name="pf_wage_ceiling" id="hf_pf_wage_ceiling" value="<?= e($in['pfWageCeiling']) ?>" />
        <input type="hidden" name="esi_employer_pct" id="hf_esi_employer_pct" value="<?= e($in['esiEmployerPct']) ?>" />
        <input type="hidden" name="employer_pf" id="hf_employer_pf" value="<?= e($d['employerPf']) ?>" />
        <input type="hidden" name="employer_esi" id="hf_employer_esi" value="<?= e($d['employerEsi']) ?>" />
        <input type="hidden" name="gross" id="hf_gross" value="<?= e($d['gross']) ?>" />
        <input type="hidden" name="basic" id="hf_basic" value="<?= e($d['basic']) ?>" />
        <input type="hidden" name="hra" id="hf_hra" value="<?= e($d['hra']) ?>" />
        <input type="hidden" name="ca" id="hf_ca" value="<?= e($d['ca']) ?>" />
        <input type="hidden" name="others" id="hf_others" value="<?= e($d['others']) ?>" />
        <input type="hidden" name="pf" id="hf_pf" value="<?= e($d['pf']) ?>" />
        <input type="hidden" name="esi" id="hf_esi" value="<?= e($d['esi']) ?>" />
        <input type="hidden" name="pt" id="hf_pt" value="<?= e($d['pt']) ?>" />
        <input type="hidden" name="lop" id="hf_lop" value="<?= e(max(0, $d['unpaidAmount'])) ?>" />
        <input type="hidden" name="per_day" id="hf_per_day" value="<?= e($d['perDay']) ?>" />
        <input type="hidden" name="net_payable" id="hf_net_payable" value="<?= e($d['attendanceNet']) ?>" />
        <input type="hidden" name="working_days" id="hf_working_days" value="<?= e($in['workingDays']) ?>" />
        <input type="hidden" name="days_present" id="hf_days_present" value="<?= e($in['daysPresent']) ?>" />
        <input type="hidden" name="lop_days" id="hf_lop_days" value="<?= e(max(0, $d['unpaidDays'])) ?>" />
        <input type="hidden" name="tds" id="hf_tds" value="0" />
        <input type="hidden" name="total_deductions" id="hf_total_deductions" value="<?= e($d['totalDeductions']) ?>" />
        <input type="hidden" name="net_salary" id="hf_net_salary" value="<?= e($d['netSalary']) ?>" />
        <input type="hidden" name="attendance_net" id="hf_attendance_net" value="<?= e($d['attendanceNet']) ?>" />
        <input type="hidden" name="from_calculator" value="1" />

        <button type="submit" class="btn btn-primary" style="width:100%; margin-top:0.6rem;" id="generatePayslipBtn">
            🧾 Generate Payslip →
        </button>
        <div class="inline-hint" style="text-align:center;">
            Saves and opens payslips.php with Basic, HRA, CA, Others as earnings and PF, ESI, PT, Lop as deductions
        </div>
    </form>

<!-- Attendance summary card — disabled per request (commented out, not deleted). -->
<!--
<div class="card">
    <div style="max-width:640px; margin:0 auto;">
        <div class="slip-head">📅 Attendance</div>
        <div class="slip-row"><span class="label">Working days in month</span><span class="value"><?= e($in['workingDays']) ?></span></div>
        <div class="slip-row"><span class="label">Days present</span><span class="value"><?= e($in['daysPresent']) ?></span></div>
        <div class="calc-line"><?= e(inr($d['netSalary'])) ?> ÷ <?= e($in['workingDays']) ?> days = <strong><?= e(inr($d['perDay'])) ?></strong> per day</div>
        <div class="slip-row"><span class="label">Per‑day <span class="sub">Net ÷ working days</span></span><span class="value"><?= e(inr($d['perDay'])) ?></span></div>
        <div class="calc-line"><?= e(inr($d['perDay'])) ?> × <?= e($in['daysPresent']) ?> days present = <strong><?= e(inr($d['attendanceNet'])) ?></strong></div>
        <?php
        $absDays = abs($d['unpaidDays']);
        section_footer(
            'Net salary (full month)', $d['netSalary'],
            ($d['unpaidDays'] >= 0 ? 'Less: ' : 'Add: ') . $absDays . ' ' . ($absDays === 1 ? 'day' : 'days')
                . ($d['unpaidDays'] >= 0 ? ' not present' : ' beyond working days'),
            $d['unpaidAmount'],
            'Net payable (attendance)', 'net'
        );
        ?>

        <div class="attendance-box">
            <span>📅 <?= e($in['workingDays']) ?> working days · ✅ <?= e($in['daysPresent']) ?> present</span>
            <span class="per-day-tag">💵 Per‑day: <?= e(inr($d['perDay'])) ?></span>
            <?= $warning ?>
        </div>

        <div style="background:#d4e6f7; border-radius:1rem; padding:0.3rem 1rem; margin-top:0.6rem; display:flex; justify-content:space-between; align-items:center;">
            <span style="font-weight:600; color:#004e8c;">📆 Net payable (attendance)</span>
            <span style="font-weight:700; font-size:1.2rem; color:#003f6b;"><?= e(inr($d['attendanceNet'])) ?></span>
        </div>

        <div style="font-size:0.72rem; color:#55748e; margin-top:0.6rem; background:#f2f7fe; padding:0.2rem 0.8rem; border-radius:1rem; display:flex; justify-content:space-between; flex-wrap:wrap;">
            <span>📌 Gross: <?= e(inr($d['gross'])) ?></span>
            <span>🧾 PF: <?= e(inr($d['pf'])) ?></span>
            <span>📊 ESI: <?= e(inr($d['esi'])) ?></span>
        </div>
    </div>
</div>
-->
<?php endif; ?>

<div class="card" <?= $compactMode ? 'style="display:none;"' : '' ?>>
    <h1>
        🧾 Payroll · All Input Fields
        <small>basic % · HRA % · CA slabs · ESI % · PF %</small>
    </h1>
    <div class="grid-2">
        <!-- LEFT: ALL INPUTS -->
        <form class="input-panel" method="post" action="">
            <h3>📋 Configure each component</h3>

            <?php if (!empty($successMessage)): ?>
                <div id="successDisplay"><span class="success-msg"><?= e($successMessage) ?></span></div>
            <?php endif; ?>

            <div class="input-group">
                <label>Gross Salary (₹) <span class="hint">(monthly)</span></label>
                <input type="number" name="gross" id="grossInput" class="payroll-field" value="<?= e($in['gross']) ?>" step="100" />
            </div>

            <div class="section-title">🔹 Basic</div>
            <div class="input-group">
                <label>Basic % <span class="hint">(of Gross)</span></label>
                <input type="number" name="basicPct" id="basicPct" class="payroll-field" value="<?= e($in['basicPct']) ?>" step="0.5" min="0" max="100" />
                <span class="inline-hint">→ Basic = ₹<span id="basicPreview"><?= e(round($d['basic'])) ?></span></span>
            </div>

            <div class="section-title">🔹 HRA (House Rent Allowance)</div>
            <div class="input-group">
                <label>HRA % <span class="hint">(of Basic)</span></label>
                <input type="number" name="hraPct" id="hraPct" class="payroll-field" value="<?= e($in['hraPct']) ?>" step="0.5" min="0" max="100" />
                <span class="inline-hint">→ HRA = ₹<span id="hraPreview"><?= e(round($d['hra'])) ?></span></span>
            </div>

            <div class="section-title">🔹 CA (Conveyance Allowance)</div>
            <div class="input-group">
                <label>CA threshold salary (₹)</label>
                <input type="number" name="caThreshold" id="caThreshold" class="payroll-field" value="<?= e($in['caThreshold']) ?>" step="1000" />
                <span class="inline-hint">Gross <strong>above</strong> this → high slab &nbsp;·&nbsp; <strong>at or below</strong> this → low slab</span>
            </div>
            <div class="input-row-2">
                <div class="input-group">
                    <label>CA if Gross &gt; threshold (₹) <span class="hint">high slab</span></label>
                    <input type="number" name="caHigh" id="caHigh" class="payroll-field" value="<?= e($in['caHigh']) ?>" step="100" />
                </div>
                <div class="input-group">
                    <label>CA if Gross &le; threshold (₹) <span class="hint">low slab</span></label>
                    <input type="number" name="caLow" id="caLow" class="payroll-field" value="<?= e($in['caLow']) ?>" step="100" />
                </div>
            </div>
            <div class="slab-note" id="caSlabNote">
                <?php if ($d['caIsHigh']): ?>
                    ✅ Gross <?= e(inr($in['gross'])) ?> &gt; <?= e(inr($in['caThreshold'])) ?> → CA <strong><?= e(inr($in['caHigh'])) ?></strong>
                <?php else: ?>
                    ✅ Gross <?= e(inr($in['gross'])) ?> &lt; <?= e(inr($in['caThreshold'])) ?> → CA <strong><?= e(inr($in['caLow'])) ?></strong>
                <?php endif; ?>
            </div>

            <div class="section-title">🔹 PT (Professional Tax)</div>
            <?php
              // Bands, where configured: the first band the salary falls within
              // decides the tax, and the last band covers everything above.
              $ptSlabs = is_string($in['ptSlabs']) ? json_decode($in['ptSlabs'], true) : $in['ptSlabs'];
              $ptSlabs = is_array($ptSlabs) && $ptSlabs !== [] ? $ptSlabs : null;
            ?>
            <div class="input-group">
                <label>PT ₹ <span class="hint"><?= $ptSlabs ? 'used only when no band matches' : 'fixed' ?></span></label>
                <input type="number" name="pt" id="ptInput" class="payroll-field" value="<?= e($in['pt']) ?>" step="10" />
            </div>
            <?php if ($ptSlabs): ?>
            <div class="slab-note" id="ptSlabNote">
                Bands by monthly gross:
                <?php $previousLimit = null; ?>
                <?php foreach ($ptSlabs as $slab): ?>
                    <?php // The last band has no upper limit, so it reads from the one before it. ?>
                    <?= $slab['upto'] === null
                        ? 'above ₹' . e(indian_number((float) ($previousLimit ?? 0)))
                        : 'up to ₹' . e(indian_number($slab['upto'])) ?>
                    → <strong>₹<?= e(indian_number($slab['amount'])) ?></strong><?= $slab['upto'] === null ? '' : ' · ' ?>
                    <?php $previousLimit = $slab['upto'] ?? $previousLimit; ?>
                <?php endforeach; ?>
                <br>This salary pays <strong>₹<?= e(indian_number(pt_for_salary((float) $in['gross'], $in['ptSlabs'], (float) $in['pt']))) ?></strong>.
                Bands are set on the payroll settings screen.
            </div>
            <?php endif; ?>

            <div class="section-title">🔹 Others</div>
            <div class="input-group">
                <label>Others <span class="hint">(auto-calculated)</span></label>
                <input type="text" id="othersDisplay" value="<?= e(inr($d['others'])) ?>" disabled style="background:#eef3f8; color:#1d3b57; font-weight:600;" />
                <span class="inline-hint">Gross − (Basic + HRA + CA)</span>
            </div>

            <div class="section-title">🔹 ESI (Employee State Insurance)</div>
            <div class="input-row-2">
                <div class="input-group">
                    <label>ESI threshold (Gross below ₹)</label>
                    <input type="number" name="esiThreshold" id="esiThreshold" class="payroll-field" value="<?= e($in['esiThreshold']) ?>" step="1000" />
                </div>
                <div class="input-group">
                    <label>ESI % <span class="hint">(employee, of Gross)</span></label>
                    <input type="number" name="esiPct" id="esiPct" class="payroll-field" value="<?= e($in['esiPct']) ?>" step="0.1" min="0" max="5" />
                    <span class="inline-hint">applies if Gross &lt; threshold</span>
                </div>
            </div>
            <div class="input-group">
                <label>Employer ESI % <span class="hint">(of Gross)</span></label>
                <input type="number" name="esiEmployerPct" id="esiEmployerPct" class="payroll-field" value="<?= e($in['esiEmployerPct']) ?>" step="0.05" min="0" max="10" />
                <span class="inline-hint">the employer's share is larger than the employee's (3.25% against 0.75% by the usual rule)</span>
            </div>

            <div class="section-title">🔹 PF (Provident Fund)</div>
            <div class="input-group">
                <label>PF % <span class="hint">(of Basic — employee contribution)</span></label>
                <input type="number" name="pfPct" id="pfPct" class="payroll-field" value="<?= e($in['pfPct']) ?>" step="0.5" min="0" max="20" />
                <span class="inline-hint">→ PF = ₹<span id="pfPreview"><?= e(round($d['pf'])) ?></span></span>
            </div>
            <div class="input-group">
                <?php
                  // The pay cycle: a calendar month, or a month that runs from a
                  // set day — the 21st to the 20th, say, when payroll is cut
                  // before month end.
                  $cycleStartDay = (int) ($latestRecord['period_start_day'] ?? 1);
                ?>
            </div>

            <div class="section-title">🔹 Pay cycle</div>
            <div class="input-group">
                <label>Pay period starts on day <span class="hint">1 = calendar month</span></label>
                <input type="number" name="periodStartDay" id="periodStartDay" class="payroll-field"
                       value="<?= e($cycleStartDay) ?>" step="1" min="1" max="28" />
                <span class="inline-hint">
                    <?php if ($cycleStartDay <= 1): ?>
                        Each payslip covers a calendar month, the 1st to the last day.
                    <?php else: ?>
                        Each payslip covers the <?= e($cycleStartDay) ?>th of one month to the
                        <?= e($cycleStartDay - 1) ?>th of the next — September's payslip would run
                        <?= e(date('d M', mktime(0, 0, 0, 8, $cycleStartDay, (int) date('Y')))) ?> to
                        <?= e(date('d M', mktime(0, 0, 0, 9, $cycleStartDay - 1, (int) date('Y')))) ?>.
                    <?php endif; ?>
                    Attendance and the per-day rate follow the same dates.
                </span>
            </div>

            <div class="input-group">
                <label>PF wage ceiling (₹) <span class="hint">0 = the whole of Basic</span></label>
                <input type="number" name="pfWageCeiling" id="pfWageCeiling" class="payroll-field" value="<?= e($in['pfWageCeiling']) ?>" step="500" min="0" />
                <span class="inline-hint">
                    PF is taken on Basic up to this figure — ₹15,000 by the usual rule.
                    This salary contributes on <strong>₹<?= e(indian_number($d['pfWages'])) ?></strong>
                    <?= $d['pfWages'] < $d['basic'] ? 'of ₹' . e(indian_number($d['basic'])) . ' Basic' : '(all of Basic)' ?>.
                </span>
            </div>

            <div class="section-title">📅 Attendance</div>
            <div class="input-row-2">
                <div class="input-group">
                    <label>Working days in month</label>
                    <input type="number" name="workingDays" id="workingDays" class="payroll-field" value="<?= e($in['workingDays']) ?>" step="0.5" min="1" max="31" />
                </div>
                <div class="input-group">
                    <label>Days present</label>
                    <input type="number" name="daysPresent" id="daysPresent" class="payroll-field" value="<?= e($in['daysPresent']) ?>" step="0.5" min="0" max="31" />
                </div>
            </div>
            <div id="attendanceBreakdown" style="font-size:0.75rem; color:#6b7280; margin:-0.25rem 0 0.75rem;"></div>
            <div id="periodWarning" style="display:none; font-size:0.78rem; color:#92400e; background:#fef3c7; border:1px solid #fcd34d; border-radius:6px; padding:0.5rem 0.65rem; margin:0 0 0.75rem;"></div>

            <input type="hidden" name="calc_id" value="<?= e($latestRecordId ?? '') ?>" />

            <div class="action-buttons">
                <?php if (!empty($latestRecordId)): ?>
                    <button type="button" class="btn btn-primary" id="editPayrollBtn">✏️ Edit</button>
                    <button type="submit" class="btn btn-primary" name="calculate" value="1" id="updatePayrollBtn" style="display:none;">💾 Update Payroll</button>
                    <button type="submit" class="btn btn-outline" name="delete_calculation" value="1" onclick="return confirm('Are you sure you want to delete this payroll record?');">🗑 Delete</button>
                <?php else: ?>
                    <button type="submit" class="btn btn-primary" name="calculate" value="1">💾 Save &amp; calculate</button>
                <?php endif; ?>
                <button type="submit" class="btn btn-outline" name="reset_defaults" value="1">↺ reset to default</button>
            </div>
            <div id="errorDisplay" style="margin-top: 0.3rem;">
                <?php if ($error !== null): ?>
                    <span class="error-msg"><?= e($error) ?></span>
                <?php endif; ?>
            </div>
        </form>

        <!-- RIGHT: Salary Slip -->
        <div class="slip-panel">
            <div class="slip-header">
                <h3>📄 salary slip</h3>
                <span class="badge">all inputs · per‑day</span>
            </div>
            <div id="slipContainer">
                <?php if ($error !== null): ?>
                    <div class="salary-slip">
                        <div style="text-align: center; color: #687e96; padding: 1.2rem 0;">
                            <span style="font-size: 1.1rem;">📊</span><br />
                            Fix the highlighted input &amp; click <strong>“Calculate”</strong>
                        </div>
                    </div>
                <?php else: ?>
                    <?php
                    $caLabel = $d['caIsHigh']
                        ? 'CA (&gt;' . inr($in['caThreshold']) . ')'
                        : 'CA (<' . inr($in['caThreshold']) . ')';
                    ?>
                    <div class="salary-slip">
                        <!-- Section headers below mirror the input panel on the left, in the same order. -->

                        <div class="slip-row total" style="margin-top:0; border-top:none;">
                            <span class="label"><strong>Gross Salary (₹) <span style="font-weight:400; font-size:0.8rem; color:#6b88a2;">monthly</span></strong></span>
                            <span class="value"><strong><?= e(inr($d['gross'])) ?></strong></span>
                        </div>

                        <div class="slip-head">🔹 ESI (Employee State Insurance)</div>
                        <div class="slip-row"><span class="label">ESI threshold</span><span class="value"><?= e(inr($in['esiThreshold'])) ?></span></div>
                        <div class="slip-row"><span class="label">ESI % <span class="sub">of Gross</span></span><span class="value"><?= $d['esiApplies'] ? e(pct($in['esiPct'])) . '%' : '— not applicable' ?></span></div>
                        <div class="calc-line">
                            <?php if ($d['esiApplies']): ?>
                                <?= e(inr($d['gross'])) ?> × <?= e(pct($in['esiPct'])) ?>% = <strong><?= e(inr($d['esi'])) ?></strong>
                            <?php else: ?>
                                Gross <?= e(inr($d['gross'])) ?> &gt; threshold <?= e(inr($in['esiThreshold'])) ?> → <strong>₹ 0</strong>
                            <?php endif; ?>
                        </div>
                        <div class="slip-row"><span class="label">ESI amount</span><span class="value"><?= e(inr($d['esi'])) ?></span></div>
                        <?php deduction_footer('ESI', $d['gross'], $d['esi']); ?>

                        <div class="slip-head">🔹 Basic</div>
                        <div class="slip-row"><span class="label">Basic % <span class="sub">of Gross</span></span><span class="value"><?= e(pct($in['basicPct'])) ?>%</span></div>
                        <div class="calc-line"><?= e(inr($d['gross'])) ?> × <?= e(pct($in['basicPct'])) ?>% = <strong><?= e(inr($d['basic'])) ?></strong></div>
                        <div class="slip-row"><span class="label">Basic amount</span><span class="value"><?= e(inr($d['basic'])) ?></span></div>
                        <?php allocation_footer('Basic', $d['gross'], $d['basic']); ?>

                        <div class="slip-head">🔹 HRA (House Rent Allowance)</div>
                        <div class="slip-row"><span class="label">HRA % <span class="sub">of Basic</span></span><span class="value"><?= e(pct($in['hraPct'])) ?>%</span></div>
                        <div class="calc-line"><?= e(inr($d['basic'])) ?> × <?= e(pct($in['hraPct'])) ?>% = <strong><?= e(inr($d['hra'])) ?></strong></div>
                        <div class="slip-row"><span class="label">HRA amount</span><span class="value"><?= e(inr($d['hra'])) ?></span></div>
                        <?php allocation_footer('HRA', $d['allocAfterBasic'], $d['hra']); ?>

                        <div class="slip-head">🔹 CA (Conveyance Allowance)</div>
                        <div class="slip-row"><span class="label">CA threshold salary</span><span class="value"><?= e(inr($in['caThreshold'])) ?></span></div>
                        <div class="slip-row"><span class="label">Slab applied</span><span class="value"><?= $d['caIsHigh'] ? 'Gross &gt; threshold' : 'Gross &le; threshold' ?></span></div>
                        <div class="calc-line">Gross <?= e(inr($d['gross'])) ?> <?= $d['caIsHigh'] ? '&ge;' : '&lt;' ?> <?= e(inr($in['caThreshold'])) ?> → <?= $d['caIsHigh'] ? 'high' : 'low' ?> slab = <strong><?= e(inr($d['ca'])) ?></strong></div>
                        <div class="slip-row"><span class="label"><?= e($caLabel) ?></span><span class="value"><?= e(inr($d['ca'])) ?></span></div>
                        <?php allocation_footer('CA', $d['allocAfterHra'], $d['ca']); ?>
                        <div class="carry-note">↳ Nothing left to allocate after this — the remaining <strong><?= e(inr($d['allocAfterCa'])) ?></strong> becomes <strong>Others</strong> below</div>

                        <div class="slip-head">🔹 Others</div>
                        <div class="slip-row"><span class="label">Formula</span><span class="value" style="font-weight:500;">Gross − (Basic + HRA + CA)</span></div>
                        <div class="calc-line">
                            <div class="step">Gross <strong><?= e(inr($d['gross'])) ?></strong></div>
                            <div class="step">− Basic <?= e(inr($d['basic'])) ?></div>
                            <div class="step">− HRA <?= e(inr($d['hra'])) ?></div>
                            <div class="step">− CA <?= e(inr($d['ca'])) ?></div>
                            <div class="step">(deducted together: <?= e(inr($d['fixedComponents'])) ?>)</div>
                            <div class="step result">Others = <strong><?= e(inr($d['others'])) ?></strong></div>
                        </div>
                        <div class="carry-note">↳ Same figure as “Unallocated gross after CA” above — carried down from the CA section</div>
                        <div class="slip-row"><span class="label">Others amount</span><span class="value"><?= e(inr($d['others'])) ?></span></div>

                        <?php if ($d['overAllocated'] > 0): ?>
                            <div class="alloc-warn">
                                ⚠️ Basic + HRA + CA come to <?= e(inr($d['fixedComponents'])) ?>, which exceeds the
                                <?= e(inr($d['gross'])) ?> gross by <?= e(inr($d['overAllocated'])) ?> — so Others is negative.
                                Lower the Basic % or HRA %.
                            </div>
                        <?php endif; ?>

                        <div class="slip-head">🔹 PT (Professional Tax)</div>
                        <div class="slip-row"><span class="label">PT <span class="sub">fixed</span></span><span class="value"><?= e(inr($d['pt'])) ?></span></div>
                        <div class="calc-line">Fixed amount, not a percentage = <strong><?= e(inr($d['pt'])) ?></strong></div>
                        <?php deduction_footer('PT', $d['afterEsi'], $d['pt']); ?>

                        <div class="slip-head">🔹 PF (Provident Fund)</div>
                        <div class="slip-row"><span class="label">PF % <span class="sub">of Basic</span></span><span class="value"><?= e(pct($in['pfPct'])) ?>%</span></div>
                        <div class="calc-line"><?= e(inr($d['basic'])) ?> × <?= e(pct($in['pfPct'])) ?>% = <strong><?= e(inr($d['pf'])) ?></strong></div>
                        <div class="slip-row"><span class="label">PF amount</span><span class="value"><?= e(inr($d['pf'])) ?></span></div>
                        <?php deduction_footer('PF', $d['afterPt'], $d['pf']); ?>

                        <div class="grand-summary">
                            <div class="slip-row"><span class="label">Gross Salary</span><span class="value"><?= e(inr($d['gross'])) ?></span></div>
                            <div class="slip-row"><span class="label">Total Deductions <span class="sub">PF + ESI + PT</span></span><span class="value minus">− <?= e(inr($d['totalDeductions'])) ?></span></div>
                            <div class="deduct-rule" style="border-top-color:#9fbdd8;"></div>
                            <div class="slip-row"><span class="label"><strong>Gross After Deductions</strong></span><span class="value"><strong><?= e(inr($d['netSalary'])) ?></strong></span></div>
                        </div>

                        <div class="highlight-net">
                            <span class="label">✅ Net Salary (full month)</span>
                            <span class="value"><?= e(inr($d['netSalary'])) ?></span>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <?php if ($error === null): ?>
                <!-- ========== SALARY PREVIEW ========== -->
                <div id="salaryPreview" style="display:none;">
                    <div class="preview-title">📋 Salary Details & Preview</div>
                    <div id="previewEmployee" class="preview-employee">
                        <span><strong>Name:</strong> <span id="previewName">—</span></span>
                        <span><strong>Card ID:</strong> <span id="previewCardId">—</span></span>
                        <span><strong>Designation:</strong> <span id="previewDesignation">—</span></span>
                        <span><strong>PAN:</strong> <span id="previewPan">—</span></span>
                    </div>
                    <div id="previewCtc" class="preview-ctc">
                        <span><strong>Annual CTC:</strong> <span id="previewAnnualCtc">₹0</span></span>
                        <span><strong>Monthly Salary:</strong> <span id="previewMonthlySalary">₹0</span></span>
                    </div>
                    <div id="previewSlip" class="preview-slip">
                        <!-- dynamically filled -->
                    </div>

                    <!-- Overtime, a bonus, an advance being recovered: the same
                         lines the payroll screen holds for this month. -->
                    <div id="extrasPanel" style="display:none; margin-top:0.8rem; border-top:1px dashed #cbd8e4; padding-top:0.6rem;">
                        <div style="display:flex; align-items:center; margin-bottom:0.4rem;">
                            <strong style="font-size:0.85rem; color:#3a5c78;">Extra pay this month</strong>
                            <button type="button" id="extrasAdd"
                                    style="margin-left:auto; font-size:0.75rem; border:1px solid #cbd8e4; background:#fff; border-radius:0.4rem; padding:0.15rem 0.5rem; cursor:pointer;">
                                + add
                            </button>
                        </div>
                        <div id="extrasList" style="font-size:0.8rem;"></div>
                        <p id="extrasNote" style="font-size:0.72rem; color:#8a94a6; margin:0.3rem 0 0;"></p>
                    </div>
                </div>
            <?php endif; ?>

            <div class="footnote">
                <strong>Per‑day salary</strong> = Net Salary ÷ Working days &nbsp;·&nbsp;
                <strong>Net payable</strong> = Per‑day × Days present
            </div>
        </div>
    </div>
</div>

<script>
    // -----------------------------------------------------------------------
    // Main script: employee lookup, preview generation, live updates
    // -----------------------------------------------------------------------
    (function () {
        const byId = (id) => document.getElementById(id);
        const num = (el) => parseFloat(el.value) || 0;

        // This page's own address, for its AJAX lookups: served through the
        // application's authenticated route it is not "Reductions.php".
        const SELF_URL = <?= json_encode($selfUrl) ?>;

        // DOM refs for employee form
        const employeeCardId = byId('employee_card_id');
        const employeeName = byId('emp_name');
        const employeeDesignation = byId('emp_designation');
        const employeePan = byId('emp_pan');
        const joiningDateInput = byId('employee_joining_date');
        const monthSelect = byId('payslip_month');
        const yearSelect = byId('payslip_year');
        const periodInput = byId('period');
        const generateBtn = byId('generatePayslipBtn');
        const payslipForm = byId('payslipForm');

        // Preview elements
        const previewDiv = byId('salaryPreview');
        const previewName = byId('previewName');
        const previewCardId = byId('previewCardId');
        const previewDesignation = byId('previewDesignation');
        const previewPan = byId('previewPan');
        const previewAnnualCtc = byId('previewAnnualCtc');
        const previewMonthlySalary = byId('previewMonthlySalary');
        const previewSlip = byId('previewSlip');
        // The panel talks to the same endpoints the payroll screen uses.
        const EXTRA_ADD_URL = <?= json_encode(function_exists('route') ? route('payroll-run.component.add') : '') ?>;
        const EXTRA_REMOVE_URL = <?= json_encode(function_exists('route') ? route('payroll-run.component.remove') : '') ?>;
        const CSRF_TOKEN = <?= json_encode(function_exists('csrf_token') ? csrf_token() : '') ?>;

        // Calculator inputs
        const grossInput = byId('grossInput');
        const basicPct = byId('basicPct');
        const hraPct = byId('hraPct');
        const caThreshold = byId('caThreshold');
        const caHigh = byId('caHigh');
        const caLow = byId('caLow');
        const esiThreshold = byId('esiThreshold');
        const esiPct = byId('esiPct');
        const pfPct = byId('pfPct');
        const ptInput = byId('ptInput');
        const workingDays = byId('workingDays');
        const daysPresent = byId('daysPresent');

        // Hidden fields for form submission
        const hf = {
            basic_pct: byId('hf_basic_pct'),
            hra_pct: byId('hf_hra_pct'),
            ca_threshold: byId('hf_ca_threshold'),
            ca_high: byId('hf_ca_high'),
            ca_low: byId('hf_ca_low'),
            esi_threshold: byId('hf_esi_threshold'),
            esi_pct: byId('hf_esi_pct'),
            pf_pct: byId('hf_pf_pct'),
            pf_wage_ceiling: byId('hf_pf_wage_ceiling'),
            esi_employer_pct: byId('hf_esi_employer_pct'),
            employer_pf: byId('hf_employer_pf'),
            employer_esi: byId('hf_employer_esi'),
            gross: byId('hf_gross'),
            basic: byId('hf_basic'),
            hra: byId('hf_hra'),
            ca: byId('hf_ca'),
            others: byId('hf_others'),
            pf: byId('hf_pf'),
            esi: byId('hf_esi'),
            pt: byId('hf_pt'),
            lop: byId('hf_lop'),
            per_day: byId('hf_per_day'),
            net_payable: byId('hf_net_payable'),
            working_days: byId('hf_working_days'),
            days_present: byId('hf_days_present'),
            lop_days: byId('hf_lop_days'),
            tds: byId('hf_tds'),
            total_deductions: byId('hf_total_deductions'),
            net_salary: byId('hf_net_salary'),
            attendance_net: byId('hf_attendance_net'),
        };

        const monthNames = [
            'January','February','March','April','May','June',
            'July','August','September','October','November','December'
        ];

        // Store employee data for preview generation
        let currentEmployeeData = null;

        // -------------------------------------------------------------------
        // Utility functions (client-side Indian number formatting)
        // -------------------------------------------------------------------
        function indianNumber(value) {
            value = Math.round(value);
            let sign = value < 0 ? '-' : '';
            let s = String(Math.abs(value));
            if (s.length <= 3) return sign + s;
            let last3 = s.slice(-3);
            let rest = s.slice(0, -3);
            rest = rest.replace(/\B(?=(\d{2})+(?!\d))/g, ',');
            return sign + rest + ',' + last3;
        }

        function fmtInr(value) {
            return '₹ ' + indianNumber(value);
        }

        function pctStr(value) {
            let s = Number(value).toFixed(2);
            s = s.replace(/\.?0+$/, '');
            return s === '' ? '0' : s;
        }

        // -------------------------------------------------------------------
        // Date / period helpers
        // -------------------------------------------------------------------
        function setPeriod() {
            const monthIndex = parseInt(monthSelect.value, 10);
            const year = yearSelect.value;
            if (monthIndex && year) {
                periodInput.value = monthNames[monthIndex - 1] + ' - ' + year;
            }

            // Working Days / Days Present are period-specific — re-fetch real attendance
            // whenever the selected month/year changes for the currently-looked-up employee.
            if (currentEmployeeData && currentEmployeeData.user_id && monthIndex && year) {
                refreshAttendance(currentEmployeeData.user_id, monthIndex, year);
            }
        }

        // -------------------------------------------------------------------
        // Working Days / Days Present (AJAX) — fetched from real attendance data
        // for the resolved employee + selected period, instead of sitting at
        // whatever was hardcoded or left over from the last payslip generated
        // for any employee.
        // -------------------------------------------------------------------
        function refreshAttendance(userId, month, year) {
            return fetch(SELF_URL + '?ajax=lookup_attendance&user_id=' + encodeURIComponent(userId)
                + '&month=' + encodeURIComponent(month) + '&year=' + encodeURIComponent(year))
                .then((res) => res.json())
                .then((data) => {
                    if (!data.success) {
                        return;
                    }

                    workingDays.value = data.working_days;
                    daysPresent.value = data.days_present;

                    // Where Days Present comes from, so a deduction can be explained.
                    const b = data.breakdown;
                    const note = byId('attendanceBreakdown');

                    // A month still running pays only the days that have happened,
                    // so absence in the days left is never deducted.
                    const warn = byId('periodWarning');
                    if (warn) {
                        const now = new Date();
                        const running = parseInt(year, 10) === now.getFullYear() && parseInt(month, 10) === now.getMonth() + 1;
                        warn.style.display = running ? 'block' : 'none';
                        warn.textContent = running
                            ? '⚠ ' + monthNames[month - 1] + ' is still running. This payslip covers the '
                                + data.working_days + ' days so far; anything that happens in the rest of the month '
                                + 'will not be counted. Generate after the month ends, or revoke and reissue then.'
                            : '';
                    }
                    if (note && b) {
                        const parts = [['worked', b.worked], ['week-off', b.week_off], ['holiday', b.holiday],
                            ['paid leave', b.paid_leave], ['LOP leave', b.lop_leave], ['absent', b.absent],
                            ['before joining / after leaving', b.not_employed]]
                            .filter((p) => p[1] > 0).map((p) => p[1] + ' ' + p[0]);
                        const lopDays = Math.max(0, data.working_days - data.days_present);
                        note.textContent = parts.join(' · ') + (lopDays > 0 ? ' → ' + lopDays + ' unpaid day' + (lopDays === 1 ? '' : 's') : '');
                    }

                    // The elements belong to the month just fetched, so they
                    // travel with the employee the preview is drawn from.
                    if (currentEmployeeData) {
                        currentEmployeeData.extras = data.extras || [];
                        currentEmployeeData.extraRows = data.extra_rows || [];
                    }

                    renderExtrasPanel();

                    if (previewDiv.style.display !== 'none' && currentEmployeeData && currentEmployeeData.ctc > 0) {
                        generateSalaryPreview(currentEmployeeData.ctc, currentEmployeeData);
                    }
                })
                .catch(() => {});
        }

        // -------------------------------------------------------------------
        // Extra pay elements for the month on screen
        // -------------------------------------------------------------------
        function renderExtrasPanel() {
            const panel = byId('extrasPanel');

            if (!panel) { return; }

            if (!currentEmployeeData || !currentEmployeeData.user_id) {
                panel.style.display = 'none';

                return;
            }

            panel.style.display = 'block';
            const rows = currentEmployeeData.extraRows || [];
            const list = byId('extrasList');

            if (!rows.length) {
                list.innerHTML = '<span style="color:#8a94a6">Nothing extra this month.</span>';
            } else {
                list.innerHTML = rows.map(function (row) {
                    const sign = row.kind === 'earning' ? '+' : '−';
                    const colour = row.kind === 'earning' ? '#1d9e6f' : '#d0342c';
                    const remove = row.payslip_id
                        ? '<span style="color:#8a94a6; font-size:0.72rem">on a payslip</span>'
                        : '<a href="#" class="extras-remove" data-id="' + row.id + '" style="color:#8a94a6">&times;</a>';

                    return '<div style="display:flex; align-items:center; gap:0.5rem; padding:0.15rem 0;">' +
                        '<span style="flex:1">' + escapeHtml(row.label) + '</span>' +
                        '<span style="color:' + colour + '">' + sign + fmtInr(row.amount) + '</span>' +
                        remove + '</div>';
                }).join('');
            }

            byId('extrasNote').textContent = rows.length
                ? 'These go on to the payslip as their own lines when it is generated.'
                : 'Overtime, a bonus, an advance: added here or on the Pay Elements screen.';
        }

        function escapeHtml(value) {
            const div = document.createElement('div');
            div.textContent = value == null ? '' : value;

            return div.innerHTML;
        }

        function refreshExtras() {
            if (!currentEmployeeData || !currentEmployeeData.user_id) { return; }

            const month = parseInt(monthSelect.value, 10);
            const year = yearSelect.value;

            if (!month || !year) { return; }

            refreshAttendance(currentEmployeeData.user_id, month, year);
        }

        document.addEventListener('click', function (event) {
            const add = event.target.closest('#extrasAdd');

            if (add) {
                event.preventDefault();
                addExtra();

                return;
            }

            const remove = event.target.closest('.extras-remove');

            if (remove) {
                event.preventDefault();
                removeExtra(remove.getAttribute('data-id'));
            }
        });

        function addExtra() {
            if (!currentEmployeeData || !currentEmployeeData.user_id) { return; }

            const kind = window.prompt('Pay extra or deduct? Type E to pay extra, D to deduct.', 'E');

            if (kind === null) { return; }

            const label = window.prompt('What is it? e.g. Overtime');

            if (!label || !label.trim()) { return; }

            const amount = parseFloat(window.prompt('Amount'));

            if (!amount || amount <= 0) { return; }

            fetch(EXTRA_ADD_URL, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': CSRF_TOKEN
                },
                body: JSON.stringify({
                    user_id: currentEmployeeData.user_id,
                    month: parseInt(monthSelect.value, 10),
                    year: parseInt(yearSelect.value, 10),
                    kind: kind.trim().toUpperCase().charAt(0) === 'D' ? 'deduction' : 'earning',
                    label: label.trim(),
                    amount: amount
                })
            }).then(function (res) { return res.json(); }).then(function (data) {
                window.alert(data.message || 'Added.');
                refreshExtras();
            }).catch(function () { window.alert('Could not add it.'); });
        }

        function removeExtra(id) {
            if (!window.confirm('Remove this line?')) { return; }

            fetch(EXTRA_REMOVE_URL, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': CSRF_TOKEN
                },
                body: JSON.stringify({ id: id })
            }).then(function (res) { return res.json(); }).then(function (data) {
                if (data.status !== 'success') { window.alert(data.message || 'Could not remove it.'); }

                refreshExtras();
            }).catch(function () { window.alert('Could not remove it.'); });
        }

        function populateYears(joinYear) {
            const currentYear = new Date().getFullYear();
            const startYear = joinYear || currentYear;
            // Up to this year only: payroll cannot run ahead of time.
            const endYear = Math.max(currentYear, startYear);
            yearSelect.innerHTML = '';
            for (let year = startYear; year <= endYear; year++) {
                const opt = document.createElement('option');
                opt.value = year;
                opt.textContent = year;
                yearSelect.appendChild(opt);
            }
            yearSelect.value = currentYear;
        }

        function populateMonths(joinYear, joinMonth) {
            monthSelect.innerHTML = '';
            const selectedYear = parseInt(yearSelect.value, 10);
            let startMonth = 1;
            if (joinYear && selectedYear === joinYear && joinMonth) {
                startMonth = joinMonth;
            }
            // Only months that have started can be paid; a future month has no
            // attendance behind it and would produce a payslip worth nothing.
            const today = new Date();
            const lastMonth = selectedYear === today.getFullYear() ? today.getMonth() + 1 : 12;

            for (let m = startMonth; m <= lastMonth; m++) {
                const opt = document.createElement('option');
                opt.value = m;
                opt.textContent = monthNames[m - 1];
                monthSelect.appendChild(opt);
            }
            const currentMonth = new Date().getMonth() + 1;
            if (selectedYear === new Date().getFullYear() && currentMonth >= startMonth) {
                monthSelect.value = currentMonth;
            }
        }

        function onYearChange() {
            const joinDate = joiningDateInput.value ? new Date(joiningDateInput.value) : null;
            const joinYear = joinDate ? joinDate.getFullYear() : null;
            const joinMonth = joinDate ? joinDate.getMonth() + 1 : null;
            populateMonths(joinYear, joinMonth);
            setPeriod();
        }

        // -------------------------------------------------------------------
        // Employee lookup (AJAX)
        // -------------------------------------------------------------------
        function lookupEmployee() {
            const cardId = employeeCardId.value.trim();
            if (!cardId) {
                employeeName.value = '';
                employeeDesignation.value = '';
                employeePan.value = '';
                joiningDateInput.value = '';
                populateYears();
                populateMonths();
                setPeriod();
                previewDiv.style.display = 'none';
                currentEmployeeData = null;
                return;
            }

            fetch(SELF_URL + '?ajax=lookup_employee&card_id=' + encodeURIComponent(cardId))
                .then((res) => res.json())
                .then((data) => {
                    if (data.success) {
                        currentEmployeeData = data;
                        employeeName.value = data.employee_name || '';
                        employeeCardId.value = data.employee_card_id || cardId;
                        employeeDesignation.value = data.designation_name || '';
                        employeePan.value = data.pan || '';
                        joiningDateInput.value = data.joining_date || '';
                        const joinYear = data.joining_date ? new Date(data.joining_date).getFullYear() : null;
                        const joinMonth = data.joining_date ? new Date(data.joining_date).getMonth() + 1 : null;
                        populateYears(joinYear);
                        populateMonths(joinYear, joinMonth);
                        setPeriod();

                        // Basic % is per-employee (users.basic_for_payroll), not the
                        // shared calculator default — only override when the employee
                        // actually has a value set, so employees without one still fall
                        // back to the current field value.
                        if (data.basic_for_payroll !== null && data.basic_for_payroll !== undefined && data.basic_for_payroll !== '') {
                            basicPct.value = data.basic_for_payroll;
                        }

                        // Show preview if CTC > 0
                        if (data.ctc && data.ctc > 0) {
                            generateSalaryPreview(data.ctc, data);
                            previewDiv.style.display = 'block';
                        } else {
                            previewDiv.style.display = 'none';
                        }
                    } else {
                        currentEmployeeData = null;
                        employeeName.value = '';
                        employeeDesignation.value = '';
                        employeePan.value = '';
                        joiningDateInput.value = '';
                        populateYears();
                        populateMonths();
                        setPeriod();
                        previewDiv.style.display = 'none';
                    }
                })
                .catch(() => {
                    currentEmployeeData = null;
                    employeeName.value = '';
                    employeeDesignation.value = '';
                    employeePan.value = '';
                    joiningDateInput.value = '';
                    populateYears();
                    populateMonths();
                    setPeriod();
                    previewDiv.style.display = 'none';
                });
        }

        // -------------------------------------------------------------------
        // Generate salary preview (client-side calculation)
        // -------------------------------------------------------------------
        // Professional tax bands, as configured on the server: the first band
        // the salary falls within decides it, the last one covers everything
        // above, and with no bands the flat figure stands.
        const PT_SLABS = <?= json_encode(is_string($in['ptSlabs']) ? json_decode($in['ptSlabs'], true) : $in['ptSlabs']) ?: 'null' ?>;

        function ptForSalary(gross, flat) {
            if (!Array.isArray(PT_SLABS) || PT_SLABS.length === 0) { return flat; }
            for (const slab of PT_SLABS) {
                if (slab.upto === null || slab.upto === '' || gross <= parseFloat(slab.upto)) {
                    return parseFloat(slab.amount);
                }
            }
            return flat;
        }

        function generateSalaryPreview(ctc, employeeData) {
            // Read current calculator values
            const monthlyGross = ctc / 12;

            const pBasic = num(basicPct);
            const pHra = num(hraPct);
            const thresh = num(caThreshold);
            const caHighVal = num(caHigh);
            const caLowVal = num(caLow);
            const esiThresh = num(esiThreshold);
            const esiPctVal = num(esiPct);
            const pfPctVal = num(pfPct);
            const ptVal = num(ptInput);
            const wDays = parseFloat(workingDays.value) || 0;
            const dPresent = parseFloat(daysPresent.value) || 0;

            // Compute components
            const basic = monthlyGross * (pBasic / 100);
            const hra = basic * (pHra / 100);
            const ca = monthlyGross > thresh ? caHighVal : caLowVal;
            const others = monthlyGross - (basic + hra + ca);
            const esi = (monthlyGross <= esiThresh) ? monthlyGross * (esiPctVal / 100) : 0;

            // Same statutory rules the server applies: PF on basic up to the
            // wage ceiling, a larger employer ESI share, and PT by salary band.
            const ceiling = parseFloat(byId('pfWageCeiling') ? byId('pfWageCeiling').value : 0) || 0;
            const pfWages = ceiling > 0 ? Math.min(basic, ceiling) : basic;
            const pf = pfWages * (pfPctVal / 100);
            const employerEsiPct = parseFloat(byId('esiEmployerPct') ? byId('esiEmployerPct').value : 0) || esiPctVal;
            const employerEsi = esi > 0 ? monthlyGross * (employerEsiPct / 100) : 0;
            const employerPf = pf;
            const ptDue = ptForSalary(monthlyGross, ptVal);
            // Income tax comes off with the statutory deductions, so the daily
            // rate and any unpaid day are worked out on the taxed figure — the
            // same order the payroll screen and the calculation sheet use.
            const tds = Math.max(0, parseFloat(employeeData.monthly_tds) || 0);
            const totalDeductions = ptDue + esi + pf + tds;
            const netSalary = monthlyGross - totalDeductions;
            const perDay = wDays > 0 ? netSalary / wDays : 0;
            const attendanceNet = perDay * dPresent;
            const lop = Math.max(0, netSalary - attendanceNet);
            const lopDays = Math.max(0, wDays - dPresent);

            // Update employee details in preview
            previewName.textContent = employeeData.employee_name || '—';
            previewCardId.textContent = employeeData.employee_card_id || '—';
            previewDesignation.textContent = employeeData.designation_name || '—';
            previewPan.textContent = employeeData.pan || '—';
            previewAnnualCtc.textContent = fmtInr(ctc);
            previewMonthlySalary.textContent = fmtInr(monthlyGross);

            // Build preview slip HTML
            let html = '';

            // Gross
            html += `<div class="slip-row total" style="border-top:none; margin-top:0;">
                <span class="label"><strong>Gross Salary (₹) monthly</strong></span>
                <span class="value"><strong>${fmtInr(monthlyGross)}</strong></span>
            </div>`;

            // Earnings
            html += `<div class="slip-head">Earnings</div>`;
            const earnings = [
                ['Basic', basic],
                ['HRA', hra],
                ['Conveyance Allowance', ca],
                ['Others', others]
            ];
            earnings.forEach(row => {
                html += `<div class="slip-row"><span class="label">${row[0]}</span><span class="value">${fmtInr(row[1])}</span></div>`;
            });
            html += `<div class="slip-row total"><span class="label">Total Earnings</span><span class="value">${fmtInr(monthlyGross)}</span></div>`;

            // Deductions
            html += `<div class="slip-head">Deductions</div>`;
            const deductions = [
                ['Employee PF', pf],
                ['Employee ESI', esi],
                ['Professional Tax', ptDue],
                ['Income Tax (TDS)', tds],
                ['Lop', lop]
            ];
            deductions.forEach(row => {
                html += `<div class="slip-row"><span class="label">${row[0]}</span><span class="value">${fmtInr(row[1])}</span></div>`;
            });
            const totalDed = pf + esi + ptDue + tds + lop;
            html += `<div class="slip-row total"><span class="label">Total Deductions</span><span class="value">${fmtInr(totalDed)}</span></div>`;

            // Net Payable
            const netPayable = monthlyGross - totalDed;
            html += `<div class="highlight-net" style="margin-top:0.5rem;">
                <span class="label">✅ Net Payable (attendance)</span>
                <span class="value">${fmtInr(netPayable)}</span>
            </div>`;

            // Attendance details
            html += `<div style="margin-top:0.4rem; font-size:0.8rem; color:#3a5c78; display:flex; justify-content:space-between; flex-wrap:wrap; background:#eef4fa; padding:0.2rem 0.8rem; border-radius:0.6rem;">
                <span>📅 ${wDays} working days · ✅ ${dPresent} present · LOP ${lopDays} days</span>
                <span>💵 Per-day: ${fmtInr(perDay)}</span>
            </div>`;

            // Overtime, a bonus, an advance being recovered. These are held per
            // month on the Pay Elements screen and go on to the payslip when it
            // is issued, each as its own line.
            if (Array.isArray(employeeData.extras) && employeeData.extras.length) {
                const lines = employeeData.extras.map(function (extra) {
                    const sign = extra.kind === 'earning' ? '+' : '−';

                    return `<div class="slip-row"><span class="label">${extra.label}</span>` +
                        `<span class="value">${sign}${fmtInr(extra.amount)}</span></div>`;
                }).join('');

                html += `<div class="slip-head">Also going on this payslip</div>${lines}`;
            }

            previewSlip.innerHTML = html;

            // Update hidden form fields with computed values for submission
            hf.basic_pct.value = pBasic;
            hf.hra_pct.value = pHra;
            hf.ca_threshold.value = thresh;
            hf.ca_high.value = caHighVal;
            hf.ca_low.value = caLowVal;
            hf.esi_threshold.value = esiThresh;
            hf.esi_pct.value = esiPctVal;
            hf.pf_pct.value = pfPctVal;
            hf.gross.value = monthlyGross;
            hf.basic.value = basic;
            hf.hra.value = hra;
            hf.ca.value = ca;
            hf.others.value = others;
            hf.pf.value = pf;
            hf.esi.value = esi;
            hf.pt.value = ptDue;
            hf.pf_wage_ceiling.value = ceiling;
            hf.esi_employer_pct.value = employerEsiPct;
            hf.employer_pf.value = employerPf;
            hf.employer_esi.value = employerEsi;
            hf.lop.value = lop;
            hf.per_day.value = perDay;
            hf.net_payable.value = attendanceNet;
            hf.working_days.value = wDays;
            hf.days_present.value = dPresent;
            hf.lop_days.value = lopDays;
            hf.tds.value = tds;
            hf.total_deductions.value = totalDeductions;
            hf.net_salary.value = netSalary;
            hf.attendance_net.value = attendanceNet;
        }

        // -------------------------------------------------------------------
        // Event listeners
        // -------------------------------------------------------------------
        // Everything in this block belongs to the Generate Payslip form, which only
        // renders in compact mode (?compact=1) — none of these elements exist in the
        // full/direct view, so guard the whole block or these calls throw and would
        // silently break the percentage-calculator JS further down the same script.
        if (employeeCardId) {

        let lookupTimeout = null;

        employeeCardId.addEventListener('input', function () {
            clearTimeout(lookupTimeout);
            lookupTimeout = setTimeout(lookupEmployee, 400);
        });

        employeeCardId.addEventListener('blur', lookupEmployee);
        yearSelect.addEventListener('change', onYearChange);
        monthSelect.addEventListener('change', setPeriod);

        if (employeeCardId.value.trim()) {
            lookupEmployee();
        } else {
            populateYears();
            populateMonths();
            setPeriod();
        }

        // Regenerate preview when any calculator input changes (if CTC already shown)
        const previewInputs = [
            basicPct, hraPct, caThreshold, caHigh, caLow,
            esiThreshold, esiPct, pfPct, ptInput, workingDays, daysPresent
        ];
        previewInputs.forEach(inp => {
            inp.addEventListener('input', function () {
                if (previewDiv.style.display !== 'none' && currentEmployeeData && currentEmployeeData.ctc > 0) {
                    generateSalaryPreview(currentEmployeeData.ctc, currentEmployeeData);
                }
            });
        });

        // Numeric-only validation for all input fields
        function validateNumericInputs() {
            const numericFields = [
                'grossInput', 'basicPct', 'hraPct', 'caThreshold', 'caHigh', 'caLow',
                'ptInput', 'esiThreshold', 'esiPct', 'pfPct', 'workingDays', 'daysPresent'
            ];

            let hasErrors = false;
            const errorMessages = [];

            numericFields.forEach(fieldId => {
                const field = byId(fieldId);
                if (field && field.value.trim()) {
                    const value = field.value.trim();
                    // Check if value is a valid number
                    if (isNaN(value) || value === '') {
                        hasErrors = true;
                        const label = field.previousElementSibling?.textContent || fieldId;
                        errorMessages.push(`${label}: Only numeric values are allowed`);
                        field.style.borderColor = '#d9534f';
                    } else {
                        field.style.borderColor = '';
                    }
                }
            });

            if (hasErrors) {
                const errorDiv = byId('errorDisplay');
                errorDiv.innerHTML = '<span class="error-msg">' +
                    errorMessages.join('<br>') +
                    '</span>';
                return false;
            } else {
                const errorDiv = byId('errorDisplay');
                errorDiv.innerHTML = '';
                return true;
            }
        }

        // Add real-time numeric validation
        const numericFields = [
            'grossInput', 'basicPct', 'hraPct', 'caThreshold', 'caHigh', 'caLow',
            'ptInput', 'esiThreshold', 'esiPct', 'pfPct', 'workingDays', 'daysPresent'
        ];

        numericFields.forEach(fieldId => {
            const field = byId(fieldId);
            if (field) {
                field.addEventListener('input', function() {
                    // Clear error styling when user starts typing valid number
                    if (!isNaN(this.value) && this.value.trim() !== '') {
                        this.style.borderColor = '';
                    }
                });

                // Prevent non-numeric input
                field.addEventListener('keypress', function(e) {
                    const char = String.fromCharCode(e.which);
                    if (!/[0-9.\-]/.test(char)) {
                        e.preventDefault();
                    }
                });
            }
        });

        // Generate button click: ensure hidden fields are set from preview before submit.
        //
        // This always prevents the default submit and re-submits programmatically once
        // ready. Previously it called setPeriod() (which kicks off an async attendance
        // refetch) and then let the browser submit the form immediately afterwards —
        // the fetch had no chance to finish, so the period label would say e.g. "July"
        // while working_days/days_present were still whatever the last-fetched month
        // (e.g. August) had left in the inputs. Awaiting the fetch before submitting
        // keeps the saved payslip's attendance figures in sync with its period.
        generateBtn.addEventListener('click', function (event) {
            event.preventDefault();

            // Validate numeric inputs first
            if (!validateNumericInputs()) {
                return;
            }

            // Validate employee details
            if (!employeeCardId.value.trim() || !employeeName.value.trim() || !employeeDesignation.value.trim() || !employeePan.value.trim()) {
                alert('Please enter a valid Employee Card ID and ensure employee details are filled automatically.');
                return;
            }
            if (!monthSelect.value || !yearSelect.value) {
                alert('Please select month and year for the pay period.');
                return;
            }

            const monthIndex = parseInt(monthSelect.value, 10);
            const year = yearSelect.value;
            periodInput.value = monthNames[monthIndex - 1] + ' - ' + year;

            generateBtn.disabled = true;
            const originalBtnText = generateBtn.textContent;
            generateBtn.textContent = 'Fetching attendance…';

            const finishAndSubmit = function () {
                // Ensure preview is up-to-date and hidden fields are populated with
                // whatever refreshAttendance() just wrote into workingDays/daysPresent.
                if (currentEmployeeData && currentEmployeeData.ctc > 0) {
                    generateSalaryPreview(currentEmployeeData.ctc, currentEmployeeData);
                }

                generateBtn.disabled = false;
                generateBtn.textContent = originalBtnText;
                payslipForm.submit();
            };

            if (currentEmployeeData && currentEmployeeData.user_id) {
                refreshAttendance(currentEmployeeData.user_id, monthIndex, year).then(finishAndSubmit);
            } else {
                finishAndSubmit();
            }
        });

        } // end if (employeeCardId) — Generate Payslip form guard

        // -------------------------------------------------------------------
        // Live previews for "→ Basic = ₹…" hints (already present)
        // -------------------------------------------------------------------
        const basicPreviewSpan = byId('basicPreview');
        const hraPreviewSpan = byId('hraPreview');
        const pfPreviewSpan = byId('pfPreview');
        const othersDisplay = byId('othersDisplay');
        const caSlabNote = byId('caSlabNote');

        function fmtCurrency(val) {
            return '₹ ' + Number(val).toLocaleString('en-IN', { maximumFractionDigits: 0 });
        }

        function updatePreviews() {
            const gross = num(grossInput);
            const basic = gross * (num(basicPct) / 100);
            const hra = basic * (num(hraPct) / 100);
            const threshold = num(caThreshold);
            const isHighSlab = gross > threshold;
            const ca = isHighSlab ? num(caHigh) : num(caLow);

            basicPreviewSpan.textContent = Math.round(basic);
            hraPreviewSpan.textContent = Math.round(hra);
            pfPreviewSpan.textContent = Math.round(basic * (num(pfPct) / 100));
            othersDisplay.value = fmtCurrency(Math.round(gross - (basic + hra + ca)));

            caSlabNote.innerHTML = '✅ Gross ' + fmtCurrency(gross)
                + (isHighSlab ? ' &ge; ' : ' &lt; ') + fmtCurrency(threshold)
                + ' → CA <strong>' + fmtCurrency(ca) + '</strong>';
        }

        document.querySelectorAll('input').forEach((inp) => {
            inp.addEventListener('input', updatePreviews);
        });
    })();

    // -----------------------------------------------------------------------
    // Lock / Edit toggle for the "Payroll · All Input Fields" panel — once a
    // calculation is saved, its fields open readonly; clicking ✏️ Edit is
    // required before they can be changed, and Update Payroll saves them.
    // -----------------------------------------------------------------------
    (function () {
        const hasSavedRecord = <?= !empty($latestRecordId) ? 'true' : 'false' ?>;
        if (!hasSavedRecord) {
            return;
        }

        const fields = document.querySelectorAll('.payroll-field');
        const editBtn = document.getElementById('editPayrollBtn');
        const updateBtn = document.getElementById('updatePayrollBtn');

        fields.forEach((field) => {
            field.setAttribute('readonly', 'readonly');

            field.addEventListener('focus', () => {
                if (field.hasAttribute('readonly')) {
                    alert('Please click the Edit button to modify this payroll record.');
                    field.blur();
                }
            });

            field.addEventListener('mousedown', (event) => {
                if (field.hasAttribute('readonly')) {
                    event.preventDefault();
                    alert('Please click the Edit button to modify this payroll record.');
                }
            });
        });

        if (editBtn) {
            editBtn.addEventListener('click', () => {
                fields.forEach((field) => field.removeAttribute('readonly'));
                editBtn.style.display = 'none';
                if (updateBtn) {
                    updateBtn.style.display = '';
                }
            });
        }
    })();
</script>
</body>
</html>
