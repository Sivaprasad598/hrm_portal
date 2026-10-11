<?php
/**
 * Arowana - Payslip Entry
 * Server-side (PHP) port of payslips_entry.html.
 *
 * Lets HR pick a department, then an employee within it, and jump straight
 * to that employee's payslip. Department/employee lists are pulled live
 * from the teams / employee_details / users / designations tables.
 */

// ---------------------------------------------------------------------------
// Include database config (Laravel style)
// ---------------------------------------------------------------------------
$dbConfig = @include __DIR__ . '/config/database.php';
$dbConn = $dbConfig['connections']['mysql'] ?? null;

// Where "View Offer Letter" goes.
//
// It was written as the relative "../account/offer-letter". That resolved
// correctly when this file was served from the web root, but it is now served
// through the framework at /account/legacy/payslip_entry and shown in an
// iframe — so the browser resolved it one directory too deep and asked for
// /account/account/offer-letter, which is a 404. Asking the router gives an
// absolute path that does not care where the page is served from; the literal
// stays as the fallback for running this file on its own.
$offerLetterUrl = function_exists('route') ? route('offer-letter.index') : '../account/offer-letter';

// Guarded: the framework defines the same helper when this page is served
// through its authenticated route, and redeclaring it would be fatal.
if (!function_exists('e')) {
    function e($value)
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}

// ---------------------------------------------------------------------------
// Fetch departments and their employees
// ---------------------------------------------------------------------------
$departments = [];
$employeesByDepartment = [];

