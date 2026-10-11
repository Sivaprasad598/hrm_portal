<?php
/**
 * Employee Probation Period Requests
 * Server-side (PHP) port of probhition_period_request.html.
 *
 * Lists employees whose probation completion date has arrived (flagged
 * "pending" by the check-probation-completion scheduled command) alongside
 * ones already Approved/Rejected, joined from employee_details/users/teams/
 * designations. The Approve/Reject buttons call the real, authenticated
 * Laravel routes (probation-requests.{approve,reject}) via fetch() — those
 * routes are what actually update employment_status and send the
 * confirmation email/notification, since that needs Eloquent, the real
 * logged-in admin (auth()->user()), and the notification/mail system this
 * standalone script deliberately doesn't bootstrap. The XSRF-TOKEN cookie
 * Laravel already sets for the active session is reused for that fetch()
 * call, exactly like Laravel's own default Axios wiring does.
 */

// Guarded: the framework defines the same helper when this page is served
// through its authenticated route, and redeclaring it would be fatal.
if (!function_exists('e')) {
    function e($value)
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}

function initials($name)
{
    $parts = preg_split('/\s+/', trim((string) $name));
    $parts = array_filter($parts);

    if (empty($parts)) {
        return '?';
    }

    $first = mb_substr(reset($parts), 0, 1);
    $last = count($parts) > 1 ? mb_substr(end($parts), 0, 1) : '';

    return mb_strtoupper($first . $last);
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

$months = null;
$requests = [];

if ($pdo) {
    $setting = $pdo->prepare('SELECT months FROM probation_period_settings WHERE company_id = :companyId ORDER BY id DESC LIMIT 1');
    $setting->execute([':companyId' => $companyId]);
    $months = $setting->fetchColumn();
    $months = $months !== false ? (int) $months : null;

    $stmt = $pdo->prepare(
        'SELECT ed.id, ed.employee_id, ed.joining_date, ed.probation_review_status,
                ed.probation_admin_remarks, ed.probation_reviewed_at,
                u.name AS employee_name, u.email,
                t.team_name AS department_name, d.name AS designation_name,
                reviewer.name AS reviewer_name
         FROM employee_details ed
         JOIN users u ON u.id = ed.user_id
         LEFT JOIN teams t ON t.id = ed.department_id
         LEFT JOIN designations d ON d.id = ed.designation_id
         LEFT JOIN users reviewer ON reviewer.id = ed.probation_reviewed_by
         WHERE ed.company_id = :companyId
           AND ed.probation_review_status IS NOT NULL
         ORDER BY (ed.probation_review_status = "pending") DESC,
                  COALESCE(ed.probation_reviewed_at, ed.updated_at) DESC'
    );
    $stmt->execute([':companyId' => $companyId]);
    $requests = $stmt->fetchAll();
}

// Probation Communication thread — the messages an admin has sent this
// employee via the "Send Message" box (see ProbationRequestController::
// message()), grouped by employee_details_id so each row's modal can show
// its own history, same idea as Resignation Management's reply thread.
$repliesByEmployee = [];

if ($pdo && !empty($requests)) {
    $employeeDetailIds = array_column($requests, 'id');
    $placeholders = implode(',', array_fill(0, count($employeeDetailIds), '?'));

    $repliesStmt = $pdo->prepare(
        "SELECT pr.employee_details_id, pr.message, pr.created_at, sender.name AS sender_name
         FROM probation_replies pr
         JOIN users sender ON sender.id = pr.user_id
         WHERE pr.employee_details_id IN ($placeholders)
         ORDER BY pr.created_at ASC"
    );
    $repliesStmt->execute($employeeDetailIds);

    foreach ($repliesStmt->fetchAll() as $reply) {
        $repliesByEmployee[$reply['employee_details_id']][] = [
            'sender' => $reply['sender_name'],
            'message' => $reply['message'],
            'timestamp' => date('d-m-Y h:i A', strtotime($reply['created_at'])),
        ];
    }
}

// Date of Joining + Probation Period = Probation Completion Date (same
// formula/config source as the scheduled command and the approve/reject
// controller — no hardcoded months here either).
foreach ($requests as &$row) {
    $row['completion_date'] = $months !== null
        ? date('Y-m-d', strtotime($row['joining_date'] . " +{$months} months"))
        : null;
    $row['replies'] = $repliesByEmployee[$row['id']] ?? [];
}
unset($row);

$totalCount = count($requests);
$pendingCount = count(array_filter($requests, fn ($r) => $r['probation_review_status'] === 'pending'));
$approvedCount = count(array_filter($requests, fn ($r) => $r['probation_review_status'] === 'approved'));
$rejectedCount = count(array_filter($requests, fn ($r) => $r['probation_review_status'] === 'rejected'));

$departments = array_values(array_unique(array_filter(array_column($requests, 'department_name'))));
$designations = array_values(array_unique(array_filter(array_column($requests, 'designation_name'))));
sort($departments);
sort($designations);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Probation Period Requests</title>

<style>
* {
    box-sizing: border-box;
}

body {
    margin: 0;
    font-family: Arial, Helvetica, sans-serif;
    background: #f3f6fa;
    color: #344054;
    font-size: 13px;
}

.page {
    padding: 22px 28px;
}

/* HEADER */
.page-header {
    margin-bottom: 20px;
}

.page-header h2 {
    margin: 0;
    font-size: 20px;
    font-weight: 600;
    color: #1f2937;
}

.page-header p {
    margin: 5px 0 0;
    font-size: 12px;
    color: #8a94a6;
}

/* MESSAGES */
.page-message {
    margin-bottom: 18px;
    padding: 11px 13px;
    border-radius: 5px;
    font-size: 11px;
}

.page-message.error {
    border: 1px solid #f2c5c5;
    background: #fdeceb;
    color: #a13a2f;
}

/* SUMMARY */
.summary-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 15px;
    margin-bottom: 20px;
}

