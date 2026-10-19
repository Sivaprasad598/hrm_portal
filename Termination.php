<?php
/**
 * Employee Termination
 * Server-side (PHP) port of Termination.html.
 *
 * Lets HR search real employees (from users/employee_details/teams) and
 * record a termination against one of them, persisted to the terminations
 * table. GET ?employee=<user_id> selects who the termination form applies
 * to (search filters are preserved across that reload); POST
 * save_termination inserts the record; POST delete_termination_id removes
 * one from the "Recent Terminations" list. Every save/delete redirects
 * back here (Post/Redirect/Get) so a page refresh never resubmits the form.
 */

// Guarded: the framework defines the same helper when this page is served
// through its authenticated route, and redeclaring it would be fatal.
if (!function_exists('e')) {
    function e($value)
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}

// Native <input type="date"> renders its visible text in whatever locale the
// browser/OS is set to (usually mm/dd/yyyy), which the HTML lang attribute
// does not reliably override in Chromium. So the real date input stays
// hidden-but-functional (for its calendar popup) and this formats the
// unambiguous d/m/Y string shown in the paired visible text field instead.
function displayDate($isoDate)
{
    if (!$isoDate) {
        return '';
    }

    $date = DateTime::createFromFormat('Y-m-d', $isoDate);

    return $date ? $date->format('d/m/Y') : '';
}

$dbConfig = @include __DIR__ . '/config/database.php';
$dbConn = $dbConfig['connections']['mysql'] ?? null;

$pdo = null;
$error = null;
$companyId = null;

if ($dbConn) {
    try {
        $dsn = "mysql:host={$dbConn['host']};port={$dbConn['port']};dbname={$dbConn['database']};charset={$dbConn['charset']}";
        $pdo = new PDO($dsn, $dbConn['username'], $dbConn['password'] ?? '', [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $companyId = (int) ($pdo->query('SELECT id FROM companies ORDER BY id ASC LIMIT 1')->fetchColumn() ?: 0);
    } catch (PDOException $ex) {
        $error = 'Could not connect to the database.';
    }
}

// ---------------------------------------------------------------------------
// Notice period options (configured under Settings > Notice Period Settings)
// so the admin picks a type instead of typing a raw day count. Months are
// converted to calendar days from today, the same way
// ResignationController::configuredNoticePeriodDays() does it, so the
// number here lines up with what the rest of the app means by "N months".
// ---------------------------------------------------------------------------
$noticePeriodOptions = [];

if ($pdo) {
    $stmt = $pdo->prepare('SELECT id, name, months FROM notice_period_settings WHERE company_id = :companyId ORDER BY months ASC');
    $stmt->execute([':companyId' => $companyId]);
    $today = new DateTime('today');

    foreach ($stmt->fetchAll() as $row) {
        $days = (int) $today->diff((clone $today)->modify('+' . (int) $row['months'] . ' months'))->days;

        $noticePeriodOptions[] = [
            'id' => $row['id'],
            'name' => ($row['name'] !== null && $row['name'] !== '') ? $row['name'] : 'Notice Period',
            'months' => (int) $row['months'],
            'days' => $days,
        ];
    }
}

$defaultNoticeDays = !empty($noticePeriodOptions) ? (string) $noticePeriodOptions[0]['days'] : '30';

// ---------------------------------------------------------------------------
// Undo — reverses the termination: restores the employee's Employee Exit
// Status to whatever it was right before this record was created, then
// removes the record. Different from Delete, which only removes the record
// and leaves the employee's current status untouched.
// ---------------------------------------------------------------------------
if ($pdo && $_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['undo_termination_id'])) {
    try {
        $stmt = $pdo->prepare('SELECT user_id, previous_employment_status FROM terminations WHERE id = :id AND company_id = :companyId LIMIT 1');
        $stmt->execute([':id' => (int) $_POST['undo_termination_id'], ':companyId' => $companyId]);
        $record = $stmt->fetch();

        if ($record) {
            if ($record['previous_employment_status']) {
                $restoreStmt = $pdo->prepare('UPDATE employee_details SET employment_status = :status WHERE user_id = :userId AND company_id = :companyId');
                $restoreStmt->execute([
                    ':status' => $record['previous_employment_status'],
                    ':userId' => $record['user_id'],
                    ':companyId' => $companyId,
                ]);
            }

            // Undoing a termination has to clear the last working day it wrote,
            // or the employee stays locked out of the application and unpaid by
            // a termination that no longer exists. Only cleared when it still
            // matches this record, so a date set for another reason survives.
            $clearStmt = $pdo->prepare(
                'UPDATE employee_details ed
                    JOIN terminations t ON t.id = :idForJoin
                     SET ed.last_date = NULL
                   WHERE ed.user_id = :userId AND ed.company_id = :companyId
                     AND ed.last_date = COALESCE(t.last_working_date, t.termination_date)'
            );
            $clearStmt->execute([
                ':idForJoin' => (int) $_POST['undo_termination_id'],
                ':userId' => $record['user_id'],
                ':companyId' => $companyId,
            ]);

            $deleteStmt = $pdo->prepare('DELETE FROM terminations WHERE id = :id AND company_id = :companyId');
            $deleteStmt->execute([':id' => (int) $_POST['undo_termination_id'], ':companyId' => $companyId]);
        }

        header('Location: Termination.php?undone=1');
        exit;
    } catch (PDOException $ex) {
        error_log('Termination undo failed: ' . $ex->getMessage());
        $error = 'Could not undo this termination. Please try again.';
    }
}

// ---------------------------------------------------------------------------
// Delete
// ---------------------------------------------------------------------------
if ($pdo && $_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['delete_termination_id'])) {
    try {
        $stmt = $pdo->prepare('DELETE FROM terminations WHERE id = :id AND company_id = :companyId');
        $stmt->execute([
            ':id' => (int) $_POST['delete_termination_id'],
            ':companyId' => $companyId,
        ]);
        header('Location: Termination.php?deleted=1');
        exit;
    } catch (PDOException $ex) {
        error_log('Termination delete failed: ' . $ex->getMessage());
        $error = 'Could not delete. Please try again.';
    }
}

