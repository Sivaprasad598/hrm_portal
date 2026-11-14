{{-- "Filters" at the top right (tab row) for a page whose filters are its own card
     (a GET form with Apply, e.g. the payroll pages): the card stays hidden until
     the button is clicked. $card: the card's CSS selector. $active: a filter other
     than the period is in use (a dot on the button). --}}
@push('page-tab-actions')
    <button type="button" class="btn-secondary rounded f-14 card-filter-btn {{ !empty($active) ? 'has-filter' : '' }}" aria-expanded="false"
            onclick="var o=document.body.classList.toggle('card-filters-open');this.setAttribute('aria-expanded',o);this.classList.toggle('is-open',o);">
        <i class="fa fa-filter mr-1"></i>Filters<span class="card-filter-dot"></span>
    </button>
@endpush

@push('styles')
    <style>
        body:not(.card-filters-open) {{ $card }} { display: none !important; }
        body.epic .page-tabs__actions > .btn-secondary, body.epic .page-tabs__actions > .btn-primary, body.epic .page-tabs__actions > a {
            height: 38px; border-radius: 8px !important; display: inline-flex; align-items: center; padding: 0 14px !important; white-space: nowrap; }
        .card-filter-btn { position: relative; cursor: pointer; order: 99; }
        html body.epic .page-tabs .card-filter-btn.is-open { background: color-mix(in srgb, var(--epic-blue, #cb6b4f) 10%, #fff) !important;
            border-color: var(--epic-blue, #cb6b4f) !important; color: var(--epic-blue, #cb6b4f) !important; }
        html body.epic .page-tabs .card-filter-btn.is-open i { color: var(--epic-blue, #cb6b4f) !important; }
        .card-filter-dot { display: none; position: absolute; top: 6px; right: 6px; width: 8px; height: 8px; border-radius: 50%; background: var(--epic-blue, #cb6b4f); border: 2px solid #fff; }
        .card-filter-btn.has-filter .card-filter-dot { display: block; }
        /* the filter card: white, rounded, plain grey labels */
        {{ $card }} { border-radius: 12px !important; box-shadow: 0 1px 3px rgba(16, 24, 40, .06) !important; }
        {{ $card }} label { font-size: 13.5px !important; font-weight: 500; color: #667085 !important; }
        {{ $card }} select.form-control, {{ $card }} .bootstrap-select > .dropdown-toggle { height: 38px !important; border-radius: 8px !important; }
    </style>
@endpush
