{{-- The relieving cum experience letter (App\Services\Employee\RelievingLetter::data), on the offer
     letter's letterhead and footer. Shown in the browser and turned into the PDF.
     Wording: Settings -> Letter Settings -> Relieving Letter. --}}
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>Relieving Letter - {{ $name }}</title>
@include('offer-letters.partials.styles')
</head>
<body class="{{ $forPdf ? 'in-pdf' : 'on-screen' }}">
@if ($forPdf)@include('offer-letters.partials.footer')@endif

<div class="sheet">
    <div class="logo">@if ($logo)<img src="{{ $logo }}" alt="">@endif</div>

    <div class="r-date">Date: {!! $letterDate !!}</div>

    <div class="r-title">{!! $title !!}</div>

    <div class="r-to">
        To,<br>
        {{ $addressee }},<br>
        {!! nl2br(e($address), false) !!}
    </div>

    <div class="r-greet">{!! $greeting !!}</div>

    <div class="r-body">
        @foreach ($paragraphs as $paragraph)
            <p>{!! $paragraph !!}</p>
        @endforeach
    </div>

    <div class="closing">{!! $closing !!}</div>
    @if ($stamp)<div class="stamp"><img src="{{ $stamp }}" alt=""></div>@endif
    <div class="signoff" @if ($stamp) style="margin-top:4px" @endif>{!! $signoff !!}</div>

    @unless ($forPdf)@include('offer-letters.partials.footer')@endunless
</div>
</body>
</html>