.summary-card {
    background: #fff;
    border: 1px solid #e0e6ed;
    border-radius: 8px;
    padding: 17px;
    height: 105px;
    cursor: pointer;
    position: relative;
    transition: .2s;
}

.summary-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,.06);
}

.summary-card.active {
    box-shadow: 0 0 0 2px #4f46e5;
}

.summary-card.total {
    border-top: 3px solid #64748b;
}

.summary-card.pending {
    border-top: 3px solid #f59e0b;
}

.summary-card.approved {
    border-top: 3px solid #22c55e;
}

.summary-card.rejected {
    border-top: 3px solid #ef4444;
}

.summary-label {
    font-size: 10px;
    font-weight: 600;
    text-transform: uppercase;
    color: #8a94a6;
    margin-bottom: 10px;
}

.summary-number {
    font-size: 24px;
    font-weight: 600;
    color: #1f2937;
}

.summary-icon {
    position: absolute;
    right: 15px;
    top: 15px;
    width: 35px;
    height: 35px;
    border-radius: 50%;
    background: #f1f3f6;
    display: flex;
    justify-content: center;
    align-items: center;
    color: #667085;
    font-size: 15px;
}

/* FILTERS */
.filter-card {
    background: #fff;
    border: 1px solid #e0e6ed;
    border-radius: 8px;
    padding: 14px;
    margin-bottom: 18px;
}

.filters {
    display: grid;
    grid-template-columns: 160px 170px 170px 1fr;
    gap: 10px;
}

select,
input {
    height: 38px;
    width: 100%;
    border: 1px solid #d8dee8;
    border-radius: 5px;
    padding: 0 11px;
    font-size: 12px;
    color: #475467;
    background: #fff;
    outline: none;
}

select:focus,
input:focus {
    border-color: #6366f1;
}

.search-box {
    position: relative;
}

.search-box input {
    padding-left: 34px;
}

.search-icon {
    position: absolute;
    left: 12px;
    top: 11px;
    color: #98a2b3;
}

/* TABLE */
.table-card {
    background: #fff;
    border: 1px solid #e0e6ed;
    border-radius: 8px;
    overflow: hidden;
}

.table-header {
    padding: 17px 18px;
    border-bottom: 1px solid #e7ebf0;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.table-header h3 {
    margin: 0;
    font-size: 14px;
    font-weight: 600;
}

.table-header span {
    color: #98a2b3;
    font-size: 11px;
}

.table-wrapper {
    overflow-x: auto;
}

