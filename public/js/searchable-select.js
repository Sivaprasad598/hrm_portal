/*
 * makeSearchable(select, placeholder): a plain <select> as a searchable dropdown
 * (for the legacy pages, which have no bootstrap-select). The <select> stays the
 * source of truth: its value, its change event and form submit work as before.
 * Department options may carry data-depth / data-name / data-division: they are
 * drawn as a tree (division bold, sub divisions indented with their division).
 */
(function () {
    var css = ''
        + '.ss{position:relative;width:100%}'
        + '.ss__btn{width:100%;height:38px;padding:0 32px 0 12px;border:1px solid #e8e9e9;border-radius:8px;background:#fff;text-align:left !important;font:inherit;font-size:14px;color:#101828;cursor:pointer;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;position:relative}'
        + '.ss__btn:after{content:"";position:absolute;right:12px;top:50%;margin-top:-2px;border:5px solid transparent;border-top-color:#667085}'
        + '.ss__btn.is-empty{color:#98a2b3}'
        + '.ss.is-open .ss__btn{border-color:var(--brand,#cb6b4f);box-shadow:0 0 0 3px rgba(203,107,79,.15)}'
        + '.ss__panel{display:none;position:absolute;z-index:60;top:calc(100% + 4px);left:0;width:100%;min-width:260px;background:#fff;border:1px solid #eaecf0;border-radius:10px;box-shadow:0 12px 28px rgba(16,24,40,.14);padding:8px}'
        + '.ss.is-open .ss__panel{display:block}'
        + '.ss__search{width:100%;height:34px;padding:0 10px;border:1px solid #e8e9e9;border-radius:6px;font:inherit;font-size:13px;margin-bottom:6px;box-sizing:border-box}'
        + '.ss__search:focus{outline:0;border-color:var(--brand,#cb6b4f)}'
        + '.ss__list{max-height:260px;overflow-y:auto;overflow-x:hidden}'
        + '.ss__opt{padding:8px 10px;border-radius:6px;font-size:13.5px;cursor:pointer;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;color:#344054}'
        + '.ss__opt:hover{background:#fdf2ee}'
        + '.ss__opt.is-selected{color:var(--brand,#cb6b4f);font-weight:600}'
        + '.ss__top{font-weight:600;color:#101828}'
        + '.ss__of{color:#98a2b3;font-size:11.5px;margin-left:4px}'
        + '.ss__none{padding:8px 10px;font-size:13px;color:#98a2b3}';
    var style = document.createElement('style');
    style.textContent = css;
    document.head.appendChild(style);

    function esc(t) { var d = document.createElement('div'); d.textContent = t == null ? '' : String(t); return d.innerHTML; }

    window.makeSearchable = function (select, placeholder) {
        if (!select || select.dataset.ss) { return; }
        select.dataset.ss = '1';
        var box = document.createElement('div');
        box.className = 'ss';
        box.innerHTML = '<button type="button" class="ss__btn"></button>'
            + '<div class="ss__panel"><input type="text" class="ss__search" placeholder="Search…" autocomplete="off">'
            + '<div class="ss__list"></div></div>';
        select.style.display = 'none';
        select.parentNode.insertBefore(box, select);
        var btn = box.querySelector('.ss__btn'), search = box.querySelector('.ss__search'), list = box.querySelector('.ss__list');

        function label() {
            var o = select.options[select.selectedIndex];
            var empty = !o || o.value === '';
            btn.textContent = o ? (o.dataset.name || o.textContent.trim()) : (placeholder || '');
            btn.title = o ? o.textContent.trim() : '';
            btn.classList.toggle('is-empty', empty && !o);
        }
        function draw() {
            var q = search.value.trim().toLowerCase();
            var rows = Array.prototype.filter.call(select.options, function (o) {
                return !q || o.textContent.toLowerCase().indexOf(q) !== -1;
            });
            list.innerHTML = rows.length ? rows.map(function (o) {
                var depth = o.dataset.depth;
                var pad = depth !== undefined ? ' style="padding-left:' + (10 + 14 * Number(depth)) + 'px"' : '';
                var html = depth === undefined ? esc(o.textContent.trim())
                    : (Number(depth) === 0 ? '<span class="ss__top">' + esc(o.dataset.name) + '</span>'
                        : esc(o.dataset.name) + ' <small class="ss__of">' + esc(o.dataset.division) + '</small>');
                return '<div class="ss__opt' + (o.value === select.value ? ' is-selected' : '') + '" data-v="' + esc(o.value) + '"' + pad + '>' + html + '</div>';
            }).join('') : '<div class="ss__none">No match</div>';
        }
        btn.addEventListener('click', function () {
            var open = !box.classList.contains('is-open');
            document.querySelectorAll('.ss.is-open').forEach(function (x) { x.classList.remove('is-open'); });
            if (open) { box.classList.add('is-open'); search.value = ''; draw(); search.focus(); }
        });
        search.addEventListener('input', draw);
        search.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') { var f = list.querySelector('.ss__opt'); if (f) { f.click(); } e.preventDefault(); }
            if (e.key === 'Escape') { box.classList.remove('is-open'); }
        });
        list.addEventListener('click', function (e) {
            var opt = e.target.closest('.ss__opt');
            if (!opt) { return; }
            select.value = opt.dataset.v;
            box.classList.remove('is-open');
            label();
            select.dispatchEvent(new Event('change', { bubbles: true }));
            if (select.getAttribute('onchange') && select.form && /submit/.test(select.getAttribute('onchange'))) { /* the inline handler ran via the event */ }
        });
        document.addEventListener('click', function (e) { if (!box.contains(e.target)) { box.classList.remove('is-open'); } });
        select.addEventListener('change', label);
        label();
    };
})();
