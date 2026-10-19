<?php

namespace App\Http\Requests\Admin\Employee;

use App\Models\EmployeeDetails;
use App\Http\Requests\CoreRequest;
use App\Traits\CustomFieldsRequestTrait;

class UpdateRequest extends CoreRequest
{
    use CustomFieldsRequestTrait;
    use OnboardingRules;

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
     * basic_for_payroll is stored as decimal(16,2), so an untouched field
     * round-trips through the edit form as "50.00" — normalize it to a
     * plain integer before the "integer" rule below sees it.
     */
    protected function prepareForValidation()
    {
        if ($this->filled('basic_for_payroll')) {
            $this->merge([
                'basic_for_payroll' => (int) round((float) $this->basic_for_payroll),
            ]);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        $detailID = EmployeeDetails::where('user_id', $this->route('employee'))->first();
        $setting = company();
        $rules = [
            'employee_id' => 'required|max:50|unique:employee_details,employee_id,'.$detailID->id.',id,company_id,' . company()->id,
            // A biometric enrolment code, which is letters, digits, hyphens and
            // underscores on these devices -- not a number. 136 of 161 codes on
            // this installation would fail a digits-only rule.
            'employee_card_id' => 'required|regex:/^[A-Za-z0-9_-]{1,50}$/',
            'pan_number' => 'nullable|regex:/^[A-Z]{5}[0-9]{4}[A-Z]$/',
            'employee_ctc' => 'nullable|numeric|min:0',
            'basic_for_payroll' => 'nullable|integer|min:0|max:100',
            'employee_image' => 'nullable|file|mimes:png,jpg,jpeg,svg,bmp|max:2048',
            'first_name'     => 'required|max:50',
            'middle_name'    => 'nullable|max:50',
            'last_name'      => 'required|max:50',
            'personal_email' => 'nullable|email:rfc,strict|max:100',
            'emergency_name'       => 'required|array|min:1',
            'emergency_name.*'     => 'required|max:100',
            'emergency_relation'   => 'required|array|min:1',
            'emergency_relation.*' => 'required|in:Spouse,Father,Mother,Brother,Sister,Friend,Colleague,Other',
            'emergency_number'     => 'required|array|min:1',
            'emergency_number.*'   => 'required|max:20',
            'hourly_rate' => 'nullable|numeric',
            'department' => 'required',
            'designation' => 'required',
            'company_address' => 'required',
            'joining_date' => 'required',
            'last_date' => 'nullable|required_if:status,deactive|date_format:"' . $setting->date_format . '"|after_or_equal:joining_date',
            'date_of_birth' => 'nullable|date_format:"' . $setting->date_format . '"|before_or_equal:'.now($setting->timezone)->toDateString(),
            'probation_end_date' => 'nullable|date_format:"' . $setting->date_format . '"|after_or_equal:joining_date',
            'notice_period_start_date' => 'nullable|required_with:notice_period_end_date|date_format:"' . $setting->date_format . '"',
            'notice_period_end_date' => 'nullable|required_with:notice_period_start_date|date_format:"' . $setting->date_format . '"|after_or_equal:notice_period_start_date',
            'internship_end_date' => 'nullable|date_format:"' . $setting->date_format . '"|after_or_equal:joining_date',
            'contract_end_date' => 'nullable|date_format:"' . $setting->date_format . '"|after_or_equal:joining_date',
            'exp_company'         => 'nullable|array',
            'exp_company.*'       => 'nullable|max:100',
            'exp_location'        => 'nullable|array',
            'exp_location.*'      => 'nullable|max:150',
            'exp_designation'     => 'nullable|array',
            'exp_designation.*'   => 'nullable|max:100',
            'exp_joining'         => 'nullable|array',
            'exp_joining.*'       => 'nullable|date_format:"' . $setting->date_format . '"',
            'exp_leaving'         => 'nullable|array',
            'exp_leaving.*'       => 'nullable|date_format:"' . $setting->date_format . '"',
            'employment_status' => 'nullable|in:confirmed,probation,notice_period,off_boarded,none',
        ];

        if (isArowana()) {
            $rules['email'] = 'required|max:100|email:rfc,strict|unique:users,email,'.$this->route('employee').',id,company_id,' . company()->id;
        }

        if ($detailID) {
            $rules['slack_username'] = 'nullable|unique:employee_details,slack_username,'.$detailID->id.',id,company_id,' . company()->id;
        }
        else {
            $rules['slack_username'] = 'nullable|unique:employee_details,slack_username,null,id,company_id,' . company()->id;
        }

        if (request()->password != '') {
            $rules['password'] = 'required|min:8|max:50';
        }

        if (request()->telegram_user_id) {
            $rules['telegram_user_id'] = 'nullable|unique:users,telegram_user_id,' . $detailID->user_id.',id,company_id,' . company()->id;
        }

        // Documents, bank account checks, monthly salary
        $rules = array_merge($rules, $this->onboardingRules(false, $detailID?->personal_account_number));

        $rules = $this->customFieldRules($rules);

        return $rules;
    }

    public function attributes()
    {
        $attributes = [];

        $attributes = $this->customFieldsAttributes($attributes);

        return $attributes;
    }

    public function messages()
    {
        return $this->onboardingMessages() + [
            'employee_card_id.required' => __('messages.employeeCardIdNumericMax10'),
            'employee_card_id.regex' => __('messages.employeeCardIdNumericMax10'),
            'pan_number.regex' => __('Please enter a valid PAN Card Number in the format ABCDE1234F.'),
            'basic_for_payroll.integer' => __('Basic (For Payroll) must be a whole number.'),
            'basic_for_payroll.min' => __('Basic (For Payroll) cannot be negative.'),
            'basic_for_payroll.max' => __('Basic (For Payroll) cannot be more than 100%.'),
        ];
    }

}
