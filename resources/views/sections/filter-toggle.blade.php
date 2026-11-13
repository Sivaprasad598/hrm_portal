{{-- "Filters" at the top right (the tab row, sections/page-tabs): the page's filter
     block stays hidden until it is clicked. A dot shows while one of $dot (the
     filter fields, e.g. ['#department', '#user_id']) is set to something other than "all".
     Used by the Attendance Register and the Shift Roster. --}}
@push('page-tab-actions')
    <button type="button" id="toggle-page-filters" class="btn-secondary rounded f-14 p-2 page-filter-btn" aria-expanded="false">
        <i class="fa fa-filter mr-1"></i>Filters<span class="page-filter-dot d-none"></span>
    </button>
@endpush

@push('styles')
    <style>
        body:not(.page-filters-open) #fullscreen > .filter-box { display: none !important; }
        /* the buttons on the tab row: one size, the same rounded corners */
        /* (body.epic: above the theme's 2px corners for .btn links) */
        body.epic .page-tabs__actions > .btn-secondary, body.epic .page-tabs__actions > .btn-primary, body.epic .page-tabs__actions > a,
        body.epic .page-tabs__actions .btn-group > .btn { height: 38px; border-radius: 8px !important; display: inline-flex; align-items: center; }
        .page-tabs__actions .btn-group > .btn:not(:first-child) { border-top-left-radius: 0 !important; border-bottom-left-radius: 0 !important; }
        .page-tabs__actions .btn-group > .btn:not(:last-child) { border-top-right-radius: 0 !important; border-bottom-right-radius: 0 !important; }
        .page-filter-btn { position: relative; cursor: pointer; }
        html body.page-filters-open .page-tabs .page-filter-btn { background: color-mix(in srgb, var(--epic-blue, #cb6b4f) 10%, #fff) !important;
            border-color: var(--epic-blue, #cb6b4f) !important; color: var(--epic-blue, #cb6b4f) !important; }
        html body.page-filters-open .page-tabs .page-filter-btn i { color: var(--epic-blue, #cb6b4f) !important; }
        .page-filter-dot { position: absolute; top: 6px; right: 6px; width: 8px; height: 8px; border-radius: 50%; background: var(--epic-blue, #cb6b4f); border: 2px solid #fff; }
    </style>
@endpush

@push('scripts')
    <script>
        $(function () {
            $('#toggle-page-filters').on('click', function () {
                var open = $('body').toggleClass('page-filters-open').hasClass('page-filters-open');
                $(this).attr('aria-expanded', open);
                if (open) { $('#fullscreen > .filter-box .select-picker').selectpicker('refresh'); }
            });

            var dotFields = @json($dot ?? []);
            function markPageFilters() {
                var set = dotFields.some(function (s) { return $(s).length && $(s).val() && $(s).val() !== 'all'; });
                $('.page-filter-dot').toggleClass('d-none', !set);
            }
            if (dotFields.length) {
                $(dotFields.join(',')).on('change', markPageFilters);
                markPageFilters();
            }
        });
    </script>
@endpush
