{{-- Content swap, part 1 (right after jQuery in the head; part 2 is sections/page-swap).

     A page's scripts attach things that outlive its content: click handlers on
     <body> or document, on the pop-ups, timers. On a full page load the browser
     throws all of it away with the page. When only the content is swapped they
     would stay - and the next visit to the page would attach them again, so one
     click would act twice. This keeps a note of what the page in view attached
     outside its content, so it can be taken off when the page is left.

     What counts as the page's: anything attached while a page script is running
     (PageSwap.scope(true) ... (false) around them in layouts.app), in a jQuery
     ready callback registered meanwhile, or at any time after the first load has
     finished. What the layout and js/main.js attach while the page first loads is
     the application's own and is never touched - and neither is anything a script
     FILE attaches while it loads (a library a page brings along, e.g. DataTables:
     it is loaded once and has to keep working on every later page). --}}
<script>
    window.PageSwap = (function ($) {
        var S = {
            inScope: false,     // a page script (or one of its ready callbacks) is running
            settled: false,     // the first load is over: whatever is attached from now on is the page's
            swapping: false,    // a swapped page's scripts are being run
            handlers: [],       // [element, types, selector, fn] attached through jQuery
            listeners: [],      // [target, type, fn, options] attached with addEventListener
            timers: [],         // setInterval ids
            onReady: [],        // DOMContentLoaded / window load callbacks asked for during a swap
            inJquery: 0,
            paused: 0,          // a library's own start-up is running
            bodyClass: ''
        };

        // a script file (not a page's inline code) is running right now
        function library() { var s = document.currentScript; return !!(s && s.src); }

        function tracking() { return !S.paused && !library() && (S.inScope || S.settled); }

        // inside the main container = goes with the content; no need to remember it
        function outlives(el) {
            if (el === window || el === document) { return true; }
            if (!el || el.nodeType !== 1) { return false; }
            var box = document.getElementById('fullscreen');
            return !(box && box !== el && box.contains(el));
        }

        function isLoadType(types, name) {
            return typeof types === 'string' && types.split(/\s+/).some(function (t) { return t.split('.')[0] === name; });
        }

        function note(set, args) {
            var types = args[0], selector = typeof args[1] === 'string' ? args[1] : undefined, fn = null, i;

            if (types && typeof types === 'object') {   // .on({ click: fn, keyup: fn }, selector)
                for (var key in types) { if (typeof types[key] === 'function') { each(key, types[key]); } }
                return;
            }

            for (i = args.length - 1; i > 0; i--) { if (typeof args[i] === 'function') { fn = args[i]; break; } }
            if (fn) { each(types, fn); }

            function each(t, f) {
                set.each(function () { if (outlives(this)) { S.handlers.push([this, t, selector, f]); } });
            }
        }

        ['on', 'one'].forEach(function (method) {
            var original = $.fn[method];

            $.fn[method] = function () {
                var args = arguments;

                // The window has already loaded when a swapped page asks to be told so:
                // it is told once its scripts have run (PageSwap.ready()).
                if (S.swapping && this.length === 1 && this[0] === window && isLoadType(args[0], 'load')) {
                    for (var i = args.length - 1; i > 0; i--) {
                        if (typeof args[i] === 'function') { S.onReady.push([1, args[i], window]); break; }
                    }
                    return this;
                }

                S.inJquery++;
                try { var result = original.apply(this, args); } finally { S.inJquery--; }

                if (tracking()) { try { note(this, args); } catch (e) {} }

                return result;
            };
        });

        // $(function () { ... }) registered by a page script runs later: still the page's
        var ready = $.fn.ready;
        $.fn.ready = function (fn) {
            if (typeof fn === 'function' && library()) {
                // a library's ready callback is still the library starting up
                var start = fn;
                fn = function () {
                    S.paused++;
                    try { return start.apply(this, arguments); } finally { S.paused--; }
                };
            } else if (typeof fn === 'function' && S.inScope) {
                var original = fn;
                fn = function () {
                    var was = S.inScope;
                    S.inScope = true;
                    try { return original.apply(this, arguments); } finally { S.inScope = was; }
                };
            }
            return ready.call(this, fn);
        };

        // addEventListener on the window / document / body (not jQuery's own, which it manages itself)
        var add = EventTarget.prototype.addEventListener;
        EventTarget.prototype.addEventListener = function (type, fn, options) {
            var lasting = this === window || this === document || this === document.body || this === document.documentElement;

            if (lasting && !S.inJquery && fn) {
                // (also for a library loaded during a swap: the event will not come again)
                if (S.swapping && ((this === document && type === 'DOMContentLoaded') || (this === window && type === 'load'))) {
                    S.onReady.push([type === 'load' ? 1 : 0, fn, this]);   // already happened: told after the scripts
                    return;
                }
                if (tracking()) { S.listeners.push([this, type, fn, options]); }
            }

            return add.call(this, type, fn, options);
        };

        var every = window.setInterval;
        window.setInterval = function () {
            var id = every.apply(window, arguments);
            if (tracking()) { S.timers.push(id); }
            return id;
        };

        // the first load is over a moment after the window has loaded
        add.call(window, 'load', function () { setTimeout(function () { S.settled = true; }, 0); });

        return {
            state: S,
            bodyClass: '',
            scope: function (on) { S.inScope = !!on; },

            /** Take off everything the page in view attached outside its content. */
            leave: function () {
                var off = $.fn.off;
                S.handlers.forEach(function (h) { try { off.call($(h[0]), h[1], h[2], h[3]); } catch (e) {} });
                S.listeners.forEach(function (l) { try { l[0].removeEventListener(l[1], l[2], l[3]); } catch (e) {} });
                S.timers.forEach(function (id) { clearInterval(id); });
                S.handlers = []; S.listeners = []; S.timers = []; S.onReady = [];
            },

            /** After a swapped page's scripts: the "page is ready" callbacks they asked for, in order. */
            ready: function () {
                var list = S.onReady.sort(function (a, b) { return a[0] - b[0]; });
                S.onReady = [];
                list.forEach(function (r) { r[1].call(r[2], new Event(r[0] ? 'load' : 'DOMContentLoaded')); });
            }
        };
    })(jQuery);
</script>
