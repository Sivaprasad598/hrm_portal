<?php

namespace App\Http\Requests\Admin\ProjectLabel;

use App\Http\Requests\CoreRequest;
use Illuminate\Validation\Rule;

class UpdateRequest extends CoreRequest
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
            'label_name' => [
                'required',
                Rule::unique('project_label_list')->where(function($query){
                    $query->where('id', '<>', $this->route('project_label'));
                })
            ],
            'color' => 'required|max:50',
        ];
    }

}