table {
    width: 100%;
    border-collapse: collapse;
}

thead th {
    background: #fafbfc;
    padding: 12px 14px;
    text-align: left;
    font-size: 10px;
    text-transform: uppercase;
    letter-spacing: .4px;
    color: #667085;
    border-bottom: 1px solid #e5e9ef;
}

tbody td {
    padding: 13px 14px;
    border-bottom: 1px solid #eef1f4;
    font-size: 12px;
    white-space: nowrap;
}

tbody tr:hover {
    background: #fafcff;
}

/* EMPLOYEE */
.employee {
    display: flex;
    align-items: center;
    gap: 10px;
}

.avatar {
    width: 34px;
    height: 34px;
    border-radius: 50%;
    background: #eef2ff;
    color: #4f46e5;
    display: flex;
    justify-content: center;
    align-items: center;
    font-weight: 600;
    font-size: 11px;
}

.employee-name {
    font-weight: 600;
}

.employee-id {
    color: #98a2b3;
    font-size: 10px;
    margin-top: 2px;
}

/* STATUS */
.status {
    display: inline-block;
    padding: 5px 10px;
    border-radius: 20px;
    font-size: 10px;
    font-weight: 600;
}

.status.pending {
    background: #fff7df;
    color: #b77900;
}

.status.approved {
    background: #e8f7ed;
    color: #18864b;
}

.status.rejected {
    background: #fdecec;
    color: #d93025;
}

/* ACTIONS */
.actions {
    display: flex;
    gap: 5px;
}

.action-btn {
    height: 30px;
    padding: 0 9px;
    border-radius: 4px;
    background: #fff;
    font-size: 10px;
    font-weight: 600;
    cursor: pointer;
}

.view-btn {
    border: 1px solid #d8dee8;
    color: #475467;
}

.approve-btn {
    border: 1px solid #bce5cc;
    color: #18864b;
}

.reject-btn {
    border: 1px solid #f1c4c1;
    color: #d93025;
}

.undo-btn {
    border: 1px solid #cfd0ff;
    color: #4f46e5;
}

.delete-req-btn {
    background: #fdeceb;
    border: 1px solid #f1c4c1;
    color: #d93025;
}

.action-btn:hover {
    background: #f8fafc;
}

.action-btn:disabled {
    opacity: .5;
    cursor: not-allowed;
}

/* MODAL */
.modal-overlay {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(15,23,42,.45);
    z-index: 1000;
    align-items: center;
    justify-content: center;
    padding: 20px;
}

.modal-overlay.show {
    display: flex;
}

.modal {
    width: 850px;
    max-width: 100%;
    max-height: 90vh;
    overflow-y: auto;
    background: #fff;
    border-radius: 8px;
    box-shadow: 0 15px 50px rgba(0,0,0,.2);
}

.modal-header {
    padding: 18px 22px;
    border-bottom: 1px solid #e7ebf0;
    display: flex;
    justify-content: space-between;
}

.modal-header h3 {
    margin: 0;
    font-size: 16px;
}

.close {
    width: 30px;
    height: 30px;
    border: 0;
    background: #f4f6f8;
    border-radius: 5px;
    cursor: pointer;
    font-size: 18px;
}

.modal-body {
    padding: 22px;
}

/* DETAILS */
.detail-title {
    font-size: 12px;
    font-weight: 600;
    margin-bottom: 10px;
}

.detail-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    border: 1px solid #e5e9ef;
    border-radius: 6px;
    overflow: hidden;
    margin-bottom: 20px;
}

.detail-item {
    padding: 13px;
    border-right: 1px solid #e5e9ef;
    border-bottom: 1px solid #e5e9ef;
}

.detail-label {
    font-size: 10px;
    color: #98a2b3;
    margin-bottom: 5px;
}

.detail-value {
    font-size: 12px;
    font-weight: 600;
    color: #344054;
}

/* ADMIN REMARK */
.remark-box {
    margin-top: 15px;
}

/* Probation Communication thread */

.comm-thread {
    padding: 12px;
    border-radius: 6px;
    background: #fafbfc;
    border: 1px solid #e5e9ef;
    margin-bottom: 6px;
    max-height: 260px;
    overflow-y: auto;
}

