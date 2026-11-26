<?php
/**
 * Arowana Salary Slip
 * Server-side (PHP) port of payslips.html.
 *
 * Now fetches data from the payslips table (one row per generated payslip).
 * Accepts ?id=123 to show a specific record, or shows the latest.
 */

// ---------------------------------------------------------------------------
// Include database config (Laravel style)
// ---------------------------------------------------------------------------
$dbConfig = @include __DIR__ . '/config/database.php';
$dbConn = $dbConfig['connections']['mysql'] ?? null;

// Who is looking at this payslip. Served through the application's
// authenticated route the signed-in user decides it; the URL parameter is only
// a fallback for running this page directly (tests, CLI), and it is no longer
// trusted to widen access, since "employee" is the restricted role.
$userRole = isset($_GET['user_role']) ? trim($_GET['user_role']) : 'employee';

if (function_exists('user_roles')) {
    // Whoever may prepare payroll (HR included, not only the admin role) gets
    // the calculator tools: back to calculator, attendance, print, and the
    // "not generated" notice. The same test as the Payslip Calculator page.
    $canPreparePayroll = class_exists(\App\Services\Access\PayrollAccess::class)
        && \App\Services\Access\PayrollAccess::can('prepare');
    $userRole = (in_array('admin', (array) user_roles()) || $canPreparePayroll) ? 'admin' : 'employee';
}

// This page's links back to the calculator, which lives at /account/legacy/...
// when served through the application.
$calculatorUrl = function_exists('route') ? route('legacy.page', ['page' => 'reductions']) : 'Reductions.php';

// ---------------------------------------------------------------------------
// Helper functions
// ---------------------------------------------------------------------------

// Indian digit grouping and amount-in-words, shared with the template
// previews, which draw a template without running this page.
require_once __DIR__ . '/payslip-templates/helpers.php';


if (!function_exists('total_of')) {
    function total_of(array $rows)
    {
        $sum = 0;
        foreach ($rows as $row) {
            $sum += (float) $row[1];
        }
        return $sum;
    }
}

if (!function_exists('total_of_labels')) {
    function total_of_labels(array $rowSets, array $labels)
    {
        $sum = 0;
        foreach ($rowSets as $rows) {
            foreach ($rows as $row) {
                if (in_array($row[0], $labels, true)) {
                    $sum += (float) $row[1];
                }
            }
        }
        return $sum;
    }
}

// ---------------------------------------------------------------------------
// Fetch data from database
// ---------------------------------------------------------------------------
$payslip = null; // will hold the data array