// ---------------------------------------------------------------------------
// Save
// ---------------------------------------------------------------------------
$formValues = [
    'user_id' => '',
    'termination_date' => date('Y-m-d'),
    'notice_period_days' => $defaultNoticeDays,
    'notice_start_date' => date('Y-m-d'),
    'last_working_date' => date('Y-m-d', strtotime('+' . $defaultNoticeDays . ' days')),
    'notice_served' => 'No',
    'reason' => '',
    'remarks' => '',
];

if ($pdo && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_termination'])) {
    foreach ($formValues as $key => $default) {
        $formValues[$key] = trim($_POST[$key] ?? '');
    }

    $userId = (int) $formValues['user_id'];
    $noticeDays = (int) $formValues['notice_period_days'];

    if (!$userId) {
        $error = 'Please search and select an employee first.';
    } elseif ($formValues['termination_date'] === '') {
        $error = 'Please enter the termination date.';
    } elseif ($noticeDays < 0 || $noticeDays > 365) {
        $error = 'Please enter a valid notice period (0-365 days).';
    } elseif ($formValues['last_working_date'] === '' || $formValues['last_working_date'] < $formValues['termination_date']) {
        $error = 'Proposed Last Working Date must be on or after the Termination Date.';
    } elseif ($formValues['reason'] === '') {
        $error = 'Please select a reason for termination.';
    } else {
        $employeeExists = $pdo->prepare('SELECT employment_status FROM employee_details WHERE user_id = :userId AND company_id = :companyId LIMIT 1');
        $employeeExists->execute([':userId' => $userId, ':companyId' => $companyId]);
        $previousStatusRow = $employeeExists->fetch();

        if ($previousStatusRow === false) {
            $error = 'Selected employee could not be found.';
        } else {
            $previousEmploymentStatus = $previousStatusRow['employment_status'] ?: 'confirmed';

            try {
                // All or nothing: a failure part-way used to leave the
                // termination recorded and the employee off-boarded, with
                // the error shown as though nothing had been saved.
                $pdo->beginTransaction();

                $stmt = $pdo->prepare(
                    'INSERT INTO terminations
                        (company_id, user_id, termination_date, notice_period_days, notice_start_date, last_working_date, notice_served, reason, remarks, previous_employment_status, created_at, updated_at)
                     VALUES
                        (:companyId, :userId, :terminationDate, :noticeDays, :noticeStart, :lastWorkingDate, :noticeServed, :reason, :remarks, :previousStatus, NOW(), NOW())'
                );
                $stmt->execute([
                    ':companyId' => $companyId,
                    ':userId' => $userId,
                    ':terminationDate' => $formValues['termination_date'],
                    ':noticeDays' => $noticeDays,
                    ':noticeStart' => $formValues['notice_start_date'] ?: null,
                    ':lastWorkingDate' => $formValues['last_working_date'],
                    ':noticeServed' => $formValues['notice_served'] === 'Yes' ? 'Yes' : 'No',
                    ':reason' => $formValues['reason'],
                    ':remarks' => $formValues['remarks'] ?: null,
                    ':previousStatus' => $previousEmploymentStatus,
                ]);

                // Immediate termination (no notice period) -> the employee is
                // off-boarded right away, so their Employee Exit Status
                // should reflect that without a separate manual step.
                if ($noticeDays === 0) {
                    $offboardStmt = $pdo->prepare('UPDATE employee_details SET employment_status = "off_boarded" WHERE user_id = :userId AND company_id = :companyId');
                    $offboardStmt->execute([':userId' => $userId, ':companyId' => $companyId]);
                }

                // The last working day belongs on the employee record too.
                // This row was the only place it was written, and everything
                // else reads employee_details: the nightly inactive-employee
                // command, which therefore never deactivated anybody who was
                // terminated, and payroll, which counted them as employed. Set
                // only when it is not already filled in, so a date entered by
                // hand on the employee screen is not overwritten.
                $lastDateStmt = $pdo->prepare(
                    'UPDATE employee_details
                        SET last_date = :lastDate
                      WHERE user_id = :userId AND company_id = :companyId
                        AND last_date IS NULL'
                );
                $lastDateStmt->execute([
                    ':lastDate' => $formValues['last_working_date'] ?: $formValues['termination_date'],
                    ':userId' => $userId,
                    ':companyId' => $companyId,
                ]);

                $pdo->commit();

                header('Location: Termination.php?added=1');
                exit;
            } catch (PDOException $ex) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }

                error_log('Termination save failed: ' . $ex->getMessage());
                $error = 'Could not save. Please try again.';
            }
        }
    }
}

