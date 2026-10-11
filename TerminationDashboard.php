<?php
/**
 * Employee Termination Dashboard
 *
 * Read-focused companion to Termination.php: summary stat cards plus the
 * full history of recorded terminations (not just the handful shown in
 * Termination.php's "Recent Terminations" preview), with department/name
 * filters. Delete reuses the same terminations table as the main page —
 * every delete redirects back here (Post/Redirect/Get).
 */

// Guarded: the framework defines the same helper when this page is served
// through its authenticated route, and redeclaring it would be fatal.
if (!function_exists('e')) {
    function e($value)
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}

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
// Undo — reverses the termination: restores the employee's Employee Exit
// Status to whatever it was right before this record was created, then
// removes the record. Different from Delete, which only removes the record.
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

            $deleteStmt = $pdo->prepare('DELETE FROM terminations WHERE id = :id AND company_id = :companyId');
            $deleteStmt->execute([':id' => (int) $_POST['undo_termination_id'], ':companyId' => $companyId]);
        }

        header('Location: TerminationDashboard.php?undone=1');
        exit;
    } catch (PDOException $ex) {
        error_log('Termination undo failed: ' . $ex->getMessage());
        $error = 'Could not undo this termination. Please try again.';
    }
}

// ---------------------------------------------------------------------------
// Delete (same table Termination.php writes to)
// ---------------------------------------------------------------------------
if ($pdo && $_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['delete_termination_id'])) {
    try {
        $stmt = $pdo->prepare('DELETE FROM terminations WHERE id = :id AND company_id = :companyId');
        $stmt->execute([
            ':id' => (int) $_POST['delete_termination_id'],
            ':companyId' => $companyId,
        ]);
        header('Location: TerminationDashboard.php?deleted=1');
        exit;
    } catch (PDOException $ex) {
        error_log('Termination delete failed: ' . $ex->getMessage());
        $error = 'Could not delete. Please try again.';
    }
}

// ---------------------------------------------------------------------------
// Departments (for the filter)
// ---------------------------------------------------------------------------
$departments = [];

if ($pdo) {
    $stmt = $pdo->prepare('SELECT id, team_name FROM teams WHERE company_id = :companyId ORDER BY team_name');
    $stmt->execute([':companyId' => $companyId]);
    $departments = $stmt->fetchAll();
}

// ---------------------------------------------------------------------------
// Summary stats
// ---------------------------------------------------------------------------
$stats = [
    'total' => 0,
    'this_month' => 0,
    'immediate' => 0,
    'with_notice' => 0,
];

if ($pdo) {
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM terminations WHERE company_id = :companyId');
    $stmt->execute([':companyId' => $companyId]);
    $stats['total'] = (int) $stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM terminations WHERE company_id = :companyId AND DATE_FORMAT(termination_date, '%Y-%m') = DATE_FORMAT(CURDATE(), '%Y-%m')");
    $stmt->execute([':companyId' => $companyId]);
    $stats['this_month'] = (int) $stmt->fetchColumn();

    $stmt = $pdo->prepare('SELECT COUNT(*) FROM terminations WHERE company_id = :companyId AND notice_period_days = 0');
    $stmt->execute([':companyId' => $companyId]);
    $stats['immediate'] = (int) $stmt->fetchColumn();

    $stats['with_notice'] = $stats['total'] - $stats['immediate'];
}

// ---------------------------------------------------------------------------
// Filters + full termination history
// ---------------------------------------------------------------------------
$filterDept = trim($_GET['dept'] ?? '');
$filterName = trim($_GET['name'] ?? '');

$terminations = [];

if ($pdo) {
    $sql = 'SELECT term.*, u.name AS employee_name, ed.employee_id AS employee_code, t.team_name AS department_name
            FROM terminations term
            JOIN users u ON u.id = term.user_id
            LEFT JOIN employee_details ed ON ed.user_id = term.user_id AND ed.company_id = term.company_id
            LEFT JOIN teams t ON t.id = ed.department_id
            WHERE term.company_id = :companyId';
    $params = [':companyId' => $companyId];

    if ($filterDept !== '') {
        $sql .= ' AND ed.department_id = :filterDept';
        $params[':filterDept'] = (int) $filterDept;
    }

    if ($filterName !== '') {
        $sql .= ' AND u.name LIKE :filterName';
        $params[':filterName'] = '%' . $filterName . '%';
    }

    $sql .= ' ORDER BY term.created_at DESC, term.id DESC';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $terminations = $stmt->fetchAll();
}