if ($dbConn) {
    try {
        $dsn = "mysql:host={$dbConn['host']};port={$dbConn['port']};dbname={$dbConn['database']};charset={$dbConn['charset']}";
        $pdo = new PDO($dsn, $dbConn['username'], $dbConn['password'] ?? '', [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);

        // If an ID is provided, fetch that record, otherwise fetch the latest
        $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && isset($_POST['from_calculator'])) {
            // One active payslip per employee per month.
            //
            // Generating used to append unconditionally, so pressing the button
            // twice produced two payslips for the same month — one employee
            // accumulated 23 rows for a single period, and only the newest was
            // ever displayed. A month that already holds an active payslip is
            // refused here; revoking or deleting that slip under Generated
            // Payslips releases the month so a corrected one can be issued.
            $guardEmpId  = trim((string) ($_POST['emp_id'] ?? ''));
            $guardPeriod = trim((string) ($_POST['period'] ?? ''));
            $existingSlipId = null;

            if ($guardEmpId !== '' && $guardPeriod !== '') {
                $guardStmt = $pdo->prepare(
                    "SELECT id FROM payslips
                      WHERE emp_id = :empId AND period = :period
                        AND status = 'active' AND deleted_at IS NULL
                      LIMIT 1"
                );
                $guardStmt->execute([':empId' => $guardEmpId, ':period' => $guardPeriod]);
                $existingSlipId = $guardStmt->fetchColumn() ?: null;
            }

            // A month that has not happened yet cannot be paid. The day counts
            // only cover days that have passed, so a future month comes back as
            // nought working days and the payslip collapses to zero pay.
            $periodStart = $guardPeriod !== '' ? strtotime('first day of ' . str_replace(' - ', ' ', $guardPeriod)) : false;

            // The figures arrive from the form, where the day counts can be typed
            // over. They are checked here as well, so a payslip cannot be stored
            // with nought working days (which pays a full month regardless of
            // attendance), more days present than the month holds, or amounts
            // that do not add up to the salary.
            $postedGross = (float) ($_POST['gross'] ?? 0);
            $postedWorking = (float) ($_POST['working_days'] ?? 0);
            $postedPresent = (float) ($_POST['days_present'] ?? 0);
            $postedComponents = (float) ($_POST['basic'] ?? 0) + (float) ($_POST['hra'] ?? 0)
                + (float) ($_POST['ca'] ?? 0) + (float) ($_POST['lta'] ?? 0) + (float) ($_POST['cea'] ?? 0)
                + (float) ($_POST['others'] ?? 0);

            // The cycle must be over and, for anyone on the biometric device, the
            // feed pulled through its last day - the same rule Payroll Run applies.
            $guardBlocker = null;
            $guardUserId = 0;

            if ($periodStart && class_exists(\App\Services\Iapp\Pipeline\PipelineStatus::class)) {
                $guardUserStmt = $pdo->prepare(
                    'SELECT user_id FROM employee_details
                      WHERE CAST(employee_card_id AS CHAR) = CAST(:card AS CHAR) LIMIT 1'
                );
                $guardUserStmt->execute([':card' => $_POST['emp_id'] ?? '']);
                $guardCycle = \App\Services\Payroll\PayPeriod::forMonth((int) date('n', $periodStart), (int) date('Y', $periodStart));
                // Same rules as Payroll Run (AttendanceGate): cycle over, device
                // feed only when payroll uses it, and some attendance in the
                // system unless the employee is "Attendance exempt".
                $guardUserId = (int) $guardUserStmt->fetchColumn();
                $guardCompany = function_exists('company') && is_object(company()) ? (int) company()->id : null;
                $guardBlocked = $guardUserId
                    ? (new \App\Services\Payroll\AttendanceGate())->blocker($guardUserId, $guardCycle, $guardCompany)
                    : null;
                $guardBlocker = $guardBlocked[0] ?? null;
            }

            // Only whether there is a salary at all is checked here; every figure
            // on the payslip is worked out by PayslipWriter (below), not taken
            // from the form.
            $figureError = $postedGross <= 0
                ? 'This employee has no salary on record, so a payslip cannot be valued. Add their CTC first.'
                : null;

            if ($figureError !== null) {
                $payslipBlockedMessage = htmlspecialchars($figureError, ENT_QUOTES, 'UTF-8');
                $id = 0;
            } elseif ($periodStart && $periodStart > strtotime('first day of this month 00:00:00')) {
                $payslipBlockedMessage = htmlspecialchars($guardPeriod, ENT_QUOTES, 'UTF-8')
                    . ' has not started yet, so there is no attendance to pay against.'
                    . ' A payslip can only be issued for the current month or an earlier one.';
                $id = 0;
            } elseif ($guardBlocker) {
                // "No attendance recorded…" is not a matter of waiting, so it gets no "Not yet:".
                $payslipBlockedMessage = (str_starts_with($guardBlocker, 'no attendance') ? '' : 'Not yet: ')
                    . htmlspecialchars(str_starts_with($guardBlocker, 'no attendance') ? ucfirst($guardBlocker) : $guardBlocker, ENT_QUOTES, 'UTF-8');
                $id = 0;

                // The period is still running: show the payslip as it stands today
                // (the same figures as Payroll Run), marked as an estimate. Nothing
                // is saved and it cannot be issued until the period has ended.
                try {
                    if ($guardUserId && isset($guardCycle) && $guardCycle->end->copy()->startOfDay()->gte(now()->startOfDay())) {
                        $preview = app(\App\Services\Payroll\PayslipWriter::class)
                            ->preview((int) $guardUserId, (int) date('n', $periodStart), (int) date('Y', $periodStart));

                        if ($preview['ok']) {
                            $previewRow = $preview['values'] + [
                                'id' => 0, 'payslip_number' => 'Not issued (estimate)', 'approval_status' => 'estimate',
                                'status' => 'active', 'deleted_at' => null, 'leave_encashment' => 0, 'encashed_days' => 0,
                            ];
                            $previewExtras = array_map(fn ($line) => ['kind' => $line->kind, 'label' => $line->label, 'amount' => (float) $line->amount],
                                $preview['extras']);
                            $payslipBlockedMessage .= ' <br>Below is an <strong>estimate</strong> calculated up to '
                                . e(now()->format('d M Y')) . ' (' . (int) $preview['counted'] . ' of ' . (int) $preview['days']
                                . ' days counted). It is not saved and cannot be issued until the period has ended.';
                        }
                    }
                } catch (Throwable $previewError) {
                    $previewRow = null;
                }
            } elseif ($existingSlipId) {
                $payslipBlockedMessage = 'A payslip for '
                    . htmlspecialchars((string) ($_POST['emp_name'] ?? 'this employee'), ENT_QUOTES, 'UTF-8')
                    . ' covering ' . htmlspecialchars($guardPeriod, ENT_QUOTES, 'UTF-8')
                    . ' already exists. Revoke or delete it under Generated Payslips before issuing another.';

                // Show what is already on record for that month rather than a
                // blank page, so the refusal is self-explanatory.
                $id = (int) $existingSlipId;
            } else {
                // Issued by PayslipWriter, exactly as Payroll Run issues it, so a
                // payslip made here and one made in bulk always carry the same
                // values: the salary in force for that month (revisions), the
                // payroll settings' rates, attendance counted the same way (with
                // pending device days written first and OT-bank days), pay
                // elements, loan instalments and leave encashment. It used to be
                // a second copy of the calculation fed by the form's figures, and
                // drifted from Payroll Run (OT-bank days, today's CTC for an old
                // month, a form sent before its attendance loaded).
                $writerResult = ['ok' => false, 'message' => 'This employee could not be found.', 'payslip_id' => null];

                try {
                    if ($guardUserId && $periodStart) {
                        $writerResult = app(\App\Services\Payroll\PayslipWriter::class)->issue(
                            (int) $guardUserId,
                            (int) date('n', $periodStart),
                            (int) date('Y', $periodStart),
                            function_exists('user') && user() ? (int) user()->id : null
                        );
                    }
                } catch (Throwable $writerError) {
                    error_log('Generate One Payslip failed: ' . $writerError->getMessage());
                    $writerResult = ['ok' => false, 'message' => 'The payslip could not be generated. Please try again.', 'payslip_id' => null];
                }

                if ($writerResult['ok'] && $writerResult['payslip_id']) {
                    $id = (int) $writerResult['payslip_id'];
                } else {
                    $payslipBlockedMessage = htmlspecialchars((string) $writerResult['message'], ENT_QUOTES, 'UTF-8');
                    $id = 0;
                }
            }
        }

        $refusedEmpId = (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && isset($_POST['from_calculator']))
            ? trim((string) ($_POST['emp_id'] ?? '')) : '';

        if (!empty($previewRow)) {
            // An estimate for a period still running (built above): not a stored payslip.
            $row = $previewRow;
        } elseif ($id > 0) {
            $stmt = $pdo->prepare("SELECT * FROM payslips WHERE id = :id");
            $stmt->execute([':id' => $id]);
            $row = $stmt->fetch();
        } elseif ($refusedEmpId !== '') {
            // A generation was refused: show that employee's slip for the month
            // asked for, if there is one - never another month's (an older draft
            // under a "not yet" message read as if that month had been refused)
            // and never another employee's.
            $stmt = $pdo->prepare("SELECT * FROM payslips WHERE emp_id = :empId AND deleted_at IS NULL
                                   AND period = :period ORDER BY id DESC LIMIT 1");
            $stmt->execute([':empId' => $refusedEmpId, ':period' => trim((string) ($_POST['period'] ?? ''))]);
            $row = $stmt->fetch();
        } elseif (!empty($_GET['emp_id'])) {
            // A month/year filter was selected on the dashboard — show that specific
            // month's payslip, not just whichever one this employee generated most
            // recently (previously the filter only relabelled the period text below,
            // while always displaying the latest record's figures).
            $filterPeriod = null;
            if (!empty($_GET['month']) && !empty($_GET['year'])) {
                $m = (int) $_GET['month'];
                $y = (int) $_GET['year'];
                if ($m >= 1 && $m <= 12 && $y > 0) {
                    $filterPeriod = date('F - Y', mktime(0, 0, 0, $m, 1, $y));
                }
            }

            if ($filterPeriod !== null) {
                $stmt = $pdo->prepare("SELECT * FROM payslips WHERE emp_id = :empId AND period = :period ORDER BY id DESC LIMIT 1");
                $stmt->execute([':empId' => $_GET['emp_id'], ':period' => $filterPeriod]);
            } else {
                $stmt = $pdo->prepare("SELECT * FROM payslips WHERE emp_id = :empId ORDER BY id DESC LIMIT 1");
                $stmt->execute([':empId' => $_GET['emp_id']]);
            }
            $row = $stmt->fetch();
        } else {
            $stmt = $pdo->query("SELECT * FROM payslips ORDER BY id DESC LIMIT 1");
            $row = $stmt->fetch();
        }

        if ($row) {
            // ------------------------------------------------------------------
            // 1. Get employee details (Name, Card ID, Designation, PAN)
            //    using the stored emp_id (which is employee_card_id)
            // ------------------------------------------------------------------
            $empCardId = $row['emp_id'] ?? $_GET['emp_id'] ?? null;
            $empName = $row['emp_name'] ?? null;
            $empDesignation = $row['emp_designation'] ?? null;
            $empPan = $row['emp_pan'] ?? null;

            // Statutory identifiers; filled in from the employee record below.
            $empUan = null;
            $empEsiNumber = null;
            $empBankAccount = null;
            $empIfsc = null;

            $ctc = null; // annual CTC from users table
            $empBasicForPayroll = null; // per-employee Basic % (users.basic_for_payroll)
            $branchName = null;
            $branchAddress = null;
            $branchEmailsRaw = null;

            if ($empCardId) {
                try {
                    // Fetch from employee_details + users + designations + the
                    // employee's assigned branch (drives the address/email shown
                    // in the payslip footer)
                    $edStmt = $pdo->prepare(
                        "SELECT ed.employee_card_id,
                                ed.pf_account_number,
                                ed.esi_number,
                                ed.personal_account_number,
                                ed.ifsc_code,
                                u.name AS employee_name,
                                u.pan_number AS pan,
                                u.employee_ctc,
                                u.basic_for_payroll,
                                d.name AS designation_name,
                                b.branch_name AS branch_name,
                                b.address AS branch_address,
                                b.emails AS branch_emails
                         FROM employee_details ed
                         LEFT JOIN users u ON u.id = ed.user_id
                         LEFT JOIN designations d ON d.id = ed.designation_id
                         LEFT JOIN branches b ON b.id = ed.branch_id
                         WHERE ed.employee_card_id = :cardId
                         LIMIT 1"
                    );
                    $edStmt->execute([':cardId' => $empCardId]);
                    $ed = $edStmt->fetch();

                    if ($ed) {
                        $empCardId = $ed['employee_card_id'] ?? $empCardId;
                        $empName = $ed['employee_name'] ?? $empName;
                        $empDesignation = $ed['designation_name'] ?? $empDesignation;
                        $empPan = $ed['pan'] ?? $empPan;
                        // Statutory identifiers, shown on the slip where the
                        // employee has them on record.
                        $empUan = $ed['pf_account_number'] ?? null;
                        $empEsiNumber = $ed['esi_number'] ?? null;
                        $empBankAccount = $ed['personal_account_number'] ?? null;
                        $empIfsc = $ed['ifsc_code'] ?? null;
                        $ctc = (float) ($ed['employee_ctc'] ?? 0);
                        $empBasicForPayroll = ($ed['basic_for_payroll'] !== null)
                            ? (float) $ed['basic_for_payroll']
                            : null;
                        $branchName = $ed['branch_name'] ?? null;
                        $branchAddress = $ed['branch_address'] ?? null;
                        $branchEmailsRaw = $ed['branch_emails'] ?? null;
                    }
                } catch (Exception $ex) {
                    // ignore – fallback to stored values
                }
            }

            // Fallback: if still empty, use GET parameters (if viewing directly)
            if (empty($empName) && !empty($_GET['emp_name'])) {
                $empName = $_GET['emp_name'];
                $empCardId = $_GET['emp_id'] ?? $empCardId;
                $empDesignation = $_GET['emp_designation'] ?? $empDesignation;
                $empPan = $_GET['emp_pan'] ?? $empPan;
            }

            // ------------------------------------------------------------------
            // 2. Recompute salary components using CTC/12 as Gross (if CTC > 0)
            // ------------------------------------------------------------------
            // A payslip is a record of what was paid, so it prints the figures
            // saved when it was generated. It used to be rebuilt from the
            // employee's *current* CTC and basic %, which quietly rewrote slips
            // already issued whenever a salary was revised — 24 of the slips on
            // file printed amounts that differed from what was stored. To
            // correct a payslip, revoke it and generate it again, which records
            // the reason and issues a new one.
            $recompute = false;
            if ($recompute) {
                $newGross = $ctc / 12;   // monthly gross from CTC

                // Basic % is per-employee (users.basic_for_payroll), never a fixed/global
                // figure — fall back to the stored calculator value only when the
                // employee has no basic_for_payroll set yet.
                $basicPct = $empBasicForPayroll !== null ? $empBasicForPayroll : (float) ($row['basic_pct'] ?? 0);
                $hraPct = (float) ($row['hra_pct'] ?? 0);
                $caThreshold = (float) ($row['ca_threshold'] ?? 0);
                $caHigh = (float) ($row['ca_high'] ?? 0);
                $caLow = (float) ($row['ca_low'] ?? 0);
                $esiThreshold = (float) ($row['esi_threshold'] ?? 0);
                $esiPct = (float) ($row['esi_pct'] ?? 0);
                $pfPct = (float) ($row['pf_pct'] ?? 0);
                $pt = (float) ($row['pt'] ?? 0);
                $workingDays = (float) ($row['working_days'] ?? 0);
                $daysPresent = (float) ($row['days_present'] ?? 0);

                // Same calculation as in Reductions.php
                $basic = $newGross * ($basicPct / 100);
                $hra = $basic * ($hraPct / 100);
                $ca = ($newGross > $caThreshold) ? $caHigh : $caLow;
                $others = $newGross - ($basic + $hra + $ca);
                $esi = ($newGross <= $esiThreshold) ? $newGross * ($esiPct / 100) : 0;
                $pf = $basic * ($pfPct / 100);
                $totalDeductions = $pt + $esi + $pf;
                $netSalary = $newGross - $totalDeductions;
                $perDay = ($workingDays > 0) ? $netSalary / $workingDays : 0;
                $attendanceNet = $perDay * $daysPresent;
                $lop = max(0, $netSalary - $attendanceNet);

                // Override row values with recomputed ones
                $row['gross'] = $newGross;
                $row['basic'] = $basic;
                $row['hra'] = $hra;
                $row['ca'] = $ca;
                $row['others'] = $others;
                $row['esi'] = $esi;
                $row['pf'] = $pf;
                $row['pt'] = $pt;
                $row['total_deductions'] = $totalDeductions;
                $row['net_salary'] = $netSalary;
                $row['per_day'] = $perDay;
                $row['attendance_net'] = $attendanceNet;
                $row['lop'] = $lop;
                // Also store LOP days for display
                $row['lop_days'] = max(0, $workingDays - $daysPresent);
            } else {
                // Use stored values, but compute LOP and LOP days
                $row['lop'] = max(0, (float) $row['net_salary'] - (float) $row['attendance_net']);
                $row['lop_days'] = max(0, $row['working_days'] - $row['days_present']);
            }
            // ------------------------------------------------------------------

            // Build the payslip array from the (possibly updated) row
            $earnings = [
                ['Basic', (float) $row['basic']],
                ['HRA', (float) $row['hra']],
                ['Conveyance Allowance', (float) $row['ca']],
            ];

            // Leave Travel and Children Education Allowance, on slips issued
            // since they became components; on older slips they are part of
            // the last line, which was called Others then.
            $splitOut = (float) ($row['lta'] ?? 0) > 0 || (float) ($row['cea'] ?? 0) > 0;

            if ((float) ($row['lta'] ?? 0) > 0) {
                $earnings[] = ['Leave Travel Allowance', (float) $row['lta']];
            }

            if ((float) ($row['cea'] ?? 0) > 0) {
                $earnings[] = ['Children Education Allowance', (float) $row['cea']];
            }

            $earnings[] = [$splitOut ? 'Special Allowance' : 'Others', (float) $row['others']];

            // Pay outside the standard salary: overtime, a bonus, arrears, an
            // advance being recovered. Each keeps its own label on the slip, so
            // the employee can see what the extra line is.
            $extraEarnings = [];
            $extraDeductions = [];

            try {
                $componentStmt = $pdo->prepare(
                    'SELECT kind, label, amount FROM payslip_components WHERE payslip_id = :id ORDER BY id'
                );
                $componentStmt->execute([':id' => $row['id']]);

                foreach (!empty($previewRow) ? ($previewExtras ?? []) : $componentStmt->fetchAll() as $component) {
                    $line = [$component['label'], (float) $component['amount']];
                    $component['kind'] === 'earning' ? $extraEarnings[] = $line : $extraDeductions[] = $line;
                }
            } catch (Throwable $componentError) {
                // An older copy without these tables still prints its payslips.
            }

            $earnings = array_merge($earnings, $extraEarnings);

            $deductions = [
                ['Employee PF', (float) $row['pf']],
                ['Employee ESI', (float) $row['esi']],
                ['Professional Tax', (float) $row['pt']],
                ['Income Tax (TDS)', (float) ($row['tds'] ?? 0)],
                ['Lop', (float) $row['lop']],
            ];

            $employer = [
                // The employer's share is its own figure: its ESI is larger than
                // the employee's. Slips issued before that was recorded fall back
                // to the employee's, which is what they were paid on.
                ['Employer PF', (float) ($row['employer_pf'] ?? $row['pf'])],
                ['Employer ESI', (float) ($row['employer_esi'] ?? $row['esi'])],
            ];

            // PF / ESI / PT that do not apply to this employee (Employee -> Payroll: paid by hand,
            // housekeeping) are not printed at all, rather than as a 0.00 line.
            if (!empty($row['user_id']) && class_exists(\App\Services\Payroll\Statutory::class)) {
                $applies = \App\Services\Payroll\Statutory::flags((int) $row['user_id']);
                $notApplicable = array_merge($applies['pf'] ? [] : ['Employee PF', 'Employer PF'],
                    $applies['esi'] ? [] : ['Employee ESI', 'Employer ESI'], $applies['pt'] ? [] : ['Professional Tax']);
                $keep = fn (array $lines) => array_values(array_filter($lines, fn ($l) => !in_array($l[0], $notApplicable, true) || (float) $l[1] != 0.0));
                $deductions = $keep($deductions);
                $employer = $keep($employer);
            }

            $deductions = array_merge($deductions, $extraDeductions);

            $attendance = [
                'Working Days' => (string) $row['working_days'],
                'Days Present' => (string) $row['days_present'],
                'LOP Days' => (string) ($row['lop_days'] ?? 0),
                'Per-day' => 'Rs. ' . indian_number($row['per_day']),
            ];

            // What this employee has been paid so far in the financial year,
            // this payslip included — the figure people are asked for when they
            // file their return, and a check that the year adds up.
            $yearToDate = null;

            try {
                $periodDate = $row['period']
                    ? strtotime('first day of ' . str_replace(' - ', ' ', (string) $row['period']))
                    : false;

                if ($periodDate) {
                    $fyStartYear = (int) date('n', $periodDate) >= 4 ? (int) date('Y', $periodDate) : (int) date('Y', $periodDate) - 1;
                    $fyStart = mktime(0, 0, 0, 4, 1, $fyStartYear);
                    $fyLabel = $fyStartYear . '-' . substr((string) ($fyStartYear + 1), -2);

                    $ytdStmt = $pdo->prepare(
                        "SELECT id, period, basic, hra, ca, lta, cea, others, pf, esi, pt, tds, employer_pf, employer_esi,
                                net_salary, attendance_net, leave_encashment
                           FROM payslips
                          WHERE emp_id = :empId AND status = 'active' AND deleted_at IS NULL"
                    );
                    $ytdStmt->execute([':empId' => $row['emp_id']]);

                    // Extra lines (incentive, OT encashed, an advance recovered) are part of what was paid.
                    $extraStmt = $pdo->prepare("SELECT kind, SUM(amount) total FROM payslip_components WHERE payslip_id = :id GROUP BY kind");

                    $totals = ['gross' => 0.0, 'pf' => 0.0, 'esi' => 0.0, 'pt' => 0.0, 'tds' => 0.0, 'lop' => 0.0, 'net' => 0.0, 'months' => 0];

                    foreach ($ytdStmt->fetchAll() as $slip) {
                        $when = $slip['period'] ? strtotime('first day of ' . str_replace(' - ', ' ', $slip['period'])) : false;

                        // Only this financial year, and nothing later than this slip.
                        if (!$when || $when < $fyStart || $when > $periodDate) {
                            continue;
                        }

                        $slipEarnings = (float) $slip['basic'] + (float) $slip['hra'] + (float) $slip['ca']
                            + (float) $slip['lta'] + (float) $slip['cea'] + (float) $slip['others'];
                        $lop = max(0, (float) $slip['net_salary'] - (float) $slip['attendance_net']);
                        $slipEmployer = (float) ($slip['employer_pf'] ?? $slip['pf']) + (float) ($slip['employer_esi'] ?? $slip['esi']);
                        $extraStmt->execute([':id' => $slip['id']]);
                        $slipExtra = ['earning' => 0.0, 'deduction' => 0.0];
                        foreach ($extraStmt->fetchAll() as $extra) {
                            $slipExtra[$extra['kind']] = (float) $extra['total'];
                        }
                        $slipEarnings += $slipExtra['earning'];

                        $totals['months']++;
                        $totals['gross'] += $slipEarnings;
                        $totals['pf'] += (float) $slip['pf'] + (float) ($slip['employer_pf'] ?? $slip['pf']);
                        $totals['esi'] += (float) $slip['esi'] + (float) ($slip['employer_esi'] ?? $slip['esi']);
                        $totals['pt'] += (float) $slip['pt'];
                        $totals['tds'] += (float) ($slip['tds'] ?? 0);
                        $totals['lop'] += $lop;
                        $totals['net'] += $slipEarnings - ((float) $slip['pf'] + (float) $slip['esi'] + (float) $slip['pt'] + (float) ($slip['tds'] ?? 0) + $lop)
                            - $slipExtra['deduction'] - $slipEmployer + (float) ($slip['leave_encashment'] ?? 0);
                    }

                    if ($totals['months'] > 0) {
                        $yearToDate = ['label' => $fyLabel] + $totals;
                    }
                }
            } catch (Throwable $ytdError) {
                // A payslip must print whether or not the year can be totalled.
                $yearToDate = null;
            }

            // Determine period
            $periodOverride = null;
            if (!empty($_GET['month']) && !empty($_GET['year'])) {
                $m = (int) $_GET['month'];
                $y = (int) $_GET['year'];
                if ($m >= 1 && $m <= 12 && $y > 0) {
                    $periodOverride = date('F - Y', mktime(0, 0, 0, $m, 1, $y));
                }
            }
            $period = $periodOverride ?? $row['period'] ?? date('F - Y');

            // Branch-specific address/email shown in the footer — falls back to
            // the default company details when the employee has no branch assigned
            $companyAddress = '#30-10-12 & 13, 1ST & 2ND FLOOR, BESIDE UNION BANK OF INDIA<br>'
                             . 'BANK STREET, MURALI NAGAR, VISAKHAPATNAM - 530007';
            $note = '<b>Note:</b> If you have any queries, please contact hr2@arowansoft.com Or hr3@arowansoft.com';

            if (!empty($branchAddress)) {
                $companyAddress = nl2br(e($branchAddress));
            }

            if (!empty($branchEmailsRaw)) {
                $branchEmails = json_decode($branchEmailsRaw, true);
                if (is_array($branchEmails) && count($branchEmails) > 0) {
                    $note = '<b>Note:</b> If you have any queries, please contact '
                          . implode(' Or ', array_map('e', $branchEmails));
                }
            }

            // The days this payslip paid for, as payroll counts them, for the
            // people who check it. The payroll month is the one the period ends in.
            $attendanceUrl = (function_exists('route') && !empty($row['user_id']) && !empty($row['period_end']))
                ? route('payroll.attendance', ['userId' => $row['user_id'],
                    'month' => (int) date('n', strtotime($row['period_end'])), 'year' => (int) date('Y', strtotime($row['period_end']))])
                : null;

            // The company's registered name for the Modern header (Classic prints
            // only the logo and the address), from the company record.
            $companyLegalName = 'AROWANA';
            try {
                $companyStmt = $pdo->prepare('SELECT company_name FROM companies WHERE id = :id LIMIT 1');
                $companyStmt->execute([':id' => (int) ($row['company_id'] ?? 1)]);
                $companyLegalName = trim((string) $companyStmt->fetchColumn()) ?: $companyLegalName;
            } catch (PDOException $e) {
                // The short name will do.
            }

            // Build full structure
            $payslip = [
                // Plain-text address (one line per line) for templates that escape
                // what they print; company.address below keeps Classic's HTML.
                'address' => trim(html_entity_decode(strip_tags(str_ireplace(['<br>', '<br/>', '<br />'], "\n", $companyAddress)))),
                'company' => [
                    'name'    => $companyLegalName,
                    'mark'    => '△',
                    'tagline' => 'PRECISION ▲ INNOVATION ▲ SUCCESS',
                    'address' => $companyAddress,
                    'stamp'   => "AROWANA\nCORPORATIONS\nAUTHORIZED",
                ],
                'period' => $period,
                'employee' => [
                    'Name'        => $empName ?? '—',
                    'Emp.ID'      => $empCardId ?? '—',
                    'Payslip No'  => $row['payslip_number'] ?? '—',
                    'Designation' => $empDesignation ?? '—',
                    'PAN No'      => $empPan ?? '—',
                    // Statutory identifiers belong on a payslip; shown only where
                    // the employee has them on record, rather than a row of dashes.
                ] + array_filter([
                    'UAN / PF No' => $empUan,
                    'ESI No'      => $empEsiNumber,
                    'Bank A/c'    => $empBankAccount,
                    'IFSC'        => $empIfsc,
                ], fn ($value) => $value !== null && $value !== ''),
                'earnings' => $earnings,
                'extra_earnings' => $extraEarnings,
                'deductions' => $deductions,
                'employer' => $employer,
                'statutory_rows' => ['Employee PF', 'Employee ESI', 'Professional Tax', 'Employer PF', 'Employer ESI'],
                'months_per_year' => 12,
                'note' => $note,
                'attendance' => $attendance,
                'year_to_date' => $yearToDate,
                // Carried here rather than read from $row at render time: the
                // template reuses $row for its own loops further down.
                'approval_status' => $row['approval_status'] ?? 'approved',
                'branch_name' => $branchName,
                // Paid alongside salary but not part of it: kept out of
                // earnings so CTC and net salary read exactly as before.
                'encashment' => [
                    'amount' => (float) ($row['leave_encashment'] ?? 0),
                    'days' => (float) ($row['encashed_days'] ?? 0),
                ],
            ];

            // "Back" returns to Generate One Payslip with this employee filled in,
            // so another month can be generated straight away.
            if (!empty($empCardId)) {
                $calculatorUrl .= '?' . http_build_query(['compact' => 1, 'card_id' => (string) $empCardId]);
            }

            // "Issue payslip" on a draft, for whoever may approve payslips: the same
            // approval as Payroll -> Issued Payslips -> Approve.
            $issueAction = null;
            try {
                if (($row['approval_status'] ?? '') === 'draft' && ($row['status'] ?? 'active') === 'active'
                    && empty($row['deleted_at']) && function_exists('route')
                    && class_exists(\App\Services\Access\PayrollAccess::class) && \App\Services\Access\PayrollAccess::can('approve')) {
                    $issueAction = [
                        'id' => (int) $row['id'],
                        'url' => route('payslips.generated.approve'),
                        'token' => csrf_token(),
                        'view' => route('legacy.page', 'payslips') . '?id=' . (int) $row['id'],
                    ];
                }
            } catch (Throwable $issueError) {
                $issueAction = null;
            }
        }
    } catch (PDOException $e) {
        error_log('Payslip fetch failed: ' . $e->getMessage());
        // Fall through to show error message
    }
}

// If no data was fetched, show an error
if (!$payslip) {
    ?>
    <!DOCTYPE html>
    <html>
    <head><title>No Payslip Found</title><meta name="viewport" content="width=device-width, initial-scale=1"></head>
    <body style="font-family: Arial, Helvetica, sans-serif; background:#f4f6fa; margin:0; padding:32px 16px;">
        <div style="max-width:720px; margin:0 auto;">
        <?php if (!empty($payslipBlockedMessage)): ?>
            <div style="padding:12px 16px; border:1px solid #f0c36d; background:#fdf7e3; border-radius:6px; color:#7a5d00; font-size:14px; line-height:1.5; margin-bottom:14px;">
                <strong>Payslip not generated.</strong> <?= $payslipBlockedMessage ?>
            </div>
            <p style="font-size:14px; color:#475467;">There is no payslip for this employee for <?= e(trim((string) ($_POST['period'] ?? '')) ?: 'this month') ?> yet.</p>
        <?php else: ?>
            <p>No payroll record found. Please generate a payslip first using the calculator.</p>
        <?php endif; ?>
            <p><a href="<?= e($calculatorUrl . '?' . http_build_query(array_filter(['compact' => 1, 'card_id' => trim((string) ($_POST['emp_id'] ?? ''))]))) ?>" style="color:#1f5b91;">← Back to calculator</a></p>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// ---------------------------------------------------------------------------
// The design this payslip is printed in
//
// Read before the derived figures below, because the loop over employer rows
// there reuses $row as its own variable and would overwrite the payslip row
// this is taken from.
// ---------------------------------------------------------------------------
$payslipTemplate = null;

// A payslip keeps the design it carries only once it has been handed over:
// e-mailed or paid. Until then (draft or approved) it shows the design chosen
// in Payslip Settings now, so changing the setting is seen straight away.
$payslipHandedOver = isset($row) && is_array($row) && (!empty($row['sent_at']) || !empty($row['paid_at']));

if ($payslipHandedOver && !empty($row['template'])) {
    $payslipTemplate = (string) $row['template'];
} elseif ($pdo) {
    try {
        // The payslip's own company where the row gives one; otherwise the
        // only row there is. $companyId is not defined in this file, so it is
        // not relied on.
        $settingCompany = (isset($row) && is_array($row)) ? ($row['company_id'] ?? null) : null;

        $templateStmt = $pdo->prepare(
            'SELECT template FROM payslip_template_settings'
            . ($settingCompany ? ' WHERE company_id = :companyId' : '')
            . ' ORDER BY id LIMIT 1'
        );
        $templateStmt->execute($settingCompany ? [':companyId' => $settingCompany] : []);
        $payslipTemplate = $templateStmt->fetchColumn() ?: null;
    } catch (PDOException $e) {
        // The setting is a preference, not a requirement: without it the
        // classic design is printed, which is what every slip looked like
        // before there was a choice.
        $payslipTemplate = null;
    }
}

// ---------------------------------------------------------------------------
// Derived figures
// ---------------------------------------------------------------------------
$grossEarnings   = total_of($payslip['earnings']);
$grossDeductions = total_of($payslip['deductions']);
$employerTotal   = total_of($payslip['employer']);

$employerAllBlank = true;
foreach ($payslip['employer'] as $row) {
    if ($row[1] !== null) {
        $employerAllBlank = false;
        break;
    }
}

$statutoryTotal = total_of_labels(
    [$payslip['deductions'], $payslip['employer']],
    $payslip['statutory_rows']
);

$netSalary = $grossEarnings - $grossDeductions - $employerTotal;
// CTC is the salary structure: this month's extra lines (incentive, OT
// encashed, reimbursement) are pay, not part of it.
$ctcMonthly = $grossEarnings - total_of($payslip['extra_earnings'] ?? []);
$ctcAnnual  = $ctcMonthly * $payslip['months_per_year'];
$netInWords = amount_in_words($netSalary);

$componentRows = max(count($payslip['earnings']), count($payslip['deductions']));

// ---------------------------------------------------------------------------
// Which design to print in
//
// The payslip's own template if it has one, because a slip must keep the look
// it was issued with; otherwise whatever is set now, which is how every slip
// issued before the setting existed is printed.
// ---------------------------------------------------------------------------
$template = $payslipTemplate ?: 'classic';
$templateFile = __DIR__ . '/payslip-templates/' . $template . '.php';

if (!is_file($templateFile)) {
    $templateFile = __DIR__ . '/payslip-templates/classic.php';
}

include $templateFile;
