{{-- Shared by the recruitment side panels: post the form (files included), then go where the
     answer says (or reload the page behind the panel). --}}
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

                if ($(RIGHT_MODAL).hasClass('in') && typeof closeTaskDetail === 'function') {
                    closeTaskDetail();
                }

                if (response.redirectUrl) {
                    PageSwap.open(response.redirectUrl);
                } else {
                    PageSwap.reload();
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
