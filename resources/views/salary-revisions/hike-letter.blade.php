{{-- One increment / hike letter (SalaryRevisionLetter::hikePage), in the company's own format, on the
     offer letter's letterhead. Wording: Settings -> Letter Settings -> Hike Letter. --}}
@if ($draft)<div class="h-draft">DRAFT</div>@endif

<div class="sheet">
    <div class="logo">@if ($logo)<img src="{{ $logo }}" alt="">@endif</div>

    <div class="h-title">{!! $title !!}</div>
    <div class="h-date">Date: {{ $letterDate }}</div>

    <div class="h-to">
        <strong>To,</strong><br>
        Employee Name: {{ $name }}<br>
        Employee ID: {{ $code ?: '—' }}<br>
        Designation: {{ $designation }}<br>
        Department: {{ $department }}
    </div>

    <div class="h-subject">Subject: {!! $subject !!}</div>

    <div class="h-body">
        {!! $greeting !!}<br>
        {!! $intro !!}
        @if ($promotion)<br>{!! $promotion !!}@endif
        @if ($arrears)<br>{!! $arrears !!}@endif
    </div>

    <div class="h-body">{!! $tableIntro !!}</div>
    <table class="h-pay">
        <tr><th>Particulars</th><th>Existing Salary</th><th>Revised Salary</th></tr>
        <tr><td>Monthly Gross Salary</td><td>{{ $inr($oldMonthly) }}</td><td>{{ $inr($newMonthly) }}</td></tr>
        <tr><td>Annual Gross Salary (CTC)</td><td>{{ $inr($oldCtc) }}</td><td>{{ $inr($newCtc) }}</td></tr>
        <tr><td>Monthly {{ $newCtc >= $oldCtc ? 'Increase' : 'Change' }}</td><td>—</td><td>{{ ($newCtc < $oldCtc ? '-' : '') . $inr(abs($newMonthly - $oldMonthly)) }}</td></tr>
        <tr><td>Annual {{ $newCtc >= $oldCtc ? 'Increase' : 'Change' }}</td><td>—</td><td>{{ ($newCtc < $oldCtc ? '-' : '') . $inr(abs($newCtc - $oldCtc)) }}</td></tr>
    </table>

    <div class="h-body">{!! $closing !!}</div>

    @if ($stamp)<div class="stamp"><img src="{{ $stamp }}" alt=""></div>@else<div style="height:40px"></div>@endif
    <div class="h-sign">{!! $signoff !!}</div>

    <div class="h-ack">
        <strong>{!! $ackTitle !!}</strong><br>
        {!! $ackText !!}<br>
        Employee Name: __________________<br>
        Employee Signature: ______________<br>
        Date: ___________________________
    </div>

    @unless ($forPdf)@include('offer-letters.partials.footer')@endunless
</div>
