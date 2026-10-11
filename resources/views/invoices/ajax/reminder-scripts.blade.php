<script type="text/template" id="invoice-reminder-before-row-template">
    @include('invoice-settings.ajax.reminder-before-row', [
        'index' => '__INDEX__',
        'rule' => ['enabled' => true, 'value' => 1, 'unit' => 'days'],
        'fieldPrefix' => 'invoice_',
    ])
</script>

<script>
    (function () {
        function toggleInvoiceCustomReminder() {
            const $form = $('#invoice-reminder-settings-form');
            if (!$form.length) {
                return;
            }
            const enabled = $('#invoice_reminder_use_custom').is(':checked');
            $form.find('.invoice-custom-reminder-fields').toggleClass('d-none', !enabled);
        }

        function toggleInvoiceBeforeAfter() {
            const $form = $('#invoice-reminder-settings-form');
            if (!$form.length) {
                return;
            }
            $form.find('.reminder-before-fields').toggleClass('d-none', !$('#invoice_reminder_before_enabled').is(':checked'));
            $form.find('.reminder-after-fields').toggleClass('d-none', !$('#invoice_reminder_after_enabled').is(':checked'));
        }

        function initInvoiceReminderDatepicker() {
            if (typeof datepicker === 'undefined' || !$('#invoice_reminder_limit_date').length) {
                return;
            }
            if ($('#invoice_reminder_limit_date').data('datepicker')) {
                return;
            }
            datepicker('#invoice_reminder_limit_date', {
                position: 'bl',
                ...datepickerConfig
            });
        }

        toggleInvoiceCustomReminder();
        toggleInvoiceBeforeAfter();
        initInvoiceReminderDatepicker();

        $('body').off('change.invoiceReminderCustom', '#invoice_reminder_use_custom')
            .on('change.invoiceReminderCustom', '#invoice_reminder_use_custom', toggleInvoiceCustomReminder);

        $('body').off('change.invoiceReminderSections', '#invoice_reminder_before_enabled, #invoice_reminder_after_enabled')
            .on('change.invoiceReminderSections', '#invoice_reminder_before_enabled, #invoice_reminder_after_enabled', toggleInvoiceBeforeAfter);

        $('body').off('click.invoiceReminderAdd', '#invoice-reminder-settings-form .add-before-reminder')
            .on('click.invoiceReminderAdd', '#invoice-reminder-settings-form .add-before-reminder', function () {
                const $tbody = $('#invoice-reminder-settings-form .reminder-before-rows');
                const index = $tbody.find('.reminder-before-row').length;
                const html = $('#invoice-reminder-before-row-template').html().replace(/__INDEX__/g, index);
                $tbody.append(html);
            });

        $('body').off('click.invoiceReminderRemove', '#invoice-reminder-settings-form .remove-before-reminder')
            .on('click.invoiceReminderRemove', '#invoice-reminder-settings-form .remove-before-reminder', function () {
                const $tbody = $('#invoice-reminder-settings-form .reminder-before-rows');
                if ($tbody.find('.reminder-before-row').length <= 1) {
                    return;
                }
                $(this).closest('.reminder-before-row').remove();
            });
    })();
</script>