.comm-bubble {
    padding: 8px 10px;
    margin-bottom: 8px;
    border-radius: 6px;
    background: #fff;
    border: 1px solid #e5e9ef;
}

.comm-bubble:last-child {
    margin-bottom: 0;
}

.comm-bubble-header {
    display: flex;
    justify-content: space-between;
    margin-bottom: 4px;
    gap: 10px;
}

.comm-sender {
    font-size: 11px;
    font-weight: 700;
    color: #344054;
}

.comm-bubble.decision .comm-sender {
    color: #4f46e5;
}

.comm-time {
    font-size: 10px;
    color: #98a2b3;
    white-space: nowrap;
}

.comm-text {
    font-size: 12px;
    color: #344054;
}

.comm-empty {
    font-size: 12px;
    color: #98a2b3;
}

.remark-actions {
    display: flex;
    justify-content: flex-end;
    margin-top: 8px;
}

.remark-box label {
    display: block;
    margin-bottom: 7px;
    font-size: 11px;
    font-weight: 600;
}

textarea {
    width: 100%;
    min-height: 90px;
    resize: vertical;
    border: 1px solid #d8dee8;
    border-radius: 5px;
    padding: 10px;
    outline: none;
    font-family: Arial, sans-serif;
    font-size: 12px;
}

textarea:focus {
    border-color: #6366f1;
}

textarea:disabled {
    background: #f8fafc;
    color: #667085;
}

/* MODAL FOOTER */
.modal-footer {
    padding: 15px 22px;
    border-top: 1px solid #e7ebf0;
    display: flex;
    justify-content: flex-end;
    gap: 8px;
}

.modal-btn {
    height: 38px;
    padding: 0 15px;
    border-radius: 5px;
    font-size: 11px;
    font-weight: 600;
    cursor: pointer;
}

.modal-cancel {
    background: #fff;
    border: 1px solid #d0d5dd;
}

.modal-approve {
    background: #16a05d;
    border: 1px solid #16a05d;
    color: #fff;
}

.modal-reject {
    background: #fff;
    border: 1px solid #efc4c1;
    color: #d93025;
}

.modal-message {
    background: #4f46e5;
    border: 1px solid #4f46e5;
    color: #fff;
}

.modal-message:hover {
    background: #4338ca;
}

.modal-message:disabled {
    opacity: .5;
    cursor: not-allowed;
}

