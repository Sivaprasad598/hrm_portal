{{-- One salary revision letter (SalaryRevisionLetter): the letter, then the annexure with the pay. --}}
@php
    $coName = $company->company_name ?? 'The Company';
    $contact = array_filter([$company->company_phone ?? null, $company->company_email ?? null, $company->website ? preg_replace('#^https?://|/$#', '', $company->website) : null]);
    $designation = $r->new_designation ?: $r->old_designation;
    $department = $r->new_department ?: $r->old_department;
    $rows = [
        ['Basic Salary', 'basic'], ['House Rent Allowance', 'hra'], ['Conveyance Allowance', 'ca'],
        ['Leave Travel Allowance', 'lta'], ['Children Education Allowance', 'cea'], ['Special Allowance', 'other'],
    ];
    $deductions = [['Provident Fund (employee)', 'pf_employee'], ['ESI (employee)', 'esi_employee'], ['Professional Tax', 'pt']];
    $employer = [['Provident Fund (employer)', 'pf_employer'], ['ESI (employer)', 'esi_employer']];
    $amount = fn ($v) => $inr($v);
@endphp

@if ($draft)<div class="draft">DRAFT</div>@endif

{{-- ===================== Letter ===================== --}}
<table class="head">
    <tr>
        <td class="logo">@if ($logo)<img src="{{ $logo }}" alt="">@endif</td>
        <td class="co">
            <strong>{{ $coName }}</strong>
            @if ($address){{ $address }}<br>@endif
            {{ implode('  ·  ', $contact) }}
        </td>
    </tr>
</table>
<div class="rule"></div><div class="rule2"></div>

<div class="conf">STRICTLY PRIVATE &amp; CONFIDENTIAL</div>

<table class="meta">
    <tr>
        <td><span class="hl">Ref:</span> {{ $r->letter_no ?: 'To be numbered on approval' }}</td>
        <td style="text-align:right"><span class="hl">Date:</span> {{ $letterDate->format('d F Y') }}</td>
    </tr>
</table>

<div class="to">
    To,<br>
    <span class="name">{{ $salutation }}{{ $r->name }}</span><br>
    Employee ID: {{ $r->code ?: '—' }}<br>
    {{ $r->old_designation ?: '' }}{{ $r->old_department ? ', ' . $r->old_department : '' }}<br>
    {{ $coName }}
</div>

<div class="subject">Subject: <span>{{ $subject }}</span></div>

<p>Dear {{ $firstName }},</p>

@if ($promoted)
    <p>We are pleased to inform you that, in recognition of your performance and contribution to the organisation, the Management has
        promoted you to the position of <span class="hl">{{ $r->new_designation }}</span>{!! $r->new_department ? ' in the <span class="hl">' . e($r->new_department) . '</span> department' : '' !!},
        with effect from <span class="hl">{{ $effective->format('d F Y') }}</span>.</p>
@elseif ($increase > 0)
    <p>We are pleased to inform you that, in recognition of your performance and contribution to the organisation, the Management has
        approved a revision of your compensation with effect from <span class="hl">{{ $effective->format('d F Y') }}</span>.</p>
@else
    <p>This is to inform you that your compensation has been revised with effect from <span class="hl">{{ $effective->format('d F Y') }}</span>.</p>
@endif

@if ($ctcChanged)
    <p>Consequently, your annual Cost to Company (CTC) stands revised from INR {{ $amount($r->old_ctc) }} to
        <span class="hl">INR {{ $amount($r->new_ctc) }}</span> (Rupees {{ $words($r->new_ctc) }} only){{ $increase > 0 ? ', an increase of INR ' . $amount($increase) . ' (' . number_format($pct, 2) . '%)' : '' }}.
        The revised salary will be paid from the salary
        month of <span class="hl">{{ $payMonth }}</span>. The detailed break-up of your revised compensation is given in the annexure.</p>
@elseif ($promoted)
    <p>Your compensation remains unchanged at an annual Cost to Company (CTC) of INR {{ $amount($r->new_ctc) }}.</p>
@endif

@if ($r->arrears_amount && abs((float) $r->arrears_amount) >= 1)
    <p>Arrears of INR {{ $amount(abs((float) $r->arrears_amount)) }} arising from this revision, {{ $r->arrears_note }}.</p>
@endif

@if ($promoted)
    <p>In your new role you will be expected to take on the responsibilities associated with the position, as communicated to you by
        your reporting manager.</p>
@endif

<p>All other terms and conditions of your employment remain unchanged. Your compensation details are strictly confidential and
    must not be discussed or shared with others.</p>

<p>We appreciate your contribution and look forward to your continued commitment to the growth of {{ $coName }}. We wish you
    every success in the years ahead.</p>

<div class="sign">
    Yours sincerely,<br>
    For <span class="hl">{{ $coName }}</span><br>
    @if ($stamp)<img src="{{ $stamp }}" alt="">@else<br><br><br>@endif<br>
    <span class="who">{{ $settings->signatory_name ?: 'Authorised Signatory' }}</span><br>
    {{ $settings->signatory_designation ?: '' }}
</div>

