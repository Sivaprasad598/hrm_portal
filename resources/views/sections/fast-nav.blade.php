{{-- Faster, smoother moves between pages, with no change to the pages themselves.

     1. Instant pages (Chrome / Edge): a menu or tab link held under the pointer for a
        moment is prepared in the background (Speculation Rules "prerender"), so the
        click shows it at once. Only plain page links: never logout, deletes,
        downloads, exports, pop-up (right modal) links, or pages that change data
        when opened.
     2. Smooth change: the sidebar, the icon strip and the top bar stay where they
        are while the page content fades across (cross-document View Transitions).
     3. Feedback: a thin bar runs along the top the moment a link is clicked.

     Browsers without these features load pages exactly as before. --}}
<script type="speculationrules">
{
    "prerender": [{
        "source": "document",
        "where": {
            "and": [
                { "href_matches": "/*" },
                { "not": { "selector_matches": "aside a, #epic-strip a, .page-tabs > a, #settingsMenu a, [target], [download], [data-method], [data-toggle], .openRightModal, .no-prerender, [href^='javascript'], [href^='#'], [href*='logout'], [href*='login'], [href*='delete'], [href*='destroy'], [href*='download'], [href*='export'], [href*='pdf'], [href*='print'], [href*='/legacy/'], [href*='probation'], [href*='payroll-run'], [href*='data-clean'], [href*='notifications'], [href*='mark'], [href*='clock'], [href*='/api/'], [href*='impersonate'], [href*='generate'], [href*='apply'], [href*='approve'], [href*='reject']" } }
            ]
        },
        "eagerness": "moderate"
    }]
}
</script>

<style>
    /* 2. the page content fades across; the frame of the app stays put */
    @view-transition { navigation: auto; }
    @media (min-width: 992px) {
        /* the white menu itself: <aside> around it has no height (the menu is position: fixed),
           so a picture of <aside> came out empty and the menu blanked out on every click */
        #mobile_menu_collapse { view-transition-name: app-sidebar; }
        #header { view-transition-name: app-topbar; }
        #epic-strip { view-transition-name: app-strip; }
    }
    ::view-transition-old(root), ::view-transition-new(root) { animation-duration: .16s; }
    /* the frame does not fade: by default the browser cross-fades a picture of the old sidebar
       into the new one, so the menu looked as if it reloaded on every click. The new one shows at once. */
    ::view-transition-group(app-sidebar), ::view-transition-group(app-topbar), ::view-transition-group(app-strip),
    ::view-transition-new(app-sidebar), ::view-transition-new(app-topbar), ::view-transition-new(app-strip) { animation: none; }
    ::view-transition-old(app-sidebar), ::view-transition-old(app-topbar), ::view-transition-old(app-strip) { display: none; }
    @media (prefers-reduced-motion: reduce) {
        ::view-transition-group(*), ::view-transition-old(*), ::view-transition-new(*) { animation: none !important; }
    }

    /* 3. the bar along the top while the next page loads */
    .nav-progress { position: fixed; top: 0; left: 0; height: 3px; width: 0; z-index: 100000; pointer-events: none;
        background: var(--epic-blue, #cb6b4f); box-shadow: 0 0 8px var(--epic-blue, #cb6b4f); opacity: 0; }
    body.nav-loading .nav-progress { opacity: 1; animation: nav-progress 8s cubic-bezier(.1, .7, .1, 1) forwards; }
    @keyframes nav-progress { 0% { width: 0; } 20% { width: 45%; } 60% { width: 80%; } 100% { width: 95%; } }
</style>

<script>
    (function () {
        function start(e) {
            var a = e.target.closest && e.target.closest('a[href]');

            if (!a || e.defaultPrevented || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) { return; }

            var href = a.getAttribute('href') || '';

            if (!href || href.charAt(0) === '#' || /^javascript:/i.test(href) || a.target || a.hasAttribute('download')
                || a.hasAttribute('data-toggle') || a.classList.contains('openRightModal')) { return; }

            var url;
            try { url = new URL(a.href, location.href); } catch (err) { return; }
            if (url.origin !== location.origin || (url.pathname === location.pathname && url.search === location.search && url.hash)) { return; }

            document.body.classList.add('nav-loading');
        }

        // after the page's own handlers (a link they take over calls preventDefault)
        document.addEventListener('click', function (e) { setTimeout(function () { start(e); }, 0); });

        // back from the browser cache (Back button): no bar left running
        window.addEventListener('pageshow', function () { document.body && document.body.classList.remove('nav-loading'); });
    })();
</script>