// ---------------------------------------------------------------------------
// Departments (for the search filter)
// ---------------------------------------------------------------------------
$departments = [];

if ($pdo) {
    $stmt = $pdo->prepare('SELECT id, team_name FROM teams WHERE company_id = :companyId ORDER BY team_name');
    $stmt->execute([':companyId' => $companyId]);
    $departments = $stmt->fetchAll();
}

// ---------------------------------------------------------------------------
// Employee options for the searchable Employee Name combobox. There's no
// separate "search results" list — picking a name here directly selects
// that employee for the Termination Details form below. Department narrows
// which names are offered.
// ---------------------------------------------------------------------------
$searchDept = trim($_GET['search_dept'] ?? '');

$employeeOptions = [];

if ($pdo) {
    $sql = 'SELECT ed.user_id, ed.employee_id, u.name AS employee_name, u.email, t.team_name AS department_name
            FROM employee_details ed
            JOIN users u ON u.id = ed.user_id
            LEFT JOIN teams t ON t.id = ed.department_id
            WHERE ed.company_id = :companyId AND u.status = "active"';
    $params = [':companyId' => $companyId];

    if ($searchDept !== '') {
        $sql .= ' AND ed.department_id = :searchDept';
        $params[':searchDept'] = (int) $searchDept;
    }

    $sql .= ' ORDER BY u.name';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $employeeOptions = $stmt->fetchAll();
}

// ---------------------------------------------------------------------------
// Selected employee (drives the Termination Details form)
// ---------------------------------------------------------------------------
$selectedEmployee = null;
$selectedUserId = isset($_GET['employee']) ? (int) $_GET['employee'] : ((int) $formValues['user_id'] ?: null);

if ($pdo && $selectedUserId) {
    $stmt = $pdo->prepare(
        'SELECT ed.user_id, ed.employee_id, u.name AS employee_name, u.email, t.team_name AS department_name
         FROM employee_details ed
         JOIN users u ON u.id = ed.user_id
         LEFT JOIN teams t ON t.id = ed.department_id
         WHERE ed.user_id = :userId AND ed.company_id = :companyId LIMIT 1'
    );
    $stmt->execute([':userId' => $selectedUserId, ':companyId' => $companyId]);
    $selectedEmployee = $stmt->fetch() ?: null;
}

if ($selectedEmployee) {
    $formValues['user_id'] = $selectedEmployee['user_id'];
}

// Preserve the current search filters on links so selecting an employee
// (or re-rendering after a failed save) doesn't lose the search the admin
// already ran.
$searchQuery = http_build_query(array_filter([
    'search_dept' => $searchDept,
]));

// ---------------------------------------------------------------------------
// Recent terminations list
// ---------------------------------------------------------------------------
$terminations = [];

if ($pdo) {
    $stmt = $pdo->prepare(
        'SELECT term.*, u.name AS employee_name, ed.employee_id AS employee_code, t.team_name AS department_name
         FROM terminations term
         JOIN users u ON u.id = term.user_id
         LEFT JOIN employee_details ed ON ed.user_id = term.user_id AND ed.company_id = term.company_id
         LEFT JOIN teams t ON t.id = ed.department_id
         WHERE term.company_id = :companyId
         ORDER BY term.created_at DESC, term.id DESC'
    );
    $stmt->execute([':companyId' => $companyId]);
    $terminations = $stmt->fetchAll();
}

