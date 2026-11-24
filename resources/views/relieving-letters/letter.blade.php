{{-- The relieving letter (App\Services\Employee\RelievingLetter::data, kind relieving), on the offer
     letter's letterhead and footer. Shown in the browser and turned into the PDF.
     Wording: Settings -> Letter Settings -> Relieving Letter. --}}
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>Relieving Letter - {{ $name }}</title>
@include('offer-letters.partials.styles')
<style>
    .rl-title { text-align: center; font-weight: bold; text-decoration: underline; font-size: 15px; margin: 26px 0 2px; }
    .rl-date { text-align: right; margin-bottom: 12px; }
    .rl-to { margin-bottom: 18px; line-height: 1.45; }
    .rl-subject { margin-bottom: 18px; }
    .rl-greet { margin-bottom: 12px; }
    .rl-body p { margin-bottom: 16px; }
</style>
</head>
<body class="{{ $forPdf ? 'in-pdf' : 'on-screen' }}">
@if ($forPdf)@include('offer-letters.partials.footer')@endif

<div class="sheet">
    <div class="logo">@if ($logo)<img src="{{ $logo }}" alt="">@endif</div>

    <div class="rl-title">{!! $title !!}</div>
    <div class="rl-date">{!! $letterDate !!}</div>

    <div class="rl-to">
        To,<br>
        {!! implode('<br>', $to) !!}
    </div>

    @if ($subject)<div class="rl-subject">Subject: {!! $subject !!}</div>@endif

    <div class="rl-greet">{!! $greeting !!}</div>

    <div class="rl-body">
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
