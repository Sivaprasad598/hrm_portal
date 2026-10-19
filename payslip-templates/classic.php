<?php
/**
 * Payslip — Classic.
 *
 * The design this application has always printed, moved here unchanged so that
 * choosing it changes nothing. Every figure it draws was worked out in
 * payslips.php before this file is reached; it renders and nothing more.
 *
 * Available here: $payslip and the derived figures — $grossEarnings,
 * $grossDeductions, $employerTotal, $employerAllBlank, $statutoryTotal,
 * $netSalary, $ctcMonthly, $ctcAnnual, $netInWords, $componentRows.
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Arowana Salary Slip</title>
<style>
  * { box-sizing: border-box; }
  body {
    margin: 0;
    padding: 35px 15px;
    background: #f3f3f3;
    font-family: Arial, Helvetica, sans-serif;
    color: #111;
  }
  .payslip {
    width: 860px;
    max-width: 100%;
    margin: auto;
    background: #fff;
    border: 2px solid #111;
  }
  table { width: 100%; border-collapse: collapse; table-layout: fixed; }
  td, th { border: 1px solid #111; padding: 5px 7px; font-size: 13px; vertical-align: middle; }
  .no-border { border: 0; }
  .header td { height: 135px; }
  .logo-cell { width: 50%; text-align: center; }
  .company-logo { max-width: 220px; width: auto; display: block; margin: 0 auto; }
  .logo {
    font-size: 38px;
    font-weight: 700;
    letter-spacing: 1px;
  }
  .logo .mark { color: #c97757; margin-right: 8px; }
  .tagline { font-size: 9px; letter-spacing: 1px; margin-top: -5px; }
  .address { text-align: center; line-height: 1.5; font-size: 13px; }
  .month { text-align: center; font-weight: 700; padding: 3px; }
  .employee-info td { height: 41px; }
  .center { text-align: center; }
  .right { text-align: right; }
  .bold { font-weight: 700; }
  .section-title { font-weight: 700; text-align: center; background: #fafafa; }
  .component { width: 28%; }
  .amount { width: 22%; text-align: center; }
  .summary-label { font-weight: 700; }
  .signature-area { height: 145px; text-align: center; vertical-align: bottom; }
  .stamp {
    width: 170px; height: 170px; margin: 5px auto; display: flex; align-items: center; justify-content: center;
  }
  .stamp img {
    max-width: 100%; max-height: 100%; object-fit: contain; transform: rotate(-8deg);
  }
  .words { font-weight: 700; }
  .note { font-size: 12px; }
  .actions {
    width: 860px; max-width: 100%; margin: 0 auto 12px;
    display: flex; gap: 10px; justify-content: flex-end;
  }
  .actions a, .actions button {
    font: inherit; font-size: 13px; padding: 7px 16px; border-radius: 20px;
    border: 1px solid #1f5b91; background: #fff; color: #1f5b91;
    text-decoration: none; cursor: pointer;
  }
  .actions button { background: #1f5b91; color: #fff; }
  @media print {
    body { background: white; padding: 0; }
    .payslip { width: 100%; }
    .actions { display: none; }
  }
</style>
</head>
<body>
<div class="actions">
  <?php if ($userRole === 'employee'): ?>
    <a href="<?= e($calculatorUrl) ?>" style="opacity: 0.5; cursor: not-allowed; pointer-events: none;" title="Locked for employees">🔒 ← Back to calculator (Locked)</a>
  <?php else: ?>
    <a href="<?= e($calculatorUrl) ?>">← Back to calculator</a>
    <?php if (!empty($attendanceUrl)): ?>
      <a href="<?= e($attendanceUrl) ?>" target="_blank" title="Every day of this salary period, as it was counted">📅 Attendance for this period</a>
    <?php endif; ?>
  <?php endif; ?>
  <button type="button" onclick="window.print()">🖨 Print</button>
</div>
<?php if (!empty($payslipBlockedMessage)): ?>
  <?php // A duplicate generation was refused; the slip already on record is shown below. ?>
  <div style="max-width:820px;margin:0 auto 16px;padding:12px 16px;border:1px solid #f0c36d;
              background:#fdf7e3;border-radius:6px;color:#7a5d00;font-size:14px;line-height:1.5;">
    <strong>Payslip not generated.</strong>
    <?= $payslipBlockedMessage ?>
  </div>
<?php endif; ?>
<div class="payslip">
  <table class="header">
    <tr>
      <td class="logo-cell">
        <img src="<?= function_exists('asset') ? asset('img/arowana_logo_new.png') : '../img/arowana_logo_new.png' ?>" alt="Arowana Logo" class="company-logo">
      </td>
      <td class="address">
        <?= $payslip['company']['address'] ?>
      </td>
    </tr>
  </table>

  <div class="month">Salary slips for the Month of <?= e($payslip['period']) ?></div>
<?php if (($payslip['approval_status'] ?? 'approved') === 'draft'): ?>
  <?php // A payslip nobody has approved is not a payslip yet, and should not be
        // mistaken for one if it is printed or passed around. ?>
  <div style="margin:6px 12px; padding:6px 10px; border:1px dashed #b45309; color:#b45309;
              font-size:12px; font-weight:bold; text-align:center; letter-spacing:1px;">
    DRAFT — NOT APPROVED
  </div>
<?php endif; ?>

  <table class="employee-info">
    <tr>
      <?php $i = 0; foreach ($payslip['employee'] as $label => $value): ?>
        <td class="<?= $i % 2 ? 'center' : '' ?>"><b><?= e($label) ?>:</b> <?= e($value) ?></td>
      <?php $i++; endforeach; ?>
    </tr>
    <?php if (!empty($payslip['attendance'])): ?>
      <tr>
        <?php $i = 0; foreach ($payslip['attendance'] as $label => $value): ?>
          <td class="<?= $i % 2 ? 'center' : '' ?>"><b><?= e($label) ?>:</b> <?= e($value) ?></td>
        <?php $i++; endforeach; ?>
      </tr>
    <?php endif; ?>
  </table>

  <table>
    <tr>
      <th colspan="2" class="section-title">Earnings</th>
      <th colspan="2" class="section-title">Deductions</th>
    </tr>
    <tr>
      <th class="component" style="text-align:left">Components</th>
      <th class="amount">Amount (in Rupees)</th>
      <th class="component" style="text-align:left">Components</th>
      <th class="amount">Amount (in Rupees)</th>
    </tr>

    <?php for ($r = 0; $r < $componentRows; $r++): ?>
      <?php
      $earn   = isset($payslip['earnings'][$r]) ? $payslip['earnings'][$r] : null;
      $deduct = isset($payslip['deductions'][$r]) ? $payslip['deductions'][$r] : null;
      ?>
      <tr>
        <td><?= $earn ? e($earn[0]) : '' ?></td>
        <td class="amount"><?= $earn ? e(amt($earn[1])) : '' ?></td>
        <td><?= $deduct ? e($deduct[0]) : '' ?></td>
        <td class="amount"><?= $deduct ? e(amt($deduct[1])) : '' ?></td>
      </tr>
    <?php endfor; ?>

    <tr>
      <td class="summary-label">Total (Gross Earning)</td>
      <td class="amount bold"><?= e(indian_number($grossEarnings)) ?></td>
      <td class="summary-label">Total (Gross Deductions)</td>
      <td class="amount bold"><?= e(indian_number($grossDeductions)) ?></td>
    </tr>

    <?php foreach ($payslip['employer'] as $index => $row): ?>
      <tr>
        <td><?= e($row[0]) ?></td>
        <td class="amount"><?= e(amt($row[1])) ?></td>
        <?php if ($index === 0): ?>
          <td rowspan="<?= count($payslip['employer']) + 3 ?>" colspan="2" class="signature-area">
            <div class="stamp"><img src="<?= function_exists('asset') ? asset('img/sign and stamp.png') : '../img/sign and stamp.png' ?>" alt="Authorized Stamp"></div>
          </td>
        <?php endif; ?>
      </tr>
    <?php endforeach; ?>

    <tr>
      <td class="summary-label">Total (Employer Contributions)</td>
      <td class="amount bold"><?= e($employerAllBlank ? '-' : indian_number($employerTotal)) ?></td>
    </tr>
    <tr>
      <td class="summary-label">Total Statutory Contributions<br>(Employee+ Employer)</td>
      <td class="amount bold"><?= e(indian_number($statutoryTotal)) ?></td>
    </tr>
    <tr>
      <td class="summary-label">CTC (Monthly &amp; Year)</td>
      <td class="amount bold"><?= e(indian_number($ctcMonthly)) ?></td>
    </tr>

    <tr>
      <td class="summary-label">Net Salary</td>
      <td class="amount bold"><?= e(indian_number($netSalary)) ?></td>
      <td class="center bold"><?= e(indian_number($ctcAnnual)) ?></td>
      <td class="center bold">Authorized Signature</td>
    </tr>
    <tr>
      <td class="summary-label">Net Salary (in words)</td>
      <td colspan="3" class="words"><?= e($netInWords) ?></td>
    </tr><?php
      $encashAmount = (float) ($payslip['encashment']['amount'] ?? 0);
      $encashDays = (float) ($payslip['encashment']['days'] ?? 0);
      if ($encashAmount > 0) {
          $encashDaysText = rtrim(rtrim(number_format($encashDays, 2, '.', ''), '0'), '.');
          echo "
    <tr>
      <td class=\"summary-label\">Leave Encashment (" . e($encashDaysText) . ' day' . ($encashDays == 1 ? '' : 's') . ")</td>
"
             . "      <td class=\"amount bold\">" . e(indian_number($encashAmount)) . "</td>
"
             . "      <td colspan=\"2\"></td>
    </tr>
"
             . "    <tr>
      <td class=\"summary-label\">Total Payable<br>(Net Salary + Leave Encashment)</td>
"
             . "      <td class=\"amount bold\">" . e(indian_number($netSalary + $encashAmount)) . "</td>
"
             . "      <td colspan=\"2\" class=\"words\">" . e(amount_in_words($netSalary + $encashAmount)) . "</td>
    </tr>";
      }
    ?>

<?php
      // What this employee has been paid so far this financial year, this
      // payslip included — the figure asked for when filing a return.
      $ytd = $payslip['year_to_date'] ?? null;

      if ($ytd) {
          echo "\n    <tr>\n      <td class=\"summary-label\">Year to date " . e($ytd['label'])
             . ' (' . (int) $ytd['months'] . ' month' . ($ytd['months'] == 1 ? '' : 's') . ")</td>\n"
             . "      <td colspan=\"3\" class=\"words\">"
             . 'Gross ' . e(indian_number($ytd['gross']))
             . ' &middot; PF ' . e(indian_number($ytd['pf']))
             . ' &middot; ESI ' . e(indian_number($ytd['esi']))
             . ' &middot; PT ' . e(indian_number($ytd['pt']))
             . ($ytd['tds'] > 0 ? ' &middot; TDS ' . e(indian_number($ytd['tds'])) : '')
             . ($ytd['lop'] > 0 ? ' &middot; LOP ' . e(indian_number($ytd['lop'])) : '')
             . ' &middot; <strong>Net ' . e(indian_number($ytd['net'])) . '</strong>'
             . "</td>\n    </tr>";
      }
    ?>

    <tr>
      <td colspan="4" class="note"><?= $payslip['note'] ?></td>
    </tr>
  </table>
</div>
</body>
</html>