/* RESPONSIVE */
@media(max-width:1000px) {
    .summary-grid {
        grid-template-columns: repeat(2, 1fr);
    }

    .filters {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media(max-width:600px) {
    .page {
        padding: 15px;
    }

    .summary-grid {
        grid-template-columns: 1fr;
    }

    .filters {
        grid-template-columns: 1fr;
    }

    .detail-grid {
        grid-template-columns: 1fr;
    }
}
</style>
</head>

<body>

<div class="page">

    <!-- HEADER -->

    <div class="page-header">
        <h2>Employee Probation Period Requests</h2>
        <p>Review and manage employee probation completion requests</p>
    </div>


    <?php if ($error): ?>
        <div class="page-message error"><?= e($error) ?></div>
    <?php elseif ($months === null): ?>
        <div class="page-message error">No Probation Period Settings configured yet — add one first so completion dates can be calculated.</div>
    <?php endif; ?>


    <!-- SUMMARY CARDS -->

    <div class="summary-grid">

        <div class="summary-card total active" onclick="filterStatus('')">
            <div class="summary-label">Total Requests</div>
            <div class="summary-number" id="totalCount"><?= $totalCount ?></div>
            <div class="summary-icon">👥</div>
        </div>

        <div class="summary-card pending" onclick="filterStatus('Pending')">
            <div class="summary-label">Pending</div>
            <div class="summary-number" id="pendingCount"><?= $pendingCount ?></div>
            <div class="summary-icon">◷</div>
        </div>

        <div class="summary-card approved" onclick="filterStatus('Approved')">
            <div class="summary-label">Approved</div>
            <div class="summary-number" id="approvedCount"><?= $approvedCount ?></div>
            <div class="summary-icon">✓</div>
        </div>

        <div class="summary-card rejected" onclick="filterStatus('Rejected')">
            <div class="summary-label">Rejected</div>
            <div class="summary-number" id="rejectedCount"><?= $rejectedCount ?></div>
            <div class="summary-icon">×</div>
        </div>

    </div>


    <!-- FILTERS -->

    <div class="filter-card">

        <div class="filters">

            <select id="statusFilter" onchange="applyFilters()">
                <option value="">All Status</option>
                <option value="Pending">Pending</option>
                <option value="Approved">Approved</option>
                <option value="Rejected">Rejected</option>
            </select>

            <select id="departmentFilter" onchange="applyFilters()">
                <option value="">All Departments</option>
                <?php foreach ($departments as $dep): ?>
                    <option value="<?= e($dep) ?>"><?= e($dep) ?></option>
                <?php endforeach; ?>
            </select>

            <select id="designationFilter" onchange="applyFilters()">
                <option value="">All Designations</option>
                <?php foreach ($designations as $des): ?>
                    <option value="<?= e($des) ?>"><?= e($des) ?></option>
                <?php endforeach; ?>
            </select>

            <div class="search-box">
                <span class="search-icon">🔍</span>
                <input type="text" id="searchInput" placeholder="Search employee..." onkeyup="applyFilters()">
            </div>

        </div>

    </div>


    <!-- TABLE -->

    <div class="table-card">

        <div class="table-header">
            <h3>Employee Probation Period Requests</h3>
            <span id="resultCount">Showing <?= $totalCount ?> requests</span>
        </div>

        <div class="table-wrapper">

            <table>

                <thead>
                    <tr>
                        <th>Employee</th>
                        <th>Department</th>
                        <th>Designation</th>
                        <th>Joining Date</th>
                        <th>Probation Period</th>
                        <th>Completion Date</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>

                <tbody id="requestTable">

                    <?php if (empty($requests)): ?>
                        <tr>
                            <td colspan="8" style="text-align:center; color:#98a2b3; padding:30px;">No probation completion requests yet.</td>
                        </tr>
                    <?php endif; ?>

                    <?php foreach ($requests as $row): ?>
                        <?php
                            $statusLabel = ucfirst($row['probation_review_status']);
                            $joiningDisplay = date('d-m-Y', strtotime($row['joining_date']));
                            $completionDisplay = $row['completion_date'] ? date('d-m-Y', strtotime($row['completion_date'])) : '-';
                            $periodDisplay = $months !== null ? ($months . ' ' . ($months === 1 ? 'Month' : 'Months')) : '-';
                        ?>
                        <tr
                            data-name="<?= e($row['employee_name']) ?>"
                            data-department="<?= e($row['department_name'] ?? '') ?>"
                            data-designation="<?= e($row['designation_name'] ?? '') ?>"
                            data-status="<?= e($statusLabel) ?>"
                        >
                            <td>
                                <div class="employee">
                                    <div class="avatar"><?= e(initials($row['employee_name'])) ?></div>
                                    <div>
                                        <div class="employee-name"><?= e($row['employee_name']) ?></div>
                                        <div class="employee-id"><?= e($row['employee_id'] ?: ('ID ' . $row['id'])) ?></div>
                                    </div>
                                </div>
                            </td>
                            <td><?= e($row['department_name'] ?: '-') ?></td>
                            <td><?= e($row['designation_name'] ?: '-') ?></td>
                            <td><?= e($joiningDisplay) ?></td>
                            <td><?= e($periodDisplay) ?></td>
                            <td><?= e($completionDisplay) ?></td>
                            <td><span class="status <?= e($row['probation_review_status']) ?>"><?= e($statusLabel) ?></span></td>
                            <td>
                                <div class="actions">
                                    <button
                                        class="action-btn view-btn"
                                        onclick='openModal(<?= json_encode([
                                            'id' => (int) $row['id'],
                                            'name' => $row['employee_name'],
                                            'employeeId' => $row['employee_id'] ?: ('ID ' . $row['id']),
                                            'department' => $row['department_name'] ?: '-',
                                            'designation' => $row['designation_name'] ?: '-',
                                            'joining' => $joiningDisplay,
                                            'period' => $periodDisplay,
                                            'completion' => $completionDisplay,
                                            'status' => $statusLabel,
                                            'remark' => $row['probation_admin_remarks'],
                                            'reviewer' => $row['reviewer_name'],
                                            'replies' => $row['replies'],
                                        ], JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'
                                    >
                                        View
                                    </button>

                                    <?php if ($row['probation_review_status'] === 'pending'): ?>
                                        <button
                                            class="action-btn approve-btn"
                                            onclick='approveEmployee(<?= (int) $row['id'] ?>, <?= json_encode($row['employee_name'], JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'
                                        >
                                            Approve
                                        </button>

                                        <button
                                            class="action-btn reject-btn"
                                            onclick='rejectEmployee(<?= (int) $row['id'] ?>, <?= json_encode($row['employee_name'], JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'
                                        >
                                            Reject
                                        </button>

                                        <button
                                            class="action-btn delete-req-btn"
                                            onclick='deleteRequest(<?= (int) $row['id'] ?>, <?= json_encode($row['employee_name'], JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'
                                        >
                                            Delete
                                        </button>
                                    <?php else: ?>
                                        <button
                                            class="action-btn undo-btn"
                                            onclick='undoRequest(<?= (int) $row['id'] ?>, <?= json_encode($row['employee_name'], JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'
                                        >
                                            Undo
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>

                </tbody>

            </table>

        </div>

    </div>

</div>


<!-- ==========================================
     DETAILS MODAL
========================================== -->

<div class="modal-overlay" id="modal">

    <div class="modal">

        <div class="modal-header">
            <h3>Probation Completion Request</h3>
            <button class="close" onclick="closeModal()">×</button>
        </div>

        <div class="modal-body">

            <div class="detail-title">Employee Information</div>

            <div class="detail-grid">
                <div class="detail-item">
                    <div class="detail-label">Employee</div>
                    <div class="detail-value" id="modalName"></div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">Employee ID</div>
                    <div class="detail-value" id="modalId"></div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">Department</div>
                    <div class="detail-value" id="modalDepartment"></div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">Designation</div>
                    <div class="detail-value" id="modalDesignation"></div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">Date of Joining</div>
                    <div class="detail-value" id="modalJoining"></div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">Current Status</div>
                    <div class="detail-value" id="modalStatus"></div>
                </div>
            </div>

            <div class="detail-title">Probation Information</div>

            <div class="detail-grid">
                <div class="detail-item">
                    <div class="detail-label">Probation Period</div>
                    <div class="detail-value" id="modalPeriod"></div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">Probation Start Date</div>
                    <div class="detail-value" id="modalStart"></div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">Completion Date</div>
                    <div class="detail-value" id="modalCompletion"></div>
                </div>
            </div>

            <div class="detail-title">Probation Communication</div>

            <div class="comm-thread" id="commThread">
                <!-- populated by openModal() -->
            </div>

            <div class="remark-box">
                <label id="remarkLabel">Reply to Employee</label>
                <textarea id="adminRemark" placeholder="Enter a message for the employee..."></textarea>
                <div class="remark-actions" id="remarkActions">
                    <button type="button" class="modal-btn modal-message" id="modalMessageBtn" onclick="sendMessageFromModal()">
                        Send Message
                    </button>
                </div>
            </div>

        </div>

        <div class="modal-footer" id="modalFooter">
            <button class="modal-btn modal-cancel" onclick="closeModal()">Close</button>
            <button class="modal-btn modal-reject" id="modalRejectBtn" onclick="rejectFromModal()">Reject</button>
            <button class="modal-btn modal-approve" id="modalApproveBtn" onclick="approveFromModal()">Approve</button>
        </div>

    </div>

</div>


<script>

/* =========================================
   CSRF (reuses the Laravel session already active
   in this browser — same mechanism Axios's default
   XSRF wiring uses)
========================================= */

function getCookie(name) {
    const match = document.cookie.match(new RegExp('(^| )' + name + '=([^;]+)'));
    return match ? decodeURIComponent(match[2]) : null;
}

function postToLaravel(path, data) {
    return fetch(path, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-XSRF-TOKEN': getCookie('XSRF-TOKEN') || ''
        },
        body: JSON.stringify(data)
    }).then(function (response) {
        return response.json().catch(function () {
            throw new Error('Unexpected response (HTTP ' + response.status + '). Make sure you are logged in and reload the page.');
        });
    });
}


