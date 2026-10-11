<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Form 16 Part B — {{ $employee->name }} — {{ $year }}</title>
    <style>
        /* Built for A4 through dompdf, so sizes are in points and the layout
           is tables rather than flexbox. */
        body { font-family: DejaVu Sans, sans-serif; font-size: 9pt; color: #1a202c; margin: 0; }
        h1 { font-size: 13pt; margin: 0 0 2pt; text-align: center; }
        .sub { text-align: center; font-size: 8pt; color: #555; margin-bottom: 10pt; }
        table { width: 100%; border-collapse: collapse; }
        .box { border: 1px solid #999; margin-bottom: 8pt; }
        .box td { padding: 3pt 5pt; vertical-align: top; }
        .box .label { color: #555; font-size: 8pt; }
        .lines td { padding: 2.5pt 5pt; border-bottom: 1px solid #e2e8f0; }
        .lines .amount { text-align: right; white-space: nowrap; }
        .lines .total td { font-weight: bold; border-top: 1px solid #999; border-bottom: 1px solid #999; }
        .lines .relief td { color: #1d6f4f; }
        .section { background: #f1f5f9; font-weight: bold; padding: 3pt 5pt; margin-top: 6pt; font-size: 8.5pt; }
        .note { font-size: 7.5pt; color: #666; margin-top: 10pt; line-height: 1.4; }
        .warn { border: 1px solid #c05621; background: #fffaf0; color: #7b341e;
                padding: 5pt; font-size: 8pt; margin-bottom: 8pt; }
        .sign { margin-top: 24pt; font-size: 8pt; }
    </style>
</head>
<body>

@php
    $money = fn ($v) => number_format((float) $v, 2);
@endphp

<h1>Form 16 — Part B</h1>
<p class="sub">
    Details of salary paid and tax deducted · Financial year {{ $year }}
    · Assessment year {{ (int) substr($year, 0, 4) + 1 }}-{{ (int) substr($year, 5, 2) + 1 }}
</p>

@if ($computation['needs_review'] ?? false)
    <div class="warn">
        <strong>Draft — the tax rates behind this have not been confirmed.</strong>
        The slabs, standard deduction and rebate used here were seeded as a starting point and are still
        marked for review. Confirm them against the current Finance Act before issuing this to an employee.
    </div>
@endif

<table class="box">
    <tr>
        <td width="50%">
            <div class="label">Employer</div>
            <strong>{{ $company->company_name ?? '—' }}</strong><br>
            {{ $company->address ?? '' }}
        </td>
        <td width="50%">
            <div class="label">Employee</div>
            <strong>{{ $employee->name }}</strong><br>
            {{ $employee->designation ?? '' }}
            @if ($employee->employee_card_id) · Code {{ $employee->employee_card_id }} @endif
        </td>
    </tr>
    <tr>
        <td>
            <div class="label">Period</div>
            1 April {{ substr($year, 0, 4) }} to 31 March {{ (int) substr($year, 0, 4) + 1 }}
        </td>
        <td>
            <div class="label">PAN of the employee</div>
            {{ $employee->pan_number ?: 'NOT ON RECORD' }}
        </td>
    </tr>
</table>

<div class="section">1. Gross salary</div>
<table class="lines">
    @php
        $grossPaid = $payslips->sum('gross');
        $basic = $payslips->sum('basic');
        $hra = $payslips->sum('hra');
        $ca = $payslips->sum('ca');
        $others = $payslips->sum('others');
    @endphp
    <tr><td>(a) Salary as per section 17(1) — basic</td><td class="amount">{{ $money($basic) }}</td></tr>
    <tr><td>(b) House rent allowance</td><td class="amount">{{ $money($hra) }}</td></tr>
    <tr><td>(c) Conveyance allowance</td><td class="amount">{{ $money($ca) }}</td></tr>
    <tr><td>(d) Other allowances</td><td class="amount">{{ $money($others) }}</td></tr>
    <tr class="total"><td>Total paid on {{ $payslips->count() }} payslip(s)</td><td class="amount">{{ $money($grossPaid) }}</td></tr>
    @if (($computation['projected_gross'] ?? 0) > $grossPaid)
        <tr><td>Expected for the rest of the year</td><td class="amount">{{ $money($computation['projected_gross'] - $grossPaid) }}</td></tr>
        <tr class="total"><td>Gross salary for the year</td><td class="amount">{{ $money($computation['projected_gross']) }}</td></tr>
    @endif
</table>

<div class="section">2. Deductions and reliefs allowed</div>
<table class="lines">
    @forelse ($computation['reliefs'] ?? [] as [$label, $amount])
        <tr class="relief"><td>{{ $label }}</td><td class="amount">{{ $money($amount) }}</td></tr>
    @empty
        <tr><td colspan="2">
            None — the {{ $computation['regime'] ?? 'new' }} regime was chosen and allows none of these.
        </td></tr>
    @endforelse
    <tr class="total"><td>Taxable income</td><td class="amount">{{ $money($computation['taxable'] ?? 0) }}</td></tr>
</table>

<div class="section">3. Tax on that income</div>
<table class="lines">
    @foreach ($computation['workings'] ?? [] as $line)
        @php [$label, $amount] = [$line[0], $line[1]]; $kind = $line[2] ?? ''; @endphp
        @if (in_array($kind, ['band', 'relief']) || str_contains(strtolower($label), 'cess')
            || str_contains(strtolower($label), 'surcharge') || str_contains(strtolower($label), 'tax for the year'))
            <tr class="{{ $kind === 'relief' ? 'relief' : '' }} {{ $kind === 'total' || str_contains(strtolower($label), 'tax for the year') ? 'total' : '' }}">
                <td>{{ $label }}</td>
                <td class="amount">{{ $money(abs($amount)) }}{{ $amount < 0 ? ' (less)' : '' }}</td>
            </tr>
        @endif
    @endforeach
</table>

<div class="section">4. Tax deducted and still to deduct</div>
<table class="lines">
    <tr><td>Tax payable for the year</td><td class="amount">{{ $money($computation['annual_tax'] ?? 0) }}</td></tr>
    <tr><td>Deducted so far on payslips</td><td class="amount">{{ $money($computation['paid_so_far']['tds'] ?? 0) }}</td></tr>
    @if (($declaration->previous_employer_tax ?? 0) > 0)
        <tr><td>Deducted by a previous employer, as declared</td><td class="amount">{{ $money($declaration->previous_employer_tax) }}</td></tr>
    @endif
    <tr class="total"><td>Still to be deducted over {{ $computation['months_left'] ?? 0 }} month(s)</td>
        <td class="amount">{{ $money($computation['outstanding'] ?? 0) }}</td></tr>
    <tr><td>Which is per month</td><td class="amount">{{ $money($computation['monthly_tds'] ?? 0) }}</td></tr>
</table>

<div class="note">
    This is Part B, prepared from the payslips issued by this employer and the declaration the employee made.
    It is not a substitute for Part A, which carries the TRACES tax-deduction certificate and must be downloaded
    from the income tax portal against the employer's TAN. Figures for months not yet paid are an expectation
    based on the current salary and will change if the salary or the declaration changes.
    @if (!($declaration->proofs_received ?? false))
        <br><strong>The proofs for the declared deductions have not been recorded as received.</strong>
    @endif
</div>

<div class="sign">
    <table>
        <tr>
            <td>Issued {{ $issuedOn->format('d M Y') }}</td>
            <td style="text-align:right">For {{ $company->company_name ?? '' }}<br><br>Authorised signatory</td>
        </tr>
    </table>
</div>

</body>
</html>
