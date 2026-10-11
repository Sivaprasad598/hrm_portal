<?php

namespace App\Http\Requests;

class UpdateInvoiceReminderSetting extends CoreRequest
{

    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'reminder_before_rules' => 'nullable|array',
            'reminder_before_rules.*.value' => 'nullable|integer|min:1',
            'reminder_before_rules.*.unit' => 'nullable|in:days,hours',
            'reminder_after_frequency' => 'nullable|integer|min:1',
            'reminder_after_frequency_unit' => 'nullable|in:days,hours',
            'reminder_after_start' => 'nullable|integer|min:0',
            'reminder_after_start_unit' => 'nullable|in:days,hours',
            'reminder_limit_type' => 'nullable|in:until_paid,times,days,custom_date',
            'reminder_limit_value' => 'nullable|integer|min:1',
            'reminder_limit_date' => 'nullable|date',
        ];
    }

}
