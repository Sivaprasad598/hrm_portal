<?php

namespace App\Http\Requests\Admin\Employee;

use App\Http\Requests\CoreRequest;
use App\Traits\CustomFieldsRequestTrait;

class StoreRequest extends CoreRequest
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
     * basic_for_payroll is stored as decimal(16,2); normalize any
     * decimal-formatted input to a plain integer before validation.
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
        $setting = company();
        $rules = [
            'employee_id' => 'required|unique:employee_details,employee_id,null,id,company_id,' . company()->id.'|max:100',
            // A biometric enrolment code, which is letters, digits, hyphens and
            // underscores on these devices -- not a number. 136 of 161 codes on
            // this installation would fail a digits-only rule.
            'employee_card_id' => 'required|regex:/^[A-Za-z0-9_-]{1,50}$/',
            'pan_number' => 'required|regex:/^[A-Z]{5}[0-9]{4}[A-Z]$/',
            'employee_ctc' => 'nullable|numeric|min:0',
            'basic_for_payroll' => 'nullable|integer|min:0|max:100',
            'employee_image' => 'nullable|file|mimes:png,jpg,jpeg,svg,bmp|max:2048',
            'first_name'     => 'required|max:50',
            'middle_name'    => 'nullable|max:50',
            'last_name'      => 'required|max:50',
            'email'          => 'required|email:rfc,strict|unique:users,email,null,id,company_id,' . company()->id.'|max:100',
            'personal_email' => 'nullable|email:rfc,strict|max:100',
            'password' => 'required|min:8|max:50',
            'slack_username' => 'nullable|unique:employee_details,slack_username,null,id,company_id,' . company()->id.'|max:30',
            'hourly_rate' => 'nullable|numeric',
            'joining_date' => 'required',
            'last_date' => 'nullable|date_format:"' . $setting->date_format . '"|after_or_equal:joining_date',
            'date_of_birth' => 'nullable|date_format:"' . $setting->date_format . '"|before_or_equal:'.now($setting->timezone)->toDateString(),
            'department' => 'required',
            'designation' => 'required',
            'company_address' => 'required',
            'total_experience'    => 'nullable|numeric|min:0',
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
            'pf_account_number' => ['nullable', 'max:30', 'regex:/^[A-Za-z0-9\/\-]+$/'],
            'esi_number' => ['nullable', 'regex:/^[0-9]+$/'],
            'personal_account_number' => ['required', 'regex:/^[0-9]{9,18}$/'],
            'ifsc_code' => ['required', 'regex:/^[A-Z]{4}0[A-Z0-9]{6}$/'],
            // Passport details are optional; what is given must be valid.
            'passport_number' => ['nullable', 'regex:/^[A-Z][0-9]{7}$/'],
            'passport_date_of_issue' => 'nullable|date_format:"' . $setting->date_format . '"',
            'passport_place_of_issue' => ['nullable', 'max:100', 'regex:/^[A-Za-z,.\- ]+$/'],
            'passport_valid_up_to' => 'nullable|date_format:"' . $setting->date_format . '"'
                . ($this->filled('passport_date_of_issue') ? '|after:passport_date_of_issue' : ''),
            'reported_duty_day' => ['required', 'integer', 'between:1,31'],
            'reported_duty_month' => ['required', 'regex:/^(?:[1-9]|1[0-2]|january|jan|february|feb|march|mar|april|apr|may|june|jun|july|jul|august|aug|september|sep|sept|october|oct|november|nov|december|dec)$/i'],
            'reported_duty_year' => ['required', 'digits:4', 'integer', 'between:1900,2100'],
            'candidate_signature' => ['required', 'max:100', 'regex:/^[A-Za-z ]+$/'],
            'hr_signature' => ['required', 'max:100', 'regex:/^[A-Za-z ]+$/'],
            'hr_comments' => ['nullable', 'max:500', 'regex:/^[A-Za-z0-9 ,\.\-\(\)\r\n]*$/'],
            'emergency_name'      => 'required|array|min:1',
            'emergency_name.*'    => 'required|max:100',
            'emergency_relation'  => 'required|array|min:1',
            'emergency_relation.*' => 'required|in:Spouse,Father,Mother,Brother,Sister,Friend,Colleague,Other',
            'emergency_number'    => 'required|array|min:1',
            'emergency_number.*'  => 'required|max:20',
            'probation_end_date' => 'nullable|date_format:"' . $setting->date_format . '"|after_or_equal:joining_date',
            'notice_period_start_date' => 'nullable|required_with:notice_period_end_date|date_format:"' . $setting->date_format . '"',
            'notice_period_end_date' => 'nullable|required_with:notice_period_start_date|date_format:"' . $setting->date_format . '"|after_or_equal:notice_period_start_date',
            'internship_end_date' => 'nullable|date_format:"' . $setting->date_format . '"|after_or_equal:joining_date',
            'contract_end_date' => 'nullable|date_format:"' . $setting->date_format . '"|after_or_equal:joining_date',
            'employment_status' => 'nullable|in:confirmed,probation,notice_period,off_boarded,none',
        ];

        if (request()->telegram_user_id) {
            $rules['telegram_user_id'] = 'nullable|unique:users,telegram_user_id,null,id,company_id,' . company()->id;
        }

        // Documents, bank account checks, monthly salary
        $rules = array_merge($rules, $this->onboardingRules(true));

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
            'employee_id.required'      => __('Employee ID is required.'),
            'employee_id.unique'        => __('This Employee ID is already taken.'),
            'first_name.required'       => __('First Name is required.'),
            'last_name.required'        => __('Last Name is required.'),
            'email.required'            => __('Employee Email is required.'),
            'email.email' => __('Please enter a valid email address.'),
            'email.unique' => __('This email is already registered.'),
            'password.required' => __('Password is required.'),
            'password.min' => __('Password must be at least 8 characters.'),
            'designation.required' => __('Designation is required.'),
            'department.required' => __('Department is required.'),
            'employee_card_id.required' => __('messages.employeeCardIdNumericMax10'),
            'employee_card_id.regex' => __('messages.employeeCardIdNumericMax10'),
            'pan_number.required' => __('Please enter a valid PAN Card Number.'),
            'pan_number.regex' => __('Please enter a valid PAN Card Number in the format ABCDE1234F.'),
            'joining_date.required' => __('Joining Date is required.'),
            'date_of_birth.date_format' => __('Please enter a valid Date of Birth.'),
            'date_of_birth.before_or_equal' => __('Date of Birth cannot be a future date.'),
            'employee_ctc.numeric' => __('Employee CTC must be a valid number.'),
            'employee_ctc.min' => __('Employee CTC cannot be negative.'),
            'basic_for_payroll.integer' => __('Basic (For Payroll) must be a whole number.'),
            'basic_for_payroll.min' => __('Basic (For Payroll) cannot be negative.'),
            'basic_for_payroll.max' => __('Basic (For Payroll) cannot be more than 100%.'),
            'pf_account_number.regex' => __('PF Account Number can only contain letters, numbers, / and -'),
            'esi_number.regex' => __('ESI (IP Number) must contain numbers only.'),
            'personal_account_number.required' => __('Personal Account Number is required.'),
            'personal_account_number.regex' => __('Personal Account Number must be 9 to 18 digits.'),
            'ifsc_code.required' => __('IFSC Code is required.'),
            'ifsc_code.regex' => __('IFSC Code must be in format SBIN0001234.'),
            'passport_number.required' => __('Passport Number is required.'),
            'passport_number.regex' => __('Passport Number must be one uppercase letter followed by 7 digits (e.g. A1234567).'),
            'passport_date_of_issue.required' => __('Passport Date of Issue is required.'),
            'passport_place_of_issue.required' => __('Passport Place of Issue is required.'),
            'passport_place_of_issue.regex' => __('Passport Place of Issue can only contain alphabets, spaces, commas, hyphens and periods.'),
            'passport_valid_up_to.required' => __('Passport Valid Up To is required.'),
            'passport_valid_up_to.after' => __('Passport Valid Up To must be greater than Passport Date of Issue.'),
            'reported_duty_day.required' => __('Reported to Duty On day is required.'),
            'reported_duty_day.integer' => __('Day must be a number.'),
            'reported_duty_day.between' => __('Day must be between 1 and 31.'),
            'reported_duty_month.required' => __('Reported to Duty On month is required.'),
            'reported_duty_month.regex' => __('Month must be a valid month name (January to December) or month number (1 to 12).'),
            'reported_duty_year.required' => __('Reported to Duty On year is required.'),
            'reported_duty_year.digits' => __('Year must be a valid 4-digit year.'),
            'reported_duty_year.between' => __('Year must be between 1900 and 2100.'),
            'candidate_signature.required' => __('Signature of the Candidate is required.'),
            'candidate_signature.regex' => __('Signature of the Candidate can only contain alphabets and spaces.'),
            'hr_signature.required' => __('Signature of the HR Representative is required.'),
            'hr_signature.regex' => __('Signature of the HR Representative can only contain alphabets and spaces.'),
            'hr_comments.max' => __('HR Comments cannot exceed 500 characters.'),
            'hr_comments.regex' => __('HR Comments can only contain alphabets, numbers, spaces, commas, periods, hyphens, and parentheses.'),
        ];
    }

}