<div class="foot">{{ $coName }} · {{ $r->letter_no ?: 'Draft' }} · Confidential</div>

<div class="page-break"></div>

{{-- ===================== Annexure ===================== --}}
<table class="head">
    <tr>
        <td class="logo">@if ($logo)<img src="{{ $logo }}" alt="" style="height:34px">@endif</td>
        <td class="co"><strong>{{ $coName }}</strong>{{ $r->letter_no }}</td>
    </tr>
</table>
<div class="rule"></div><div class="rule2"></div>

<h3>ANNEXURE — COMPENSATION STRUCTURE</h3>
<div class="sub">{{ $salutation }}{{ $r->name }} ({{ $r->code }}) · {{ $designation }}{{ $department ? ' · ' . $department : '' }} ·
    effective {{ $effective->format('d M Y') }}, paid from {{ $payMonth }} salary</div>

<table class="summary">
    <tr>
        <td><div class="k">Current annual CTC</div><div class="v">INR {{ $amount($r->old_ctc) }}</div></td>
        <td><div class="k">Revised annual CTC</div><div class="v">INR {{ $amount($r->new_ctc) }}</div></td>
        <td><div class="k">Increase</div><div class="v">{{ $increase >= 0 ? '' : '-' }}INR {{ $amount(abs($increase)) }}</div></td>
        <td><div class="k">Increase %</div><div class="v">{{ number_format($pct, 2) }}%</div></td>
    </tr>
</table>

<table class="pay">
    <thead>
        <tr><th>Component</th><th>Current (monthly)</th><th>Revised (monthly)</th><th>Revised (annual)</th></tr>
    </thead>
    <tbody>
        <tr class="group"><td colspan="4">EARNINGS</td></tr>
        @foreach ($rows as [$label, $key])
            @if (($old[$key] ?? 0) > 0 || ($new[$key] ?? 0) > 0)
                <tr><td>{{ $label }}</td><td>{{ $amount($old[$key] ?? 0) }}</td><td>{{ $amount($new[$key] ?? 0) }}</td><td>{{ $amount(($new[$key] ?? 0) * 12) }}</td></tr>
            @endif
        @endforeach
        <tr class="total"><td>Gross Salary</td><td>{{ $amount($old['gross']) }}</td><td>{{ $amount($new['gross']) }}</td><td>{{ $amount($new['gross'] * 12) }}</td></tr>

        <tr class="group"><td colspan="4">DEDUCTIONS</td></tr>
        @foreach ($deductions as [$label, $key])
            @if (($old[$key] ?? 0) > 0 || ($new[$key] ?? 0) > 0)
                <tr><td>{{ $label }}</td><td>{{ $amount($old[$key] ?? 0) }}</td><td>{{ $amount($new[$key] ?? 0) }}</td><td>{{ $amount(($new[$key] ?? 0) * 12) }}</td></tr>
            @endif
        @endforeach
        <tr class="total"><td>Net Take-home (before income tax)</td><td>{{ $amount($old['gross'] - $old['pf_employee'] - $old['esi_employee'] - $old['pt']) }}</td>
            <td>{{ $amount($new['gross'] - $new['pf_employee'] - $new['esi_employee'] - $new['pt']) }}</td>
            <td>{{ $amount(($new['gross'] - $new['pf_employee'] - $new['esi_employee'] - $new['pt']) * 12) }}</td></tr>

        @if (($new['pf_employer'] ?? 0) > 0 || ($new['esi_employer'] ?? 0) > 0)
            <tr class="group"><td colspan="4">EMPLOYER CONTRIBUTIONS (for information)</td></tr>
            @foreach ($employer as [$label, $key])
                @if (($old[$key] ?? 0) > 0 || ($new[$key] ?? 0) > 0)
                    <tr><td>{{ $label }}</td><td>{{ $amount($old[$key] ?? 0) }}</td><td>{{ $amount($new[$key] ?? 0) }}</td><td>{{ $amount(($new[$key] ?? 0) * 12) }}</td></tr>
                @endif
            @endforeach
        @endif

        <tr class="total"><td>Annual Cost to Company (CTC)</td><td>{{ $amount($r->old_ctc) }} p.a.</td><td colspan="2">{{ $amount($r->new_ctc) }} p.a.</td></tr>
    </tbody>
</table>

<ul class="notes">
    <li>All amounts are in Indian Rupees (INR). Monthly figures are for a full month; days of loss of pay reduce them in proportion.</li>
    <li>Income tax (TDS) is deducted as per the applicable law and your investment declarations, and is not shown above.</li>
    <li>Statutory contributions (PF, ESI, Professional Tax) follow the rates and ceilings in force and may change with them.</li>
</ul>

<div class="ack">
    <div class="t">Acknowledgement</div>
    I acknowledge receipt of this letter and accept the revision of my compensation on the terms stated above.
    <div style="height:34px"></div>
    <table><tr><td>Signature</td><td class="gap"></td><td>Name</td><td class="gap"></td><td>Date</td></tr></table>
</div>

<div class="foot">{{ $coName }} · {{ $r->letter_no ?: 'Draft' }} · Confidential</div>
