@php
    $reminderSettings = $invoiceSetting->reminderSettingsArray();
    if (empty($reminderSettings['before_rules'])) {
        $reminderSettings['before_rules'] = \App\Models\InvoiceSetting::defaultBeforeReminderRules();
    }
    if (!empty($reminderSettings['limit_date'])) {
        try {
            $reminderSettings['limit_date'] = \Carbon\Carbon::parse($reminderSettings['limit_date'])->format(company()->date_format);
        } catch (\Throwable $e) {
            // keep stored value
        }
    }
@endphp

<div class="col-lg-12 col-md-12 ntfcn-tab-content-left w-100 p-4">
    @method('PUT')

    <div class="d-flex flex-wrap justify-content-between align-items-start mb-4">
        <div>
            <h4 class="mb-1 f-18 f-w-500">@lang('modules.invoiceSettings.reminderSettingsTitle')</h4>
            <p class="text-lightest f-13 mb-0">@lang('modules.invoiceSettings.reminderSettingsSubtitle')</p>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-8 col-md-12">
            <div class="border rounded p-4 mb-3">
                <h5 class="f-16 f-w-500 mb-1">@lang('modules.invoiceSettings.globalReminderSettings')</h5>
                <p class="text-lightest f-13 mb-4">@lang('modules.invoiceSettings.globalReminderSettingsInfo')</p>

                @include('invoice-settings.ajax.reminder-form', [
                    'reminderSettings' => $reminderSettings,
                    'fieldPrefix' => 'global_',
                    'formId' => 'global-reminder-settings-form',
                    'showCustomToggle' => false,
                ])
            </div>
        </div>

        <div class="col-lg-4 col-md-12">
            <div class="alert alert-info mb-3">
                <h6 class="f-14 f-w-500 mb-2">
                    <i class="fa fa-info-circle mr-1"></i>@lang('modules.invoiceSettings.howRemindersWork')
                </h6>
                <p class="f-13 mb-0">@lang('modules.invoiceSettings.howRemindersWorkInfo')</p>
            </div>

            <div class="border rounded p-3 bg-white mb-3" id="reminder-summary-card">
                <h6 class="f-14 f-w-500 mb-3">@lang('modules.invoiceSettings.reminderSummary')</h6>

                <p class="f-13 f-w-500 mb-1 text-dark-grey">@lang('modules.invoiceSettings.beforeDueDateReminders')</p>
                <ul class="pl-3 mb-3 f-13" id="summary-before-list">
                    <li class="text-lightest">@lang('modules.invoiceSettings.noBeforeRemindersConfigured')</li>
                </ul>

                <p class="f-13 f-w-500 mb-1 text-dark-grey">@lang('modules.invoiceSettings.afterDueDateReminders')</p>
                <p class="f-13 mb-3" id="summary-after-text">@lang('modules.invoiceSettings.afterRemindersDisabled')</p>

                <div class="f-13">
                    <div class="d-flex justify-content-between mb-1">
                        <span class="text-dark-grey">@lang('modules.invoiceSettings.until'):</span>
                        <span id="summary-until">@lang('modules.invoiceSettings.untilInvoicePaid')</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="alert alert-info d-flex justify-content-between align-items-center mb-0">
        <span>
            <i class="fa fa-info-circle mr-1"></i>
            @lang('modules.invoiceSettings.overrideReminderInfo')
        </span>
    </div>
</div>

<div class="w-100 border-top-grey">
    <x-setting-form-actions>
        <x-forms.button-primary id="save-reminder-form" class="mr-3" icon="check">
            @lang('modules.invoiceSettings.saveChanges')
        </x-forms.button-primary>
    </x-setting-form-actions>
</div>

<script type="text/template" id="reminder-before-row-template">
    @include('invoice-settings.ajax.reminder-before-row', [
        'index' => '__INDEX__',
        'rule' => ['enabled' => true, 'value' => 1, 'unit' => 'days'],
        'fieldPrefix' => 'global_',
    ])
</script>