/* =========================================
   MODAL
========================================= */

let selectedRequest = null;

function escapeHtml(value) {
    return String(value == null ? "" : value)
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#39;");
}

function commBubbleHtml(sender, time, message, isDecision) {
    return '<div class="comm-bubble' + (isDecision ? ' decision' : '') + '">'
        + '<div class="comm-bubble-header">'
        + '<span class="comm-sender">' + escapeHtml(sender) + '</span>'
        + '<span class="comm-time">' + escapeHtml(time) + '</span>'
        + '</div>'
        + '<div class="comm-text">' + escapeHtml(message) + '</div>'
        + '</div>';
}

function renderCommThread(request) {

    const thread = document.getElementById("commThread");
    let html = "";

    (request.replies || []).forEach(function (reply) {
        html += commBubbleHtml(reply.sender + " — Admin", reply.timestamp, reply.message, false);
    });

    if (request.status !== "Pending" && request.remark) {
        const decisionLabel = (request.reviewer || "Admin") + " — " + request.status + " Decision";
        html += commBubbleHtml(decisionLabel, "", request.remark, true);
    }

    thread.innerHTML = html || '<div class="comm-empty">No messages yet.</div>';
    thread.scrollTop = thread.scrollHeight;

}

function openModal(request) {

    selectedRequest = request;

    document.getElementById("modalName").textContent = request.name;
    document.getElementById("modalId").textContent = request.employeeId;
    document.getElementById("modalDepartment").textContent = request.department;
    document.getElementById("modalDesignation").textContent = request.designation;
    document.getElementById("modalJoining").textContent = request.joining;
    document.getElementById("modalPeriod").textContent = request.period;
    document.getElementById("modalStart").textContent = request.joining;
    document.getElementById("modalCompletion").textContent = request.completion;
    document.getElementById("modalStatus").textContent = request.status;

    const isPending = request.status === "Pending";

    document.getElementById("modalApproveBtn").style.display = isPending ? "" : "none";
    document.getElementById("modalRejectBtn").style.display = isPending ? "" : "none";

    // The communication thread (and replying into it) stays available
    // regardless of whether the request has been decided yet — same as
    // Resignation Communication's reply box.
    document.getElementById("remarkLabel").textContent = "Reply to Employee";
    document.getElementById("adminRemark").value = "";
    document.getElementById("adminRemark").disabled = false;

    renderCommThread(request);

    document.getElementById("modal").classList.add("show");

}

