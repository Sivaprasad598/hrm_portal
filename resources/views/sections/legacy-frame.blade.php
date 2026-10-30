{{-- The legacy pages (served from /legacy/{page}) are shown in an iframe. Kept at a
     fixed height, each scrolled inside its own box while the page around it did
     not move. Here the frame grows to its content instead, so the whole page
     scrolls as one, and follows the content as it changes (tabs, filters).

     Inside the frame the pages' own "min-height: 100vh" on body is lifted: the
     frame's height would feed back into the content's and grow without end. --}}
<script>
    (function () {
        var LEGACY = /\/legacy\/|\/payslips\/legacy/;

        function fit(frame) {
            try {
                var doc = frame.contentDocument;

                if (!doc || !doc.body) {
                    return;
                }

                var style = doc.defaultView.getComputedStyle(doc.body);
                var height = Math.ceil(doc.body.scrollHeight + parseFloat(style.marginTop || 0) + parseFloat(style.marginBottom || 0));

                if (height > 0 && Math.abs(height - frame.offsetHeight) > 1) {
                    frame.style.height = height + 'px';
                }
            } catch (e) {
                // another origin: leave the frame as it is
            }
        }

        function prepare(frame) {
            try {
                var doc = frame.contentDocument;

                if (!doc || !doc.body || doc.getElementById('legacy-frame-fit')) {
                    return;
                }

                var css = doc.createElement('style');
                css.id = 'legacy-frame-fit';
                css.textContent = 'html, body { min-height: 0 !important; height: auto !important; } html { overflow: hidden !important; }';
                doc.head.appendChild(css);

                frame.style.minHeight = '0';
                frame.setAttribute('scrolling', 'no');

                if (window.ResizeObserver) {
                    new doc.defaultView.ResizeObserver(function () { fit(frame); }).observe(doc.body);
                }

                // A pop-up (.modal-overlay) covers the whole frame, now taller than
                // the window, and centred in it the card could sit off screen. It is
                // placed where the frame is in view instead.
                new doc.defaultView.MutationObserver(function () {
                    doc.querySelectorAll('.modal-overlay.show').forEach(function (overlay) {
                        var top = Math.max(0, -frame.getBoundingClientRect().top) + 40;
                        overlay.style.alignItems = 'flex-start';
                        overlay.style.paddingTop = top + 'px';
                        overlay.querySelectorAll('.card').forEach(function (card) {
                            card.style.maxHeight = (window.innerHeight - 80) + 'px';
                        });
                    });
                }).observe(doc.body, { subtree: true, attributes: true, attributeFilter: ['class'] });

                fit(frame);
            } catch (e) {
            }
        }

        function hook(frame) {
            if (frame.dataset.legacyFit || !LEGACY.test(frame.getAttribute('src') || frame.getAttribute('data-src') || '')) {
                return;
            }

            frame.dataset.legacyFit = '1';
            // Every load: the pages link and post to themselves.
            frame.addEventListener('load', function () { prepare(frame); });
            prepare(frame);
        }

        function scan() {
            document.querySelectorAll('#fullscreen iframe').forEach(hook);
        }

        var queued = false;

        function later() {
            if (!queued) {
                queued = true;
                requestAnimationFrame(function () { queued = false; scan(); });
            }
        }

        document.addEventListener('DOMContentLoaded', function () {
            scan();
            // Frames added or given their address later (tabs that load on first view).
            var main = document.getElementById('fullscreen');

            if (main) {
                new MutationObserver(later).observe(main, { subtree: true, childList: true, attributes: true, attributeFilter: ['src'] });
            }
        });
    })();
</script>
