<?php
/**
 * Notice Period Settings
 * Server-side (PHP) port of Notice_period_settings.html.
 *
 * Lists, adds, edits and deletes company notice period durations (in
 * months), persisted to the notice_period_settings table. ?edit=<id> loads
 * that row into the form; POST save_notice_period inserts (no
 * notice_period_id) or updates (with it); POST delete_notice_period_id
 * removes a row. Every save/delete redirects back here (Post/Redirect/Get)
 * so a page refresh never resubmits the form.
 */

// Guarded: the framework defines the same helper when this page is served
// through its authenticated route, and redeclaring it would be fatal.
if (!function_exists('e')) {
    function e($value)
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
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
// Delete
// ---------------------------------------------------------------------------
if ($pdo && $_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['delete_notice_period_id'])) {
    try {
        $stmt = $pdo->prepare('DELETE FROM notice_period_settings WHERE id = :id AND company_id = :companyId');
        $stmt->execute([
            ':id' => (int) $_POST['delete_notice_period_id'],
            ':companyId' => $companyId,
        ]);
        header('Location: Notice_period_settings.php?deleted=1');
        exit;
    } catch (PDOException $ex) {
        error_log('Notice period delete failed: ' . $ex->getMessage());
        $error = 'Could not delete. Please try again.';
    }
}

// ---------------------------------------------------------------------------
// Add / Update
// ---------------------------------------------------------------------------
$openModal = false;
$formEditId = null;
$formMonths = null;
$formName = null;

if ($pdo && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_notice_period'])) {
    $months = isset($_POST['months']) ? (int) $_POST['months'] : 0;
    $name = trim($_POST['name'] ?? '');
    $editingId = !empty($_POST['notice_period_id']) ? (int) $_POST['notice_period_id'] : null;

    if ($name === '') {
        $error = 'Please enter the notice period name.';
        $openModal = true;
        $formEditId = $editingId;
        $formMonths = $_POST['months'] ?? '';
        $formName = $name;
    } elseif ($months < 0 || $months > 60) {
        $error = 'Please enter a valid number of months (0-60).';
        $openModal = true;
        $formEditId = $editingId;
        $formMonths = $_POST['months'] ?? '';
        $formName = $name;
    } else {
        try {
            if ($editingId) {
                $stmt = $pdo->prepare('UPDATE notice_period_settings SET name = :name, months = :months, updated_at = NOW() WHERE id = :id AND company_id = :companyId');
                $stmt->execute([':name' => $name, ':months' => $months, ':id' => $editingId, ':companyId' => $companyId]);
                header('Location: Notice_period_settings.php?updated=1');
            } else {
                $stmt = $pdo->prepare('INSERT INTO notice_period_settings (company_id, name, months, created_at, updated_at) VALUES (:companyId, :name, :months, NOW(), NOW())');
                $stmt->execute([':companyId' => $companyId, ':name' => $name, ':months' => $months]);
                header('Location: Notice_period_settings.php?added=1');
            }
            exit;
        } catch (PDOException $ex) {
            error_log('Notice period save failed: ' . $ex->getMessage());
            $error = 'Could not save. Please try again.';
            $openModal = true;
            $formEditId = $editingId;
            $formMonths = (string) $months;
            $formName = $name;
        }
    }
}

// ---------------------------------------------------------------------------
// Row being edited — either ?edit=<id> on a fresh GET, or the row a failed
// save above was trying to update (so a validation/save error re-opens the
// modal in the same Edit context instead of dropping back to Add).
// ---------------------------------------------------------------------------
$editingRow = null;
$editId = isset($_GET['edit']) ? (int) $_GET['edit'] : $formEditId;

if ($pdo && $editId) {
    $stmt = $pdo->prepare('SELECT * FROM notice_period_settings WHERE id = :id AND company_id = :companyId LIMIT 1');
    $stmt->execute([':id' => $editId, ':companyId' => $companyId]);
    $editingRow = $stmt->fetch() ?: null;

    if ($editingRow) {
        $openModal = true;

        if ($formMonths !== null) {
            $editingRow['months'] = $formMonths;
        }

        if ($formName !== null) {
            $editingRow['name'] = $formName;
        }
    }
}