function closeModal() {
    document.getElementById("modal").classList.remove("show");
}


/* =========================================
   APPROVE
========================================= */

function approveEmployee(id, name) {

    if (!confirm("Are you sure you want to approve the probation completion of " + name + "?")) {
        return;
    }

    postToLaravel("../account/settings/probation-requests/" + id + "/approve", { remark: "" })
        .then(function (response) {
            if (response.status === "success") {
                window.location.reload();
            } else {
                alert(response.message || "Could not approve this request.");
            }
        })
        .catch(function (err) {
            alert(err.message);
        });

}

function approveFromModal() {

    if (!selectedRequest) {
        return;
    }

    const remark = document.getElementById("adminRemark").value;

    if (!confirm("Approve probation completion for " + selectedRequest.name + "?")) {
        return;
    }

    postToLaravel("../account/settings/probation-requests/" + selectedRequest.id + "/approve", { remark: remark })
        .then(function (response) {
            if (response.status === "success") {
                window.location.reload();
            } else {
                alert(response.message || "Could not approve this request.");
            }
        })
        .catch(function (err) {
            alert(err.message);
        });

}


/* =========================================
   REJECT
========================================= */

function rejectEmployee(id, name) {

    const reason = prompt("Enter the reason for rejecting " + name + "'s probation completion:");

    if (!reason || reason.trim() === "") {
        return;
    }

    postToLaravel("../account/settings/probation-requests/" + id + "/reject", { remark: reason })
        .then(function (response) {
            if (response.status === "success") {
                window.location.reload();
            } else {
                alert(response.message || "Could not reject this request.");
            }
        })
        .catch(function (err) {
            alert(err.message);
        });

}

