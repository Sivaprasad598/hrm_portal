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

// Every calendar day of the salary period is paid (week offs and holidays
// included), so the example attendance is the whole current salary period:
// 30 or 31 days, as on the calculation sheet, never a count of shift days.
$salaryPeriodDays = 30;
try {
    if (class_exists(\App\Services\Payroll\PayPeriod::class)) {
        $salaryPeriodDays = \App\Services\Payroll\PayPeriod::containing(\Carbon\Carbon::now())->days();
    }
} catch (Throwable $periodError) {
    // Fall back to 30.
}

$DEFAULTS = [
    'gross'        => 37500,
    'basicPct'     => 40,
    'hraPct'       => 40,
    'caBasis'      => 'basic',
    'caThreshold'  => 20000,
    'caHigh'       => 1600,
    'caLow'        => 800,
    'ltaPct'       => 10,
    'ceaThreshold' => 15000,
    'ceaLow'       => 1200,
    'ceaHigh'      => 2400,
    'pt'           => 200,
    'esiThreshold' => 21000,
    'esiPct'       => 0.75,
    'pfPct'        => 12,
    'workingDays'  => $salaryPeriodDays,
    'daysPresent'  => $salaryPeriodDays,
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
// The record being edited (?id=). Without one, Add New starts from the
// settings in force (the newest row), because the newest row is what payroll
// uses: a new row holding only this form's fields would leave the PF ceiling,
// the employer rates, the PT bands and the pay cycle empty.
// ---------------------------------------------------------------------------
$latestRecord = null;
$editId = isset($_GET['id']) ? (int) $_GET['id'] : null;
$inForce = null;

if ($pdo) {
    $inForce = $pdo->query("SELECT * FROM payroll_calculations ORDER BY id DESC LIMIT 1")->fetch() ?: null;

    if ($editId) {
        $stmt = $pdo->prepare("SELECT * FROM payroll_calculations WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $editId]);
        $latestRecord = $stmt->fetch() ?: null;
        $latestRecordId = $latestRecord['id'] ?? null;
    }
}

$base = $latestRecord ?? $inForce ?? [];

$carry = [
    'gross'       => (float) ($base['gross'] ?? $DEFAULTS['gross']),
    'workingDays' => (float) $DEFAULTS['workingDays'],
    'daysPresent' => (float) $DEFAULTS['daysPresent'],
    // Basic % has no field on this lean form — carry forward whatever was
    // last saved (via this form or the full calculator) instead of exposing
    // it here.
    'basicPct'    => (float) ($base['basic_pct'] ?? $DEFAULTS['basicPct']),
];

// ---------------------------------------------------------------------------
// Input (POSTed values win on save; otherwise pre-fill from the record)
// ---------------------------------------------------------------------------
$isPost = $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_payroll']);

function field($post, $key, $default)
{
    return isset($post[$key]) && $post[$key] !== '' ? (float) $post[$key] : (float) $default;
}

$pick = fn ($post, $col, $default) => $isPost ? field($_POST, $post, $base[$col] ?? $default) : (float) ($base[$col] ?? $default);

$in = [
    'hraPct'       => $pick('hraPct', 'hra_pct', $DEFAULTS['hraPct']),
    'caBasis'      => $isPost ? (($_POST['caBasis'] ?? 'basic') === 'gross' ? 'gross' : 'basic') : (($base['ca_basis'] ?? 'basic') === 'gross' ? 'gross' : 'basic'),
    'caThreshold'  => $pick('caThreshold', 'ca_threshold', $DEFAULTS['caThreshold']),
    'caHigh'       => $pick('caHigh', 'ca_high', $DEFAULTS['caHigh']),
    'caLow'        => $pick('caLow', 'ca_low', $DEFAULTS['caLow']),
    'ltaPct'       => $pick('ltaPct', 'lta_pct', $DEFAULTS['ltaPct']),
    'ceaThreshold' => $pick('ceaThreshold', 'cea_threshold', $DEFAULTS['ceaThreshold']),
    'ceaLow'       => $pick('ceaLow', 'cea_low', $DEFAULTS['ceaLow']),
    'ceaHigh'      => $pick('ceaHigh', 'cea_high', $DEFAULTS['ceaHigh']),
    'pt'           => $pick('pt', 'pt', $DEFAULTS['pt']),
    'esiThreshold' => $pick('esiThreshold', 'esi_threshold', $DEFAULTS['esiThreshold']),
    'esiPct'       => $pick('esiPct', 'esi_pct', $DEFAULTS['esiPct']),
    'pfPct'        => $pick('pfPct', 'pf_pct', $DEFAULTS['pfPct']),
];

$gross = $carry['gross'];

// ---------------------------------------------------------------------------
// Calculation: the payroll formula itself (SalaryCalculator), so this preview,
// the calculator page, the payroll run and the payslips agree.
// ---------------------------------------------------------------------------
$settings = array_merge($base, [
    'basic_pct' => $carry['basicPct'], 'hra_pct' => $in['hraPct'],
    'ca_basis' => $in['caBasis'], 'ca_threshold' => $in['caThreshold'], 'ca_high' => $in['caHigh'], 'ca_low' => $in['caLow'],
    'lta_pct' => $in['ltaPct'], 'cea_threshold' => $in['ceaThreshold'], 'cea_low' => $in['ceaLow'], 'cea_high' => $in['ceaHigh'],
    'pt' => $in['pt'], 'esi_threshold' => $in['esiThreshold'], 'esi_pct' => $in['esiPct'], 'pf_pct' => $in['pfPct'],
]);
$settings += ['esi_employer_pct' => 3.25, 'pf_employer_pct' => $in['pfPct'], 'pf_wage_ceiling' => 25000, 'prorate_statutory' => 1, 'round_net' => 1, 'pt_slabs' => null];
$pay = (new \App\Services\Payroll\SalaryCalculator((object) $settings))
    ->forEmployee($gross * 12, $carry['basicPct'], (int) round($carry['workingDays']), $carry['daysPresent'], 0.0);

$basic = $pay['basic'];
$hra = $pay['hra'];
$ca = $pay['ca'];
$lta = $pay['lta'];
$cea = $pay['cea'];
$others = $pay['other'];          // the Special Allowance
$esi = $pay['esi'];
$pf = $pay['pf'];
$caOn = $in['caBasis'] === 'gross' ? $gross : $basic;
$totalDeductions = $pay['pf'] + $pay['esi'] + $pay['pt'];
$netSalary = $pay['net'];
$perDay = $pay['per_day'];
$attendanceNet = $pay['final_net'];

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
            ':caBasis'         => $in['caBasis'],
            ':caThreshold'     => $in['caThreshold'],
            ':caHigh'          => $in['caHigh'],
            ':caLow'           => $in['caLow'],
            ':ltaPct'          => $in['ltaPct'],
            ':ceaThreshold'    => $in['ceaThreshold'],
            ':ceaLow'          => $in['ceaLow'],
            ':ceaHigh'         => $in['ceaHigh'],
            ':pt'              => $in['pt'],
            ':esiThreshold'    => $in['esiThreshold'],
            ':esiPct'          => $in['esiPct'],
            ':pfPct'           => $in['pfPct'],
            ':workingDays'     => $carry['workingDays'],
            ':daysPresent'     => $carry['daysPresent'],
            ':basic'           => $basic,
            ':hra'             => $hra,
            ':ca'              => $ca,
            ':lta'             => $lta,
            ':cea'             => $cea,
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
                    ca_basis = :caBasis, ca_threshold = :caThreshold, ca_high = :caHigh, ca_low = :caLow,
                    lta_pct = :ltaPct, cea_threshold = :ceaThreshold, cea_low = :ceaLow, cea_high = :ceaHigh,
                    pt = :pt, esi_threshold = :esiThreshold, esi_pct = :esiPct, pf_pct = :pfPct,
                    working_days = :workingDays, days_present = :daysPresent,
                    basic = :basic, hra = :hra, ca = :ca, lta = :lta, cea = :cea, others = :others, esi = :esi, pf = :pf,
                    total_deductions = :totalDeductions, net_salary = :netSalary,
                    per_day = :perDay, attendance_net = :attendanceNet
                WHERE id = :id
            ");
            $params[':id'] = $editingId;
            $stmt->execute($params);
            $latestRecordId = $editingId;
        } else {
            // A new row carries over everything this form does not show.
            $params += [
                ':pfEmployerPct' => $inForce['pf_employer_pct'] ?? $in['pfPct'],
                ':pfWageCeiling' => $inForce['pf_wage_ceiling'] ?? 25000,
                ':esiEmployerPct' => $inForce['esi_employer_pct'] ?? 3.25,
                ':ptSlabs' => $inForce['pt_slabs'] ?? null,
                ':periodStartDay' => $inForce['period_start_day'] ?? 1,
                ':prorate' => $inForce['prorate_statutory'] ?? 1,
                ':roundNet' => $inForce['round_net'] ?? 1,
            ];
            $stmt = $pdo->prepare("
                INSERT INTO payroll_calculations (
                    gross, basic_pct, hra_pct, ca_basis, ca_threshold, ca_high, ca_low,
                    lta_pct, cea_threshold, cea_low, cea_high,
                    pt, esi_threshold, esi_pct, pf_pct, working_days, days_present,
                    basic, hra, ca, lta, cea, others, esi, pf,
                    total_deductions, net_salary, per_day, attendance_net,
                    pf_employer_pct, pf_wage_ceiling, esi_employer_pct, pt_slabs, period_start_day, prorate_statutory, round_net
                ) VALUES (
                    :gross, :basicPct, :hraPct, :caBasis, :caThreshold, :caHigh, :caLow,
                    :ltaPct, :ceaThreshold, :ceaLow, :ceaHigh,
                    :pt, :esiThreshold, :esiPct, :pfPct, :workingDays, :daysPresent,
                    :basic, :hra, :ca, :lta, :cea, :others, :esi, :pf,
                    :totalDeductions, :netSalary, :perDay, :attendanceNet,
                    :pfEmployerPct, :pfWageCeiling, :esiEmployerPct, :ptSlabs, :periodStartDay, :prorate, :roundNet
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
                <label>CA judged on</label>
                <select name="caBasis" id="caBasis" class="input">
                    <option value="basic" <?= $in['caBasis'] === 'basic' ? 'selected' : '' ?>>Basic — IF(Basic &gt; threshold, high, low), as on the sheet</option>
                    <option value="gross" <?= $in['caBasis'] === 'gross' ? 'selected' : '' ?>>Gross</option>
                </select>
            </div>
            <div class="field">
                <label>CA threshold salary (₹)</label>
                <input type="number" name="caThreshold" id="caThreshold" class="input" value="<?= e($in['caThreshold']) ?>">
                <div class="calculation"><span class="ca-on"><?= $in['caBasis'] === 'gross' ? 'Gross' : 'Basic' ?></span> above this → high slab &nbsp; at or below this → low slab</div>
            </div>
            <div class="two-column">
                <div class="field">
                    <label>CA if <span class="ca-on"><?= $in['caBasis'] === 'gross' ? 'Gross' : 'Basic' ?></span> &gt; threshold (₹)</label>
                    <input type="number" name="caHigh" id="caHigh" class="input" value="<?= e($in['caHigh']) ?>">
                </div>
                <div class="field">
                    <label>CA if <span class="ca-on"><?= $in['caBasis'] === 'gross' ? 'Gross' : 'Basic' ?></span> ≤ threshold (₹)</label>
                    <input type="number" name="caLow" id="caLow" class="input" value="<?= e($in['caLow']) ?>">
                </div>
            </div>
            <div class="calculation-result">
                <span class="ca-on"><?= $in['caBasis'] === 'gross' ? 'Gross' : 'Basic' ?></span> ₹<span id="grossDisplay"><?= e(fmt($caOn)) ?></span>
                <?= $caOn > $in['caThreshold'] ? '&gt;' : '≤' ?>
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
                <label>LTA % <span class="formula">(Leave Travel Allowance, of Basic — rounded up)</span></label>
                <input type="number" name="ltaPct" id="ltaPct" class="input" value="<?= e($in['ltaPct']) ?>" min="0" max="100" step="0.01">
                <div class="calculation">→ LTA = ₹<span id="ltaAmount"><?= e(fmt($lta)) ?></span></div>
            </div>
            <div class="field">
                <label>CEA threshold — Basic (₹) <span class="formula">(Children Education Allowance)</span></label>
                <input type="number" name="ceaThreshold" id="ceaThreshold" class="input" value="<?= e($in['ceaThreshold']) ?>">
            </div>
            <div class="two-column">
                <div class="field">
                    <label>CEA if Basic &lt; threshold (₹)</label>
                    <input type="number" name="ceaLow" id="ceaLow" class="input" value="<?= e($in['ceaLow']) ?>">
                </div>
                <div class="field">
                    <label>CEA if Basic ≥ threshold (₹)</label>
                    <input type="number" name="ceaHigh" id="ceaHigh" class="input" value="<?= e($in['ceaHigh']) ?>">
                </div>
            </div>
            <div class="calculation-result">→ CEA ₹<strong id="ceaAmount"><?= e(fmt($cea)) ?></strong></div>
            <div class="field">
                <label>Special Allowance <span class="formula">(auto-calculated)</span></label>
                <input type="text" id="others" class="input readonly" value="₹ <?= e(fmt($others)) ?>" readonly>
                <div class="calculation">Gross − (Basic + HRA + CA + LTA + CEA)</div>
            </div>
        </div>

        <!-- ESI -->
        <div class="section">
            <div class="section-title">ESI (Employee State Insurance)</div>
            <div class="two-column">
                <div class="field">
                    <label>ESI Threshold (Gross up to ₹)</label>
                    <input type="number" name="esiThreshold" id="esiThreshold" class="input" value="<?= e($in['esiThreshold']) ?>">
                </div>
                <div class="field">
                    <label>ESI % <span class="formula">(of Gross)</span></label>
                    <input type="number" name="esiPct" id="esiPercent" class="input" value="<?= e($in['esiPct']) ?>" step="0.01">
                    <div class="calculation">employee share; applies if Gross ≤ threshold</div>
                </div>
            </div>
            <div class="calculation-result">
                ESI (employee + employer) = <strong id="esiAmount">₹<?= e(fmt($esi)) ?></strong>
            </div>
        </div>

        <!-- PF -->
        <div class="section">
            <div class="section-title">PF (Provident Fund)</div>
            <div class="field">
                <label>PF % of Basic <span class="formula">— employee contribution</span></label>
                <input type="number" name="pfPct" id="pfPercent" class="input" value="<?= e($in['pfPct']) ?>" min="0" max="100" step="0.01">
                <div class="calculation">→ PF (employee + employer, on Basic up to the ceiling) = ₹<span id="pfAmount"><?= e(fmt($pf)) ?></span></div>
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
const caBasis = document.getElementById("caBasis");
const ltaPct = document.getElementById("ltaPct");
const ceaThreshold = document.getElementById("ceaThreshold");
const ceaLow = document.getElementById("ceaLow");
const ceaHigh = document.getElementById("ceaHigh");
const ESI_EMPLOYER = <?= json_encode((float) ($settings['esi_employer_pct'] ?? 3.25)) ?>;
const PF_EMPLOYER = <?= json_encode((float) ($settings['pf_employer_pct'] ?? 12)) ?>;
const PF_CEILING = <?= json_encode((float) ($settings['pf_wage_ceiling'] ?? 0)) ?>;

const DEFAULTS = {
    hraPercent: <?= json_encode($DEFAULTS['hraPct']) ?>,
    caThreshold: <?= json_encode($DEFAULTS['caThreshold']) ?>,
    caHigh: <?= json_encode($DEFAULTS['caHigh']) ?>,
    caLow: <?= json_encode($DEFAULTS['caLow']) ?>,
    pt: <?= json_encode($DEFAULTS['pt']) ?>,
    esiThreshold: <?= json_encode($DEFAULTS['esiThreshold']) ?>,
    esiPercent: <?= json_encode($DEFAULTS['esiPct']) ?>,
    pfPercent: <?= json_encode($DEFAULTS['pfPct']) ?>,
    caBasis: <?= json_encode($DEFAULTS['caBasis']) ?>,
    ltaPct: <?= json_encode($DEFAULTS['ltaPct']) ?>,
    ceaThreshold: <?= json_encode($DEFAULTS['ceaThreshold']) ?>,
    ceaLow: <?= json_encode($DEFAULTS['ceaLow']) ?>,
    ceaHigh: <?= json_encode($DEFAULTS['ceaHigh']) ?>,
};

function formatNumber(value) {
    return Number(value).toLocaleString("en-IN", { maximumFractionDigits: 2 });
}

function calculatePayroll() {
    const hra = BASIC * (Number(hraPercent.value) / 100);
    document.getElementById("hraAmount").textContent = formatNumber(hra);

    const threshold = Number(caThreshold.value);
    const onGross = caBasis.value === "gross";
    const caOn = onGross ? GROSS : BASIC;
    const ca = caOn > threshold ? Number(caHigh.value) : Number(caLow.value);
    document.querySelectorAll(".ca-on").forEach(function (el) { el.textContent = onGross ? "Gross" : "Basic"; });

    document.getElementById("grossDisplay").textContent = formatNumber(caOn);
    document.getElementById("thresholdDisplay").textContent = formatNumber(threshold);
    document.getElementById("caResult").textContent = formatNumber(ca);

    const lta = Math.ceil(Math.round(BASIC * Number(ltaPct.value) / 100 * 1e6) / 1e6);
    const cea = BASIC < Number(ceaThreshold.value) ? Number(ceaLow.value) : Number(ceaHigh.value);
    document.getElementById("ltaAmount").textContent = formatNumber(lta);
    document.getElementById("ceaAmount").textContent = formatNumber(cea);

    const others = GROSS - BASIC - hra - ca - lta - cea;
    document.getElementById("others").value = "₹ " + formatNumber(others);

    // Both shares, as on the sheet and in payroll
    const esiLimit = Number(esiThreshold.value);
    const esi = GROSS <= esiLimit ? GROSS * ((Number(esiPercent.value) + ESI_EMPLOYER) / 100) : 0;
    document.getElementById("esiAmount").textContent = "₹" + formatNumber(Math.round(esi));

    const pfOn = PF_CEILING > 0 ? Math.min(BASIC, PF_CEILING) : BASIC;
    const pf = pfOn * ((Number(pfPercent.value) + PF_EMPLOYER) / 100);
    document.getElementById("pfAmount").textContent = formatNumber(Math.round(pf));
}

document.querySelectorAll(".input:not(.readonly)").forEach(function (input) {
    input.addEventListener("input", calculatePayroll);
});
caBasis.addEventListener("change", calculatePayroll);

document.getElementById("resetBtn").addEventListener("click", function () {
    hraPercent.value = DEFAULTS.hraPercent;
    caThreshold.value = DEFAULTS.caThreshold;
    caHigh.value = DEFAULTS.caHigh;
    caLow.value = DEFAULTS.caLow;
    pt.value = DEFAULTS.pt;
    esiThreshold.value = DEFAULTS.esiThreshold;
    esiPercent.value = DEFAULTS.esiPercent;
    pfPercent.value = DEFAULTS.pfPercent;
    caBasis.value = DEFAULTS.caBasis;
    ltaPct.value = DEFAULTS.ltaPct;
    ceaThreshold.value = DEFAULTS.ceaThreshold;
    ceaLow.value = DEFAULTS.ceaLow;
    ceaHigh.value = DEFAULTS.ceaHigh;

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