$formMonthsValue = $formMonths !== null ? $formMonths : ($editingRow['months'] ?? '');
$formNameValue = $formName !== null ? $formName : ($editingRow['name'] ?? '');

// ---------------------------------------------------------------------------
// List
// ---------------------------------------------------------------------------
$noticePeriods = [];

if ($pdo) {
    $stmt = $pdo->prepare('SELECT * FROM notice_period_settings WHERE company_id = :companyId ORDER BY created_at DESC, id DESC');
    $stmt->execute([':companyId' => $companyId]);
    $noticePeriods = $stmt->fetchAll();
}

$successMessage = null;
if (isset($_GET['added'])) {
    $successMessage = 'Notice period added successfully.';
} elseif (isset($_GET['updated'])) {
    $successMessage = 'Notice period updated successfully.';
} elseif (isset($_GET['deleted'])) {
    $successMessage = 'Notice period deleted successfully.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Notice Period Settings</title>

<style>

/* ================================
   RESET
================================ */

* {
    box-sizing: border-box;
}

body {
    margin: 0;
    font-family: Arial, Helvetica, sans-serif;
    background: #f4f7fb;
    color: #344054;
    font-size: 13px;
}


/* ================================
   PAGE
================================ */

.page {
    padding: 28px;
    max-width: 1250px;
    margin: 0 auto;
}


/* ================================
   PAGE HEADER
================================ */

.page-header {
    margin-bottom: 22px;
}

.page-header h2 {
    margin: 0;
    font-size: 21px;
    font-weight: 600;
    color: #1f2937;
}

.page-header p {
    margin: 6px 0 0;
    font-size: 12px;
    color: #8a94a6;
}


/* ================================
   MAIN CONTENT
================================ */

.content {
    display: grid;
    grid-template-columns: 1fr;
    gap: 20px;
    align-items: start;
}

.content .card:first-child {
    max-width: 500px;
}


/* ================================
   CARD
================================ */

.card {
    background: #ffffff;
    border: 1px solid #e1e6ed;
    border-radius: 8px;
    box-shadow: 0 2px 7px rgba(0, 0, 0, 0.03);
    overflow: hidden;
}


/* ================================
   CARD HEADER
================================ */

.card-header {
    padding: 18px 20px;
    border-bottom: 1px solid #e8ecf1;
}

.card-header h3 {
    margin: 0;
    font-size: 14px;
    font-weight: 600;
    color: #344054;
}

.card-header p {
    margin: 5px 0 0;
    font-size: 11px;
    color: #98a2b3;
}


/* ================================
   CARD BODY
================================ */

.card-body {
    padding: 20px;
}


/* ================================
   FORM
================================ */

.form-group {
    margin-bottom: 20px;
}

.form-group label {
    display: block;
    margin-bottom: 7px;
    font-size: 12px;
    font-weight: 600;
    color: #475467;
}

.required {
    color: #e53935;
}


/* ================================
   INPUT
================================ */

.input-wrapper {
    position: relative;
}

.input-wrapper input {
    width: 100%;
    height: 42px;
    padding: 0 75px 0 12px;
    border: 1px solid #d8dee8;
    border-radius: 5px;
    outline: none;
    font-size: 13px;
    color: #344054;
    background: #ffffff;
}

.input-wrapper.no-unit input {
    padding-right: 12px;
}

.input-wrapper input::placeholder {
    color: #a0a8b5;
}

.input-wrapper input:focus {
    border-color: #6366f1;
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.08);
}

.input-unit {
    position: absolute;
    right: 12px;
    top: 13px;
    color: #98a2b3;
    font-size: 12px;
    pointer-events: none;
}