$successMessage = null;
if (isset($_GET['added'])) {
    $successMessage = 'Termination recorded successfully.';
} elseif (isset($_GET['deleted'])) {
    $successMessage = 'Termination record deleted successfully.';
} elseif (isset($_GET['undone'])) {
    $successMessage = 'Termination undone — the employee\'s previous status has been restored.';
}

$reasons = [
    'Performance Issues',
    'Misconduct / Policy Violation',
    'Redundancy / Layoff',
    'Contract Completion',
    'Absenteeism',
    'Other',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Employee Termination</title>

  <style>
    * {
      box-sizing: border-box;
    }

    body {
      margin: 0;
      padding: 24px;
      background: #f1f5f9;
      font-family: Arial, Helvetica, sans-serif;
      color: #172033;
    }

    .page-title {
      font-size: 14px;
      font-weight: 400;
      color: #334155;
      margin: 0 0 16px;
    }

    .card {
      background: #fff;
      border: 1px solid #dbe3ee;
      border-radius: 10px;
      box-shadow: 0 4px 18px rgba(15, 23, 42, 0.06);
      padding: 18px;
      margin-bottom: 16px;
    }

    .section-title {
      font-size: 14px;
      font-weight: 600;
      margin: 0 0 16px;
    }

    /* Search Employee */

    .search-grid {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 16px 14px;
    }

    .search-actions {
      display: flex;
      gap: 8px;
      margin-top: 16px;
    }

    /* Searchable Employee Name combobox */

    .combo-field {
      position: relative;
    }

    .combo-dropdown {
      position: absolute;
      top: calc(100% + 4px);
      left: 0;
      right: 0;
      z-index: 20;
      max-height: 220px;
      overflow-y: auto;
      background: #fff;
      border: 1px solid #dbe3ee;
      border-radius: 6px;
      box-shadow: 0 8px 20px rgba(15, 23, 42, 0.10);
      padding: 4px 0;
    }

    .combo-option {
      padding: 8px 12px;
      cursor: pointer;
    }

    .combo-option:hover,
    .combo-option.is-active,
    .combo-option.is-selected {
      background: #eff6ff;
    }

    .combo-option-name {
      font-weight: 600;
      font-size: 13px;
      color: #253247;
    }

    .combo-option:hover .combo-option-name,
    .combo-option.is-active .combo-option-name,
    .combo-option.is-selected .combo-option-name {
      color: #1d4ed8;
    }

    .combo-option-sub {
      margin-top: 2px;
      font-size: 11px;
      color: #64748b;
    }

    .combo-empty {
      padding: 8px 12px;
      font-size: 12px;
      color: #98a2b3;
    }

    /* Termination Details */

    .form-grid {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 18px 26px;
    }

    .field {
      min-width: 0;
    }

    .full {
      grid-column: 1 / -1;
    }

    label {
      display: block;
      color: #52627a;
      font-size: 12px;
      margin-bottom: 8px;
    }

    .required {
      color: #ef4444;
    }

    input,
    select,
    textarea {
      width: 100%;
      border: 1px solid #dbe3ee;
      border-radius: 6px;
      background: #fff;
      color: #253247;
      font: inherit;
      font-size: 13px;
      outline: none;
      padding: 10px 12px;
    }

    input,
    select {
      height: 36px;
    }

    input:focus,
    select:focus,
    textarea:focus {
      border-color: #2563eb;
      box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.10);
    }

    input[readonly] {
      background: #f4f7fb;
      color: #64748b;
    }

    .date-field {
      position: relative;
    }

    .date-field .date-display {
      cursor: pointer;
      background: #fff;
      color: #253247;
    }

    .date-field .date-display:focus {
      border-color: #2563eb;
      box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.10);
    }

    .date-field .date-hidden-native {
      position: absolute;
      inset: 0;
      width: 100%;
      height: 100%;
      opacity: 0;
      pointer-events: none;
      border: 0;
      padding: 0;
    }

    textarea {
      min-height: 70px;
      resize: vertical;
      padding-top: 12px;
    }

    .radio-group {
      display: flex;
      flex-direction: column;
      gap: 11px;
      padding-top: 1px;
    }

    .radio-option {
      display: flex;
      align-items: center;
      gap: 8px;
      font-size: 13px;
      color: #334155;
      cursor: pointer;
      margin: 0;
    }

    .radio-option input {
      width: 15px;
      height: 15px;
      accent-color: #2563eb;
      padding: 0;
      margin: 0;
      height: auto;
    }

    button, a.button {
      border: 0;
      border-radius: 6px;
      font: inherit;
      font-size: 13px;
      font-weight: 600;
      padding: 11px 18px;
      cursor: pointer;
      text-decoration: none;
      display: inline-flex;
      align-items: center;
    }

    .search-button {
      background: #2563eb;
      color: #fff;
    }

    .search-button:hover {
      background: #1d4ed8;
    }

    .reset-button {
      background: #eef3f9;
      color: #334155;
      border: 1px solid #dbe3ee;
    }

    .reset-button:hover {
      background: #e2e8f0;
    }

    .footer {
      border-top: 1px solid #e5eaf1;
      margin: 18px -18px -18px;
      padding: 14px 18px;
      display: flex;
      justify-content: flex-end;
    }

    .submit-button {
      background: #4f46e5;
      color: #fff;
      box-shadow: 0 3px 8px rgba(79, 70, 229, 0.25);
    }

    .submit-button:hover {
      background: #4338ca;
    }

    .submit-button:disabled {
      background: #a5a6f6;
      box-shadow: none;
      cursor: not-allowed;
    }

    .message {
      margin-bottom: 17px;
      padding: 11px 13px;
      border-radius: 5px;
      font-size: 12px;
    }

    .success-message {
      border: 1px solid #bce5cc;
      background: #eaf8ef;
      color: #18864b;
    }

    .error-message {
      border: 1px solid #f2c5c5;
      background: #fdeceb;
      color: #a13a2f;
    }


    /* Selected employee banner */

    .selected-banner {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 12px;
      margin-bottom: 18px;
      padding: 11px 13px;
      border-radius: 6px;
      background: #eff6ff;
      border: 1px solid #bfdbfe;
      font-size: 12px;
      color: #1d4ed8;
    }

    .selected-banner strong {
      color: #172033;
    }

    .no-employee-banner {
      margin-bottom: 18px;
      padding: 11px 13px;
      border-radius: 6px;
      background: #fef9c3;
      border: 1px solid #fde68a;
      color: #854d0e;
      font-size: 12px;
    }

    .change-link {
      color: #2563eb;
      font-size: 12px;
      text-decoration: none;
      font-weight: 600;
      white-space: nowrap;
    }

    .change-link:hover {
      text-decoration: underline;
    }

    /* Recent Terminations list (mirrors Notice/Probation settings pages) */

    .period-list {
      width: 100%;
    }

    .period-row {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 15px;
      padding: 16px 20px;
      margin: 0 -18px;
      border-bottom: 1px solid #edf0f4;
    }

    .period-row:last-child {
      border-bottom: none;
    }

    .period-info {
      display: flex;
      align-items: center;
      gap: 12px;
      min-width: 0;
    }

    .period-icon {
      flex: 0 0 36px;
      width: 36px;
      height: 36px;
      border-radius: 50%;
      background: #fee2e2;
      color: #dc2626;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 15px;
    }

    .period-name {
      font-size: 12px;
      font-weight: 600;
      color: #344054;
    }

    .period-sub {
      margin-top: 4px;
      font-size: 10px;
      color: #98a2b3;
    }

    .period-right {
      display: flex;
      align-items: center;
      gap: 7px;
      flex-shrink: 0;
    }

    .period-badge {
      padding: 6px 10px;
      border-radius: 20px;
      background: #fee2e2;
      color: #b91c1c;
      font-size: 11px;
      font-weight: 600;
      white-space: nowrap;
    }

    .action-btn {
      height: 30px;
      padding: 0 10px;
      border-radius: 5px;
      font-size: 10px;
      font-weight: 600;
      cursor: pointer;
      background: #ffffff;
      text-decoration: none;
      display: inline-flex;
      align-items: center;
    }

    .delete-btn {
      color: #dc2626;
      border: 1px solid #f2c5c5;
    }

    .delete-btn:hover {
      background: #fff5f5;
    }

    .undo-btn {
      color: #4f46e5;
      border: 1px solid #cfd0ff;
    }

    .undo-btn:hover {
      background: #f3f2ff;
    }

    .delete-form {
      margin: 0;
    }

    .empty-state {
      padding: 45px 20px;
      text-align: center;
      color: #98a2b3;
    }

    .empty-icon {
      font-size: 28px;
      margin-bottom: 10px;
    }

    .empty-state p {
      margin: 0;
      font-size: 11px;
    }

    @media (max-width: 700px) {
      .search-grid,
      .form-grid {
        grid-template-columns: 1fr;
      }

      .full {
        grid-column: auto;
      }
    }

    @media (max-width: 450px) {
      body {
        padding: 12px;
      }

      .card {
        padding: 14px;
      }

      .footer {
        margin-left: -14px;
        margin-right: -14px;
        padding: 12px 14px;
      }

      .submit-button {
        width: 100%;
      }
    }
  </style>
