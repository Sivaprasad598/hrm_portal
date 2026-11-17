{{-- Pop-ups and the side panel while their content is on the way.

     A pop-up used to show at once as a placeholder - a "..." title, a loader in an
     empty box and dummy buttons (a bare "Cancel" from the second time on) - and then
     jump to its real size when the content arrived. The side panel slid in still
     showing whatever it held last time.

     Now: the page dims with one loader in the middle, and the pop-up comes in
     complete. It is opened at the same moment as before (only kept invisible until
     its content is in), so the forms and pickers inside it start up exactly as they
     did. The side panel opens empty under its loader instead of on the previous record.

     Loaded right after js/main.js (it replaces $.ajaxModal from there). --}}
<style>
    .modal.modal-loading .modal-dialog { opacity: 0 !important; pointer-events: none; }
    .modal .modal-dialog { transition: opacity .12s ease-out, transform .3s ease-out; }
    .modal > .modal-busy { display: none; }
    .modal.modal-loading > .modal-busy { display: flex; position: fixed; inset: 0; align-items: center; justify-content: center; }
    .modal.modal-loading > .modal-busy .brand-loader-box { min-height: 0; padding: 22px; border-radius: 16px; background: #fff; box-shadow: 0 10px 30px rgba(16, 24, 40, .18); }
    /* the side panel while its record is fetched: its height is held (main.js's own loader shows over it) */
    #right-modal-content > .right-modal-wait { min-height: 55vh; }
    @media (prefers-reduced-motion: reduce) { .modal .modal-dialog { transition: none; } }
</style>
<script>
    (function ($) {
        var loader = window.BRAND_LOADER || '<div class="brand-loader-box"><span class="spinner-border" role="status"></span></div>';

        $.ajaxModal = function (selector, url, onOpen) {
            var $modal = $(selector);
            var run = ($modal.data('ajaxModalRun') || 0) + 1;

            if (!$modal.children('.modal-busy').length) {
                $modal.append('<div class="modal-busy">' + loader + '</div>');
            }

            $modal.data('ajaxModalRun', run).addClass('modal-loading');

            // shown complete; also if the content never comes, so it can still be closed
            var reveal = function () {
                if ($modal.data('ajaxModalRun') === run) { $modal.removeClass('modal-loading'); }
            };
            var giveUp = setTimeout(reveal, 10000);

            $modal.find('.modal-content').load(url, function (response, status, xhr) {
                clearTimeout(giveUp);

                if (status === 'error' && $modal.data('ajaxModalRun') === run) {
                    $modal.find('.modal-body').html('<div class="text-center text-dark-grey p-4 f-14">'
                        + (xhr && xhr.status === 403 ? '403 | Permission Denied' : (xhr && xhr.status === 404 ? '404 | Not Found' : 'Could not load this. Please close and try again.'))
                        + '</div>');
                }

                reveal();
            });

            // (as js/main.js did from here on)
            $modal.removeData('bs.modal').modal({ remote: url, show: true, backdrop: 'static', keyboard: false });
            $(document).trigger('ajaxPageLoad');

            if (typeof onOpen !== 'undefined') { onOpen(); }

            // once, not one more handler for every pop-up opened
            $modal.off('hidden.bs.modal.ajaxModal').on('hidden.bs.modal.ajaxModal', function () {
                clearTimeout(giveUp);
                $(this).removeClass('modal-loading');
                $(this).find('.modal-body').html(document.loading ? document.loading : 'Loading...');
                $(this).find('.modal-footer').html('<button type="button" data-dismiss="modal" class="btn dark btn-outline">Cancel</button>');
                $(this).data('bs.modal', null);
            });
        };

        // The side panel: a loader until the record asked for arrives, not the last one opened.
        // (Bound after main.js's own handlers for these links, which open the panel and fetch.)
        $('body').on('click', '.openRightModal', function () {
            if ($(this).hasClass('inModal')) { return; }   // a step inside the open panel keeps what is there
            $(RIGHT_MODAL_TITLE).html('');
            $(RIGHT_MODAL_CONTENT).html('<div class="right-modal-wait"></div>');
        });
    })(jQuery);
</script>
