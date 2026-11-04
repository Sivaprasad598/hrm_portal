@php
    $fmt = company()->date_format;
    $tz = company()->timezone;
    $payload = $item->payload ?? [];
@endphp
<div class="bg-white rounded">
    <div class="p-20 border-bottom-grey">
        <h4 class="mb-1 f-21 font-weight-normal">{{ $item->subject ?: '(no subject)' }}</h4>
        <div class="f-13 text-dark-grey">
            {{ $item->channel === 'email' ? 'Careers mailbox' : 'Website' }} ·
            {{ $item->from_name }} &lt;{{ $item->from_email }}&gt; ·
            {{ ($item->received_at ?? $item->created_at)->tz($tz)->format($fmt . ' h:i A') }}
            @if ($item->ip) · IP {{ $item->ip }} @endif
        </div>
        <div class="mt-2">{!! $item->statusBadge() !!} @if ($item->reason)<span class="f-13 text-dark-grey">{{ $item->reason }}</span>@endif
            @if ($item->candidate) · <a href="{{ route('candidates.show', $item->candidate_id) }}" class="f-13">{{ $item->candidate->name }}</a>@endif
        </div>
        <div class="f-11 text-lightest mt-1">ID: {{ $item->external_id }}</div>
    </div>

    <div class="p-20">
        @if (!empty($payload['attachments']))
            <h6 class="f-13 font-weight-600 text-dark-grey">Attachments</h6>
            <ul class="f-13">
                @foreach ($payload['attachments'] as $attachment)
                    <li>{{ $attachment['name'] }} <span class="text-dark-grey">({{ number_format(($attachment['size'] ?? 0) / 1024) }} KB)</span></li>
                @endforeach
            </ul>
        @endif

        @if ($item->channel === 'api')
            <h6 class="f-13 font-weight-600 text-dark-grey">Fields sent</h6>
            <table class="table table-sm f-13">
                @foreach ($payload as $key => $value)
                    @continue($value === null || $value === '' || $key === 'attachments')
                    <tr><td class="text-dark-grey" style="width: 180px">{{ $key }}</td><td>{{ is_array($value) ? implode(', ', $value) : $value }}</td></tr>
                @endforeach
            </table>
        @endif

        @if ($item->body)
            <h6 class="f-13 font-weight-600 text-dark-grey">{{ $item->channel === 'email' ? 'Email' : 'Message' }}</h6>
            <div class="border rounded p-3 bg-light" style="white-space: pre-wrap; word-break: break-word; font-size: 13px; max-height: 60vh; overflow: auto">{{ $item->body }}</div>
        @endif
    </div>
</div>
