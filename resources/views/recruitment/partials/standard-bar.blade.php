{{-- Recruitment pages, the app's page standard:
     the page's own buttons (Add Position / Add Candidate) and "Filters" at the top right;
     the filter card ($form, a CSS selector) hidden until "Filters" opens it (open on load
     when a filter is in use, $active); no Filter / Clear buttons - a change applies at once
     (typing in a search box applies after a short pause or on Enter). --}}
<style>
    .rc-std-bar .btn-group > .btn { height: 38px; display: inline-flex; align-items: center; }
    /* the filter fields share the whole card */
    .rc-std-form > .row { flex-wrap: nowrap; }
    .rc-std-form > .row > [class*="col-"] { flex: 1 1 0 !important; max-width: none !important; min-width: 0; }
    .rc-std-form > .row > [class*="col-"]:has(input[type="text"], input[type="search"]) { flex: 2 1 0 !important; }
    .rc-std-form .bootstrap-select { width: 100% !important; }
    .rc-std-form > .row > .rc-col-wide { flex: 1.6 1 0 !important; min-width: 190px; }
    /* filter dropdowns: section titles (Active / Closed), icons in line */
    .rc-std-form .bootstrap-select .dropdown-header { padding: 10px 14px 4px; font-size: 11px; font-weight: 700; letter-spacing: .06em; text-transform: uppercase; color: #98a2b3; }
    .rc-std-form .bootstrap-select .dropdown-divider { margin: 6px 0; }
    .rc-std-form .bootstrap-select .dropdown-menu li a { padding: 8px 14px; font-size: 13.5px; }
    .rc-stage-opt i { width: 14px; text-align: center; opacity: .9; }
    @media (max-width: 991px) { .rc-std-form > .row { flex-wrap: wrap; } .rc-std-form > .row > [class*="col-"] { flex: 1 1 45% !important; } }
    /* Applicants board: it fills the page down to the bottom, so the sideways scrollbar sits at the
       foot of the screen (not under the last card); each stage is its own lane - tinted, bordered,
       its colour on top - and scrolls down by itself when its cards do not fit */
    .rc-board { display: flex !important; gap: 14px; overflow-x: auto !important; overflow-y: hidden !important; align-items: stretch !important;
        height: calc(100vh - 205px); min-height: 380px; max-height: none !important; padding: 0 2px 12px !important; }
    .rc-board .rc-col { --lane: #98a2b3; display: flex; flex-direction: column; flex: 0 0 290px !important; min-width: 290px !important; max-width: 290px;
        height: 100%; padding: 0 !important; background: #f4f5f7; border: 1px solid #e4e7ec; border-top: 3px solid var(--lane); border-radius: 12px; overflow: hidden; }
    .rc-board .rc-col.rc-col--empty { flex: 0 0 230px !important; min-width: 230px !important; max-width: 230px; }
    .rc-board .rc-col-head { position: static; flex: none; display: flex; justify-content: space-between; align-items: center; padding: 12px 14px !important;
        background: #fff; border-bottom: 1px solid #e4e7ec; }
    .rc-board .rc-col-body { flex: 1 1 auto; overflow-y: auto; padding: 12px 10px 4px; }
    .rc-board .rc-col--empty .rc-col-body > .text-center, .rc-board .rc-col-body > .rc-empty { margin-top: 6px; padding: 18px 8px; border: 1px dashed #d0d5dd; border-radius: 10px;
        color: #98a2b3; font-size: 13px; text-align: center; background: rgba(255, 255, 255, .5); }
    .rc-board .rc-card { padding: 12px 14px; border-radius: 10px; margin-bottom: 10px; background: #fff; border: 1px solid #eaecf0; box-shadow: 0 1px 2px rgba(16, 24, 40, .05); }
    .rc-board .rc-card:hover { border-color: color-mix(in srgb, var(--lane) 55%, #fff); box-shadow: 0 4px 12px rgba(16, 24, 40, .08); }
    .rc-board .rc-col.rc-drop { background: color-mix(in srgb, var(--lane) 10%, #fff); border-color: var(--lane); }
    .rc-board .rc-col[data-stage="new"] { --lane: #98a2b3; }
    .rc-board .rc-col[data-stage="shortlisted"] { --lane: #0ba5ec; }
    .rc-board .rc-col[data-stage="interview"] { --lane: #7a5af8; }
    .rc-board .rc-col[data-stage="selected"] { --lane: #cb6b4f; }
    .rc-board .rc-col[data-stage="offered"] { --lane: #f79009; }
    .rc-board .rc-col[data-stage="offer_accepted"] { --lane: #12b76a; }
    .rc-board .rc-col[data-stage="joined"] { --lane: #344054; }
    .rc-board .rc-col[data-stage="rejected"] { --lane: #f04438; }
    .rc-board .rc-col[data-stage="on_hold"] { --lane: #eaaa08; }
    .rc-board .rc-col[data-stage="withdrawn"] { --lane: #667085; }
    .rc-board::-webkit-scrollbar, .rc-board .rc-col-body::-webkit-scrollbar { width: 8px; height: 10px; }
    .rc-board::-webkit-scrollbar-thumb, .rc-board .rc-col-body::-webkit-scrollbar-thumb { background: #d0d5dd; border-radius: 10px; }
    .rc-board::-webkit-scrollbar-track, .rc-board .rc-col-body::-webkit-scrollbar-track { background: transparent; }
    body.dark-theme .rc-board .rc-col { background: #1f2937; border-color: #374151; }
    body.dark-theme .rc-board .rc-col-head { background: #111827; border-color: #374151; }
    .rc-std-bar .rc-pos-tabs { margin: 0 auto 0 0 !important; flex: 1 1 auto; min-width: 0; display: flex; flex-wrap: nowrap !important; overflow-x: auto; gap: 6px; scrollbar-width: thin; }
    .rc-std-bar .rc-pos-tabs > a { flex: none; white-space: nowrap; }
    .rc-std-bar:has(.rc-pos-tabs) { flex-wrap: nowrap; }
    .rc-std-bar { display: flex; justify-content: flex-end; align-items: center; gap: 10px; flex-wrap: wrap; margin-bottom: 16px; }
    body.epic .rc-std-bar > a, body.epic .rc-std-bar > button { height: 38px; border-radius: 8px !important; display: inline-flex; align-items: center;
        padding: 0 14px !important; margin: 0 !important; white-space: nowrap; }
    body:not(.rc-filters-open) .rc-std-form { display: none !important; }
    .rc-std-form { border-radius: 12px !important; box-shadow: 0 1px 3px rgba(16, 24, 40, .06) !important; margin-bottom: 16px !important; }
    html body.epic .rc-filter-btn.is-open { background: color-mix(in srgb, var(--epic-blue, #cb6b4f) 10%, #fff) !important;
        border-color: var(--epic-blue, #cb6b4f) !important; color: var(--epic-blue, #cb6b4f) !important; }
    html body.epic .rc-filter-btn.is-open i, html body.epic .rc-filter-btn.is-open svg { color: var(--epic-blue, #cb6b4f) !important; }
    .rc-filter-btn { position: relative; }
    .rc-filter-btn .rc-dot { display: none; position: absolute; top: 6px; right: 6px; width: 8px; height: 8px; border-radius: 50%;
        background: var(--epic-blue, #cb6b4f); border: 2px solid #fff; }
    .rc-filter-btn.has-filter .rc-dot { display: block; }
</style>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        var wrap = document.querySelector('#fullscreen .content-wrapper');
        var form = wrap && wrap.querySelector(@json($form));
        if (!wrap || !form) { return; }

        // no Filter / Clear buttons: a change applies at once
        form.querySelectorAll('button[type="submit"], a.btn-secondary').forEach(function (b) {
            var col = b.closest('[class*="col-"]');
            (col && col.parentNode === form.querySelector('.row') ? col : b).remove();
        });
        var send = function () { PageSwap.submit(form); };
        form.querySelectorAll('select, input[type="checkbox"]').forEach(function (s) {
            if (!s.getAttribute('onchange')) { s.addEventListener('change', send); }
        });
        form.querySelectorAll('input[type="text"], input[type="search"], input[type="number"]').forEach(function (inp) {
            var t;
            inp.addEventListener('input', function () { clearTimeout(t); t = setTimeout(send, 800); });
            inp.addEventListener('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); send(); } });
        });
        form.querySelectorAll('label').forEach(function (l) {
            if (l.textContent.trim() === 'Search') { l.textContent = @json($searchLabel ?? 'Search in the table'); }   // what is being searched: the table, the board, the list
        });

        // the top bar: the page's own buttons, then Filters
        var bar = document.createElement('div');
        bar.className = 'rc-std-bar';
        Array.prototype.slice.call(wrap.querySelectorAll('a.openRightModal.btn-primary, a.openRightModal.btn-secondary')).forEach(function (a) {
            if (!a.closest('.card, table, .rc-board, form, .rc-std-bar, .rc-list-card, .rc-pos, [data-no-bar]')) {
                a.classList.remove('mb-2', 'ml-1', 'btn-sm');
                bar.appendChild(a);
            }
        });
        // other page controls marked for the bar (e.g. the Board / List switch), first
        Array.prototype.slice.call(wrap.querySelectorAll('[data-rc-bar]')).reverse().forEach(function (el) {
            el.classList.remove('mb-2');
            bar.insertBefore(el, bar.firstChild);
        });
        var btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'btn btn-secondary rounded f-14 rc-filter-btn' + (@json((bool) $active) ? ' has-filter' : '');
        btn.innerHTML = '<i class="fa fa-filter mr-1"></i>Filters<span class="rc-dot"></span>';
        btn.addEventListener('click', function () {
            var open = document.body.classList.toggle('rc-filters-open');
            btn.classList.toggle('is-open', open);
            if (open && window.jQuery) { jQuery(form).find('.select-picker').selectpicker('refresh'); }
        });
        bar.appendChild(btn);

        var chips = wrap.querySelector('.rc-pos-tabs');
        if (chips) {
            chips.classList.remove('mb-2');
            chips.style.marginRight = 'auto';
            bar.insertBefore(chips, bar.firstChild);
        }
        form.classList.add('rc-std-form');
        var tabsRow = form.closest('.rc-toolbar');
        if (tabsRow) {
            // a page with its own tab row (Interviews): Filters on the right of the tabs, the filters under them
            bar.style.marginBottom = '0';
            bar.style.marginLeft = 'auto';
            tabsRow.appendChild(bar);
            tabsRow.parentNode.insertBefore(form, tabsRow.nextSibling);
            form.classList.add('bg-white', 'p-3', 'mt-3', 'd-flex', 'flex-wrap', 'align-items-center');
            form.style.gap = '12px';
            Array.prototype.forEach.call(form.querySelectorAll('select'), function (sel) { sel.style.flex = '1 1 0'; sel.style.minWidth = '200px'; sel.style.height = '38px'; });
        } else {
            wrap.insertBefore(bar, wrap.firstChild);
            bar.parentNode.insertBefore(form, bar.nextSibling);
        }
        if (@json((bool) $active)) { document.body.classList.add('rc-filters-open'); btn.classList.add('is-open'); }
    });
</script>
