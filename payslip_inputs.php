<?php
/**
 * Payroll Salary Components
 * Server-side (PHP) port of payslip_inputs.html.
 *
 * Shows/edits just the salary-component percentages (Basic, HRA, CA, PT,
 * ESI, PF) and saves them into payroll_calculations. Gross / working days /
 * days present aren't editable on this lean form.
 *
 * Accepts ?id=123 to edit that specific payroll_calculations row (values +
 * gross/basic/attendance carried forward from it). With no ?id=, the form
 * starts blank/default — this is "Add New", and Save inserts a new row
 * rather than updating an existing one.
 */

// Guarded: the framework defines the same helper when this page is served
// through its authenticated route, and redeclaring it would be fatal.
if (!function_exists('e')) {
    function e($value)
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}

function fmt($value)
{
    return number_format((float) $value, (fmod((float) $value, 1) == 0) ? 0 : 2);
}

$dbConfig = @include __DIR__ . '/config/database.php';
$dbConn = $dbConfig['connections']['mysql'] ?? null;

$DEFAULTS = [
    'gross'        => 37500,
    'basicPct'     => 30,
    'hraPct'       => 50,
    'caThreshold'  => 20000,
    'caHigh'       => 1600,
    'caLow'        => 800,
    'pt'           => 200,
    'esiThreshold' => 20000,
    'esiPct'       => 0.4,
    'pfPct'        => 12,
    'workingDays'  => 22,
    'daysPresent'  => 22,
];

$latestRecordId = null;
$successMessage = null;
$error = null;
$pdo = null;

