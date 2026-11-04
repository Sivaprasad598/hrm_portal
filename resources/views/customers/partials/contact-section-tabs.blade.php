{{--
    "Lead Contacts | Meetings" (or "Clients | Meetings") tabs, shown on the list page and on its Meetings page.
    $active ('list' | 'meetings' | 'projects'), $listUrl, $listLabel, $meetingsUrl
    Optional: $class (extra classes), $margin (default 'mb-3': on its own row above the page's buttons),
    $projectsUrl ("Search Projects" first: the Lead Finder's project search; Lead Contacts, administrators)
--}}
<div class="d-flex {{ $margin ?? 'mb-3' }} {{ $class ?? '' }}" style="gap: 8px;">
    @if (!empty($projectsUrl))
        <a href="{{ $projectsUrl }}" class="btn {{ $active === 'projects' ? 'btn-primary' : 'btn-outline-primary' }}"><i class="fa fa-search mr-1"></i>Search Projects</a>
    @endif
    <a href="{{ $listUrl }}" class="btn {{ $active === 'list' ? 'btn-primary' : 'btn-outline-primary' }}">{{ $listLabel }}</a>
    <a href="{{ $meetingsUrl }}" class="btn {{ $active === 'meetings' ? 'btn-primary' : 'btn-outline-primary' }}">Meetings</a>
</div>
