{{-- The experience letter (App\Services\Employee\RelievingLetter::data, kind experience), on the offer
     letter's letterhead and footer: To Whom It May Concern, the particulars table. Shown in the
     browser and turned into the PDF. Wording: Settings -> Letter Settings -> Experience Letter. --}}
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>Experience Letter - {{ $name }}</title>
@include('offer-letters.partials.styles')
<style>
    .xl-date { text-align: right; margin: 18px 0 22px; }
    .xl-date b { font-weight: bold; }
    .xl-title, .xl-heading { text-align: center; text-decoration: underline; font-size: 17px; }
    .xl-title { margin-bottom: 40px; }
    .xl-heading { margin-bottom: 22px; }
    .xl-body p { margin-bottom: 16px; }
    table.xl-part { border-collapse: collapse; margin: -6px 0 34px; font-size: 15px; }
    table.xl-part td { padding: 3px 8px 3px 0; vertical-align: top; }
    table.xl-part td.l { font-weight: bold; width: 170px; }
    table.xl-part tr.h td { font-weight: bold; }
</style>
</head>
<body class="{{ $forPdf ? 'in-pdf' : 'on-screen' }}">
@if ($forPdf)@include('offer-letters.partials.footer')@endif

<div class="sheet">
    <div class="logo">@if ($logo)<img src="{{ $logo }}" alt="">@endif</div>

    <div class="xl-date"><b>Date:</b> {!! $letterDate !!}</div>

    <div class="xl-title">{!! $title !!}</div>
    @if (trim(strip_tags($heading)) !== '')<div class="xl-heading">{!! $heading !!}</div>@endif

    <div class="xl-body">
        @foreach ($intro as $paragraph)
            <p>{!! $paragraph !!}</p>
        @endforeach
    </div>

    @if ($particulars)
        <table class="xl-part">
            @foreach ($particulars as $i => [$label, $value])
                <tr class="{{ $i === 0 ? 'h' : '' }}"><td class="l">{!! $label !!}</td><td>{!! $value !!}</td></tr>
            @endforeach
        </table>
    @endif

    <div class="xl-body">
        @foreach ($paragraphs as $paragraph)
            <p>{!! $paragraph !!}</p>
        @endforeach
    </div>

    @if ($closing)<div class="closing">{!! $closing !!}</div>@endif
    @if ($stamp)<div class="stamp"><img src="{{ $stamp }}" alt=""></div>@endif
    <div class="signoff" @if ($stamp) style="margin-top:4px" @endif>{!! $signoff !!}</div>

    @unless ($forPdf)@include('offer-letters.partials.footer')@endunless
</div>
</body>
</html>
