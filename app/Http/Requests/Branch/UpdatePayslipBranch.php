<?php

namespace App\Http\Requests\Branch;

use App\Http\Requests\CoreRequest;

class UpdatePayslipBranch extends CoreRequest
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
     * Drop blank phone rows (the form always renders at least one row) before
     * validation so they aren't rejected as invalid 10-digit numbers.
     */
    protected function prepareForValidation()
    {
        $this->merge([
            'branch_phone' => array_values(array_filter((array) $this->branch_phone, fn ($value) => trim((string) $value) !== '')),
        ]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'branch_name' => 'required|regex:/^[\pL\s]+$/u|unique:branches,branch_name,' . $this->route('payslip_setting') . ',id,company_id,' . company()->id,
            'address' => 'required|string',
            'branch_email' => 'required|array|min:1',
            'branch_email.*' => 'required|email|regex:/^[a-zA-Z0-9._%+-]+@gmail\.com$/i',
            'branch_phone' => 'nullable|array',
            'branch_phone.*' => 'nullable|digits:10',
        ];
    }

    public function messages()
    {
        return [
            'branch_name.required' => __('messages.branchName'),
            'branch_name.regex' => __('messages.branchAlpha'),
            'branch_name.unique' => __('messages.branchUniq'),
            'address.required' => __('messages.branchAddressRequired'),
            'branch_email.required' => __('messages.branchEmailRequired'),
            'branch_email.*.email' => __('messages.branchEmailInvalid'),
            'branch_email.*.regex' => __('messages.branchEmailInvalid'),
            'branch_phone.*.digits' => __('messages.branchPhoneDigits'),
        ];
    }

}