$successMessage = null;
if (isset($_GET['deleted'])) {
    $successMessage = 'Termination record deleted successfully.';
} elseif (isset($_GET['undone'])) {
    $successMessage = 'Termination undone — the employee\'s previous status has been restored.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Employee Termination Dashboard</title>

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
    font-size: 13px;
  }

  .page-title {
    font-size: 14px;
    font-weight: 400;
    color: #334155;
    margin: 0;
  }

  .page-header-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    margin-bottom: 16px;
  }

  .add-button {
    border: 0;
    border-radius: 6px;
    font: inherit;
    font-size: 13px;
    font-weight: 600;
    height: 36px;
    padding: 0 16px;
    cursor: pointer;
    background: #4f46e5;
    color: #fff;
    box-shadow: 0 3px 8px rgba(79, 70, 229, 0.25);
    white-space: nowrap;
  }

  .add-button:hover {
    background: #4338ca;
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

  /* Stat cards */

  .stats-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 14px;
    margin-bottom: 16px;
  }

  .stat-card {
    background: #fff;
    border: 1px solid #dbe3ee;
    border-left: 4px solid #4f46e5;
    border-radius: 10px;
    box-shadow: 0 4px 18px rgba(15, 23, 42, 0.06);
    padding: 16px 18px;
  }

  .stat-card.immediate {
    border-left-color: #dc2626;
  }

  .stat-card.with-notice {
    border-left-color: #2563eb;
  }

  .stat-card.this-month {
    border-left-color: #d97706;
  }

  .stat-label {
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: #8a94a6;
    margin: 0 0 6px;
  }

  .stat-value {
    font-size: 24px;
    font-weight: 700;
    color: #172033;
    margin: 0;
  }

  /* Filters */

  .filter-grid {
    display: grid;
    grid-template-columns: 1fr 1fr auto;
    gap: 14px;
    align-items: end;
  }

  .field label {
    display: block;
    color: #52627a;
    font-size: 12px;
    margin-bottom: 8px;
  }

  input, select {
    width: 100%;
    height: 36px;
    border: 1px solid #dbe3ee;
    border-radius: 6px;
    background: #fff;
    color: #253247;
    font: inherit;
    font-size: 13px;
    outline: none;
    padding: 0 12px;
  }

  input:focus, select:focus {
    border-color: #2563eb;
    box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.10);
  }

  button, a.button {
    border: 0;
    border-radius: 6px;
    font: inherit;
    font-size: 13px;
    font-weight: 600;
    height: 36px;
    padding: 0 18px;
    cursor: pointer;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    justify-content: center;
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

  .filter-actions {
    display: flex;
    gap: 8px;
  }

  /* Table */

  .table-wrap {
    overflow-x: auto;
  }

  table {
    width: 100%;
    border-collapse: collapse;
    font-size: 12px;
  }

  thead th {
    text-align: left;
    font-size: 11px;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: #8a94a6;
    padding: 10px 12px;
    border-bottom: 1px solid #e5eaf1;
    white-space: nowrap;
  }

  tbody td {
    padding: 12px;
    border-bottom: 1px solid #edf0f4;
    vertical-align: top;
  }

  tbody tr:last-child td {
    border-bottom: none;
  }

  .emp-name {
    font-weight: 600;
    color: #172033;
  }

  .emp-sub {
    color: #64748b;
    font-size: 11px;
    margin-top: 2px;
  }

  .badge {
    display: inline-block;
    padding: 4px 9px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 600;
  }

  .badge.immediate {
    background: #fee2e2;
    color: #b91c1c;
  }

  .badge.with-notice {
    background: #dbeafe;
    color: #1d4ed8;
  }

  .row-actions {
    display: flex;
    gap: 6px;
  }

  .action-btn {
    height: 28px;
    padding: 0 10px;
    border-radius: 5px;
    font-size: 10px;
    font-weight: 600;
    cursor: pointer;
    background: #ffffff;
    border: 1px solid #f2c5c5;
    color: #dc2626;
  }

  .action-btn:hover {
    background: #fff5f5;
  }

  .action-btn.undo-btn {
    border-color: #cfd0ff;
    color: #4f46e5;
  }

  .action-btn.undo-btn:hover {
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

  @media (max-width: 900px) {
    .stats-grid {
      grid-template-columns: repeat(2, 1fr);
    }

    .filter-grid {
      grid-template-columns: 1fr;
    }
  }
</style>
</head>

<body>

  <div class="page-header-row">
    <h2 class="page-title">Employee Termination Dashboard</h2>
    <button type="button" class="add-button" id="addTerminationBtn">+ Add Employee Termination</button>
  </div>

  <?php if ($successMessage): ?>
    <div class="message success-message">✓ <?= e($successMessage) ?></div>
  <?php elseif ($error): ?>
    <div class="message error-message"><?= e($error) ?></div>
  <?php endif; ?>

  <div class="stats-grid">
    <div class="stat-card">
      <p class="stat-label">Total Terminations</p>
      <p class="stat-value"><?= (int) $stats['total'] ?></p>
    </div>
    <div class="stat-card this-month">
      <p class="stat-label">This Month</p>
      <p class="stat-value"><?= (int) $stats['this_month'] ?></p>
    </div>
    <div class="stat-card immediate">
      <p class="stat-label">Immediate (No Notice)</p>
      <p class="stat-value"><?= (int) $stats['immediate'] ?></p>
    </div>
    <div class="stat-card with-notice">
      <p class="stat-label">With Notice Period</p>
      <p class="stat-value"><?= (int) $stats['with_notice'] ?></p>
    </div>
  </div>

  <section class="card">
    <h3 class="section-title">Filter</h3>

    <form method="get" action="TerminationDashboard.php">
      <div class="filter-grid">
        <div class="field">
          <label for="dept">Department</label>
          <select id="dept" name="dept" onchange="this.form.submit()">
            <option value="">All Departments</option>
            <?php foreach ($departments as $dept): ?>
              <option value="<?= e($dept['id']) ?>" <?= $filterDept == $dept['id'] ? 'selected' : '' ?>>
                <?= e($dept['team_name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="field">
          <label for="name">Employee Name</label>
          <input id="name" name="name" type="text" placeholder="Search by name..." value="<?= e($filterName) ?>">
        </div>

        <div class="filter-actions">
          <button type="submit" class="search-button">Search</button>
          <a href="TerminationDashboard.php" class="button reset-button">Reset</a>
        </div>
      </div>
    </form>
  </section>

  <section class="card">
    <h3 class="section-title">Termination History</h3>

    <?php if (empty($terminations)): ?>
      <div class="empty-state">
        <div class="empty-icon">⏻</div>
        <p>No terminations recorded yet.</p>
      </div>
    <?php else: ?>
      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>Employee</th>
              <th>Department</th>
              <th>Termination Date</th>
              <th>Notice Period</th>
              <th>Last Working Date</th>
              <th>Notice Served</th>
              <th>Reason</th>
              <th>Recorded On</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($terminations as $row): ?>
              <tr>
                <td>
                  <div class="emp-name"><?= e($row['employee_name']) ?></div>
                  <?php if ($row['employee_code']): ?>
                    <div class="emp-sub">ID <?= e($row['employee_code']) ?></div>
                  <?php endif; ?>
                </td>
                <td><?= e($row['department_name'] ?? '—') ?></td>
                <td><?= e(displayDate($row['termination_date'])) ?></td>
                <td>
                  <?php if ((int) $row['notice_period_days'] === 0): ?>
                    <span class="badge immediate">Immediate</span>
                  <?php else: ?>
                    <span class="badge with-notice"><?= (int) $row['notice_period_days'] ?> Days</span>
                  <?php endif; ?>
                </td>
                <td><?= e(displayDate($row['last_working_date'])) ?></td>
                <td><?= e($row['notice_served']) ?></td>
                <td><?= e($row['reason']) ?></td>
                <td><?= e(displayDate(substr($row['created_at'], 0, 10))) ?></td>
                <td>
                  <div class="row-actions">
                    <form
                      method="post"
                      action="TerminationDashboard.php"
                      class="delete-form"
                      onsubmit="return confirm('Undo this termination? The employee\'s status will be restored to what it was before (<?= e($row['previous_employment_status'] ?: 'confirmed') ?>).');"
                    >
                      <input type="hidden" name="undo_termination_id" value="<?= (int) $row['id'] ?>">
                      <button type="submit" class="action-btn undo-btn">Undo</button>
                    </form>

                    <form
                      method="post"
                      action="TerminationDashboard.php"
                      class="delete-form"
                      onsubmit="return confirm('Are you sure you want to delete this termination record?');"
                    >
                      <input type="hidden" name="delete_termination_id" value="<?= (int) $row['id'] ?>">
                      <button type="submit" class="action-btn">Delete</button>
                    </form>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </section>

  <script>
    // The tabs live in the parent Laravel page (this dashboard is just one
    // of its iframes), and both are same-origin, so switch tabs there directly
    // rather than navigating this iframe away.
    document.getElementById('addTerminationBtn').addEventListener('click', function () {
      try {
        var parentDoc = window.parent.document;
        var formTab = parentDoc.getElementById('termination-form-tab');

        if (formTab) {
          if (window.parent.jQuery) {
            window.parent.jQuery(formTab).tab('show');
          } else {
            formTab.click();
          }

          return;
        }
      } catch (e) {
        // Fall through to a plain navigation if the parent frame isn't reachable.
      }

      window.location.href = <?= json_encode(function_exists('route') ? route('legacy.page', 'termination') : 'Termination.php') ?>;
    });
  </script>

</body>
</html>