<script>
    (function () {
        const daysLabel = "@lang('app.days')";
        const hoursLabel = "@lang('app.hour')";
        const beforeLabel = "@lang('modules.invoiceSettings.beforeDueDate')";
        const everyLabel = "@lang('modules.invoiceSettings.every')";
        const startingAfterLabel = "@lang('modules.invoiceSettings.startingAfter')";
        const noBeforeLabel = "@lang('modules.invoiceSettings.noBeforeRemindersConfigured')";
        const afterDisabledLabel = "@lang('modules.invoiceSettings.afterRemindersDisabled')";
        const untilPaidLabel = "@lang('modules.invoiceSettings.untilInvoicePaid')";
        const onlyTimesLabel = "@lang('modules.invoiceSettings.onlyTimes')";
        const timesLabel = "@lang('modules.invoiceSettings.times')";
        const forDaysLabel = "@lang('modules.invoiceSettings.forDays')";
        const customEndLabel = "@lang('modules.invoiceSettings.customEndDate')";

        function updateReminderSummary() {
            const beforeEnabled = $('#global_reminder_before_enabled').is(':checked');
            const $beforeList = $('#summary-before-list');
            $beforeList.empty();

            if (beforeEnabled) {
                let hasItems = false;
                $('.reminder-before-rows .reminder-before-row').each(function () {
                    const enabled = $(this).find('input[type="checkbox"]').is(':checked');
                    const value = $(this).find('input[type="number"]').val();
                    const unit = $(this).find('select').val();
                    if (enabled && value) {
                        hasItems = true;
                        const unitText = unit === 'hours' ? hoursLabel : daysLabel;
                        $beforeList.append('<li>' + value + ' ' + unitText + ' ' + beforeLabel + '</li>');
                    }
                });
                if (!hasItems) {
                    $beforeList.append('<li class="text-lightest">' + noBeforeLabel + '</li>');
                }
            } else {
                $beforeList.append('<li class="text-lightest">' + noBeforeLabel + '</li>');
            }

            const afterEnabled = $('#global_reminder_after_enabled').is(':checked');
            if (afterEnabled) {
                const freq = $('input[name="reminder_after_frequency"]').val() || 3;
                const freqUnit = $('select[name="reminder_after_frequency_unit"]').val() === 'days' ? daysLabel : hoursLabel;
                const start = $('input[name="reminder_after_start"]').val() || 1;
                const startUnit = $('select[name="reminder_after_start_unit"]').val() === 'days' ? daysLabel : hoursLabel;
                $('#summary-after-text').text(everyLabel + ' ' + freq + ' ' + freqUnit + ', ' + startingAfterLabel + ' ' + start + ' ' + startUnit);
            } else {
                $('#summary-after-text').text(afterDisabledLabel);
            }

            const limitType = $('input[name="reminder_limit_type"]:checked').val();
            let untilText = untilPaidLabel;
            if (limitType === 'times') {
                untilText = onlyTimesLabel + ' ' + ($('input[name="reminder_limit_value_times"]').val() || 5) + ' ' + timesLabel;
            } else if (limitType === 'days') {
                untilText = forDaysLabel + ' ' + ($('input[name="reminder_limit_value_days"]').val() || 10) + ' ' + daysLabel;
            } else if (limitType === 'custom_date') {
                untilText = customEndLabel + ' ' + ($('input[name="reminder_limit_date"]').val() || '');
            }
            $('#summary-until').text(untilText);
        }

        function toggleSections() {
            $('.reminder-before-fields').toggleClass('d-none', !$('#global_reminder_before_enabled').is(':checked'));
            $('.reminder-after-fields').toggleClass('d-none', !$('#global_reminder_after_enabled').is(':checked'));
            updateReminderSummary();
        }

        toggleSections();

        if (typeof datepicker !== 'undefined' && $('#global_reminder_limit_date').length) {
            datepicker('#global_reminder_limit_date', {
                position: 'bl',
                ...datepickerConfig
            });
        }

        $('body').off('change.reminderSettings', '#global_reminder_before_enabled, #global_reminder_after_enabled')
            .on('change.reminderSettings', '#global_reminder_before_enabled, #global_reminder_after_enabled', toggleSections);

        $('body').off('change.reminderSummary', '.reminder-summary-input')
            .on('change.reminderSummary', '.reminder-summary-input', updateReminderSummary);

        $('body').off('click.reminderAdd', '.add-before-reminder').on('click.reminderAdd', '.add-before-reminder', function () {
            const $tbody = $(this).closest('.reminder-before-fields').find('.reminder-before-rows');
            const index = $tbody.find('.reminder-before-row').length;
            const html = $('#reminder-before-row-template').html().replace(/__INDEX__/g, index);
            $tbody.append(html);
            updateReminderSummary();
        });

        $('body').off('click.reminderRemove', '.remove-before-reminder').on('click.reminderRemove', '.remove-before-reminder', function () {
            const $tbody = $(this).closest('.reminder-before-rows');
            if ($tbody.find('.reminder-before-row').length <= 1) {
                return;
            }
            $(this).closest('.reminder-before-row').remove();
            updateReminderSummary();
        });

        $('#save-reminder-form').off('click').on('click', function () {
            const limitType = $('input[name="reminder_limit_type"]:checked').val();
            let limitValue = null;

            if (limitType === 'times') {
                limitValue = $('input[name="reminder_limit_value_times"]').val();
            } else if (limitType === 'days') {
                limitValue = $('input[name="reminder_limit_value_days"]').val();
            }

            let data = $('#editSettings').serializeArray();
            data.push({name: 'reminder_limit_value', value: limitValue});

            $.easyAjax({
                url: "{{ route('invoice_settings.update_reminder_setting', $invoiceSetting->id) }}",
                container: '#editSettings',
                type: "POST",
                redirect: true,
                data: $.param(data),
                disableButton: true,
                blockUI: true,
                buttonSelector: "#save-reminder-form",
            });
        });
    })();
</script>
