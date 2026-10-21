{{--
    "Lead Contacts | Meetings" (or "Clients | Meetings") tabs, shown on the list page and on its Meetings page.
    $active ('list' | 'meetings'), $listUrl, $listLabel, $meetingsUrl
--}}
<div class="d-flex mb-3 {{ $class ?? '' }}" style="gap: 8px;">
    <a href="{{ $listUrl }}" class="btn {{ $active === 'list' ? 'btn-primary' : 'btn-outline-primary' }}">{{ $listLabel }}</a>
    <a href="{{ $meetingsUrl }}" class="btn {{ $active === 'meetings' ? 'btn-primary' : 'btn-outline-primary' }}">Meetings</a>
</div>