/* ================================
   HELP TEXT
================================ */

.help-text {
    margin-top: 7px;
    font-size: 10px;
    color: #98a2b3;
    line-height: 1.5;
}


/* ================================
   BUTTONS
================================ */

.form-actions {
    display: flex;
    justify-content: flex-end;
    gap: 8px;
    margin-top: 5px;
}

button {
    font-family: inherit;
}

.btn {
    height: 38px;
    padding: 0 17px;
    border-radius: 5px;
    font-size: 12px;
    font-weight: 600;
    cursor: pointer;
    transition: 0.15s ease;
    border: none;
}

.btn-cancel {
    background: #ffffff;
    border: 1px solid #d0d5dd;
    color: #475467;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
}

.btn-cancel:hover {
    background: #f8fafc;
}

.btn-save {
    background: #4f46e5;
    border: 1px solid #4f46e5;
    color: #ffffff;
}

.btn-save:hover {
    background: #4338ca;
}


/* ================================
   MESSAGES
================================ */

.success-message {
    margin-bottom: 17px;
    padding: 11px 13px;
    border: 1px solid #bce5cc;
    background: #eaf8ef;
    color: #18864b;
    border-radius: 5px;
    font-size: 11px;
}

.error-message {
    margin-bottom: 17px;
    padding: 11px 13px;
    border: 1px solid #f2c5c5;
    background: #fdeceb;
    color: #a13a2f;
    border-radius: 5px;
    font-size: 11px;
}


/* ================================
   PERIOD LIST
================================ */

.period-list {
    width: 100%;
}


/* ================================
   PERIOD ROW
================================ */

.period-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 15px;
    padding: 16px 20px;
    border-bottom: 1px solid #edf0f4;
}

.period-row:last-child {
    border-bottom: none;
}


/* ================================
   PERIOD INFORMATION
================================ */

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
    background: #eef2ff;
    color: #4f46e5;
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

.period-date {
    margin-top: 4px;
    font-size: 10px;
    color: #98a2b3;
}


/* ================================
   RIGHT SIDE
================================ */

.period-right {
    display: flex;
    align-items: center;
    gap: 7px;
    flex-shrink: 0;
}


/* ================================
   PERIOD BADGE
================================ */

.period-badge {
    padding: 6px 10px;
    border-radius: 20px;
    background: #e8f7ed;
    color: #18864b;
    font-size: 11px;
    font-weight: 600;
    white-space: nowrap;
}


/* ================================
   ACTION BUTTONS
================================ */

.action-btn {
    height: 30px;
    padding: 0 10px;
    border-radius: 5px;
    font-size: 10px;
    font-weight: 600;
    cursor: pointer;
    background: #ffffff;
    transition: 0.15s ease;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
}


/* EDIT */

.edit-btn {
    color: #4f46e5;
    border: 1px solid #cfd0ff;
}

.edit-btn:hover {
    background: #f3f2ff;
}


/* DELETE */

.delete-btn {
    color: #dc2626;
    border: 1px solid #f2c5c5;
}

.delete-btn:hover {
    background: #fff5f5;
}

.delete-form {
    margin: 0;
}


/* ================================
   EMPTY STATE
================================ */

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


/* ================================
   EDIT MODE
================================ */

.editing-notice {
    display: none;
    margin-bottom: 15px;
    padding: 9px 11px;
    border-radius: 5px;
    background: #eef2ff;
    color: #4f46e5;
    font-size: 11px;
}

.editing-notice.show {
    display: block;
}


/* ================================
   CARD HEADER ACTIONS
================================ */

.card-header-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
}

.card-header-row .card-header-text {
    flex: 1;
    min-width: 0;
}


/* ================================
   MODAL
================================ */

.modal-overlay {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(15, 23, 42, 0.45);
    z-index: 1000;
    align-items: center;
    justify-content: center;
    padding: 20px;
}

.modal-overlay.show {
    display: flex;
}

