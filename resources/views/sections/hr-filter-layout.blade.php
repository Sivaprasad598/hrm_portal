{{-- HR pages (the pages under the HR menu): a filter card that has a search box
     - the fields share the full width of the card,
     - the search box is named "Search in the table",
     - the "Filters" button (the side panel of more filters) moves to the top right:
       on the tab row when the page has tabs, else at the right of the page's
       action bar.
     Only filter cards with a search box are touched. --}}
@php
    $hrFilterRoutes = \Illuminate\Support\Facades\Cache::remember('hr-filter-layout-routes', 600, function () {
        $hrId = \App\Models\SidebarMenu::whereNull('parent_id')->where('slug', 'hr')->value('id');

        return $hrId
            ? \App\Models\SidebarMenu::where('parent_id', $hrId)->whereNotNull('route')->pluck('route')
                ->map(fn ($r) => strtok((string) $r, '?|'))->filter()->unique()->values()->all()
            : [];
    });
    // Holidays (moved from HR to Leave Management): calendar and table view too
    $hrFilterRoutes = array_merge($hrFilterRoutes, ['holidays.index', 'holidays.table_view', 'leaves.index', 'leaves.calendar', 'assets.index']);
@endphp

@if ($hrFilterRoutes && request()->routeIs(...$hrFilterRoutes))
    <style>
        /* the fields share the whole card */
        body.epic .filter-box.hr-search-card .filter-box { flex-wrap: wrap; }
        body.epic .filter-box.hr-search-card .select-box:not(:has(> [id*="reset"])) { flex: 1 1 0; min-width: 170px; }
        /* the Clear Filters slot only takes its button's room (none while hidden) */
        body.epic .filter-box.hr-search-card .select-box:has(> [id*="reset"]) { flex: 0 0 auto; }
        body.epic .filter-box.hr-search-card .select-box:has(> [id*="reset"].d-none) { display: none !important; }
        body.epic .filter-box.hr-search-card .select-box .bootstrap-select { width: 100% !important; }
        body.epic .filter-box.hr-search-card .select-box > div, body.epic .filter-box.hr-search-card .select-box input.form-control { width: 100% !important; }
        body.epic .filter-box.hr-search-card .task-search { flex: 2 1 0; max-width: none; min-width: 240px; }
        body.epic .filter-box.hr-search-card .task-search::before { content: 'Search in the table'; }
        /* the old Filters link in the card: the button at the top right replaces it */
        body.epic .filter-box.hr-search-card .more-filters > a { display: none !important; }
        body.epic .filter-box.hr-search-card .ml-auto:has(> .more-filters) { margin-left: 0 !important; }
        /* the Filters button at the top right: the app's white button */
        body.epic .hr-top-filter { height: 38px; padding: 0 14px !important; border-radius: 8px !important; display: inline-flex; align-items: center; gap: 6px; cursor: pointer; }
        body.epic .hr-top-filter-slot { display: flex; justify-content: flex-end; margin-left: auto; }
        /* the page's buttons on the tab row: one size, rounded */
        body.epic .page-tabs__actions > a, body.epic .page-tabs__actions > button, body.epic .page-tabs__actions .dt-buttons > .btn {
            height: 38px; border-radius: 8px !important; display: inline-flex !important; align-items: center; padding: 0 14px !important; white-space: nowrap; margin: 0 !important; }
        body.epic .page-tabs__actions .dt-buttons { display: flex; gap: 10px; margin: 0; }
        /* "More filters" (the side panel) at the end of the filter card */
        body.epic .hr-more-filters { align-self: flex-end; margin-left: auto; height: 38px; display: inline-flex; align-items: center; padding: 0 4px;
            font-size: 13.5px; font-weight: 500; color: var(--epic-blue, #cb6b4f) !important; white-space: nowrap; }
        body.epic .action-bar > .hr-top-filter-slot { margin-left: auto; align-items: center; gap: 10px; }
        body.epic .action-bar:has(> .hr-top-filter-slot) { gap: 10px; }
        body.epic .hr-top-filter-slot > a, body.epic .hr-top-filter-slot > button { height: 38px; border-radius: 8px !important; display: inline-flex !important; align-items: center; padding: 0 14px !important; margin: 0 !important; white-space: nowrap; }
        body.epic .hr-top-filter { position: relative; }
        /* pages without a side panel: the filter card shows only after "Filters" is clicked */
        body.hr-filters-toggle:not(.hr-filters-open) .filter-box.hr-search-card { display: none !important; }
        /* no tab row: the card opens under the button row (buttons first, as on pages with tabs) */
        body.epic .filter-box.hr-search-card.hr-card-moved { margin: 16px 0 0 !important; }
        html body.epic .hr-top-filter.is-open, html body.epic .page-tabs .hr-top-filter.is-open { background: color-mix(in srgb, var(--epic-blue, #cb6b4f) 10%, #fff) !important;
            border-color: var(--epic-blue, #cb6b4f) !important; color: var(--epic-blue, #cb6b4f) !important; }
        html body.epic .hr-top-filter.is-open i, html body.epic .page-tabs .hr-top-filter.is-open i { color: var(--epic-blue, #cb6b4f) !important; }
        .hr-filter-dot { display: none; position: absolute; top: 6px; right: 6px; width: 8px; height: 8px; border-radius: 50%; background: var(--epic-blue, #cb6b4f); border: 2px solid #fff; }
        .hr-top-filter.has-filter .hr-filter-dot { display: block; }
    </style>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            document.querySelectorAll('#fullscreen > .filter-box').forEach(function (card) {
                var search = card.querySelector('.task-search');
                if (!search) { return; }
                card.classList.add('hr-search-card');

                var input = search.querySelector('input[type="text"], input[type="search"]');
                if (input) { input.setAttribute('placeholder', 'Search in the table'); }

                var btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'btn-secondary rounded f-14 hr-top-filter';
                btn.innerHTML = '<i class="fa fa-filter"></i> Filters<span class="hr-filter-dot"></span>';

                // (no "More filters" link: the card's own fields are the filters)
                {
                    // no side panel: the button shows / hides the filter card itself
                    // (hidden until clicked, like the Attendance Register); a dot while
                    // a filter is set or something is typed in the search
                    document.body.classList.add('hr-filters-toggle');
                    btn.addEventListener('click', function () {
                        var open = document.body.classList.toggle('hr-filters-open');
                        btn.classList.toggle('is-open', open);
                        if (open && window.jQuery) { jQuery(card).find('.select-picker').selectpicker('refresh'); }
                    });
                    // "set" = changed from how the page opened (Month / Year always have a value)
                    var initial = Array.prototype.map.call(card.querySelectorAll('select'), function (s) { return s.value; });
                    var mark = function () {
                        var set = Array.prototype.some.call(card.querySelectorAll('select'), function (s, i) { return s.value !== initial[i]; })
                            || (input && input.value.trim() !== '');
                        btn.classList.toggle('has-filter', !!set);
                    };
                    card.addEventListener('change', mark);
                    if (input) { input.addEventListener('input', mark); }
                    mark();
                }

                var tabActions = document.querySelector('.page-tabs .page-tabs__actions');
                var actionBar = document.querySelector('#fullscreen .content-wrapper .action-bar');
                if (tabActions) {
                    tabActions.appendChild(btn);
                    var own = document.querySelector('#fullscreen .content-wrapper .action-bar #table-actions');
                    if (own) {
                        var lift = function () {
                            Array.prototype.slice.call(own.children).forEach(function (el) {
                                // (d-none + d-lg-block = shown on desktop: keep it)
                                var hidden = el.classList.contains('d-none') && !el.classList.contains('d-lg-block');
                                if (el.matches('a, button, .dt-buttons') && !hidden) {
                                    el.classList.remove('mr-3', 'mb-2', 'mb-lg-0', 'd-none', 'd-lg-block', 'float-left');
                                    tabActions.insertBefore(el, btn);
                                }
                            });
                        };
                        lift();
                        new MutationObserver(lift).observe(own, { childList: true });
                    }
                } else if (actionBar) {
                    var slot = document.createElement('div');
                    slot.className = 'hr-top-filter-slot';
                    var ownBtns = actionBar.querySelector('#table-actions');
                    if (ownBtns) {
                        Array.prototype.slice.call(ownBtns.children).forEach(function (el) {
                            if (el.matches('a, button')) {
                                el.classList.remove('mr-3', 'mb-2', 'mb-lg-0', 'float-left', 'd-none', 'd-lg-block');
                                slot.appendChild(el);
                            }
                        });
                    }
                    slot.appendChild(btn);
                    actionBar.appendChild(slot);
                    // the button row at the very top of the page (above any count cards)
                    var cw = actionBar.closest('.content-wrapper');
                    if (cw && cw.firstElementChild !== actionBar) { cw.insertBefore(actionBar, cw.firstChild); actionBar.classList.add('mb-3'); }
                    actionBar.parentNode.insertBefore(card, actionBar.nextSibling);
                    card.classList.add('hr-card-moved');
                } else {
                    var wrap = document.createElement('div');
                    wrap.className = 'hr-top-filter-slot px-4 pt-3';
                    wrap.appendChild(btn);
                    card.parentNode.insertBefore(wrap, card);
                }
            });
        });
    </script>
@endif
