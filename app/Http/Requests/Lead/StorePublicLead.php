<?php

namespace App\Http\Requests\Lead;

use App\Models\Company;
use App\Http\Requests\CoreRequest;
use App\Traits\CustomFieldsRequestTrait;

class StorePublicLead extends CoreRequest
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
        $company = Company::findOrFail($this->request->get('company_id'));
        $rules = array();
        
        // Get lead form fields configuration
        $leadFormFields = \App\Models\LeadCustomForm::where('company_id', $company->id)->get();
        
        // Build validation rules based on form configuration
        foreach ($leadFormFields as $field) {
            if ($field->status == 'active') {
                $fieldRules = [];
                
                // Add required rule if field is marked as required
                if ($field->required == 1) {
                    $fieldRules[] = 'required';
                } else {
                    $fieldRules[] = 'nullable';
                }
                
                // Add specific validation rules based on field type
                if ($field->field_name == 'email') {
                    $fieldRules[] = 'email:rfc,strict';
                    $fieldRules[] = 'unique:leads,client_email,null,id,company_id,' . $company->id;
                    $fieldRules[] = 'unique:users,email,null,id,company_id,' . $company->id;
                }
                
                $rules[$field->field_name] = implode('|', $fieldRules);
            }
        }

        $rules = $this->customFieldRules($rules);

        if(global_setting()->google_recaptcha_status == 'active' && global_setting()->ticket_form_google_captcha == 1 && (global_setting()->google_recaptcha_v2_status == 'active')){
            $rules['g-recaptcha-response'] = 'required';
        }

        return $rules;
    }

    public function attributes()
    {
        $attributes = [];

        $attributes = $this->customFieldsAttributes($attributes);

        return $attributes;
    }

}
