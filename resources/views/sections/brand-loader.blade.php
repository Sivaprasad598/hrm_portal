{{-- The app's loader: the company "A" drawing itself (public/img/loader-a.gif, made
     from the logo), on a small white card in the middle of the screen, in place of the
     round spinner. It restyles every big .spinner-border (page preloader, the block
     shown while an action runs, anything else using it) and gives modals the same
     loader while their content loads. Small in-button spinners are left as they are. --}}
<style>
    .brand-a,
    body .spinner-border:not(.spinner-border-sm) {
        display: inline-block; width: 96px; height: 96px; vertical-align: middle; flex: none;
        border: 0 !important; border-radius: 20px !important; color: transparent !important;
        background: #fff url('{{ asset('img/loader-a.gif') }}?v={{ @filemtime(public_path('img/loader-a.gif')) }}') center / 80px 80px no-repeat !important;
        box-shadow: 0 12px 32px rgba(16, 24, 40, .14), 0 0 0 1px rgba(16, 24, 40, .04);
        animation: none !important; overflow: hidden;
    }
    .brand-a .sr-only, body .spinner-border:not(.spinner-border-sm) .sr-only { display: none; }

    /* in the middle of the screen, whatever part of the page is waiting */
    body .blockUI.blockMsg { position: fixed !important; top: 50% !important; left: 50% !important; width: auto !important;
        transform: translate(-50%, -50%); margin: 0 !important; }

    /* the loader inside a modal while it fetches its content */
    .brand-loader-box { display: flex; align-items: center; justify-content: center; min-height: 180px; }
    .brand-loader-box .brand-a { box-shadow: none; }
    /* the block over a page/section while an action runs: a soft grey veil */
    body .blockUI.blockOverlay { background-color: #f3f3f9 !important; opacity: .6 !important; }
</style>

<script>
    window.BRAND_LOADER = '<div class="brand-loader-box"><span class="brand-a" role="status" aria-label="Loading"></span></div>';

    // main.js puts plain "Loading..." text back into a modal after it closes, ready for
    // the next one; swap it for the loader. (Runs after main.js's own handler.)
    document.addEventListener('DOMContentLoaded', function () {
        if (!window.jQuery) { return; }
        jQuery(document).on('hidden.bs.modal', '.modal', function () {
            var body = jQuery(this).find('.modal-body');
            if (body.children().length === 0 && /^\s*Loading\.*\s*$/i.test(body.text())) {
                body.html(window.BRAND_LOADER);
            }
        });
    });
</script>