</head>

<body>

  <h2 class="page-title">Employee Termination</h2>

  <?php if ($successMessage): ?>
    <div class="message success-message">✓ <?= e($successMessage) ?></div>
  <?php elseif ($error): ?>
    <div class="message error-message"><?= e($error) ?></div>
  <?php endif; ?>

  <!-- Search Employee -->
  <section class="card">

    <h3 class="section-title">Search Employee</h3>

    <form method="get" action="Termination.php">

      <div class="search-grid">

        <div>
          <label for="department">Department</label>
          <select id="department" name="search_dept" onchange="this.form.submit()">
            <option value="">All Departments</option>
            <?php foreach ($departments as $dept): ?>
              <option value="<?= e($dept['id']) ?>" <?= $searchDept == $dept['id'] ? 'selected' : '' ?>>
                <?= e($dept['team_name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="combo-field" id="employeeNameCombo">
          <label for="employee-name">Employee Name</label>
          <input
            type="text"
            id="employee-name"
            autocomplete="off"
            placeholder="Search employee name..."
          >
          <div class="combo-dropdown" id="employeeNameDropdown" hidden>
            <?php foreach ($employeeOptions as $option): ?>
              <div
                class="combo-option <?= $selectedUserId == $option['user_id'] ? 'is-selected' : '' ?>"
                data-href="Termination.php?<?= $searchQuery ? $searchQuery . '&' : '' ?>employee=<?= (int) $option['user_id'] ?>#termination-details"
              >
                <div class="combo-option-name"><?= e($option['employee_name']) ?></div>
                <div class="combo-option-sub">
                  ID <?= e($option['employee_id']) ?> &middot; <?= e($option['department_name'] ?? 'No department') ?> &middot; <?= e($option['email']) ?>
                </div>
              </div>
            <?php endforeach; ?>
            <div class="combo-empty" hidden>No matching employees</div>
          </div>
        </div>

      </div>

      <div class="search-actions">
        <a href="Termination.php" class="button reset-button">Reset</a>
      </div>

    </form>

  </section>

  <!-- Termination Details - Always Visible -->
  <section class="card" id="termination-details">

    <h3 class="section-title">Termination Details</h3>

    <?php if ($selectedEmployee): ?>
      <div class="selected-banner">
        <span>
          Recording termination for
          <strong><?= e($selectedEmployee['employee_name']) ?></strong>
          (<?= e($selectedEmployee['employee_id']) ?>)
          &middot; <?= e($selectedEmployee['department_name'] ?? 'No department') ?>
        </span>
        <a class="change-link" href="Termination.php?<?= $searchQuery ?>">Change</a>
      </div>
    <?php else: ?>
      <div class="no-employee-banner">
        Search and select an employee above before recording a termination.
      </div>
    <?php endif; ?>

    <form id="termination-form" method="post" action="Termination.php">

      <input type="hidden" name="user_id" value="<?= e($formValues['user_id']) ?>">

      <div class="form-grid">

        <div class="field">
          <label for="termination-date-display">
            Termination Date <span class="required">*</span>
          </label>
          <div class="date-field">
            <input
              type="text"
              id="termination-date-display"
              class="date-display"
              placeholder="DD/MM/YYYY"
              value="<?= e(displayDate($formValues['termination_date'])) ?>"
              readonly
            >
            <input
              id="termination-date"
              name="termination_date"
              type="date"
              class="date-hidden-native"
              value="<?= e($formValues['termination_date']) ?>"
              tabindex="-1"
              required
            >
          </div>
        </div>

        <div class="field">
          <label for="notice-period">
            Notice Period (Days) <span class="required">*</span>
          </label>

          <?php if (!empty($noticePeriodOptions)): ?>
            <select id="notice-period" name="notice_period_days" required>
              <?php foreach ($noticePeriodOptions as $option): ?>
                <option
                  value="<?= (int) $option['days'] ?>"
                  <?= (string) $option['days'] === (string) $formValues['notice_period_days'] ? 'selected' : '' ?>
                >
                  <?= e($option['name']) ?> (<?= (int) $option['months'] ?> <?= $option['months'] == 1 ? 'Month' : 'Months' ?> &middot; <?= (int) $option['days'] ?> Days)
                </option>
              <?php endforeach; ?>
            </select>
          <?php else: ?>
            <input
              id="notice-period"
              name="notice_period_days"
              type="number"
              min="0"
              max="365"
              value="<?= e($formValues['notice_period_days']) ?>"
              required
            >
          <?php endif; ?>
        </div>

        <div class="field">
          <label for="notice-start-display">
            Notice Period Start Date
          </label>
          <div class="date-field">
            <input
              type="text"
              id="notice-start-display"
              class="date-display"
              placeholder="DD/MM/YYYY"
              value="<?= e(displayDate($formValues['notice_start_date'])) ?>"
              readonly
            >
            <input
              id="notice-start"
              name="notice_start_date"
              type="date"
              class="date-hidden-native"
              value="<?= e($formValues['notice_start_date']) ?>"
              tabindex="-1"
            >
          </div>
        </div>

        <div class="field">
          <label for="last-working-date-display">
            Proposed Last Working Date <span class="required">*</span>
          </label>
          <div class="date-field">
            <input
              type="text"
              id="last-working-date-display"
              class="date-display"
              placeholder="DD/MM/YYYY"
              value="<?= e(displayDate($formValues['last_working_date'])) ?>"
              readonly
            >
            <input
              id="last-working-date"
              name="last_working_date"
              type="date"
              class="date-hidden-native"
              value="<?= e($formValues['last_working_date']) ?>"
              tabindex="-1"
              required
            >
          </div>
        </div>

        <div class="field">
          <label>Notice Period Served?</label>

          <div class="radio-group">
            <label class="radio-option">
              <input
                type="radio"
                name="notice_served"
                value="Yes"
                <?= $formValues['notice_served'] === 'Yes' ? 'checked' : '' ?>
              >
              Yes
            </label>

            <label class="radio-option">
              <input
                type="radio"
                name="notice_served"
                value="No"
                <?= $formValues['notice_served'] !== 'Yes' ? 'checked' : '' ?>
              >
              No
            </label>
          </div>
        </div>

        <div class="field">
          <label for="termination-reason">
            Reason for Termination <span class="required">*</span>
          </label>

          <select id="termination-reason" name="reason" required>
            <option value="">Select reason</option>
            <?php foreach ($reasons as $reasonOption): ?>
              <option <?= $formValues['reason'] === $reasonOption ? 'selected' : '' ?>><?= e($reasonOption) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="field full">
          <label for="termination-remarks">
            Termination Remarks
          </label>

          <textarea
            id="termination-remarks"
            name="remarks"
            placeholder="Enter additional details about this termination..."
          ><?= e($formValues['remarks']) ?></textarea>
        </div>

      </div>

      <div class="footer">
        <button
          type="submit"
          name="save_termination"
          value="1"
          class="submit-button"
          <?= $selectedEmployee ? '' : 'disabled' ?>
        >
          ✓ Submit Termination
        </button>
      </div>

    </form>

  </section>

  <!-- Recent Terminations -->
  <section class="card">

    <h3 class="section-title">Recent Terminations</h3>

    <div class="period-list">

      <?php if (empty($terminations)): ?>

        <div class="empty-state">
          <div class="empty-icon">⏻</div>
          <p>No terminations recorded yet.</p>
        </div>

      <?php else: ?>

        <?php foreach ($terminations as $row): ?>

          <?php
            $label = 'Recorded on ' . date('d-m-Y', strtotime($row['created_at']));
            $sub = e($row['department_name'] ?? 'No department')
                . ' &middot; Last working: ' . e(date('d-m-Y', strtotime($row['last_working_date'])));
          ?>

          <div class="period-row">

            <div class="period-info">
              <div class="period-icon">⏻</div>

              <div>
                <div class="period-name">
                  <?= e($row['employee_name']) ?>
                  <?= $row['employee_code'] ? '(' . e($row['employee_code']) . ')' : '' ?>
                </div>
                <div class="period-sub"><?= $sub ?></div>
                <div class="period-sub"><?= e($label) ?> &middot; <?= e($row['reason']) ?></div>
              </div>
            </div>

            <div class="period-right">

              <div class="period-badge">
                <?= (int) $row['notice_period_days'] ?> Days Notice
              </div>

              <form
                method="post"
                action="Termination.php"
                class="delete-form"
                onsubmit="return confirm('Undo this termination? The employee\'s status will be restored to what it was before (<?= e($row['previous_employment_status'] ?: 'confirmed') ?>).');"
              >
                <input type="hidden" name="undo_termination_id" value="<?= (int) $row['id'] ?>">
                <button type="submit" class="action-btn undo-btn">Undo</button>
              </form>

              <form
                method="post"
                action="Termination.php"
                class="delete-form"
                onsubmit="return confirm('Are you sure you want to delete this termination record?');"
              >
                <input type="hidden" name="delete_termination_id" value="<?= (int) $row['id'] ?>">
                <button type="submit" class="action-btn delete-btn">Delete</button>
              </form>

            </div>

          </div>

        <?php endforeach; ?>

      <?php endif; ?>

    </div>

  </section>

  <script>
    // Date calculation
    const terminationDate = document.getElementById("termination-date");
    const noticeStart = document.getElementById("notice-start");
    const lastWorkingDate = document.getElementById("last-working-date");
    const noticePeriod = document.getElementById("notice-period");

    function formatDate(date) {
      // Build the ISO string from local date parts, not toISOString() —
      // toISOString() converts to UTC first, which silently rolls the date
      // back a day in any positive-UTC-offset timezone (e.g. IST) once the
      // local time-of-day is midnight, as it always is here.
      const year = date.getFullYear();
      const month = String(date.getMonth() + 1).padStart(2, "0");
      const day = String(date.getDate()).padStart(2, "0");
      return year + "-" + month + "-" + day;
    }

    function addDays(dateString, days) {
      const date = new Date(dateString + "T00:00:00");
      date.setDate(date.getDate() + Number(days || 0));
      return formatDate(date);
    }

    // Each real <input type="date"> is kept (invisibly) for its native
    // calendar popup, paired with a visible text field that always shows
    // an unambiguous DD/MM/YYYY — the native control's own displayed text
    // follows the browser/OS locale (usually mm/dd/yyyy) and isn't
    // reliably overridable per-field, so it's hidden instead of shown.
    function isoToDisplay(iso) {
      if (!iso) {
        return "";
      }

      const parts = iso.split("-");
      return parts.length === 3 ? parts[2] + "/" + parts[1] + "/" + parts[0] : "";
    }

    function wireDateDisplay(hiddenInput, displayInput) {
      function sync() {
        displayInput.value = isoToDisplay(hiddenInput.value);
      }

      displayInput.addEventListener("click", () => {
        if (hiddenInput.showPicker) {
          hiddenInput.showPicker();
        } else {
          hiddenInput.focus();
        }
      });

      hiddenInput.addEventListener("change", sync);

      return sync;
    }

    const syncTerminationDisplay = wireDateDisplay(terminationDate, document.getElementById("termination-date-display"));
    const syncNoticeStartDisplay = wireDateDisplay(noticeStart, document.getElementById("notice-start-display"));
    const syncLastWorkingDisplay = wireDateDisplay(lastWorkingDate, document.getElementById("last-working-date-display"));

    terminationDate.addEventListener("change", () => {
      syncTerminationDisplay();
      noticeStart.value = terminationDate.value;
      syncNoticeStartDisplay();
      lastWorkingDate.value = addDays(terminationDate.value, noticePeriod.value);
      syncLastWorkingDisplay();
    });

    noticePeriod.addEventListener("input", updateLastWorkingDateFromNotice);
    noticePeriod.addEventListener("change", updateLastWorkingDateFromNotice);

    function updateLastWorkingDateFromNotice() {
      if (terminationDate.value) {
        lastWorkingDate.value = addDays(terminationDate.value, noticePeriod.value);
        syncLastWorkingDisplay();
      }
    }

    // Searchable Employee Name combobox — filters the option list as the
    // admin types, and picking one navigates straight to that employee's
    // Termination Details (there's no separate "search results" list;
    // this combobox is the whole search-and-select step).
    (function () {
      const wrap = document.getElementById("employeeNameCombo");
      const input = document.getElementById("employee-name");
      const dropdown = document.getElementById("employeeNameDropdown");
      const options = Array.prototype.slice.call(dropdown.querySelectorAll(".combo-option"));
      const emptyState = dropdown.querySelector(".combo-empty");

      function filterOptions() {
        const query = input.value.trim().toLowerCase();
        let visibleCount = 0;

        options.forEach(function (option) {
          const matches = option.textContent.toLowerCase().indexOf(query) !== -1;
          option.hidden = !matches;
          if (matches) {
            visibleCount++;
          }
        });

        emptyState.hidden = visibleCount > 0;
      }

      function openDropdown() {
        filterOptions();
        dropdown.hidden = false;
      }

      function closeDropdown() {
        dropdown.hidden = true;
      }

      input.addEventListener("focus", openDropdown);
      input.addEventListener("input", openDropdown);

      options.forEach(function (option) {
        option.addEventListener("mousedown", function (e) {
          // mousedown (not click) fires before the input's blur.
          e.preventDefault();
          window.location.href = option.dataset.href;
        });
      });

      document.addEventListener("click", function (e) {
        if (!wrap.contains(e.target)) {
          closeDropdown();
        }
      });

      input.addEventListener("keydown", function (e) {
        if (e.key === "Escape") {
          closeDropdown();
        }
      });
    })();
  </script>

</body>
</html>
