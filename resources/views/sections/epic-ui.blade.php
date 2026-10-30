{{-- Epic HR look: the few touches a stylesheet alone cannot make. Display only;
     nothing here changes what a page does. --}}
<script>
    (function () {
        if (!window.jQuery || !document.body.classList.contains('epic')) {
            return;
        }

        var $ = window.jQuery;

        // The strip's menu button does what the arrow at the foot of the menu does
        $(document).on('click', '#epic-menu-toggle', function () {
            $('#sidebarToggle').trigger('click');
        });

        // Count cards ("Total Resignations 0", "Pending 0"...) came with a thick
        // coloured stripe down the left, set inline with !important so no
        // stylesheet can lift it. The template's cards are plain white: a card
        // with such a stripe loses it and is tagged epic-count, with its label,
        // figure and icon tagged too, for public/css/epic/theme.css.
        function px(value) { return parseFloat(value) || 0; }

        function tagCounts(root) {
            $(root).find('.main-container *').addBack('.main-container *').each(function () {
                if (this.classList.contains('epic-count') || this.closest('.epic-count, .task-detail-panel, .fc, table, .pd-card, .modal')) {
                    return;
                }

                var style = getComputedStyle(this);
                var left = px(style.borderLeftWidth);
                var striped = left >= 3 && px(style.borderTopWidth) <= 1;
                // Or a card that is just a small capital label over a figure
                var tile = this.matches('.card, .b-shadow-4, .arw-stat')
                    && this.querySelector('.text-uppercase') && this.querySelector('h2, h3, h4, .f-21, .f-27, .arw-stat__value');

                if (!(striped || tile) || this.offsetHeight > 220 || this.offsetHeight < 50
                    || this.offsetWidth > 520 || !/\d/.test(this.textContent)) {
                    return;
                }

                this.style.setProperty('border-left', '0', 'important');
                this.classList.add('epic-count');

                var biggest = null;
                var biggestSize = 0;

                $(this).find('*').each(function () {
                    var s = getComputedStyle(this);
                    var size = px(s.fontSize);
                    var own = Array.prototype.some.call(this.childNodes, function (n) { return n.nodeType === 3 && n.textContent.trim(); });

                    if (own && s.textTransform === 'uppercase' && size <= 14) {
                        this.classList.add('epic-count__label');
                    }

                    if (own && /\d/.test(this.textContent) && size > biggestSize) {
                        biggest = this;
                        biggestSize = size;
                    }

                    if (s.borderRadius.indexOf('50%') !== -1 && this.offsetWidth >= 30 && this.offsetWidth <= 64 && this.querySelector('i, svg')) {
                        this.classList.add('epic-count__icon');
                    }
                });

                if (biggest && biggestSize >= 18) {
                    biggest.classList.add('epic-count__value');
                }
            });
        }

        var queued = false;

        function later() {
            if (!queued) {
                queued = true;
                setTimeout(function () { queued = false; tagCounts(document.body); cardOptions(); }, 50);
            }
        }

        // Card headers on dashboards get the template's collapse and fullscreen
        function cardOptions() {
            if (!/dashboard/.test(location.pathname)) {
                return;
            }

            $('.main-container .card > .card-header').each(function () {
                // A header with its own button or link keeps just that
                if ($(this).find('.epic-card-options, [class*="btn"], a[href], button').length) {
                    return;
                }

                $(this).addClass('d-flex align-items-center').append(
                    '<div class="epic-card-options">' +
                    '<a data-epic-card="collapse" title="Collapse"><i class="bi bi-chevron-up"></i></a>' +
                    '<a data-epic-card="full" title="Full screen"><i class="bi bi-arrows-fullscreen"></i></a>' +
                    '</div>');
            });
        }

        $(document).on('click', '[data-epic-card]', function () {
            var card = $(this).closest('.card');

            if ($(this).data('epic-card') === 'collapse') {
                card.toggleClass('epic-collapsed');
                $(this).find('i').toggleClass('bi-chevron-up bi-chevron-down');
            } else {
                card.toggleClass('epic-full');
                $(this).find('i').toggleClass('bi-arrows-fullscreen bi-fullscreen-exit');
            }
        });

        $(function () {
            tagCounts(document.body);
            cardOptions();

            if (window.MutationObserver) {
                new MutationObserver(function (changes) {
                    for (var i = 0; i < changes.length; i++) {
                        if (changes[i].addedNodes.length) {
                            later();
                            return;
                        }
                    }
                }).observe(document.querySelector('.main-container') || document.body, { childList: true, subtree: true });
            }
        });
    })();
</script>