.modal-overlay .card {
    width: 100%;
    max-width: 440px;
    max-height: calc(100vh - 40px);
    overflow-y: auto;
}

.modal-close-btn {
    border: none;
    background: transparent;
    color: #98a2b3;
    font-size: 18px;
    line-height: 1;
    cursor: pointer;
    padding: 2px 4px;
}

.modal-close-btn:hover {
    color: #475467;
}


/* ================================
   RESPONSIVE
================================ */

@media (max-width: 900px) {

    .content {
        grid-template-columns: 1fr;
    }

}


@media (max-width: 650px) {

    .page {
        padding: 15px;
    }

    .period-row {
        align-items: flex-start;
        flex-direction: column;
    }

    .period-right {
        width: 100%;
        justify-content: flex-end;
    }

}


@media (max-width: 450px) {

    .period-right {
        flex-wrap: wrap;
        justify-content: flex-start;
    }

    .form-actions {
        flex-direction: column;
    }

    .form-actions button,
    .form-actions a {
        width: 100%;
        justify-content: center;
    }

}

</style>
</head>


<body>

<div class="page">


    <!-- =====================================
         PAGE HEADER
    ====================================== -->

    <div class="page-header">

        <h2>
            Notice Period
        </h2>

        <p>
            Configure the notice period for employees
        </p>

    </div>


    <?php if ($successMessage): ?>
        <div class="success-message">
            ✓ <?= e($successMessage) ?>
        </div>
    <?php elseif ($error && !$openModal): ?>
        <div class="error-message">
            <?= e($error) ?>
        </div>
    <?php endif; ?>


    <!-- =====================================
         CONTENT
    ====================================== -->

    <div class="content">


        <!-- =================================
             ADD / EDIT NOTICE PERIOD MODAL
        ================================== -->

        <div
            class="modal-overlay<?= $openModal ? ' show' : '' ?>"
            id="noticePeriodModal"
            onclick="if (event.target === this) { closeNoticeModal(); }"
        >

        <div class="card">

            <div class="card-header card-header-row">

                <div class="card-header-text">

                    <h3>
                        <?= $editingRow ? 'Edit Notice Period' : 'Add Notice Period' ?>
                    </h3>

                    <p>
                        Enter the notice period duration in months
                    </p>

                </div>

                <button
                    type="button"
                    class="modal-close-btn"
                    onclick="closeNoticeModal()"
                    aria-label="Close"
                >
                    ✕
                </button>

            </div>


            <div class="card-body">


                <?php if ($error && $openModal): ?>
                    <div class="error-message">
                        <?= e($error) ?>
                    </div>
                <?php endif; ?>


                <!-- EDITING -->

                <div class="editing-notice<?= $editingRow ? ' show' : '' ?>">
                    Editing notice period
                </div>


                <!-- FORM -->

                <form method="post" action="Notice_period_settings.php">

                    <?php if ($editingRow): ?>
                        <input type="hidden" name="notice_period_id" value="<?= e($editingRow['id']) ?>">
                    <?php endif; ?>


                    <!-- NAME -->

                    <div class="form-group">

                        <label for="noticeName">

                            Notice Period Name

                            <span class="required">
                                *
                            </span>

                        </label>


                        <div class="input-wrapper no-unit">

                            <input
                                type="text"
                                id="noticeName"
                                name="name"
                                maxlength="100"
                                placeholder="Enter notice period name"
                                value="<?= e($formNameValue) ?>"
                                required
                            >

                        </div>


                        <div class="help-text">

                            A short label to identify this notice period
                            (e.g. Standard Notice Period).

                        </div>

                    </div>


                    <!-- MONTHS -->

                    <div class="form-group">

                        <label for="noticeMonths">

                            Notice Period

                            <span class="required">
                                *
                            </span>

                        </label>


                        <div class="input-wrapper">

                            <input
                                type="number"
                                id="noticeMonths"
                                name="months"
                                min="0"
                                max="60"
                                step="1"
                                placeholder="Enter number of months"
                                value="<?= e($formMonthsValue) ?>"
                                required
                            >

                            <span class="input-unit">
                                Months
                            </span>

                        </div>


                        <div class="help-text">

                            Enter the standard notice period
                            applicable to employees.

                        </div>

                    </div>


                    <!-- BUTTONS -->

                    <div class="form-actions">

                        <a
                            href="Notice_period_settings.php"
                            class="btn btn-cancel"
                        >
                            Cancel
                        </a>


                        <button
                            type="submit"
                            name="save_notice_period"
                            value="1"
                            class="btn btn-save"
                        >
                            <?= $editingRow ? 'Update' : 'Save' ?>
                        </button>

                    </div>


                </form>

            </div>

        </div>

        </div>
        <!-- END ADD / EDIT NOTICE PERIOD MODAL -->



        <!-- =================================
             ADDED PERIODS CARD
        ================================== -->

        <div class="card">


            <div class="card-header card-header-row">

                <div class="card-header-text">

                    <h3>
                        Added Notice Periods
                    </h3>

                    <p>
                        Existing notice period configurations
                    </p>

                </div>

                <button
                    type="button"
                    class="btn btn-save"
                    onclick="openNoticeModal()"
                >
                    + Add Notice Period
                </button>

            </div>


            <div class="period-list">


                <?php if (empty($noticePeriods)): ?>

                    <div class="empty-state">

                        <div class="empty-icon">
                            ◷
                        </div>

                        <p>
                            No notice periods added yet.
                        </p>

                    </div>

                <?php else: ?>

                    <?php foreach ($noticePeriods as $row): ?>

                        <?php
                            $months = (int) $row['months'];
                            $wasUpdated = $row['updated_at'] !== $row['created_at'];
                            $stamp = $wasUpdated ? $row['updated_at'] : $row['created_at'];
                            $label = ($wasUpdated ? 'Updated on ' : 'Added on ') . date('d-m-Y', strtotime($stamp));
                        ?>

                        <div class="period-row">

                            <div class="period-info">

                                <div class="period-icon">
                                    ◷
                                </div>


                                <div>

                                    <div class="period-name">
                                        <?= e($row['name'] !== null && $row['name'] !== '' ? $row['name'] : 'Notice Period') ?>
                                    </div>

                                    <div class="period-date">
                                        <?= e($label) ?>
                                    </div>

                                </div>

                            </div>


                            <div class="period-right">

                                <div class="period-badge">
                                    <?= $months ?> <?= $months === 1 ? 'Month' : 'Months' ?>
                                </div>


                                <a
                                    href="Notice_period_settings.php?edit=<?= (int) $row['id'] ?>"
                                    class="action-btn edit-btn"
                                >
                                    Edit
                                </a>


                                <form
                                    method="post"
                                    action="Notice_period_settings.php"
                                    class="delete-form"
                                    onsubmit="return confirm('Are you sure you want to delete the <?= $months ?> month notice period?');"
                                >
                                    <input type="hidden" name="delete_notice_period_id" value="<?= (int) $row['id'] ?>">
                                    <button type="submit" class="action-btn delete-btn">
                                        Delete
                                    </button>
                                </form>

                            </div>

                        </div>

                    <?php endforeach; ?>

                <?php endif; ?>


            </div>

        </div>

    </div>

</div>

<script>

/* ==========================================
   ADD / EDIT MODAL
========================================== */

function openNoticeModal() {

    document.getElementById("noticePeriodModal").classList.add("show");

    var input = document.getElementById("noticeName");

    if (input) {
        input.focus();
    }

}


function closeNoticeModal() {

    /* Full round-trip so the server always re-renders a clean Add form —
       this page has no client-side state to reset otherwise. */

    window.location.href = <?= json_encode(function_exists('route') ? route('legacy.page', 'notice_period_settings') : 'Notice_period_settings.php') ?>;

}

</script>

</body>
</html>
