{{-- The hike letters of one or more salary revisions (SalaryRevisionLetter): one page each, on the
     shared letterhead (offer-letters.partials). --}}
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>Increment letter</title>
@include('offer-letters.partials.styles')
<style>
    .h-title { text-align: center; font-weight: bold; text-decoration: underline; margin: 4px 0 6px; }
    .h-date { text-align: right; margin-bottom: 16px; }
    .h-to { margin-bottom: 18px; line-height: 1.35; }
    .h-subject { font-weight: bold; margin-bottom: 18px; }
    .h-body { margin-bottom: 16px; line-height: 1.35; }
    table.h-pay { border-collapse: collapse; margin: -8px 0 18px; }
    table.h-pay th { text-align: left; font-weight: bold; padding: 2px 22px 2px 0; }
    table.h-pay td { padding: 2px 22px 2px 0; }
    .h-sign { line-height: 1.35; margin-bottom: 14px; }
    .h-ack { border-top: 1.5px solid #9aa5b1; padding-top: 8px; line-height: 1.35; }
    .stamp img { height: 86px; margin: 0 0 2px -4px; }
    .h-draft { position: fixed; top: 38%; left: 12%; font-size: 90px; color: rgba(155, 44, 44, 0.08); transform: rotate(-30deg); font-weight: bold; letter-spacing: 8px; }
</style>
</head>
<body class="{{ $forPdf ? 'in-pdf' : 'on-screen' }}">
@if ($forPdf)@include('offer-letters.partials.footer', ['s' => $s])@endif
@foreach ($pages as $page)
    {!! $page !!}
    @unless ($loop->last)<div class="page-break"></div>@endunless
@endforeach
</body>
</html>
