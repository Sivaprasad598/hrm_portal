<?php

namespace App\Http\Requests\Branch;

use App\Http\Requests\CoreRequest;

class StoreBranch extends CoreRequest
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
            'branch_name' => 'required|regex:/^[\pL\s]+$/u|unique:branches,branch_name,null,id,company_id,' . company()->id
        ];
    }

    public function messages()
    {
        return [
            'branch_name.required' => __('messages.branchName'),
            'branch_name.regex' => __('messages.branchAlpha'),
            'branch_name.unique' => __('messages.branchUniq'),
        ];
    }

}
