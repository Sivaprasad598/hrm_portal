{{-- An employee's offer letter (App\Services\Employee\OfferLetter::data): the letter, then Annexure I.
     The same page is shown in the browser and turned into the PDF. Wording: Settings -> Offer Letter Settings. --}}
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>Offer Letter {{ $reference }}</title>
@include('offer-letters.partials.styles')
</head>
<body class="{{ $forPdf ? 'in-pdf' : 'on-screen' }}">
@php
    $footer = fn () => view('offer-letters.partials.footer', ['s' => $s])->render();
    $amount = fn ($v) => (float) $v != 0 ? $inr($v) : '-';
    $showZero = (bool) $s['show_zero_rows'];
@endphp

{{-- In the PDF the footer is fixed, so DomPDF repeats it on every page: written once. --}}
@if ($forPdf){!! $footer() !!}@endif

{{-- ============================ Letter ============================ --}}
<div class="sheet">
    <div class="logo">@if ($logo)<img src="{{ $logo }}" alt="">@endif</div>

    <div class="meta">
        <div>Reference No.: {{ $reference }}</div>
        <div>{!! $letterDate !!}</div>
    </div>

    <div class="to">
        {{ $addressee }}<br>
        {!! nl2br(e($address), false) !!}
    </div>

    <div class="subject">Sub.: {!! $subject !!}</div>

    @foreach ($paragraphs as $paragraph)
        <p>{!! $paragraph !!}</p>
    @endforeach

    @if ($documents)
        <ol class="docs">
            @foreach ($documents as $doc)<li>{!! $doc !!}</li>@endforeach
        </ol>
    @endif

    @if (trim(strip_tags($note)) !== '')<div class="note">{!! $note !!}</div>@endif

    <div class="closing">{!! $closing !!}</div>
    @if ($stamp)<div class="stamp"><img src="{{ $stamp }}" alt=""></div>@endif
    <div class="signoff" @if ($stamp) style="margin-top:4px" @endif>{!! $signoff !!}</div>
    @if (trim(strip_tags($enclosure)) !== '')<div class="encl">{!! $enclosure !!}</div>@endif

    @unless ($forPdf){!! $footer() !!}@endunless
</div>

<div class="page-break"></div>

{{-- ============================ Annexure I ============================ --}}
<div class="sheet">
    <div class="logo">@if ($logo)<img src="{{ $logo }}" alt="">@endif</div>

    <table class="ann">
        <tr class="b"><td>{{ $s['annexure_title'] }}</td><td class="n"></td><td class="n"></td></tr>
        <tr class="band"><td colspan="3">{{ $s['annexure_company'] }}</td></tr>
        <tr class="dark"><td colspan="3">{{ $s['annexure_heading'] }}</td></tr>
        <tr><td>Employee Name</td><td colspan="2">{{ $addressee }}</td></tr>
        <tr><td>Designation</td><td colspan="2">{{ $e->designation }}</td></tr>
        <tr><td>Date of Joining</td><td colspan="2">{!! $joiningDate !!}</td></tr>
        <tr><td colspan="3"></td></tr>

        <tr class="head"><td colspan="3">Gross Salary</td></tr>
        <tr class="head"><td>Salary Components</td><td class="n" style="text-align:center">Per Month</td><td class="n" style="text-align:center">Per Annum</td></tr>
        @foreach ($pay['earnings'] as $label => $value)
            @if ($value > 0 || $showZero)
                <tr><td>{{ $label }}</td><td class="n">{{ $amount($value) }}</td><td class="n">{{ $amount($value * 12) }}</td></tr>
            @endif
        @endforeach
        <tr class="b"><td>Gross Salary (A)</td><td class="n">{{ $amount($pay['gross']) }}</td><td class="n">{{ $amount($pay['gross'] * 12) }}</td></tr>
        <tr><td colspan="3"></td></tr>

        <tr class="head"><td colspan="3">Employer's Contribution</td></tr>
        @foreach ($pay['employer'] as $label => $value)
            @if ($value > 0 || $showZero)
                <tr><td>{{ $label }}</td><td class="n">{{ $amount($value) }}</td><td class="n">{{ $amount($value * 12) }}</td></tr>
            @endif
        @endforeach
        <tr class="b"><td>Total Employer's Contribution (B)</td><td class="n">{{ $amount($pay['employer_total']) }}</td><td class="n">{{ $amount($pay['employer_total'] * 12) }}</td></tr>
        <tr><td colspan="3"></td></tr>

        <tr class="b"><td>Fixed CTC (A) + (B)</td><td class="n">{{ $amount($pay['fixed_ctc']) }}</td><td class="n">{{ $amount($pay['fixed_ctc'] * 12) }}</td></tr>
        <tr><td colspan="3"></td></tr>

        <tr class="head"><td colspan="3">Employee's Contributions</td></tr>
        @foreach ($pay['deductions'] as $label => $value)
            @if ($value > 0 || $showZero)
                <tr><td>{{ $label }}</td><td class="n">{{ $amount($value) }}</td><td class="n">{{ $amount($value * 12) }}</td></tr>
            @endif
        @endforeach
        <tr class="b"><td>Total Employee Deductions (C)</td><td class="n">{{ $amount($pay['deductions_total']) }}</td><td class="n">{{ $amount($pay['deductions_total'] * 12) }}</td></tr>
        <tr><td colspan="3"></td></tr>

        <tr class="b"><td>Net In Hand Salary (Before Income Tax Deductions)</td><td class="n">{{ $amount($pay['net']) }}</td><td class="n">{{ $amount($pay['net'] * 12) }}</td></tr>
    </table>

    <div class="ack">
        <p>{!! $acknowledgement !!}</p>
        <p>{!! $acceptance !!}</p>
    </div>

    <div class="sigs">Signature:<br>Date:</div>

    @unless ($forPdf){!! $footer() !!}@endunless
</div>
</body>
</html>
