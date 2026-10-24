{{--
    Meeting cards per project in a carousel: two side by side on wide screens (one on narrow ones), ‹ › arrows,
    dots and "1–2 of 3". Used by the Meetings page and by a lead's / client's Meetings tab, which is loaded by
    AJAX - so the script binds once and works on any page.
--}}
<style>
    /* ---- Meetings: one carousel per project, full width, two cards at a time with ‹ › arrows on its sides ---- */
    .meeting-carousel { display: flex; align-items: center; gap: 12px; }
    .meeting-track {
        flex: 1 1 auto; min-width: 0; display: flex; overflow-x: hidden; scroll-snap-type: x mandatory;
        scroll-behavior: smooth; padding: 6px 0 10px;
    }
    .meeting-slide { flex: 0 0 50%; min-width: 0; scroll-snap-align: start; display: flex; padding: 0 8px; }
    @media (max-width: 991.98px) {
        .meeting-slide { flex-basis: 100%; }
    }
    .meeting-carousel-arrow {
        flex: 0 0 36px; width: 36px; height: 36px; border-radius: 50%; border: 1px solid #dbe3ee; background: #fff;
        color: #334155; box-shadow: 0 2px 8px rgba(15, 23, 42, .12); display: flex; align-items: center; justify-content: center;
    }
    .meeting-carousel-arrow:hover:not(:disabled) { background: #eef4fc; }
    .meeting-carousel-arrow:disabled { opacity: .35; cursor: default; }
    /* Every card already fits: no arrows */
    .meeting-carousel.single .meeting-carousel-arrow { visibility: hidden; }
    /* Dots under the cards (one per position, the current one wide), then "1–2 of 3" */
    .meeting-carousel-dots { display: flex; justify-content: center; gap: 6px; margin-top: 4px; }
    .meeting-carousel-dots:empty { display: none; }
    .meeting-carousel-dot {
        width: 8px; height: 8px; padding: 0; border: 0; border-radius: 4px; background: #cbd5e1;
        cursor: pointer; transition: width .2s, background-color .2s;
    }
    .meeting-carousel-dot:hover { background: #94a3b8; }
    .meeting-carousel-dot.active { width: 22px; background: #2563eb; }
    .meeting-carousel-count { text-align: center; font-size: 12px; color: #64748b; margin-top: 6px; }
    .meeting-carousel-count:empty { display: none; }

    .meeting-card {
        width: 100%; border: 1px solid #e6ebf2; border-radius: 14px; overflow: hidden;
        box-shadow: 0 4px 14px rgba(15, 23, 42, .06); position: relative;
    }
    .meeting-card::before {
        content: ''; position: absolute; top: 0; left: 0; right: 0; height: 4px;
        background: linear-gradient(90deg, #2563eb, #60a5fa);
    }
    .meeting-card .card-body { padding: 20px 20px 18px; }
    .meeting-card-title { font-size: 20px; font-weight: 700; color: #1e293b; word-break: break-word; }
    .meeting-card-pin { color: #e11d48; font-size: 18px; transform: rotate(35deg); margin-top: 4px; }
    .meeting-chip {
        display: inline-block; font-size: 11px; font-weight: 600; padding: 3px 9px; border-radius: 6px;
        border: 1px solid #d5dbe4; background: #f8fafc; color: #334155;
    }
    .meeting-chip-type { border-color: #bfdbfe; background: #eff6ff; color: #1d4ed8; }
    /* Client / Lead / Lead Team */
    .meeting-chip-role { border-color: #e2e8f0; background: #f8fafc; color: #475569; }
    .meeting-chip-team { border-color: #c7d2fe; background: #eef2ff; color: #4338ca; }
    .meeting-card-actions .btn { font-size: 11px; font-weight: 600; padding: 3px 8px; border-radius: 6px; line-height: 1.4; }
    /* Edit, Meet Notes, Meeting Result and Delete all in Edit's blue */
    .meeting-card-actions .btn,
    .meeting-card-actions .btn:focus {
        color: #1d82f5 !important; border-color: #1d82f5 !important; background-color: transparent !important;
    }
    .meeting-card-actions .btn:hover {
        color: #fff !important; border-color: #1d82f5 !important; background-color: #1d82f5 !important;
    }
    .meeting-card hr { border-top-color: #eef2f7; }
    .meeting-card-details p { margin-bottom: 10px; color: #334155; }
    .meeting-card-details p.small { font-size: 12.5px; }
    .meeting-card-link { word-break: break-all; }
    .meeting-result-pill {
        display: inline-block; margin-top: 4px; padding: 6px 12px; border-radius: 8px; font-size: 12.5px;
        background: #ecfdf3; border: 1px solid #bbf7d0; color: #166534;
    }
    .meeting-result-pill b { margin-right: 6px; }
    .meeting-result-ended { background: #fef2f2; border-color: #fecaca; color: #991b1b; }
    .meeting-result-followup { background: #eff6ff; border-color: #bfdbfe; color: #1e40af; }
</style>

<script>
    (function () {
        // Width of one card (the step of the arrows and dots)
        function slideWidth(track) {
            var slide = $(track).children('.meeting-slide')[0];
            return slide ? slide.getBoundingClientRect().width : track.clientWidth;
        }

        function updateMeetingCarousel(carousel) {
            var track = carousel.find('.meeting-track')[0];
            if (!track || !track.clientWidth) return;

            var total = $(track).children('.meeting-slide').length;
            var step = slideWidth(track);
            var perView = Math.max(1, Math.round(track.clientWidth / step));
            var positions = Math.max(1, total - perView + 1); // where the first visible card can be
            var index = Math.min(positions - 1, Math.round(track.scrollLeft / step));

            // Arrows, dots and the count show whenever there are 2+ meetings; an arrow greys out when there is
            // nothing more that way (with 2 meetings both fit side by side, so both arrows are grey)
            carousel.toggleClass('single', total < 2);
            carousel.find('.meeting-carousel-arrow.prev').prop('disabled', index <= 0);
            carousel.find('.meeting-carousel-arrow.next').prop('disabled', index >= positions - 1);

            // One dot per meeting, the ones on screen highlighted; made here so both pages need no extra markup
            var dots = carousel.next('.meeting-carousel-dots');
            if (!dots.length) dots = $('<div class="meeting-carousel-dots"></div>').insertAfter(carousel);
            if (dots.children().length !== (total > 1 ? total : 0)) {
                dots.empty();
                for (var i = 0; total > 1 && i < total; i++) {
                    dots.append($('<button type="button" class="meeting-carousel-dot"></button>')
                        .attr('data-index', i).attr('aria-label', 'Go to meeting ' + (i + 1)));
                }
            }
            var last = Math.min(total, index + perView);
            dots.children().each(function (i) { $(this).toggleClass('active', i >= index && i < last); });

            // "1–2 of 3" with two cards showing, "1 / 3" with one
            dots.next('.meeting-carousel-count').text(total < 2 ? ''
                : (perView > 1 ? (index + 1) + '–' + last + ' of ' + total : (index + 1) + ' / ' + total));
        }

        window.updateMeetingCarousels = function () {
            $('.meeting-carousel').each(function () { updateMeetingCarousel($(this)); });
        };

        $(document).off('click.meetingCarousel')
            .on('click.meetingCarousel', '.meeting-carousel-arrow', function () {
                var track = $(this).closest('.meeting-carousel').find('.meeting-track')[0];
                // One card at a time
                var step = slideWidth(track);
                track.scrollBy({ left: $(this).hasClass('next') ? step : -step, behavior: 'smooth' });
            })
            // A dot brings its meeting on screen (the browser stops at the last position by itself)
            .on('click.meetingCarousel', '.meeting-carousel-dot', function () {
                var track = $(this).parent().prev('.meeting-carousel').find('.meeting-track')[0];
                track.scrollTo({ left: $(this).data('index') * slideWidth(track), behavior: 'smooth' });
            })
            // A collapsed project section has no width until it opens; the Meetings tab is hidden until clicked
            .on('click.meetingCarousel', '.project-group-header, .tab-btn', function () { setTimeout(updateMeetingCarousels, 250); });

        // scroll does not bubble: bind on each track (again after an AJAX tab load)
        $('.meeting-track').off('scroll.meetingCarousel').on('scroll.meetingCarousel', function () {
            updateMeetingCarousel($(this).closest('.meeting-carousel'));
        });

        $(window).off('resize.meetingCarousel').on('resize.meetingCarousel', updateMeetingCarousels);
        updateMeetingCarousels();
        setTimeout(updateMeetingCarousels, 300);
    })();
</script>