if ($dbConn) {
    try {
        $dsn = "mysql:host={$dbConn['host']};port={$dbConn['port']};dbname={$dbConn['database']};charset={$dbConn['charset']}";
        $pdo = new PDO($dsn, $dbConn['username'], $dbConn['password'] ?? '', [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    } catch (PDOException $ex) {
        $error = 'Could not connect to the database.';
    }
}

// ---------------------------------------------------------------------------
// Fetch the record being edited (?id=), if any — pre-fill + gross/attendance
// carried forward from it. No ?id= means Add New: blank/default, and Save
// inserts a new row.
// ---------------------------------------------------------------------------
$latestRecord = null;
$editId = isset($_GET['id']) ? (int) $_GET['id'] : null;

if ($pdo && $editId) {
    $stmt = $pdo->prepare("SELECT * FROM payroll_calculations WHERE id = :id LIMIT 1");
    $stmt->execute([':id' => $editId]);
    $latestRecord = $stmt->fetch() ?: null;
    $latestRecordId = $latestRecord['id'] ?? null;
}

$carry = [
    'gross'       => (float) ($latestRecord['gross'] ?? $DEFAULTS['gross']),
    'workingDays' => (float) ($latestRecord['working_days'] ?? $DEFAULTS['workingDays']),
    'daysPresent' => (float) ($latestRecord['days_present'] ?? $DEFAULTS['daysPresent']),
    // Basic % has no field on this lean form — carry forward whatever was
    // last saved (via this form or the full calculator) instead of exposing
    // it here.
    'basicPct'    => (float) ($latestRecord['basic_pct'] ?? $DEFAULTS['basicPct']),
];

// ---------------------------------------------------------------------------
// Input (POSTed values win on save; otherwise pre-fill from latest record)
// ---------------------------------------------------------------------------
$isPost = $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_payroll']);

function field($post, $key, $default)
{
    return isset($post[$key]) && $post[$key] !== '' ? (float) $post[$key] : (float) $default;
}

$in = [
    'hraPct'       => $isPost ? field($_POST, 'hraPct', $DEFAULTS['hraPct']) : (float) ($latestRecord['hra_pct'] ?? $DEFAULTS['hraPct']),
    'caThreshold'  => $isPost ? field($_POST, 'caThreshold', $DEFAULTS['caThreshold']) : (float) ($latestRecord['ca_threshold'] ?? $DEFAULTS['caThreshold']),
    'caHigh'       => $isPost ? field($_POST, 'caHigh', $DEFAULTS['caHigh']) : (float) ($latestRecord['ca_high'] ?? $DEFAULTS['caHigh']),
    'caLow'        => $isPost ? field($_POST, 'caLow', $DEFAULTS['caLow']) : (float) ($latestRecord['ca_low'] ?? $DEFAULTS['caLow']),
    'pt'           => $isPost ? field($_POST, 'pt', $DEFAULTS['pt']) : (float) ($latestRecord['pt'] ?? $DEFAULTS['pt']),
    'esiThreshold' => $isPost ? field($_POST, 'esiThreshold', $DEFAULTS['esiThreshold']) : (float) ($latestRecord['esi_threshold'] ?? $DEFAULTS['esiThreshold']),
    'esiPct'       => $isPost ? field($_POST, 'esiPct', $DEFAULTS['esiPct']) : (float) ($latestRecord['esi_pct'] ?? $DEFAULTS['esiPct']),
    'pfPct'        => $isPost ? field($_POST, 'pfPct', $DEFAULTS['pfPct']) : (float) ($latestRecord['pf_pct'] ?? $DEFAULTS['pfPct']),
];

$gross = $carry['gross'];

// ---------------------------------------------------------------------------
// Calculation (mirrors payslip_inputs.html's JS formulas)
// ---------------------------------------------------------------------------
$basic = $gross * ($carry['basicPct'] / 100);
$hra = $basic * ($in['hraPct'] / 100);
$ca = $gross >= $in['caThreshold'] ? $in['caHigh'] : $in['caLow'];
$others = $gross - $basic - $hra - $ca;
$esi = $gross < $in['esiThreshold'] ? $gross * ($in['esiPct'] / 100) : 0.0;
$pf = $basic * ($in['pfPct'] / 100);
$totalDeductions = $in['pt'] + $esi + $pf;
$netSalary = $gross - $totalDeductions;
$perDay = $carry['workingDays'] > 0 ? $netSalary / $carry['workingDays'] : 0.0;
$attendanceNet = $perDay * $carry['daysPresent'];

// ---------------------------------------------------------------------------
// Save (insert first time, update the same row after that)
// ---------------------------------------------------------------------------
if ($isPost && $pdo && $error === null) {
    try {
        $editingId = !empty($_POST['calc_id']) ? (int) $_POST['calc_id'] : null;

        $params = [
            ':gross'           => $gross,
            ':basicPct'        => $carry['basicPct'],
            ':hraPct'          => $in['hraPct'],
            ':caThreshold'     => $in['caThreshold'],
            ':caHigh'          => $in['caHigh'],
            ':caLow'           => $in['caLow'],
            ':pt'              => $in['pt'],
            ':esiThreshold'    => $in['esiThreshold'],
            ':esiPct'          => $in['esiPct'],
            ':pfPct'           => $in['pfPct'],
            ':workingDays'     => $carry['workingDays'],
            ':daysPresent'     => $carry['daysPresent'],
            ':basic'           => $basic,
            ':hra'             => $hra,
            ':ca'              => $ca,
            ':others'          => $others,
            ':esi'             => $esi,
            ':pf'              => $pf,
            ':totalDeductions' => $totalDeductions,
            ':netSalary'       => $netSalary,
            ':perDay'          => $perDay,
            ':attendanceNet'   => $attendanceNet,
        ];

        if ($editingId) {
            $stmt = $pdo->prepare("
                UPDATE payroll_calculations SET
                    gross = :gross, basic_pct = :basicPct, hra_pct = :hraPct,
                    ca_threshold = :caThreshold, ca_high = :caHigh, ca_low = :caLow,
                    pt = :pt, esi_threshold = :esiThreshold, esi_pct = :esiPct, pf_pct = :pfPct,
                    working_days = :workingDays, days_present = :daysPresent,
                    basic = :basic, hra = :hra, ca = :ca, others = :others, esi = :esi, pf = :pf,
                    total_deductions = :totalDeductions, net_salary = :netSalary,
                    per_day = :perDay, attendance_net = :attendanceNet
                WHERE id = :id
            ");
            $params[':id'] = $editingId;
            $stmt->execute($params);
            $latestRecordId = $editingId;
        } else {
            $stmt = $pdo->prepare("
                INSERT INTO payroll_calculations (
                    gross, basic_pct, hra_pct, ca_threshold, ca_high, ca_low,
                    pt, esi_threshold, esi_pct, pf_pct, working_days, days_present,
                    basic, hra, ca, others, esi, pf,
                    total_deductions, net_salary, per_day, attendance_net
                ) VALUES (
                    :gross, :basicPct, :hraPct, :caThreshold, :caHigh, :caLow,
                    :pt, :esiThreshold, :esiPct, :pfPct, :workingDays, :daysPresent,
                    :basic, :hra, :ca, :others, :esi, :pf,
                    :totalDeductions, :netSalary, :perDay, :attendanceNet
                )
            ");
            $stmt->execute($params);
            $latestRecordId = (int) $pdo->lastInsertId();
        }

        $successMessage = 'Payroll salary components updated successfully.';
    } catch (PDOException $ex) {
        error_log('Payroll salary components save failed: ' . $ex->getMessage());
        $error = 'Could not save. Please try again.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Payroll Salary Components</title>

<style>

* {
    box-sizing: border-box;
    margin: 0;
    padding: 0;
}

body {
    background: #f5f6f8;
    font-family: Arial, Helvetica, sans-serif;
    color: #333;
}

.payroll-container {
    width: 100%;
    max-width: 760px;
    margin: 20px auto;
    padding: 0 18px 40px;
}

.section {
    margin-bottom: 10px;
}

.section-title {
    border-top: 1px solid #d4dce5;
    padding: 7px 0 6px 12px;
    color: #004b87;
    font-size: 11px;
    font-weight: 500;
    position: relative;
}

.section-title::before {
    content: "";
    position: absolute;
    left: 0;
    top: 12px;
    width: 4px;
    height: 4px;
    background: #004b87;
    border-radius: 50%;
}

.field {
    margin-bottom: 7px;
}

.field label {
    display: block;
    font-size: 9px;
    color: #555;
    margin-bottom: 4px;
}

.field label .formula {
    color: #888;
}

.input {
    width: 100%;
    height: 34px;
    border: 1px solid #cbd7e3;
    border-radius: 7px;
    background: #fff;
    padding: 0 10px;
    font-size: 10px;
    color: #333;
    outline: none;
}

.input:focus {
    border-color: #72a9d5;
    box-shadow: 0 0 0 2px rgba(65, 145, 205, .10);
}

.two-column {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 10px;
}

.calculation {
    font-size: 8px;
    color: #777;
    margin-top: 3px;
    line-height: 1.4;
}

.calculation strong {
    color: #555;
}

.calculation-result {
    background: #eaf4fc;
    border-radius: 5px;
    padding: 5px 8px;
    margin-top: 4px;
    font-size: 9px;
    color: #0067a8;
}

.calculation-result strong {
    color: #00568d;
}

.readonly {
    background: #eef2f6;
    color: #444;
}

.button-area {
    border-top: 1px solid #d4dce5;
    margin-top: 15px;
    padding-top: 15px;
    display: flex;
    justify-content: flex-end;
    gap: 8px;
}

button {
    border: none;
    border-radius: 6px;
    padding: 9px 18px;
    font-size: 11px;
    cursor: pointer;
}

.btn-reset {
    background: #e9edf1;
    color: #555;
}

.btn-save {
    background: #f4ac83;
    color: #222;
    font-weight: 600;
}

.btn-save:hover {
    background: #e69a70;
}

.message {
    display: none;
    margin-bottom: 12px;
    padding: 9px 12px;
    border-radius: 5px;
    font-size: 10px;
}

.message.success {
    display: block;
    background: #e9f7ef;
    color: #287a4b;
    border: 1px solid #b9e3c9;
}

.message.error {
    display: block;
    background: #fdeceb;
    color: #a13a2f;
    border: 1px solid #f3c6c1;
}

@media(max-width:600px) {
    .payroll-container {
        padding: 0 12px 30px;
    }
    .two-column {
        grid-template-columns: 1fr;
        gap: 0;
    }
}

</style>
</head>

<body>

<div class="payroll-container">

    <div id="successMessage" class="message<?= $successMessage ? ' success' : '' ?>">
        <?= e($successMessage ?? '') ?>
    </div>

    <?php if ($error !== null): ?>
        <div class="message error"><?= e($error) ?></div>
    <?php endif; ?>

    <form method="post" action="">
        <input type="hidden" name="calc_id" value="<?= e($latestRecordId ?? '') ?>">

        <!-- HRA -->
        <div class="section">
            <div class="section-title">HRA (House Rent Allowance)</div>
            <div class="field">
                <label>HRA % <span class="formula">(of Basic)</span></label>
                <input type="number" name="hraPct" id="hraPercent" class="input" value="<?= e($in['hraPct']) ?>" min="0" max="100" step="0.01">
                <div class="calculation">→ HRA = ₹<span id="hraAmount"><?= e(fmt($hra)) ?></span></div>
            </div>
        </div>

        <!-- CA -->
        <div class="section">
            <div class="section-title">CA (Conveyance Allowance)</div>
            <div class="field">
                <label>CA threshold salary (₹)</label>
                <input type="number" name="caThreshold" id="caThreshold" class="input" value="<?= e($in['caThreshold']) ?>">
                <div class="calculation">Gross at or above this → high slab &nbsp; below this → low slab</div>
            </div>
            <div class="two-column">
                <div class="field">
                    <label>CA if Gross ≥ threshold (₹)</label>
                    <input type="number" name="caHigh" id="caHigh" class="input" value="<?= e($in['caHigh']) ?>">
                </div>
                <div class="field">
                    <label>CA if Gross &lt; threshold (₹)</label>
                    <input type="number" name="caLow" id="caLow" class="input" value="<?= e($in['caLow']) ?>">
                </div>
            </div>
            <div class="calculation-result">
                <?= $gross >= $in['caThreshold'] ? '✓' : '✗' ?>
                Gross ₹<span id="grossDisplay"><?= e(fmt($gross)) ?></span>
                <?= $gross >= $in['caThreshold'] ? '≥' : '&lt;' ?>
                ₹<span id="thresholdDisplay"><?= e(fmt($in['caThreshold'])) ?></span>
                → CA ₹<strong id="caResult"><?= e(fmt($ca)) ?></strong>
            </div>
        </div>

        <!-- PT -->
        <div class="section">
            <div class="section-title">PT (Professional Tax)</div>
            <div class="field">
                <label>PT ₹</label>
                <input type="number" name="pt" id="pt" class="input" value="<?= e($in['pt']) ?>">
            </div>
        </div>

        <!-- OTHERS -->
        <div class="section">
            <div class="section-title">Others</div>
            <div class="field">
                <label>Others <span class="formula">(auto-calculated)</span></label>
                <input type="text" id="others" class="input readonly" value="₹ <?= e(fmt($others)) ?>" readonly>
                <div class="calculation">Gross − (Basic + HRA + CA)</div>
            </div>
        </div>

        <!-- ESI -->
        <div class="section">
            <div class="section-title">ESI (Employee State Insurance)</div>
            <div class="two-column">
                <div class="field">
                    <label>ESI Threshold (Gross below ₹)</label>
                    <input type="number" name="esiThreshold" id="esiThreshold" class="input" value="<?= e($in['esiThreshold']) ?>">
                </div>
                <div class="field">
                    <label>ESI % <span class="formula">(of Gross)</span></label>
                    <input type="number" name="esiPct" id="esiPercent" class="input" value="<?= e($in['esiPct']) ?>" step="0.01">
                    <div class="calculation">applies if Gross &lt; threshold</div>
                </div>
            </div>
            <div class="calculation-result">
                ESI = <strong id="esiAmount">₹<?= e(fmt($esi)) ?></strong>
            </div>
        </div>

        <!-- PF -->
        <div class="section">
            <div class="section-title">PF (Provident Fund)</div>
            <div class="field">
                <label>PF % of Basic <span class="formula">— employee contribution</span></label>
                <input type="number" name="pfPct" id="pfPercent" class="input" value="<?= e($in['pfPct']) ?>" min="0" max="100" step="0.01">
                <div class="calculation">→ PF = ₹<span id="pfAmount"><?= e(fmt($pf)) ?></span></div>
            </div>
        </div>

        <!-- BUTTONS -->
        <div class="button-area">
            <button type="button" class="btn-reset" id="resetBtn">Reset</button>
            <button type="submit" name="save_payroll" value="1" class="btn-save" id="saveBtn">Save Payroll Settings</button>
        </div>
    </form>

</div>

<script>

const GROSS = <?= json_encode($gross) ?>;
const BASIC = <?= json_encode($basic) ?>;

const hraPercent = document.getElementById("hraPercent");
const caThreshold = document.getElementById("caThreshold");
const caHigh = document.getElementById("caHigh");
const caLow = document.getElementById("caLow");
const pt = document.getElementById("pt");
const esiThreshold = document.getElementById("esiThreshold");
const esiPercent = document.getElementById("esiPercent");
const pfPercent = document.getElementById("pfPercent");

const DEFAULTS = {
    hraPercent: <?= json_encode($DEFAULTS['hraPct']) ?>,
    caThreshold: <?= json_encode($DEFAULTS['caThreshold']) ?>,
    caHigh: <?= json_encode($DEFAULTS['caHigh']) ?>,
    caLow: <?= json_encode($DEFAULTS['caLow']) ?>,
    pt: <?= json_encode($DEFAULTS['pt']) ?>,
    esiThreshold: <?= json_encode($DEFAULTS['esiThreshold']) ?>,
    esiPercent: <?= json_encode($DEFAULTS['esiPct']) ?>,
    pfPercent: <?= json_encode($DEFAULTS['pfPct']) ?>,
};

function formatNumber(value) {
    return Number(value).toLocaleString("en-IN", { maximumFractionDigits: 2 });
}

function calculatePayroll() {
    const hra = BASIC * (Number(hraPercent.value) / 100);
    document.getElementById("hraAmount").textContent = formatNumber(hra);

    const threshold = Number(caThreshold.value);
    const isHigh = GROSS >= threshold;
    const ca = isHigh ? Number(caHigh.value) : Number(caLow.value);

    document.getElementById("grossDisplay").textContent = formatNumber(GROSS);
    document.getElementById("thresholdDisplay").textContent = formatNumber(threshold);
    document.getElementById("caResult").textContent = formatNumber(ca);

    const others = GROSS - BASIC - hra - ca;
    document.getElementById("others").value = "₹ " + formatNumber(others);

    const esiLimit = Number(esiThreshold.value);
    const esi = GROSS < esiLimit ? GROSS * (Number(esiPercent.value) / 100) : 0;
    document.getElementById("esiAmount").textContent = "₹" + formatNumber(esi);

    const pf = BASIC * (Number(pfPercent.value) / 100);
    document.getElementById("pfAmount").textContent = formatNumber(pf);
}

document.querySelectorAll(".input:not(.readonly)").forEach(function (input) {
    input.addEventListener("input", calculatePayroll);
});

document.getElementById("resetBtn").addEventListener("click", function () {
    hraPercent.value = DEFAULTS.hraPercent;
    caThreshold.value = DEFAULTS.caThreshold;
    caHigh.value = DEFAULTS.caHigh;
    caLow.value = DEFAULTS.caLow;
    pt.value = DEFAULTS.pt;
    esiThreshold.value = DEFAULTS.esiThreshold;
    esiPercent.value = DEFAULTS.esiPercent;
    pfPercent.value = DEFAULTS.pfPercent;

    calculatePayroll();

    document.getElementById("successMessage").classList.remove("success");
});

<?php if ($successMessage): ?>
document.getElementById("successMessage").scrollIntoView({ behavior: "smooth", block: "center" });
if (window.parent && window.parent !== window) {
    window.parent.postMessage('payrollCalculationSaved', '*');
}
<?php endif; ?>

calculatePayroll();

</script>

</body>
</html>
