<?php
/**
 * Payslip — Modern.
 *
 * A4 from the start rather than a screen layout squeezed onto a page: one
 * sheet, 210mm wide, with margins a printer can hold.
 *
 * Built for DomPDF, which renders a limited subset of CSS — no flexbox, no
 * grid, no custom properties, no gap. Everything here is tables, fixed widths
 * and inline-block, because that is what comes out of the PDF looking like
 * what is on the screen. Arial and Helvetica are core PDF fonts the reader
 * already has; naming a font like DejaVu makes DomPDF embed the whole 1.1MB
 * face in every download.
 *
 * Draws only what payslips.php has already worked out: $payslip and the derived
 * figures — $grossEarnings, $grossDeductions, $employerTotal, $employerAllBlank,
 * $statutoryTotal, $netSalary, $ctcMonthly, $ctcAnnual, $netInWords,
 * $componentRows.
 */

$fmt = static function ($value) {
    return $value === null ? '' : number_format((float) $value, 2);
};

$asset = static function (string $path) {
    return function_exists('asset') ? asset($path) : '../' . $path;
};

$company = $payslip['company'] ?? [];
$employee = $payslip['employee'] ?? [];
$attendance = $payslip['attendance'] ?? [];
$status = strtolower((string) ($payslip['approval_status'] ?? 'approved'));

// The year-to-date block is keyed the way payslips.php builds it — label,
// months, then the figures. Printing those keys as they stand gave a panel
// reading "pf", "esi", "lop"; they are named here, and label and months become
// the panel's own heading rather than two more rows.
$ytdSource = $payslip['year_to_date'] ?? [];
$ytdNames = [
    'gross' => 'Gross',
    'net' => 'Net paid',
    'pf' => 'Provident fund',
    'esi' => 'Employee insurance',
    'pt' => 'Professional tax',
    'tds' => 'Income tax',
    'lop' => 'Loss of pay',
];

$ytdRows = [];

foreach ($ytdNames as $key => $name) {
    if (!array_key_exists($key, $ytdSource)) {
        continue;
    }

    // A zero deduction is noise on a payslip; the gross and the net are not.
    if (!in_array($key, ['gross', 'net'], true) && (float) $ytdSource[$key] <= 0) {
        continue;
    }

    $ytdRows[$name] = (float) $ytdSource[$key];
}

$ytdHeading = 'Year to date';

