<tr class="reminder-before-row border-bottom">
    <td class="align-middle py-2" style="width:70px;">
        <input type="hidden" name="reminder_before_rules[{{ $index }}][enabled]" value="0">
        <div class="form-check mb-0">
            <input type="checkbox"
                   name="reminder_before_rules[{{ $index }}][enabled]"
                   id="{{ ($fieldPrefix ?? '') }}before_rule_enabled_{{ $index }}"
                   value="1"
                   class="form-check-input reminder-summary-input"
                   @checked(!empty($rule['enabled']))>
            <label class="form-check-label" for="{{ ($fieldPrefix ?? '') }}before_rule_enabled_{{ $index }}"></label>
        </div>
    </td>
    <td class="align-middle py-2">
        <div class="d-flex align-items-center flex-nowrap">
            <input type="number" min="1"
                   name="reminder_before_rules[{{ $index }}][value]"
                   value="{{ $rule['value'] ?? 1 }}"
                   class="form-control height-35 f-14 mr-2 reminder-summary-input flex-shrink-0" style="width:80px;">
            <select name="reminder_before_rules[{{ $index }}][unit]"
                    class="form-control height-35 f-14 mr-2 reminder-summary-input flex-shrink-0" style="width:120px;">
                <option value="days" @selected(($rule['unit'] ?? 'days') === 'days')>@lang('app.days')</option>
                <option value="hours" @selected(($rule['unit'] ?? '') === 'hours')>@lang('app.hour')</option>
            </select>
            <span class="f-14 text-dark-grey text-nowrap">@lang('modules.invoiceSettings.beforeDueDate')</span>
        </div>
    </td>
    <td class="align-middle text-center py-2" style="width:60px;">
        <a href="javascript:;" class="text-lightest remove-before-reminder" data-toggle="tooltip" title="@lang('app.remove')">
            <i class="fa fa-trash-alt"></i>
        </a>
    </td>
</tr>
