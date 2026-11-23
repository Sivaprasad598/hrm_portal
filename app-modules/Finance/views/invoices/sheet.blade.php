{{-- One invoice as it prints (AppModules\Finance\Services\GstInvoice::sheet): the live preview, the
     invoice page and the PDF. Tables only (DomPDF); DejaVu Sans for the rupee sign. --}}
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>{{ $c['title'] }} {{ $number }}</title>
<style>
    @page { margin: 12mm 12mm 30mm 12mm; }
    * { box-sizing: border-box; }
    body { font-family: "DejaVu Sans", Arial, sans-serif; font-size: 10.5px; color: #1d2433; margin: 0; line-height: 1.35; }
    body.on-screen { background: #fff; padding: 22px 26px 120px; }
    table { border-collapse: collapse; width: 100%; }
    td, th { vertical-align: top; }
    .head td { padding: 0; }
    .logo img { height: 46px; }
    .title { text-align: right; }
    .title b { display: block; font-size: 19px; letter-spacing: 1px; color: #c0603c; text-transform: uppercase; }
    .title span { font-size: 9.5px; color: #6b7280; }
    .rule { height: 3px; background: #c0603c; margin: 8px 0 10px; }
    .box { border: 1px solid #d5d9e0; }
    .box td { padding: 7px 9px; }
    .lbl { font-size: 8.5px; text-transform: uppercase; letter-spacing: .6px; color: #6b7280; margin-bottom: 2px; }
    .strong { font-weight: bold; font-size: 11.5px; }
    .kv td { padding: 1.5px 0; border: 0; }
    .kv td.k { color: #6b7280; width: 42%; }
    .intro { margin: 10px 0 2px; }
    .items { margin-top: 10px; }
    .items th { background: #f3f4f6; border: 1px solid #d5d9e0; padding: 5px 5px; font-size: 8.5px; text-transform: uppercase; letter-spacing: .3px; text-align: left; }
    .items td { border: 1px solid #e3e6eb; padding: 5px 5px; font-size: 10px; }
    .items .n { text-align: right; white-space: nowrap; }
    .items .c { text-align: center; }
    .items .desc { color: #6b7280; font-size: 9.5px; margin-top: 2px; white-space: pre-line; }
    .totals td { padding: 3px 6px; border: 0; }
    .totals td.n { text-align: right; width: 120px; white-space: nowrap; }
    .totals tr.grand td { border-top: 1.5px solid #1d2433; font-weight: bold; font-size: 12.5px; padding-top: 6px; }
    .words { margin-top: 6px; padding: 6px 9px; background: #f9fafb; border: 1px solid #e3e6eb; }
    .sum { margin-top: 10px; }
    .sum th { background: #f9fafb; border: 1px solid #e3e6eb; padding: 4px 6px; font-size: 8.5px; text-transform: uppercase; text-align: right; }
    .sum th:first-child, .sum td:first-child { text-align: left; }
    .sum td { border: 1px solid #e3e6eb; padding: 4px 6px; text-align: right; font-size: 9.5px; }
    .foot2 td { padding: 0; }
    .pre { white-space: pre-line; }
    .sign { text-align: right; }
    .sign img { height: 70px; margin: 4px 0; }
    .sign .space { height: 54px; }
    .mark { position: fixed; top: 38%; left: 0; right: 0; text-align: center; font-size: 80px; font-weight: bold; letter-spacing: 8px;
        color: rgba(192, 96, 60, .09); transform: rotate(-24deg); }
    .on-screen .mark { position: absolute; }
    .foot { position: fixed; left: 0; right: 0; bottom: -24mm; text-align: center; }
    .on-screen .foot { position: static; margin-top: 26px; }
    .foot .co { color: #c0603c; font-weight: bold; font-size: 12px; }
    .foot .addr { font-size: 8.5px; margin: 2px 0 3px; }
    .foot table td { font-size: 8.5px; width: 33%; }
    .foot .bar { height: 4px; background: #c0603c; margin-top: 3px; }
    .muted { color: #6b7280; }
</style>
</head>
<body class="{{ $forPdf ? 'in-pdf' : 'on-screen' }}">
@php
    $intra = $c['supply_type'] === 'intra';
    $supply = $c['title'] !== 'Bill of Supply';
    $state = fn ($code) => $code ? (($states[$code] ?? $code) . ' (' . $code . ')') : '—';
    $pan = $s->gstin ? substr($s->gstin, 2, 10) : null;
    $qty = fn ($v) => rtrim(rtrim(number_format((float) $v, 3, '.', ''), '0'), '.');
    $rate = fn ($v) => rtrim(rtrim(number_format((float) $v, 2, '.', ''), '0'), '.');
@endphp

@if ($status === 'draft')<div class="mark">DRAFT</div>@elseif ($status === 'canceled')<div class="mark">CANCELLED</div>@endif

@if ($forPdf)
    <div class="foot">
        <div class="co">{{ $letter['footer_company'] }}</div>
        <div class="addr">{{ $letter['footer_address'] }}</div>
        <table><tr><td style="text-align:left">{{ $letter['footer_phone'] }}</td><td style="text-align:center">{{ $letter['footer_website'] }}</td><td style="text-align:right">{{ $letter['footer_email'] }}</td></tr></table>
        <div class="bar"></div>
    </div>
@endif

<table class="head">
    <tr>
        <td class="logo">@if ($logo)<img src="{{ $logo }}" alt="">@else<b style="font-size:16px">{{ $s->company }}</b>@endif</td>
        <td class="title"><b>{{ $c['title'] }}</b><span>{{ $supply ? 'Original for recipient' : 'Composition / exempt supply' }}</span></td>
    </tr>
</table>
<div class="rule"></div>

{{-- Who and when --}}
<table class="box">
    <tr>
        <td style="width:50%; border-right:1px solid #d5d9e0">
            <div class="lbl">From</div>
            <div class="strong">{{ $s->company }}</div>
            <div class="pre">{{ $s->address }}</div>
            <table class="kv" style="margin-top:4px">
                <tr><td class="k">GSTIN</td><td><b>{{ $s->gstin ?: 'Not set' }}</b></td></tr>
                @if ($pan)<tr><td class="k">PAN</td><td>{{ $pan }}</td></tr>@endif
                <tr><td class="k">State</td><td>{{ $state($s->state_code) }}</td></tr>
            </table>
        </td>
        <td style="width:50%">
            <table class="kv">
                <tr><td class="k">Invoice no.</td><td><b>{{ $number ?: 'Draft — numbered when issued' }}</b></td></tr>
                <tr><td class="k">Invoice date</td><td>{{ $issueDate?->format('d M Y') ?? '—' }}</td></tr>
                <tr><td class="k">Due date</td><td>{{ $dueDate?->format('d M Y') ?? '—' }}</td></tr>
                <tr><td class="k">Place of supply</td><td>{{ $state($c['place_of_supply']) }}</td></tr>
                <tr><td class="k">Reverse charge</td><td>{{ $c['reverse_charge'] ? 'Yes' : 'No' }}</td></tr>
                @if ($c['po'])<tr><td class="k">PO / reference</td><td>{{ $c['po'] }}</td></tr>@endif
                @if ($project)<tr><td class="k">Project</td><td>{{ $project }}</td></tr>@endif
            </table>
        </td>
    </tr>
    <tr>
        <td colspan="2" style="border-top:1px solid #d5d9e0">
            <div class="lbl">Bill to</div>
            <div class="strong">{{ $c['bill_to']['name'] ?: '—' }}</div>
            @if ($c['bill_to']['address'])<div class="pre">{{ $c['bill_to']['address'] }}</div>@endif
            <div>
                GSTIN: <b>{{ $c['bill_to']['gstin'] ?: 'Unregistered' }}</b>
                &nbsp;·&nbsp; State: {{ $state($c['bill_to']['state']) }}
                @if ($c['bill_to']['email']) &nbsp;·&nbsp; {{ $c['bill_to']['email'] }}@endif
            </div>
        </td>
    </tr>
</table>

@if ($c['intro'])<div class="intro pre">{{ $c['intro'] }}</div>@endif

{{-- The lines --}}
<table class="items">
    <thead>
        <tr>
            <th style="width:18px">#</th>
            <th>Description</th>
            <th style="width:54px">HSN/SAC</th>
            <th class="n" style="width:50px">Qty</th>
            <th class="n" style="width:64px">Rate</th>
            <th class="n" style="width:74px">Taxable</th>
            @if ($supply)<th class="n" style="width:34px">GST</th><th class="n" style="width:62px">Tax</th>@endif
            <th class="n" style="width:76px">Amount</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($c['lines'] as $i => $l)
            <tr>
                <td class="c">{{ $i + 1 }}</td>
                <td><b>{{ $l['name'] }}</b>@if ($l['description'])<div class="desc">{{ $l['description'] }}</div>@endif</td>
                <td>{{ $l['hsn'] }}</td>
                <td class="n">{{ $qty($l['qty']) }}{{ $l['unit'] ? ' ' . $l['unit'] : '' }}</td>
                <td class="n">{{ $money($l['rate']) }}</td>
                <td class="n">{{ $money($l['taxable']) }}</td>
                @if ($supply)<td class="n">{{ $rate($l['gst']) }}%</td><td class="n">{{ $money($l['tax']) }}</td>@endif
                <td class="n">{{ $money($l['taxable'] + ($supply ? $l['tax'] : 0)) }}</td>
            </tr>
        @empty
            <tr><td colspan="{{ $supply ? 9 : 7 }}" class="muted" style="text-align:center; padding:14px">Lines appear here as they are added.</td></tr>
        @endforelse
    </tbody>
</table>

{{-- Totals --}}
<table style="margin-top:6px">
    <tr>
        <td style="width:55%; padding-right:12px">
            <div class="words"><span class="lbl">Amount in words</span><br><b>{{ $words }}</b></div>
        </td>
        <td>
            <table class="totals">
                <tr><td>Taxable value</td><td class="n">{{ $money($c['sub_total']) }}</td></tr>
                @if ($supply)
                    @if ($intra)
                        <tr><td>CGST</td><td class="n">{{ $money($c['cgst']) }}</td></tr>
                        <tr><td>SGST</td><td class="n">{{ $money($c['sgst']) }}</td></tr>
                    @else
                        <tr><td>IGST</td><td class="n">{{ $money($c['igst']) }}</td></tr>
                    @endif
                @endif
                @if (abs($c['round_off']) > 0.001)<tr><td>Round off</td><td class="n">{{ $money($c['round_off']) }}</td></tr>@endif
                <tr class="grand"><td>Total</td><td class="n">₹ {{ $money($c['total']) }}</td></tr>
            </table>
        </td>
    </tr>
</table>

{{-- Tax by HSN / SAC and rate --}}
@if ($supply && count($c['summary']))
    <table class="sum">
        <thead>
            <tr>
                <th>HSN / SAC</th><th>Taxable value</th>
                @if ($intra)<th>CGST rate</th><th>CGST</th><th>SGST rate</th><th>SGST</th>@else<th>IGST rate</th><th>IGST</th>@endif
                <th>Total tax</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($c['summary'] as $r)
                <tr>
                    <td>{{ $r['hsn'] ?: '—' }}</td><td>{{ $money($r['taxable']) }}</td>
                    @if ($intra)
                        <td>{{ $rate($r['gst'] / 2) }}%</td><td>{{ $money($r['cgst']) }}</td><td>{{ $rate($r['gst'] / 2) }}%</td><td>{{ $money($r['sgst']) }}</td>
                    @else
                        <td>{{ $rate($r['gst']) }}%</td><td>{{ $money($r['tax']) }}</td>
                    @endif
                    <td>{{ $money($r['tax']) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endif

@if ($c['reverse_charge'])<p class="muted" style="margin:8px 0 0">Tax on this supply is payable by the recipient under reverse charge.</p>@endif

{{-- Bank, terms, signature --}}
<table class="foot2" style="margin-top:14px">
    <tr>
        <td style="width:58%; padding-right:16px">
            @if ($c['bank'])<div class="lbl">Bank details</div><div class="pre" style="margin-bottom:8px">{{ $c['bank'] }}</div>@endif
            @if ($c['terms'])<div class="lbl">Terms</div><div class="pre" style="margin-bottom:8px">{{ $c['terms'] }}</div>@endif
            @if ($c['notes'])<div class="lbl">Notes</div><div class="pre">{{ $c['notes'] }}</div>@endif
        </td>
        <td class="sign">
            <div>For <b>{{ $s->company }}</b></div>
            @if ($stamp)<img src="{{ $stamp }}" alt="">@else<div class="space"></div>@endif
            <div>{{ $c['signatory'] ?: 'Authorised Signatory' }}</div>
            @if ($c['signatory'])<div class="muted">Authorised Signatory</div>@endif
        </td>
    </tr>
</table>

@unless ($forPdf)
    <div class="foot">
        <div class="co">{{ $letter['footer_company'] }}</div>
        <div class="addr">{{ $letter['footer_address'] }}</div>
        <table><tr><td style="text-align:left">{{ $letter['footer_phone'] }}</td><td style="text-align:center">{{ $letter['footer_website'] }}</td><td style="text-align:right">{{ $letter['footer_email'] }}</td></tr></table>
        <div class="bar"></div>
    </div>
@endunless
</body>
</html>