if (!empty($ytdSource['label'])) {
    $ytdHeading .= ' — ' . $ytdSource['label'];

    if (!empty($ytdSource['months'])) {
        $months = (int) $ytdSource['months'];
        $ytdHeading .= ' (' . $months . ' month' . ($months === 1 ? '' : 's') . ')';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Salary Slip<?= !empty($payslip['period']) ? ' — ' . e($payslip['period']) : '' ?></title>
<style>
  @page { size: A4; margin: 11mm 9mm; }

  * { box-sizing: border-box; }

  body {
    margin: 0;
    padding: 0;
    background: #ffffff;
    font-family: Arial, Helvetica, sans-serif;
    font-size: 10px;
    line-height: 1.45;
    color: #1f2937;
  }

  /* The default is the printed page: @page already supplies the margin, so the
     sheet adds none and fills what is left. The desk, the fixed width and the
     inner margin are decoration for a monitor, and live in @media screen —
     DomPDF ignores that block.
     Written this way round because the other way round does not work: DomPDF
     does not honour @media print either, so overrides put there never reached
     the PDF. A 210mm sheet with 14mm of its own padding, inside a page already
     margined 11mm, overflowed — one payslip came out over four pages. */
  .desk { background: #ffffff; padding: 0; }
  .sheet { width: auto; margin: 0; background: #ffffff; padding: 0; }

  /* The buttons above the sheet are for a monitor only: hidden on paper and,
     since DomPDF skips @media screen, in the PDF too. */
  .actions { display: none; }

  @media screen {
    .actions {
      display: flex; gap: 10px; justify-content: flex-end;
      width: 210mm; max-width: 100%; margin: 0 auto 12px;
      font-family: Arial, Helvetica, sans-serif;
    }
    .actions a, .actions button {
      font: inherit; font-size: 13px; padding: 7px 16px; border-radius: 20px;
      border: 1px solid #1f5b91; background: #fff; color: #1f5b91;
      text-decoration: none; cursor: pointer;
    }
    .actions button { background: #1f5b91; color: #fff; }
    .desk { background: #e9edf3; padding: 26px 0; }
    .sheet {
      width: 210mm;
      margin: 0 auto;
      padding: 14mm 13mm;
      box-shadow: 0 2px 10px rgba(16, 24, 40, .1);
    }
  }

  table { width: 100%; border-collapse: collapse; }
  td, th { vertical-align: top; }

  /* ---- the band across the top ---- */
  .masthead td { padding-bottom: 10px; vertical-align: middle; }
  .masthead .logo { width: 210px; }
  .masthead .org { text-align: right; }
  .org-name { font-size: 16px; font-weight: bold; color: #16305c; letter-spacing: .2px; }
  .org-line { font-size: 9px; color: #6b7280; }
  .rule { border-bottom: 2px solid #16305c; }

  .doc-title {
    margin-top: 13px;
    background: #16305c;
    color: #ffffff;
    padding: 9px 12px;
    font-size: 11px;
    font-weight: bold;
    letter-spacing: 1.6px;
    text-transform: uppercase;
  }
  .doc-title .period { float: right; font-weight: normal; letter-spacing: .5px; }

  .stamp {
    display: inline-block;
    padding: 1px 6px;
    margin-left: 6px;
    border: 1px solid #ffffff;
    border-radius: 3px;
    font-size: 8px;
    font-weight: bold;
    letter-spacing: .8px;
    text-transform: uppercase;
  }

  /* ---- who it is for ---- */
  .who { margin-top: 14px; border: 1px solid #ccd5e2; }
  .who td { padding: 7px 11px; border-bottom: 1px solid #eef1f6; }
  .who tr:last-child td { border-bottom: 0; }
  .who .k {
    width: 92px;
    color: #6b7280;
    font-size: 8.5px;
    text-transform: uppercase;
    letter-spacing: .6px;
    white-space: nowrap;
  }
  .who .v { font-weight: bold; color: #111827; }
  .who .mid { border-left: 1px solid #eef1f6; }

  /* ---- the money ---- */
  .money { margin-top: 14px; border: 1px solid #ccd5e2; }
  .money th {
    background: #eef2f8;
    color: #16305c;
    font-size: 8.5px;
    letter-spacing: .9px;
    text-transform: uppercase;
    text-align: left;
    padding: 8px 11px;
    border-bottom: 1px solid #ccd5e2;
  }
  .money th.amt, .money td.amt { text-align: right; }
  .money td { padding: 6px 11px; border-bottom: 1px solid #f3f6fa; }
  .money .split { border-left: 1px solid #ccd5e2; }
  .money tr.sum td {
    background: #eef2f8;
    font-weight: bold;
    color: #16305c;
    border-top: 1px solid #ccd5e2;
    border-bottom: 0;
  }

  /* ---- what is actually paid ---- */
  .net { margin-top: 14px; background: #16305c; color: #ffffff; }
  .net td { padding: 12px 14px; }
  .net .label { font-size: 9px; letter-spacing: 1.1px; text-transform: uppercase; }
  .net .figure { font-size: 19px; font-weight: bold; text-align: right; white-space: nowrap; }
  .net .words { font-size: 8.5px; font-style: italic; padding-top: 0; }

  /* ---- the smaller blocks under it ---- */
  .panels { margin-top: 14px; }
  /* No height here. DomPDF reads height:100% on a nested table as the full
     page height, and the three panels came out on a page each — one payslip
     printed over four. They are cells of one row, so they are already the
     same height as each other. */
  .panel { border: 1px solid #ccd5e2; }
  .panel .head {
    background: #eef2f8;
    padding: 7px 11px;
    font-size: 8.5px;
    font-weight: bold;
    letter-spacing: .8px;
    text-transform: uppercase;
    color: #16305c;
    border-bottom: 1px solid #ccd5e2;
  }
  .panel td { padding: 6px 11px; border-bottom: 1px solid #f3f6fa; }
  .panel tr:last-child td { border-bottom: 0; }
  .panel .k { color: #6b7280; }
  .panel .v { text-align: right; font-weight: bold; color: #111827; white-space: nowrap; }
  .gut { width: 11px; }

  .note {
    margin-top: 14px;
    padding: 8px 10px;
    border-left: 3px solid #ccd5e2;
    background: #f7f9fc;
    color: #6b7280;
    font-size: 8.5px;
    line-height: 1.5;
  }

  /* ---- signatures ---- */
  .sign { margin-top: 14px; }
  .sign td { font-size: 8.5px; color: #6b7280; }
  .sign .box { height: 74px; }
  .sign .line { border-top: 1px solid #9aa6b8; padding-top: 5px; }
  .sign .right { text-align: right; }
  .sign .for { color: #374151; font-weight: bold; }

  .foot {
    margin-top: 10px;
    padding-top: 7px;
    border-top: 1px solid #eef1f6;
    text-align: center;
    color: #9aa6b8;
    font-size: 7.5px;
  }
</style>
</head>
<body>
<div class="desk">
<?php if (($userRole ?? 'employee') !== 'employee'): ?>
<div class="actions">
  <?php if (!empty($calculatorUrl)): ?><a href="<?= e($calculatorUrl) ?>">← Back to calculator</a><?php endif; ?>
  <?php if (!empty($attendanceUrl)): ?>
    <a href="<?= e($attendanceUrl) ?>" target="_blank" title="Every day of this salary period, as it was counted">📅 Attendance for this period</a>
  <?php endif; ?>
  <button type="button" onclick="window.print()">🖨 Print</button>
</div>
<?php endif; ?>
<div class="sheet">

  <!-- who issued it -->
  <table class="masthead">
    <tr class="rule">
      <td class="logo">
        <?php // Sized on the element, not in CSS: DomPDF ignores max-width and ?>
<?php // max-height on an image and draws it at its natural size. ?>
        <img src="<?= e(!empty($company['logo']) ? $company['logo'] : $asset('img/arowana_logo_new.png')) ?>"
             width="186" height="40" alt="">
      </td>
      <td class="org">
        <div class="org-name"><?= e($company['name'] ?? '') ?></div>
        <?php if (!empty($payslip['address'])) { ?>
          <div class="org-line"><?= e($payslip['address']) ?></div>
        <?php } ?>
        <?php if (!empty($payslip['branch_name'])) { ?>
          <div class="org-line"><?= e($payslip['branch_name']) ?></div>
        <?php } ?>
      </td>
    </tr>
  </table>

  <div class="doc-title">
    Salary Slip
    <span class="period">
      <?= e($payslip['period'] ?? '') ?>
      <?php if ($status !== '') { ?>
        <span class="stamp"><?= e($status) ?></span>
      <?php } ?>
    </span>
  </div>

  <!-- who it is for -->
  <table class="who">
    <?php
      $pairs = [];
      foreach ($employee as $key => $value) {
          $pairs[] = [$key, $value];
      }
      for ($i = 0; $i < count($pairs); $i += 2) {
          $left = $pairs[$i];
          $right = $pairs[$i + 1] ?? null;
    ?>
      <tr>
        <td class="k"><?= e($left[0]) ?></td>
        <td class="v"><?= e($left[1]) ?></td>
        <td class="k mid"><?= $right ? e($right[0]) : '' ?></td>
        <td class="v"><?= $right ? e($right[1]) : '' ?></td>
      </tr>
    <?php } ?>
  </table>

  <!-- what was earned and what came off -->
  <table class="money">
    <tr>
      <th width="33%">Earnings</th>
      <th width="17%" class="amt">Amount</th>
      <th width="33%" class="split">Deductions</th>
      <th width="17%" class="amt">Amount</th>
    </tr>
    <?php for ($r = 0; $r < $componentRows; $r++) {
        $earn = $payslip['earnings'][$r] ?? null;
        $cut = $payslip['deductions'][$r] ?? null;
    ?>
      <tr>
        <td><?= $earn ? e($earn[0]) : '' ?></td>
        <td class="amt"><?= $earn ? $fmt($earn[1]) : '' ?></td>
        <td class="split"><?= $cut ? e($cut[0]) : '' ?></td>
        <td class="amt"><?= $cut ? $fmt($cut[1]) : '' ?></td>
      </tr>
    <?php } ?>
    <tr class="sum">
      <td>Gross earnings</td>
      <td class="amt"><?= $fmt($grossEarnings) ?></td>
      <td class="split">Total deductions</td>
      <td class="amt"><?= $fmt($grossDeductions) ?></td>
    </tr>
  </table>

  <!-- what the employee is actually paid -->
  <table class="net">
    <tr>
      <td class="label">Net pay<?= !empty($payslip['period']) ? ' for ' . e($payslip['period']) : '' ?></td>
      <td class="figure"><?= $fmt($netSalary) ?></td>
    </tr>
    <?php if ($netInWords !== '') { ?>
      <tr><td class="words" colspan="2"><?= e($netInWords) ?></td></tr>
    <?php } ?>
  </table>

  <!-- the detail that does not belong in the headline -->
  <table class="panels">
    <tr>
      <td width="33%">
        <table class="panel">
          <tr><td class="head" colspan="2">Days</td></tr>
          <?php foreach ($attendance as $key => $value) { ?>
            <tr><td class="k"><?= e($key) ?></td><td class="v"><?= e($value) ?></td></tr>
          <?php } ?>
          <?php if (!$attendance) { ?>
            <tr><td class="k" colspan="2">Not recorded</td></tr>
          <?php } ?>
        </table>
      </td>
      <td class="gut"></td>
      <td width="33%">
        <table class="panel">
          <tr><td class="head" colspan="2">Employer's own share</td></tr>
          <?php if ($employerAllBlank) { ?>
            <tr><td class="k" colspan="2">None</td></tr>
          <?php } else { ?>
            <?php foreach ($payslip['employer'] as $line) { ?>
              <?php if ($line[1] === null) { continue; } ?>
              <tr><td class="k"><?= e($line[0]) ?></td><td class="v"><?= $fmt($line[1]) ?></td></tr>
            <?php } ?>
            <tr><td class="k">Cost to company</td><td class="v"><?= $fmt($ctcMonthly + $employerTotal) ?></td></tr>
          <?php } ?>
        </table>
      </td>
      <td class="gut"></td>
      <td width="33%">
        <table class="panel">
          <tr><td class="head" colspan="2"><?= e($ytdHeading) ?></td></tr>
          <?php foreach ($ytdRows as $name => $value) { ?>
            <tr><td class="k"><?= e($name) ?></td><td class="v"><?= $fmt($value) ?></td></tr>
          <?php } ?>
          <?php if (!$ytdRows) { ?>
            <tr><td class="k">Annual CTC</td><td class="v"><?= $fmt($ctcAnnual) ?></td></tr>
          <?php } ?>
        </table>
      </td>
    </tr>
  </table>

  <?php if (!empty($payslip['encashment']['amount'])) { ?>
    <table class="panel panels">
      <tr><td class="head" colspan="2">Leave encashment</td></tr>
      <tr>
        <td class="k"><?= $fmt($payslip['encashment']['days']) ?> day(s) encashed</td>
        <td class="v"><?= $fmt($payslip['encashment']['amount']) ?></td>
      </tr>
    </table>
  <?php } ?>

  <?php if (!empty($payslip['note'])) { ?>
    <div class="note"><?= $payslip['note'] ?></div>
  <?php } ?>

  <table class="sign">
    <tr>
      <td width="45%" class="box"></td>
      <td></td>
      <td width="45%" class="box right">
        <img src="<?= e($asset('img/sign and stamp.png')) ?>" width="108" height="72" alt="">
      </td>
    </tr>
    <tr>
      <td class="line">Employee signature</td>
      <td></td>
      <td class="line right">
        <span class="for">For <?= e($company['name'] ?? 'the company') ?></span>
      </td>
    </tr>
  </table>

  <div class="foot">
    This is a computer-generated payslip and does not require a physical signature.
  </div>

</div>
</div>
</body>
</html>
