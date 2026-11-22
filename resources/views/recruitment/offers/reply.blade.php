{{-- The candidate's page from the offer email (OfferReplyController): the offer, and Accept / Decline.
     Stand-alone (no portal layout: the candidate is not signed in). --}}
@php
    $candidate = $offer->application->candidate;
    $format = $company?->date_format ?? 'd-m-Y';
    $branch = $offer->branch_id ? \App\Models\Branch::withoutGlobalScopes()->find($offer->branch_id)?->branch_name : null;
    $division = $offer->department_id ? \App\Models\Team::withoutGlobalScopes()->find($offer->department_id)?->team_name : null;
    $accent = $company?->header_color ?: '#cb6b4f';
    $state = $offer->status;
@endphp
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Your offer - {{ $company?->company_name ?? config('app.name') }}</title>
    <style>
        :root { --accent: {{ $accent }}; }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: -apple-system, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; background: #f4f5f7; color: #1d2939; font-size: 15px; line-height: 1.5; }
        .wrap { max-width: 640px; margin: 0 auto; padding: 32px 16px; }
        .brand { display: flex; align-items: center; gap: 12px; margin-bottom: 18px; }
        .brand img { max-height: 44px; max-width: 180px; }
        .brand span { font-weight: 600; font-size: 17px; }
        .card { background: #fff; border-radius: 14px; box-shadow: 0 2px 10px rgba(16, 24, 40, .06); padding: 26px; }
        h1 { font-size: 22px; margin: 0 0 6px; }
        .lead { color: #475467; margin: 0 0 18px; }
        dl { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin: 0 0 20px; }
        dl div { background: #f9fafb; border: 1px solid #f2f4f7; border-radius: 10px; padding: 10px 12px; }
        dt { font-size: 12px; color: #667085; margin: 0 0 2px; }
        dd { margin: 0; font-weight: 600; }
        @media (max-width: 520px) { dl { grid-template-columns: 1fr; } .card { padding: 20px; } }
        .note { background: #f9fafb; border-radius: 10px; padding: 12px 14px; color: #475467; margin-bottom: 20px; white-space: pre-line; }
        label { display: block; font-size: 14px; font-weight: 600; margin: 14px 0 6px; }
        input[type=text], textarea { width: 100%; padding: 10px 12px; border: 1px solid #d0d5dd; border-radius: 8px; font: inherit; }
        textarea { min-height: 80px; }
        .check { display: flex; gap: 10px; align-items: flex-start; font-weight: 400; margin-top: 16px; }
        .check input { margin-top: 4px; width: 18px; height: 18px; accent-color: var(--accent); }
        .choice { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-top: 4px; }
        .choice label { margin: 0; display: flex; align-items: center; gap: 8px; justify-content: center; padding: 12px; border: 1.5px solid #d0d5dd; border-radius: 10px; cursor: pointer; font-weight: 600; }
        .choice input { accent-color: var(--accent); }
        .choice label:has(input:checked) { border-color: var(--accent); background: color-mix(in srgb, var(--accent) 8%, #fff); }
        button { margin-top: 20px; width: 100%; padding: 13px; border: 0; border-radius: 10px; background: var(--accent); color: #fff; font: inherit; font-weight: 600; cursor: pointer; }
        .errors { background: #fef3f2; border: 1px solid #fecdca; color: #b42318; border-radius: 10px; padding: 10px 14px; margin-bottom: 16px; }
        .state { text-align: center; padding: 10px 0; }
        .state .icon { width: 56px; height: 56px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; font-size: 26px; margin-bottom: 10px; }
        .ok .icon { background: #dcfae6; color: #067647; } .no .icon { background: #f2f4f7; color: #475467; }
        .muted { color: #667085; font-size: 13px; }
        .hide { display: none; }
    </style>
</head>
<body>
<div class="wrap">
    <div class="brand">
        @if ($company?->logo_url)<img src="{{ $company->logo_url }}" alt="">@endif
        <span>{{ $company?->company_name ?? config('app.name') }}</span>
    </div>

    <div class="card">
        @if ($state === 'accepted')
            <div class="state ok">
                <div class="icon">&#10003;</div>
                <h1>{{ $done ? 'Thank you, ' . $candidate->name . '!' : 'Offer accepted' }}</h1>
                <p class="lead">You have accepted the offer{{ $offer->responded_at ? ' on ' . $offer->responded_at->tz($company?->timezone ?? config('app.timezone'))->format($format) : '' }}.
                    We look forward to welcoming you on {{ $offer->joining_date->format($format) }}. HR will be in touch about joining.</p>
            </div>
        @elseif ($state === 'declined')
            <div class="state no">
                <div class="icon">&#8212;</div>
                <h1>Offer declined</h1>
                <p class="lead">{{ $done ? 'Thank you for letting us know. We wish you the very best.' : 'This offer was declined.' }}</p>
            </div>
        @elseif ($state !== 'sent')
            <div class="state no">
                <div class="icon">!</div>
                <h1>This offer is no longer open</h1>
                <p class="lead">Please contact HR if you have any questions.</p>
            </div>
        @else
            <h1>Congratulations, {{ $candidate->name }}</h1>
            <p class="lead">We are pleased to offer you a position with {{ $company?->company_name ?? config('app.name') }}. Please read the offer and let us know your answer.</p>

            <dl>
                <div><dt>Position</dt><dd>{{ $offer->designation?->name ?: $offer->application->positionLabel() }}</dd></div>
                <div><dt>Annual CTC</dt><dd>{{ number_format($offer->annual_ctc) }}</dd></div>
                <div><dt>Joining date</dt><dd>{{ $offer->joining_date->format($format) }}</dd></div>
                @if ($branch)<div><dt>Location</dt><dd>{{ $branch }}</dd></div>@endif
                @if ($division)<div><dt>Division</dt><dd>{{ $division }}</dd></div>@endif
                @if ($offer->reference_no)<div><dt>Reference</dt><dd>{{ $offer->reference_no }}</dd></div>@endif
                @if ($offer->valid_until)<div><dt>Please answer by</dt><dd>{{ $offer->valid_until->format($format) }}</dd></div>@endif
            </dl>

            @if ($offer->notes)<div class="note">{{ $offer->notes }}</div>@endif

            @if ($errors->any())
                <div class="errors">{{ $errors->first() }}</div>
            @endif

            <form method="POST" action="{{ $answerUrl }}">
                @csrf
                <label>Your answer</label>
                <div class="choice">
                    <label><input type="radio" name="answer" value="accepted" @checked(old('answer', 'accepted') === 'accepted')> I accept</label>
                    <label><input type="radio" name="answer" value="declined" @checked(old('answer') === 'declined')> I decline</label>
                </div>

                <label class="check" id="agree-box"><input type="checkbox" name="agree" value="1" @checked(old('agree'))>
                    <span>I accept this offer and confirm I will join on {{ $offer->joining_date->format($format) }}.</span></label>

                <label for="signature">Type your full name to sign</label>
                <input type="text" id="signature" name="signature" value="{{ old('signature') }}" placeholder="{{ $candidate->name }}" autocomplete="name" required>

                <label for="note" id="note-label">A message for HR <span class="muted">(optional)</span></label>
                <textarea id="note" name="note" placeholder="Anything you would like us to know">{{ old('note') }}</textarea>

                <button type="submit" id="send">Send my answer</button>
                <p class="muted" style="margin-top:12px">Your answer is recorded with the date and time. To discuss the offer first, reply to the offer email.</p>
            </form>
        @endif
    </div>
</div>
<script>
    (function () {
        var radios = document.querySelectorAll('input[name=answer]');
        function sync() {
            var accept = document.querySelector('input[name=answer]:checked').value === 'accepted';
            document.getElementById('agree-box').classList.toggle('hide', !accept);
            document.getElementById('note-label').firstChild.textContent = accept ? 'A message for HR ' : 'The reason, if you would like to share it ';
            document.getElementById('send').textContent = accept ? 'Accept the offer' : 'Decline the offer';
        }
        if (radios.length) { radios.forEach(function (r) { r.addEventListener('change', sync); }); sync(); }
    })();
</script>
</body>
</html>
