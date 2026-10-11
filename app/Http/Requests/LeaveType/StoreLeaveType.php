<?php

namespace App\Http\Requests\LeaveType;

use App\Http\Requests\CoreRequest;

class StoreLeaveType extends CoreRequest
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
        $rules = [
            'type_name' => 'required',
            'color' => 'required',
            'gender' => 'required',
            'marital_status' => 'required',
            'department' => 'required',
            'designation' => 'required',
            'role' => 'required',
            'code' => 'nullable|string|max:10',
            'carry_forward_max' => 'nullable|numeric|min:0|max:9999',
            'carry_forward_excess' => 'nullable|in:discard,encash',
            'unused_leave' => 'nullable|in:carry forward,lapse,paid',
        ];

        if(!is_null(request('effective_after'))){
            $rules['effective_after'] = 'numeric|min:1';
        }

        if(!is_null(request('leavetype'))){
            $rules['leavetype'] = 'required';
        }

        if(!is_null(request('leavetype')) && request('leavetype') == 'yearly'){
            $rules['monthly_limit'] = 'required|numeric|min:0';
            $rules['yearly_leave_number'] = 'required|numeric|min:0';
        }

        if(!is_null(request('leavetype')) && request('leavetype') == 'monthly'){
            $rules['monthly_leave_number'] = 'required|numeric|min:0';
        }

        return $rules;
    }

}
