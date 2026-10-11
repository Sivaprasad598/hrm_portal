@php
    $reminderSettings = $reminderSettings ?? ($invoiceSetting->reminderSettingsArray() ?? \App\Services\InvoiceReminderService::emptySettings());
    $fieldPrefix = $fieldPrefix ?? '';
    $beforeRules = !empty($reminderSettings['before_rules'])
        ? $reminderSettings['before_rules']
        : \App\Models\InvoiceSetting::defaultBeforeReminderRules();
    $showCustomToggle = $showCustomToggle ?? false;
    $useCustom = $useCustom ?? false;
    $formId = $formId ?? 'reminder-settings-form';
@endphp

<div class="reminder-settings-form" id="{{ $formId }}">
    @if ($showCustomToggle)
        <div class="mb-3">
            <x-forms.checkbox :checked="$useCustom"
                :fieldLabel="__('modules.invoiceSettings.useCustomReminder')"
                fieldName="reminder_use_custom"
                fieldId="{{ $fieldPrefix }}reminder_use_custom"
                :popover="__('modules.invoiceSettings.useCustomReminderInfo')"/>
        </div>
        <div class="invoice-custom-reminder-fields {{ $useCustom ? '' : 'd-none' }}">
    @endif

    {{-- 1. When to Send Reminders --}}
    <div class="mb-4">
        <h5 class="f-15 f-w-500 mb-3">
            <span class="badge badge-primary rounded-circle mr-1" style="width:22px;height:22px;line-height:22px;padding:0;">1</span>
            @lang('modules.invoiceSettings.whenToSendReminders')
        </h5>

        {{-- Before due date --}}
        <div class="border rounded p-3 mb-3">
            <x-forms.checkbox :checked="!empty($reminderSettings['before_enabled'])"
                :fieldLabel="__('modules.invoiceSettings.sendReminderBeforeDueDate')"
                fieldName="reminder_before_enabled"
                fieldId="{{ $fieldPrefix }}reminder_before_enabled"
                class="reminder-before-enabled"/>

            <div class="reminder-before-fields mt-3 {{ !empty($reminderSettings['before_enabled']) ? '' : 'd-none' }}">
                <div class="table-responsive">
                    <table class="table mb-2 reminder-before-table">
                        <thead>
                            <tr class="text-dark-grey f-12">
                                <th style="width:70px;">@lang('modules.invoiceSettings.send')</th>
                                <th>@lang('modules.invoiceSettings.timeBeforeDueDate')</th>
                                <th class="text-center" style="width:60px;">@lang('modules.invoiceSettings.action')</th>
                            </tr>
                        </thead>
                        <tbody class="reminder-before-rows">
                            @foreach ($beforeRules as $index => $rule)
                                @include('invoice-settings.ajax.reminder-before-row', [
                                    'index' => $index,
                                    'rule' => $rule,
                                    'fieldPrefix' => $fieldPrefix,
                                ])
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <button type="button" class="btn btn-outline-primary btn-sm add-before-reminder">
                    <i class="fa fa-plus mr-1"></i>@lang('modules.invoiceSettings.addAnotherReminderBefore')
                </button>
            </div>
        </div>

        {{-- After due date --}}
        <div class="border rounded p-3">
            <x-forms.checkbox :checked="!empty($reminderSettings['after_enabled'])"
                :fieldLabel="__('modules.invoiceSettings.sendReminderAfterDueDate')"
                fieldName="reminder_after_enabled"
                fieldId="{{ $fieldPrefix }}reminder_after_enabled"
                class="reminder-after-enabled"/>

            <div class="reminder-after-fields mt-3 {{ !empty($reminderSettings['after_enabled']) ? '' : 'd-none' }}">
                <div class="d-flex flex-wrap align-items-center">
                    <span class="f-14 text-dark-grey mr-2 mb-2">@lang('modules.invoiceSettings.sendEvery')</span>
                    <span class="form-control height-35 f-14 mr-2 mb-2 d-inline-flex align-items-center bg-additional-grey text-dark-grey" style="width:auto; min-width:80px;">
                        @lang('modules.invoiceSettings.every')
                    </span>
                    <input type="number" min="1" name="reminder_after_frequency"
                           value="{{ $reminderSettings['after_frequency'] ?? 3 }}"
                           class="form-control height-35 f-14 mr-2 mb-2 reminder-summary-input" style="width:80px;">
                    <select name="reminder_after_frequency_unit"
                            class="form-control height-35 f-14 mr-2 mb-2 reminder-summary-input" style="width:120px;">
                        <option value="hours" @selected(($reminderSettings['after_frequency_unit'] ?? 'hours') === 'hours')>@lang('app.hour')</option>
                        <option value="days" @selected(($reminderSettings['after_frequency_unit'] ?? '') === 'days')>@lang('app.days')</option>
                    </select>
                    <span class="f-14 text-dark-grey mr-2 mb-2">@lang('modules.invoiceSettings.afterDueDate'),</span>
                    <span class="f-14 text-dark-grey mr-2 mb-2">@lang('modules.invoiceSettings.startAfter')</span>
                    <input type="number" min="0" name="reminder_after_start"
                           value="{{ $reminderSettings['after_start'] ?? 1 }}"
                           class="form-control height-35 f-14 mr-2 mb-2 reminder-summary-input" style="width:80px;">
                    <select name="reminder_after_start_unit"
                            class="form-control height-35 f-14 mb-2 reminder-summary-input" style="width:120px;">
                        <option value="hours" @selected(($reminderSettings['after_start_unit'] ?? 'hours') === 'hours')>@lang('app.hour')</option>
                        <option value="days" @selected(($reminderSettings['after_start_unit'] ?? '') === 'days')>@lang('app.days')</option>
                    </select>
                </div>
                <div class="alert alert-info mt-3 mb-0 f-13">
                    <i class="fa fa-info-circle mr-1"></i>
                    @lang('modules.invoiceSettings.remindersUntilPaidInfo')
                </div>
            </div>
        </div>
    </div>

    {{-- 2. Reminder Schedule --}}
    <div class="mb-3">
        <h5 class="f-15 f-w-500 mb-3">
            <span class="badge badge-primary rounded-circle mr-1" style="width:22px;height:22px;line-height:22px;padding:0;">2</span>
            @lang('modules.invoiceSettings.reminderSchedule')
        </h5>

        <div class="border rounded p-3">
            <label class="f-14 text-dark-grey mb-3 d-block">@lang('modules.invoiceSettings.sendReminderLabel')</label>

            @php $limitType = $reminderSettings['limit_type'] ?? 'until_paid'; @endphp

            <div class="radio-group mb-2">
                <label class="d-flex align-items-center f-14 mb-2">
                    <input type="radio" name="reminder_limit_type" value="until_paid" class="mr-2 reminder-summary-input"
                           @checked($limitType === 'until_paid')>
                    @lang('modules.invoiceSettings.untilInvoicePaid')
                </label>

                <label class="d-flex align-items-center f-14 mb-2">
                    <input type="radio" name="reminder_limit_type" value="times" class="mr-2 reminder-summary-input"
                           @checked($limitType === 'times')>
                    <span class="mr-2">@lang('modules.invoiceSettings.onlyTimes')</span>
                    <input type="number" min="1" name="reminder_limit_value_times"
                           value="{{ $limitType === 'times' ? ($reminderSettings['limit_value'] ?? 5) : 5 }}"
                           class="form-control height-35 f-14 mx-1 reminder-limit-times reminder-summary-input" style="width:70px;">
                    <span>@lang('modules.invoiceSettings.times')</span>
                </label>

                <label class="d-flex align-items-center f-14 mb-2">
                    <input type="radio" name="reminder_limit_type" value="days" class="mr-2 reminder-summary-input"
                           @checked($limitType === 'days')>
                    <span class="mr-2">@lang('modules.invoiceSettings.forDays')</span>
                    <input type="number" min="1" name="reminder_limit_value_days"
                           value="{{ $limitType === 'days' ? ($reminderSettings['limit_value'] ?? 10) : 10 }}"
                           class="form-control height-35 f-14 mx-1 reminder-limit-days reminder-summary-input" style="width:70px;">
                    <span>@lang('app.days')</span>
                </label>

                <label class="d-flex align-items-center f-14 mb-2">
                    <input type="radio" name="reminder_limit_type" value="custom_date" class="mr-2 reminder-summary-input"
                           @checked($limitType === 'custom_date')>
                    <span class="mr-2">@lang('modules.invoiceSettings.customEndDate')</span>
                    <input type="text" name="reminder_limit_date" id="{{ $fieldPrefix }}reminder_limit_date"
                           value="{{ $reminderSettings['limit_date'] ?? '' }}"
                           class="form-control height-35 f-14 ml-1 reminder-limit-date reminder-summary-input"
                           placeholder="@lang('placeholders.date')" style="width:140px;" autocomplete="off">
                </label>
            </div>
        </div>
    </div>

    @if ($showCustomToggle)
        </div>
    @endif
</div>
