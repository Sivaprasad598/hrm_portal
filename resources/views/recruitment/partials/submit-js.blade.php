{{-- Shared by the recruitment side panels: post the form (files included), then go where the
     answer says (or reload the page behind the panel). Also the one look of their fields
     (.rc-form): every label right on top of its field, the same height for every control, the
     same gap below each, whichever component drew it. --}}
<style>
    .rc-form .form-group { margin: 0 0 16px !important; }
    .rc-form label[data-label] { display: flex; align-items: baseline; flex-wrap: wrap; margin: 0 0 6px !important; font-size: 13.5px; line-height: 1.3; color: var(--epic-text, #28313c); }
    .rc-form label[data-label] sup { top: 0 !important; left: 0 !important; font-size: 13px; color: #d64545; margin-left: 3px; }
    .rc-form label[data-label] .fa-question-circle { margin-left: 4px; }
    .rc-form .form-control:not(textarea),
    .rc-form .bootstrap-select > .dropdown-toggle { height: 40px !important; min-height: 40px; }
    .rc-form .bootstrap-select > .btn.dropdown-toggle { display: flex; align-items: center; box-sizing: border-box; }
    .rc-form .bootstrap-select:not(.input-group-btn) { width: 100% !important; }
    .rc-form textarea.form-control { min-height: 84px; }
    .rc-form .rc-form-intro { padding: 12px 14px; margin: 0 0 16px; border-radius: 8px; background: var(--epic-page, #f6f7f9); font-size: 13.5px; }
    .rc-form .form-text, .rc-form .rc-help { display: block; margin-top: 5px; font-size: 12px; color: var(--epic-muted, #8a94a6); }
    .rc-form .form-check { padding-top: 2px; }
</style>
<script>
    // In the pop-up the title is on its dark bar already: the form's own heading is not repeated
    (function () {
        var heading = $('#right-modal-content .add-client > h4').last();
        if (heading.length && $(RIGHT_MODAL).hasClass('in')) {
            var text = $.trim(heading.text());
            setTimeout(function () { if (!$.trim($(RIGHT_MODAL_TITLE).text())) { $(RIGHT_MODAL_TITLE).text(text); } }, 0);
            heading.remove();
        }
    })();
</script>
<script>
    window.recruitSubmit = function (formId, url, buttonId) {
        $.easyAjax({
            url: url,
            container: '#' + formId,
            type: 'POST',
            disableButton: true,
            blockUI: true,
            buttonSelector: '#' + buttonId,
            file: true,
            data: $('#' + formId).serialize(),
            success: function (response) {
                if (response.status !== 'success') {
                    return;
                }

                // Saved from the side panel: stay on the page under it (the board, a list, the
                // candidate), shown again with the change. A form opened as its own page goes on
                // to where the answer says.
                if ($(RIGHT_MODAL).hasClass('in') || !response.redirectUrl) {
                    PageSwap.reload();
                } else {
                    PageSwap.open(response.redirectUrl);
                }
            }
        });
    };

    window.recruitClose = function (fallback) {
        if ($(RIGHT_MODAL).hasClass('in') && typeof closeTaskDetail === 'function') {
            closeTaskDetail();
        } else {
            PageSwap.open(fallback);
        }
    };
</script>
