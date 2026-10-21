{{-- Shared by the asset side-panel forms: post, then close the panel and refresh what is behind it. --}}
<script>
    window.assetPanelSubmit = function (formId, url, buttonId) {
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

                var table = window.LaravelDataTables && window.LaravelDataTables['assets-table'];

                if ($(RIGHT_MODAL).hasClass('in') && typeof closeTaskDetail === 'function') {
                    closeTaskDetail();

                    if (table) {
                        table.draw(false);
                    } else {
                        window.location.reload();
                    }
                } else {
                    window.location.href = response.redirectUrl || '{{ route('assets.index') }}';
                }
            }
        });
    };

    window.assetPanelClose = function () {
        if ($(RIGHT_MODAL).hasClass('in') && typeof closeTaskDetail === 'function') {
            closeTaskDetail();
        } else {
            window.location.href = '{{ route('assets.index') }}';
        }
    };
</script>
