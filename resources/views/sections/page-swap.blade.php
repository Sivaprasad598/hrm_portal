{{-- Content swap, part 2 (after js/main.js; part 1 is sections/page-swap-track).

     A click on a sidebar, icon-strip, tab or Settings-menu link fetches only that page's content
     (the server answers "X-Page-Swap: 1" with layouts.swap) and puts it in the main
     container: the sidebar, the top bar and every loaded script and stylesheet stay
     as they are, so there is no blank page in between and the browser does not
     rebuild everything.

     It never guesses. Whatever is not a plain page-to-page move - a pop-up or side
     panel link, a new tab, a download, logout, an answer that is not a swap answer
     (another layout, an error page, the login page), a failed request, a script of
     the new page that fails - is opened as a normal page, exactly as before.
     Back / Forward reload the page as they always did (js/main.js).

     To switch it off for a link: class="no-swap" (or data-no-swap) on it. For a
     page: add its path to NEVER below. For everything: PAGE_SWAP_ENABLED=false in .env. --}}
@if (config('app.page_swap', true))
<script>
    (function ($) {
        if (!window.fetch || !window.DOMParser || !history.pushState || !window.PageSwap) { return; }

        // Links that take part: the app's own navigation.
        var AREAS = 'aside a[href], #epic-strip a[href], .page-tabs > a[href], #settingsMenu a[href]';
        // Pages that are always opened in full: they print markup outside the layout's
        // sections, write to the document as they load, or are not pages of this layout.
        var NEVER = [/\/logout/i, /\/login/i, /\/attendances\/by-request/i, /\/departments-hierarchy/i, /\/designations-hierarchy/i,
/\/(export|download|pdf|print)(\/|$|\?)/i];
        var box = document.getElementById('fullscreen');
        var current = null;       // the swap in flight
        var failed = {};          // paths that did not swap cleanly lately: opened in full for a while
        var loaded = {};          // script files already in the document
        var FAILED_KEY = 'page-swap-failed-2', FAILED_FOR = 15 * 60 * 1000;

        // A mark lasts 15 minutes, not the whole tab: one bad moment (a page that failed while it
        // was being fixed) made that page reload in full for as long as the tab stayed open.
        try {
            var marks = JSON.parse(sessionStorage.getItem(FAILED_KEY) || '{}') || {};
            Object.keys(marks).forEach(function (p) { if (marks[p] && Date.now() - (marks[p].at || 0) < FAILED_FOR) { failed[p] = marks[p]; } });
            sessionStorage.removeItem('page-swap-failed');   // the older, never-ending list
        } catch (e) {}

        function fileOf(src) { try { return new URL(src, location.href).href.split('?')[0]; } catch (e) { return src; } }
        Array.prototype.forEach.call(document.querySelectorAll('script[src]'), function (s) { loaded[fileOf(s.src)] = true; });

        function fullLoad(url) { window.location.href = url; }

        function giveUp(url, why) {
            try {
                failed[new URL(url, location.href).pathname] = { why: why || '', at: Date.now() };
                sessionStorage.setItem(FAILED_KEY, JSON.stringify(failed));
            } catch (e) {}
        }

        function wanted(a, e) {
            if (!a || e.defaultPrevented || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) { return null; }
            if (a.target || a.hasAttribute('download') || a.hasAttribute('data-toggle') || a.hasAttribute('data-no-swap')
                || a.classList.contains('no-swap') || a.classList.contains('openRightModal')) { return null; }

            var href = a.getAttribute('href') || '';
            if (!href || href.charAt(0) === '#' || /^javascript:/i.test(href)) { return null; }

            var url;
            try { url = new URL(a.href, location.href); } catch (err) { return null; }

            if (url.origin !== location.origin || failed[url.pathname]) { return null; }
            if (url.pathname === location.pathname && url.search === location.search) { return null; }   // the page in view: a reload, as before
            if (NEVER.some(function (re) { return re.test(url.pathname + url.search); })) { return null; }
            // not while a pop-up or the side panel is open
            var panel = document.getElementById('task-detail-1');
            if (document.querySelector('.modal.show') || (panel && panel.classList.contains('in'))) { return null; }

            return url;
        }

        // After the page's own handlers (which run on <body>): a link they took over is left to them.
        document.addEventListener('click', function (e) {
            var a = e.target.closest && e.target.closest(AREAS);
            var url = wanted(a, e);

            if (!url) { return; }

            e.preventDefault();
            go(url.href);
        });

        function go(url) {
            if (current) { current.abort(); }

            var request = current = new AbortController();
            document.body.classList.add('nav-loading');

            fetch(url, { headers: { 'X-Page-Swap': '1', 'Accept': 'text/html' }, credentials: 'same-origin', cache: 'no-store', signal: request.signal })
                .then(function (response) {
                    var landed = new URL(response.url, location.href);

                    if (!response.ok || /\/login$/.test(landed.pathname)) { throw new Error('not a page'); }

                    return response.text().then(function (html) { return [html, response.url]; });
                })
                .then(function (answer) {
                    if (request !== current) { return; }

                    var page = new DOMParser().parseFromString(answer[0], 'text/html');
                    var root = page.getElementById('page-swap');

                    if (!root) { throw new Error('not a swap answer'); }

                    show(page, root, answer[1]);
                })
                .catch(function (err) {
                    if (err && err.name === 'AbortError') { return; }
                    fullLoad(url);   // the browser opens it in the ordinary way (and shows whatever it is)
                });
        }

        // (for a script that wants to move to a page the same way: PageSwap.go(url))
        PageSwap.go = go;
        PageSwap.wanted = function (url) { try { var u = new URL(url, location.href); return !failed[u.pathname] && !NEVER.some(function (re) { return re.test(u.pathname + u.search); }); } catch (e) { return false; } };

        // ---- putting the new page in ------------------------------------------------

        function between(startName, endName) {
            var start = document.querySelector('meta[name="' + startName + '"]'), end = document.querySelector('meta[name="' + endName + '"]');
            var nodes = [];
            if (!start || !end) { return null; }
            for (var n = start.nextSibling; n && n !== end; n = n.nextSibling) { nodes.push(n); }
            return { nodes: nodes, end: end };
        }

        function sameMarkup(nodes, incoming) {
            var a = nodes.filter(function (n) { return n.nodeType === 1; }).map(function (n) { return n.outerHTML; }).join('');
            var b = Array.prototype.map.call(incoming.children, function (n) { return n.outerHTML; }).join('');
            return a === b;
        }

        /** The page's own styles in the head; resolves when new stylesheets have loaded (no unstyled flash). */
        function styles(markerStart, markerEnd, incoming) {
            var place = between(markerStart, markerEnd);

            if (!place || !incoming || sameMarkup(place.nodes, incoming)) { return Promise.resolve(null); }

            var waits = [];
            var fresh = Array.prototype.map.call(incoming.children, function (n) {
                var copy = document.importNode(n, true);

                if (copy.tagName === 'LINK' && copy.rel === 'stylesheet') {
                    waits.push(new Promise(function (done) { copy.onload = copy.onerror = done; setTimeout(done, 3000); }));
                }

                return copy;
            });

            fresh.forEach(function (n) { place.end.parentNode.insertBefore(n, place.end); });

            // the old ones go once the new page is in (returned to the caller)
            return Promise.all(waits).then(function () { return place.nodes; });
        }

        /** Same shape: carry over classes, styles and text. Different shape: take the new markup. */
        function sync(from, to, childrenOnly) {
            if (!from || !to) { return; }

            if (!childrenOnly) {
                ['class', 'style', 'aria-expanded', 'aria-current', 'data-server-active', 'href', 'title'].forEach(function (name) {
                    var value = from.getAttribute(name);
                    if (value === null) { if (to.hasAttribute(name)) { to.removeAttribute(name); } }
                    else if (to.getAttribute(name) !== value) { to.setAttribute(name, value); }
                });
            }

            if (to.id === 'epic-strip-sections') { return; }   // built in the browser from the menu (epic-strip)

            var a = from.children, b = to.children, same = a.length === b.length;

            for (var i = 0; same && i < a.length; i++) { same = a[i].tagName === b[i].tagName; }

            if (!same) {
                to.innerHTML = from.innerHTML;
                // the sidebar's top-level headings are wired one by one in main.js
                if (typeof toggleItem === 'function') {
                    Array.prototype.forEach.call(to.querySelectorAll('.accordionItemHeading'), function (h) { h.addEventListener('click', toggleItem, false); });
                }
                return;
            }

            if (!a.length) {
                if (to.textContent !== from.textContent) { to.textContent = from.textContent; }
                return;
            }

            for (var j = 0; j < a.length; j++) {
                if (a[j].tagName !== 'SCRIPT' && a[j].tagName !== 'STYLE') { sync(a[j], b[j]); }
            }
        }

        /**
         * The sidebar stays exactly where it is (nodes, scroll position, open sub-menus):
         * only which entries are open and lit, and the "waiting for you" counts, are
         * brought in line with what the server worked out for the new page. Only if the
         * entries themselves differ (the person's menu changed) is the menu redrawn.
         */
        function menuState(from, to) {
            var links = function (box) { return Array.prototype.slice.call(box.querySelectorAll('a')); };
            var key = function (a) { return (a.getAttribute('href') || '') + '|' + (a.getAttribute('title') || '') + '|' + a.className.indexOf('sidebar-submenu-toggle'); };
            var incoming = links(from), current = links(to);

            if (incoming.length !== current.length || incoming.some(function (a, i) { return key(a) !== key(current[i]); })) {
                to.innerHTML = from.innerHTML;   // a different menu: drawn afresh
                if (typeof toggleItem === 'function') {
                    Array.prototype.forEach.call(to.querySelectorAll('.accordionItemHeading'), function (h) { h.addEventListener('click', toggleItem, false); });
                }
                return;
            }

            // the same entries, in the same order: classes and counts only
            incoming.forEach(function (a, i) {
                var b = current[i];
                ['active', 'is-open'].forEach(function (c) { b.classList.toggle(c, a.classList.contains(c)); });
                if (a.hasAttribute('data-server-active')) { b.setAttribute('data-server-active', '1'); } else { b.removeAttribute('data-server-active'); }
                if (a.hasAttribute('aria-expanded')) { b.setAttribute('aria-expanded', a.getAttribute('aria-expanded')); }

                var count = a.querySelector('.epic-menu-count'), countHere = b.querySelector('.epic-menu-count');
                if (count && countHere) { countHere.textContent = count.textContent; }
                else if (count) { b.appendChild(count.cloneNode(true)); }
                else if (countHere) { countHere.remove(); }
            });

            var groups = from.querySelectorAll('li.accordionItem'), groupsHere = to.querySelectorAll('li.accordionItem');
            Array.prototype.forEach.call(groups, function (li, i) {
                if (groupsHere[i]) { groupsHere[i].className = li.className; }   // "accordionItem openIt|closeIt"
            });

            var subs = from.querySelectorAll('.sidebar-submenu-children'), subsHere = to.querySelectorAll('.sidebar-submenu-children');
            Array.prototype.forEach.call(subs, function (box, i) {
                if (subsHere[i]) { subsHere[i].style.display = box.style.display; }
            });
        }

        function menuHighlights(root) {
            var menu = root.querySelector('#ps-menu'), here = document.getElementById('sideMenuScroll');
            if (menu && here) { menuState(menu, here); }

            var strip = root.querySelector('#ps-strip #epic-strip'), stripHere = document.getElementById('epic-strip');
            if (strip && stripHere) { sync(strip, stripHere); }

            // what js/main.js adds on a full load: the link of this address, and its group, lit up
            var parts = location.pathname.split('account/');
            if (typeof parts[1] !== 'undefined') {
                var route = parts[1].split('/')[0];
                $('#sideMenuScroll li a').filter(function () { return this.href == location.href; })
                    .addClass('active').closest('li').removeClass('closeIt').addClass('openIt');
                var group = $('#sideMenuScroll li a').filter(function () { return route == this.href.split('account/')[1]; });
                group.addClass('active');
                group.closest('li').removeClass('closeIt').addClass('openIt').children('a').addClass('active');
            }

            // ...and of that, only the page in view and its group stay (epic-ui, as after a full load)
            if (typeof window.epicMenuTrustServer === 'function') { window.epicMenuTrustServer(); }

            // the strip's section icons: lit for the open section (epic-ui)
            if (typeof window.epicStripSections === 'function') { window.epicStripSections(); }

            // the page's own entry in view, if the menu is long (without moving the page)
            var lit = document.querySelector('#sideMenuScroll a[data-server-active]');
            if (lit && here) {
                var top = lit.getBoundingClientRect().top, box = here.getBoundingClientRect();
                if (top < box.top || top > box.bottom - 40) { here.scrollTop += top - box.top - box.height / 3; }
            }
        }

        /** Run the page's scripts in order: files it brings (once each), then its inline code. */
        function run(scripts, done) {
            var i = 0, broke = false, lastError = '';
            // A script that throws is reported and the rest still run - as on a full page
            // load, where each script stands on its own. Only a script file that will not
            // load at all makes the page open in full instead.
            var onError = function (e) { if (e instanceof ErrorEvent) { lastError = e.message; } };

            window.addEventListener('error', onError, true);

            (function next() {
                if (broke || i >= scripts.length) {
                    window.removeEventListener('error', onError, true);
                    return done(!broke, lastError);
                }

                var old = scripts[i++], type = (old.getAttribute('type') || '').toLowerCase();

                if (type && type !== 'text/javascript' && type !== 'application/javascript' && type !== 'module') { return next(); }

                var s = document.createElement('script');
                Array.prototype.forEach.call(old.attributes, function (at) { if (at.name !== 'defer' && at.name !== 'async') { s.setAttribute(at.name, at.value); } });

                if (old.src) {
                    var file = fileOf(old.getAttribute('src'));
                    if (loaded[file]) { return next(); }
                    loaded[file] = true;
                    s.onload = next;
                    s.onerror = function () { broke = true; next(); };
                    s.async = false;
                    document.body.appendChild(s);
                } else {
                    s.text = old.text;
                    try { document.body.appendChild(s); } catch (e) { lastError = String(e); }
                    s.parentNode && s.parentNode.removeChild(s);
                    next();
                }
            })();
        }

        function show(page, root, url) {
            var incoming = root.querySelector('#ps-content');
            var scriptBox = root.querySelector('#ps-scripts');

            if (!incoming || !box) { return fullLoad(url); }

            // the page's scripts, in the order a full page runs them: content first, then the stack
            var scripts = Array.prototype.slice.call(incoming.querySelectorAll('script'))
                .concat(scriptBox ? Array.prototype.slice.call(scriptBox.querySelectorAll('script')) : []);

            if (scripts.some(function (s) { return /document\.write/.test(s.text || ''); })) { giveUp(url, 'writes'); return fullLoad(url); }

            scripts.forEach(function (s) { s.parentNode.removeChild(s); });

            Promise.all([
                styles('page-styles-early', 'page-styles-early-end', root.querySelector('#ps-styles-early')),
                styles('page-styles', 'page-styles-end', root.querySelector('#ps-styles'))
            ]).then(function (oldStyles) {
                var swapIn = function () {
                    // 1. the page being left: its handlers, timers and stray widgets
                    PageSwap.leave();
                    $('.tooltip, .popover, body > .daterangepicker, body > .bootstrap-select, body > .qs-datepicker-container').remove();
                    if ($.fn.dataTable && $.fn.dataTable.settings) {
                        var kept = $.fn.dataTable.settings.filter(function (s) { return !box.contains(s.nTable); });
                        $.fn.dataTable.settings.length = 0;
                        Array.prototype.push.apply($.fn.dataTable.settings, kept);
                    }

                    var toggled = document.body.classList.contains('sidebar-toggled');
                    document.body.className = PageSwap.bodyClass;
                    document.body.classList.toggle('sidebar-toggled', toggled);

                    // 2. the address, the title, the content
                    history.pushState({ pageSwap: true }, '', url);
                    document.title = page.title;

                    var title = root.querySelector('#ps-title .page-title'), titleHere = document.querySelector('#header .page-title');
                    if (title && titleHere) { titleHere.parentNode.replaceChild(document.importNode(title, true), titleHere); }

                    $(box).children().not('.preloader-container').remove();   // jQuery: with their data and handlers
                    Array.prototype.slice.call(incoming.childNodes).forEach(function (n) { box.appendChild(document.importNode(n, true)); });
                    oldStyles.forEach(function (nodes) { (nodes || []).forEach(function (n) { n.parentNode && n.parentNode.removeChild(n); }); });

                    menuHighlights(root);
                    window.scrollTo(0, 0);
                    box.scrollTop = 0;
                };

                var after = function () {
                    // 3. the page's scripts, then what a full load does once they have run
                    PageSwap.state.swapping = true;
                    PageSwap.scope(true);

                    run(scripts, function (clean, why) {
                        try { PageSwap.ready(); } catch (e) { if (window.console) { console.error(e); } }

                        PageSwap.scope(false);
                        PageSwap.state.swapping = false;

                        if (!clean) {
                            // a script of this page does not run as a swap: open it in full, and from now on always
                            giveUp(url, 'script: ' + (why || ''));
                            return window.location.reload();
                        }

                        // after the page's own ready callbacks (queued by jQuery), like the layout's
                        setTimeout(function () {
                            try { init('#fullscreen'); } catch (e) {}
                            $(document).trigger('ajaxPageLoad');

                            // close buttons main.js wires by id when a page has them
                            [['close-admin-overlay', 'closeAdminDashboard'], ['close-admin', 'closeAdminDashboard'], ['close-settings', 'closeSettingsSidebar'],
                             ['close-settings-overlay', 'closeSettingsSidebar'], ['close-tickets', 'closeTicketsSidebar'], ['close-tickets-overlay', 'closeTicketsSidebar'],
                             ['close-client-overlay', 'closeClientDetail'], ['close-client-detail', 'closeClientDetail'], ['close-project-overlay', 'closeProjectSidebar'],
                             ['close-projects', 'closeProjectSidebar']].forEach(function (pair) {
                                var el = document.getElementById(pair[0]);
                                if (el && box.contains(el) && typeof window[pair[1]] === 'function') { el.addEventListener('click', window[pair[1]]); }
                            });

                            document.body.classList.remove('nav-loading');
                            current = null;
                        }, 0);
                    });
                };

                // the content fades across where the browser can do it; the frame of the app does not move
                if (document.startViewTransition && !window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
                    document.startViewTransition(swapIn).updateCallbackDone.then(after, function () { window.location.href = url; });
                } else {
                    swapIn();
                    after();
                }
            });
        }
    })(jQuery);
</script>
@endif
