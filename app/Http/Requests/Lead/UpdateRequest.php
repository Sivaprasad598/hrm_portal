<?php

namespace App\Http\Requests\Lead;

use App\Http\Requests\CoreRequest;
use App\Traits\CustomFieldsRequestTrait;

class UpdateRequest extends CoreRequest
{
    use CustomFieldsRequestTrait;

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
            'client_name' => 'required',
            'lead_project_id' => 'required|exists:lead_projects,id,company_id,' . company()->id,
            'client_email' => 'nullable|email:rfc,strict|unique:leads,client_email,'.$this->route('lead_contact').',id,company_id,' . company()->id,
        ];

        // Deal section of the edit form (same rules as the create form)
        if (request()->create_deal == 'on') {
            $rules['name'] = 'required';
            $rules['pipeline'] = 'required';
            $rules['stage_id'] = 'required';
            $rules['close_date'] = 'required';
            $rules['value'] = 'required';
        }

        $rules = $this->customFieldRules($rules);

        return $rules;
    }

    public function attributes()
    {
        $attributes = [];

        $attributes = $this->customFieldsAttributes($attributes);

        $attributes['client_name'] = __('app.name');
        $attributes['lead_project_id'] = 'Project Name';
        $attributes['client_email'] = __('app.email');
        $attributes['name'] = __('modules.deal.dealName');
        $attributes['stage_id'] = __('modules.deal.leadStages');

        return $attributes;
    }

}
