<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Full and final settlement — {{ $settlement->name }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 9.5pt; color: #1a202c; margin: 0; }
        h1 { font-size: 14pt; margin: 0 0 2pt; text-align: center; }
        .sub { text-align: center; font-size: 8.5pt; color: #555; margin-bottom: 12pt; }
        table { width: 100%; border-collapse: collapse; }
        .box { border: 1px solid #999; margin-bottom: 10pt; }
        .box td { padding: 4pt 6pt; vertical-align: top; }
        .box .label { color: #555; font-size: 8pt; }
        .lines td { padding: 4pt 6pt; border-bottom: 1px solid #e8edf2; }
        .lines .amount { text-align: right; white-space: nowrap; }
        .lines .why { color: #666; font-size: 8pt; }
        .lines .total td { font-weight: bold; border-top: 1.5pt solid #333; border-bottom: 1.5pt solid #333;
                           font-size: 11pt; }
        .lines .less td { color: #9b2c2c; }
        .section { background: #f1f5f9; font-weight: bold; padding: 4pt 6pt; margin-top: 8pt; font-size: 9pt; }
        .draft { border: 1px solid #c05621; background: #fffaf0; color: #7b341e;
                 padding: 6pt; font-size: 8.5pt; margin-bottom: 10pt; }
        .note { font-size: 8pt; color: #666; margin-top: 12pt; line-height: 1.5; }
        .sign { margin-top: 28pt; font-size: 8.5pt; }
    </style>
</head>
<body>

@php
    $money = fn ($v) => number_format(abs((float) $v), 2);
@endphp

<h1>Full and Final Settlement</h1>
<p class="sub">{{ $company->company_name ?? '' }}</p>

@if ($settlement->status === 'draft')
    <div class="draft">
        <strong>Draft — not yet agreed.</strong>
        The figures below are worked out from today's records and can still change.
    </div>
@elseif ($settlement->status === 'cancelled')
    <div class="draft"><strong>Withdrawn.</strong> This settlement is no longer in force.</div>
@endif

<table class="box">
    <tr>
        <td width="50%">
            <div class="label">Employee</div>
            <strong>{{ $settlement->name }}</strong><br>
            {{ $settlement->designation ?? '' }}
            @if ($settlement->employee_card_id) · Code {{ $settlement->employee_card_id }} @endif
        </td>
        <td width="50%">
            <div class="label">Service</div>
            @if ($settlement->joined_on)
                Joined {{ \Carbon\Carbon::parse($settlement->joined_on)->format('d M Y') }}<br>
            @endif
            Last working day {{ \Carbon\Carbon::parse($settlement->last_working_day)->format('d M Y') }}
            @if ($settlement->years_served > 0)
                · {{ rtrim(rtrim(number_format($settlement->years_served, 2), '0'), '.') }} year(s)
            @endif
        </td>
    </tr>
    <tr>
        <td>
            <div class="label">PAN</div>
            {{ $settlement->pan_number ?: '—' }}
        </td>
        <td>
            <div class="label">Where it is paid</div>
            @if ($settlement->personal_account_number)
                {{ $settlement->personal_account_number }}
                @if ($settlement->ifsc_code) · {{ $settlement->ifsc_code }} @endif
            @else
                No bank account on record
            @endif
        </td>
    </tr>
</table>

<div class="section">How this was worked out</div>
<table class="lines">
    @foreach ($workings as $line)
        @php
            $label = $line[0];
            $amount = (float) $line[1];
            $why = $line[2] ?? '';
            $isTotal = ($line[3] ?? '') === 'total';
        @endphp
        <tr class="{{ $isTotal ? 'total' : '' }} {{ $amount < 0 ? 'less' : '' }}">
            <td>
                {{ $label }}
                @if ($why)<div class="why">{{ $why }}</div>@endif
            </td>
            <td class="amount">
                {{ $amount < 0 ? '(less) ' : '' }}{{ $money($amount) }}
            </td>
        </tr>
    @endforeach
</table>

@if ($settlement->net_payable < 0)
    <p style="margin-top:10pt; font-size:9pt; color:#9b2c2c;">
        <strong>This settlement is negative.</strong> After everything owed and everything recovered,
        {{ $money($settlement->net_payable) }} is due from the employee to the company.
    </p>
@endif

<div class="note">
    This statement covers salary to the last working day, leave not taken, gratuity where the service
    qualifies, and anything else agreed — less notice not served, advances still owed, and the statutory
    deductions on the final month's pay.
    @if ($settlement->status === 'agreed' || $settlement->status === 'paid')
        The figures were fixed when the settlement was agreed
        @if ($settlement->agreed_at) on {{ \Carbon\Carbon::parse($settlement->agreed_at)->format('d M Y') }} @endif
        and do not change afterwards.
    @endif
    @if ($settlement->status === 'paid' && $settlement->payment_reference)
        Paid on {{ \Carbon\Carbon::parse($settlement->paid_at)->format('d M Y') }},
        reference {{ $settlement->payment_reference }}.
    @endif
    @if ($settlement->note)
        <br>{{ $settlement->note }}
    @endif
</div>

<div class="sign">
    <table>
        <tr>
            <td width="50%">
                Prepared {{ \Carbon\Carbon::parse($settlement->created_at)->format('d M Y') }}<br><br><br>
                _______________________<br>
                Employee
            </td>
            <td width="50%" style="text-align:right">
                For {{ $company->company_name ?? '' }}<br><br><br>
                _______________________<br>
                Authorised signatory
            </td>
        </tr>
    </table>
</div>

</body>
</html>
