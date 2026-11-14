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
    @media (max-width: 991px) { .rc-std-form > .row { flex-wrap: wrap; } .rc-std-form > .row > [class*="col-"] { flex: 1 1 45% !important; } }
    /* Applicants board: wide columns, scrolling both ways inside a fixed-height area */
    .rc-board { overflow: auto !important; max-height: calc(100vh - 230px); padding: 0 4px 10px 0 !important; align-items: flex-start !important; }
    .rc-board .rc-col { flex: 0 0 290px !important; min-width: 290px !important; max-width: 290px; }
    .rc-board .rc-col.rc-col--empty { flex: 0 0 220px !important; min-width: 220px !important; }
    .rc-board .rc-col-head { position: sticky; top: 0; z-index: 2; background: #f4f6f9; }
    .rc-board .rc-card { padding: 12px 14px; border-radius: 10px; margin-bottom: 10px; }
    .rc-board::-webkit-scrollbar { width: 10px; height: 10px; }
    .rc-board::-webkit-scrollbar-thumb { background: #d0d5dd; border-radius: 10px; }
    .rc-board::-webkit-scrollbar-track { background: transparent; }
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
        var send = function () { form.submit(); };
        form.querySelectorAll('select, input[type="checkbox"]').forEach(function (s) {
            if (!s.getAttribute('onchange')) { s.addEventListener('change', send); }
        });
        form.querySelectorAll('input[type="text"], input[type="search"], input[type="number"]').forEach(function (inp) {
            var t;
            inp.addEventListener('input', function () { clearTimeout(t); t = setTimeout(send, 800); });
            inp.addEventListener('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); send(); } });
        });
        form.querySelectorAll('label').forEach(function (l) {
            if (l.textContent.trim() === 'Search') { l.textContent = 'Search in the table'; }
        });

        // the top bar: the page's own buttons, then Filters
        var bar = document.createElement('div');
        bar.className = 'rc-std-bar';
        Array.prototype.slice.call(wrap.querySelectorAll('a.openRightModal.btn-primary, a.openRightModal.btn-secondary')).forEach(function (a) {
            if (!a.closest('.card, table, .rc-board, form, .rc-std-bar')) {
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