if ($dbConn) {
    try {
        $dsn = "mysql:host={$dbConn['host']};port={$dbConn['port']};dbname={$dbConn['database']};charset={$dbConn['charset']}";
        $pdo = new PDO($dsn, $dbConn['username'], $dbConn['password'] ?? '', [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);

        $deptStmt = $pdo->query("SELECT id, team_name FROM teams ORDER BY team_name");
        $departments = $deptStmt->fetchAll();

        $empStmt = $pdo->query(
            "SELECT ed.department_id,
                    ed.employee_card_id AS card_id,
                    u.name AS employee_name,
                    d.name AS designation_name
             FROM employee_details ed
             LEFT JOIN users u ON u.id = ed.user_id
             LEFT JOIN designations d ON d.id = ed.designation_id
             WHERE ed.department_id IS NOT NULL
               AND ed.employee_card_id IS NOT NULL
             ORDER BY u.name"
        );

        foreach ($empStmt->fetchAll() as $row) {
            $employeesByDepartment[$row['department_id']][] = [
                'cardId' => $row['card_id'],
                'name' => $row['employee_name'] ?? '—',
                'designation' => $row['designation_name'] ?? '—',
            ];
        }
    } catch (PDOException $e) {
        error_log('Payslip entry fetch failed: ' . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Department & Employee Selection</title>

<style>

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    font-family: Arial, Helvetica, sans-serif;
    background: #f6f6f6;
    color: #333;
}

/* MAIN */

.container {
    max-width: 1100px;
    margin: 25px auto;
    padding: 0 20px;
}

/* PAGE HEADER */

.page-header {
    background: white;
    border: 1px solid #e5e5e5;
    border-radius: 10px;
    padding: 25px;
    margin-bottom: 20px;
}

.page-header h1 {
    font-size: 22px;
    margin-bottom: 7px;
}

.page-header p {
    font-size: 13px;
    color: #888;
}

/* CARD */

.card {
    background: white;
    border: 1px solid #e3e3e3;
    border-radius: 10px;
    padding: 25px;
}

/* FORM */

.form-group {
    margin-bottom: 20px;
}

label {
    display: block;
    font-size: 13px;
    font-weight: 600;
    color: #555;
    margin-bottom: 8px;
}

.required {
    color: #d9534f;
}

select {
    width: 100%;
    height: 45px;
    border: 1px solid #ddd;
    border-radius: 6px;
    padding: 0 12px;
    font-size: 13px;
    outline: none;
    background: white;
    cursor: pointer;
}

select:focus {
    border-color: #f4ac83;
    box-shadow: 0 0 0 3px rgba(244,172,131,.15);
}

/* EMPLOYEE SECTION */

.employee-section {
    display: none;
    margin-top: 25px;
    border-top: 1px solid #eee;
    padding-top: 25px;
}

.employee-section.show {
    display: block;
}

.employee-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 15px;
    margin-bottom: 15px;
}

.employee-title {
    font-size: 14px;
    font-weight: 600;
    white-space: nowrap;
}

/* EMPLOYEE SEARCH */

.employee-search {
    width: 330px;
    position: relative;
}

.employee-search input {
    width: 100%;
    height: 40px;
    border: 1px solid #ddd;
    border-radius: 6px;
    padding: 0 12px 0 38px;
    font-size: 12px;
    outline: none;
}

.employee-search input:focus {
    border-color: #f4ac83;
    box-shadow: 0 0 0 3px rgba(244,172,131,.12);
}

.search-icon {
    position: absolute;
    left: 13px;
    top: 11px;
    color: #999;
    font-size: 14px;
}

/* EMPLOYEE COUNT */

.employee-count {
    font-size: 11px;
    color: #999;
    margin-bottom: 12px;
}

/* EMPLOYEES */

.employee-list {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 12px;
}

.employee-card {
    border: 1px solid #ddd;
    border-radius: 8px;
    padding: 15px;
    cursor: pointer;
    transition: .2s;
    background: white;
}

.employee-card:hover {
    border-color: #f4ac83;
    background: #fffaf7;
}

.employee-card.selected {
    border-color: #f4ac83;
    background: #fff1e8;
    box-shadow: 0 0 0 1px #f4ac83;
}

.employee-name {
    font-size: 13px;
    font-weight: 600;
    margin-bottom: 7px;
}

.employee-card-id {
    font-size: 11px;
    color: #777;
    margin-bottom: 5px;
}

.employee-designation {
    font-size: 11px;
    color: #999;
}

/* EMPTY */

.empty-message {
    display: none;
    padding: 20px;
    text-align: center;
    background: #fafafa;
    border: 1px dashed #ddd;
    border-radius: 7px;
    font-size: 12px;
    color: #999;
}

/* BUTTON */

.button-area {
    margin-top: 25px;
    display: flex;
    justify-content: flex-end;
}

.submit-btn {
    border: none;
    background: #f4ac83;
    color: #222;
    padding: 12px 25px;
    border-radius: 6px;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
}

.submit-btn:hover {
    background: #e59768;
}

/* RESULT */

.result {
    display: none;
    margin-top: 20px;
    background: #fff8f4;
    border: 1px solid #f0d4c3;
    border-radius: 8px;
    padding: 20px;
}

.result.show {
    display: block;
}

.result-title {
    font-size: 14px;
    font-weight: 600;
    margin-bottom: 15px;
    color: #555;
}

.result-row {
    display: flex;
    justify-content: space-between;
    padding: 9px 0;
    border-bottom: 1px solid #f1ded4;
    font-size: 12px;
}

.result-row:last-child {
    border-bottom: none;
}

.result-label {
    color: #888;
}

.result-value {
    font-weight: 600;
    color: #333;
}

.view-payslip-btn {
    display: inline-block;
    margin-top: 15px;
    border: none;
    background: #f4ac83;
    color: #222;
    padding: 11px 22px;
    border-radius: 6px;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    text-decoration: none;
}

.view-payslip-btn:hover {
    background: #e59768;
}

/* RESPONSIVE */

@media(max-width: 800px) {
    .employee-header {
        flex-direction: column;
        align-items: stretch;
    }
    .employee-search {
        width: 100%;
    }
    .employee-list {
        grid-template-columns: 1fr;
    }
    .container {
        margin-top: 20px;
    }
}

</style>
</head>

<body>

<div class="container">

    <!-- PAGE HEADER -->
    <div class="page-header">
        <h1>Department &amp; Employee Selection</h1>
        <p>Select a department and choose an employee by searching their name or Card ID.</p>
    </div>

    <!-- CARD -->
    <div class="card">

        <!-- DEPARTMENT -->
        <div class="form-group">
            <label>Department <span class="required">*</span></label>

            <select id="department">
                <option value="">Select Department</option>
                <?php foreach ($departments as $dept): ?>
                    <option value="<?= e($dept['id']) ?>"><?= e($dept['team_name']) ?></option>
                <?php endforeach; ?>
            </select>

            <?php if (empty($departments)): ?>
                <div class="empty-message" style="display:block; margin-top: 10px;">No departments found.</div>
            <?php endif; ?>
        </div>

        <!-- EMPLOYEE SECTION -->
        <div class="employee-section" id="employeeSection">

            <div class="employee-header">
                <div class="employee-title">Employees</div>

                <div class="employee-search">
                    <span class="search-icon">🔍</span>
                    <input type="text" id="employeeSearch" placeholder="Search by Employee Name or Card ID">
                </div>
            </div>

            <div class="employee-count" id="employeeCount"></div>

            <div class="employee-list" id="employeeList"></div>

            <div class="empty-message" id="emptyMessage">No employees found.</div>

        </div>

        <!-- SUBMIT -->
        <div class="button-area">
            <button type="button" class="submit-btn" id="submitBtn">Submit</button>
        </div>

    </div>

    <!-- RESULT -->
    <div class="result" id="result">

        <div class="result-title">Submitted Information</div>

        <div class="result-row">
            <span class="result-label">Department</span>
            <span class="result-value" id="resultDepartment"></span>
        </div>

        <div class="result-row">
            <span class="result-label">Employee Name</span>
            <span class="result-value" id="resultEmployee"></span>
        </div>

        <div class="result-row">
            <span class="result-label">Card ID</span>
            <span class="result-value" id="resultCardId"></span>
        </div>

        <div class="result-row">
            <span class="result-label">Designation</span>
            <span class="result-value" id="resultDesignation"></span>
        </div>

        <a href="#" class="view-payslip-btn" id="viewOfferLetterBtn" target="_top">View Offer Letter →</a>

    </div>

</div>

<script>
    const OFFER_LETTER_URL = <?= json_encode($offerLetterUrl) ?>;


/* =====================================================
   EMPLOYEE DATA (from the database)
===================================================== */

const employees = <?= json_encode($employeesByDepartment, JSON_HEX_TAG | JSON_HEX_AMP) ?>;

/* =====================================================
   VARIABLES
===================================================== */

let selectedEmployee = null;
let currentEmployees = [];

/* =====================================================
   ELEMENTS
===================================================== */

const department = document.getElementById("department");
const employeeSection = document.getElementById("employeeSection");
const employeeSearch = document.getElementById("employeeSearch");
const employeeList = document.getElementById("employeeList");
const employeeCount = document.getElementById("employeeCount");
const emptyMessage = document.getElementById("emptyMessage");
const submitBtn = document.getElementById("submitBtn");

/* =====================================================
   DEPARTMENT CHANGE
===================================================== */

department.addEventListener("change", function () {
    const selectedDepartment = this.value;

    selectedEmployee = null;
    employeeSearch.value = "";
    employeeList.innerHTML = "";
    document.getElementById("result").classList.remove("show");

    if (!selectedDepartment) {
        employeeSection.classList.remove("show");
        return;
    }

    employeeSection.classList.add("show");
    currentEmployees = employees[selectedDepartment] || [];
    renderEmployees(currentEmployees);
});

/* =====================================================
   RENDER EMPLOYEES
===================================================== */

function renderEmployees(employeeData) {
    employeeList.innerHTML = "";

    employeeCount.textContent = employeeData.length + " employee" + (employeeData.length !== 1 ? "s" : "") + " found";

    if (employeeData.length === 0) {
        emptyMessage.style.display = "block";
        return;
    }

    emptyMessage.style.display = "none";

    employeeData.forEach(function (employee) {
        const card = document.createElement("div");
        card.className = "employee-card";

        if (selectedEmployee && selectedEmployee.cardId === employee.cardId) {
            card.classList.add("selected");
        }

        card.innerHTML = `
            <div class="employee-name">${escapeHTML(employee.name)}</div>
            <div class="employee-card-id">Card ID: ${escapeHTML(employee.cardId)}</div>
            <div class="employee-designation">${escapeHTML(employee.designation)}</div>
        `;

        card.addEventListener("click", function () {
            document.querySelectorAll(".employee-card").forEach(function (item) {
                item.classList.remove("selected");
            });

            card.classList.add("selected");
            selectedEmployee = employee;
        });

        employeeList.appendChild(card);
    });
}

/* =====================================================
   EMPLOYEE SEARCH
===================================================== */

employeeSearch.addEventListener("input", function () {
    const search = this.value.trim().toLowerCase();

    if (!search) {
        renderEmployees(currentEmployees);
        return;
    }

    const filteredEmployees = currentEmployees.filter(function (employee) {
        const name = employee.name.toLowerCase();
        const cardId = String(employee.cardId).toLowerCase();
        return name.includes(search) || cardId.includes(search);
    });

    renderEmployees(filteredEmployees);
});

/* =====================================================
   SUBMIT
===================================================== */

submitBtn.addEventListener("click", function () {
    const selectedDepartment = department.value;

    if (!selectedDepartment) {
        alert("Please select a department.");
        return;
    }

    if (!selectedEmployee) {
        alert("Please select an employee.");
        return;
    }

    const selectedDepartmentName = department.options[department.selectedIndex].text;

    document.getElementById("resultDepartment").textContent = selectedDepartmentName;
    document.getElementById("resultEmployee").textContent = selectedEmployee.name;
    document.getElementById("resultCardId").textContent = selectedEmployee.cardId;
    document.getElementById("resultDesignation").textContent = selectedEmployee.designation;

    const offerLetterParams = new URLSearchParams({
        emp_id: selectedEmployee.cardId,
        emp_name: selectedEmployee.name,
        emp_designation: selectedEmployee.designation
    });
    document.getElementById("viewOfferLetterBtn").href = OFFER_LETTER_URL + "?" + offerLetterParams.toString();

    document.getElementById("result").classList.add("show");

    document.getElementById("result").scrollIntoView({ behavior: "smooth", block: "center" });
});

/* =====================================================
   ESCAPE HTML
===================================================== */

function escapeHTML(value) {
    return String(value)
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#039;");
}

</script>

</body>
</html>