function rejectFromModal() {

    const reason = document.getElementById("adminRemark").value.trim();

    if (!reason) {
        alert("Please enter a reason before rejecting.");
        return;
    }

    if (!confirm("Reject probation completion for " + selectedRequest.name + "?")) {
        return;
    }

    postToLaravel("../account/settings/probation-requests/" + selectedRequest.id + "/reject", { remark: reason })
        .then(function (response) {
            if (response.status === "success") {
                window.location.reload();
            } else {
                alert(response.message || "Could not reject this request.");
            }
        })
        .catch(function (err) {
            alert(err.message);
        });

}


/* =========================================
   SEND MESSAGE (without approving/rejecting)
========================================= */

function sendMessageFromModal() {

    const message = document.getElementById("adminRemark").value.trim();

    if (!message) {
        alert("Please enter a message before sending.");
        return;
    }

    if (!confirm("Send this message to " + selectedRequest.name + "?")) {
        return;
    }

    const btn = document.getElementById("modalMessageBtn");
    btn.disabled = true;
    btn.textContent = "Sending...";

    postToLaravel("../account/settings/probation-requests/" + selectedRequest.id + "/message", { message: message })
        .then(function (response) {
            if (response.status === "success") {
                document.getElementById("adminRemark").value = "";

                if (!selectedRequest.replies) {
                    selectedRequest.replies = [];
                }
                selectedRequest.replies.push(response.reply);
                renderCommThread(selectedRequest);
            } else {
                alert(response.message || "Could not send this message.");
            }
        })
        .catch(function (err) {
            alert(err.message);
        })
        .finally(function () {
            btn.disabled = false;
            btn.textContent = "Send Message";
        });

}


/* =========================================
   UNDO
========================================= */

function undoRequest(id, name) {

    if (!confirm("Undo the decision for " + name + "'s probation request? It will go back to Pending so it can be reviewed again.")) {
        return;
    }

    postToLaravel("../account/settings/probation-requests/" + id + "/undo", {})
        .then(function (response) {
            if (response.status === "success") {
                window.location.reload();
            } else {
                alert(response.message || "Could not undo this request.");
            }
        })
        .catch(function (err) {
            alert(err.message);
        });

}


/* =========================================
   DELETE
========================================= */

function deleteRequest(id, name) {

    if (!confirm("Delete " + name + "'s probation request? This removes it from the list without approving or rejecting — it may reappear if the completion check runs again.")) {
        return;
    }

    postToLaravel("../account/settings/probation-requests/" + id + "/delete", {})
        .then(function (response) {
            if (response.status === "success") {
                window.location.reload();
            } else {
                alert(response.message || "Could not delete this request.");
            }
        })
        .catch(function (err) {
            alert(err.message);
        });

}


/* =========================================
   SUMMARY FILTER
========================================= */

function filterStatus(status) {
    document.getElementById("statusFilter").value = status;
    applyFilters();
}


/* =========================================
   FILTER
========================================= */

function applyFilters() {

    const status = document.getElementById("statusFilter").value.toLowerCase();
    const department = document.getElementById("departmentFilter").value.toLowerCase();
    const designation = document.getElementById("designationFilter").value.toLowerCase();
    const search = document.getElementById("searchInput").value.toLowerCase();

    const rows = document.querySelectorAll("#requestTable tr[data-status]");
    let visible = 0;

    rows.forEach(function (row) {

        const rowStatus = row.dataset.status.toLowerCase();
        const rowDepartment = row.dataset.department.toLowerCase();
        const rowDesignation = row.dataset.designation.toLowerCase();
        const rowName = row.dataset.name.toLowerCase();

        const matchStatus = !status || rowStatus === status;
        const matchDepartment = !department || rowDepartment === department;
        const matchDesignation = !designation || rowDesignation === designation;
        const matchSearch = !search || rowName.includes(search);

        if (matchStatus && matchDepartment && matchDesignation && matchSearch) {
            row.style.display = "";
            visible++;
        } else {
            row.style.display = "none";
        }

    });

    document.getElementById("resultCount").textContent = "Showing " + visible + " requests";

}


/* =========================================
   CLOSE OUTSIDE MODAL
========================================= */

document.getElementById("modal").addEventListener("click", function (event) {
    if (event.target === this) {
        closeModal();
    }
});

</script>

</body>
</html>
