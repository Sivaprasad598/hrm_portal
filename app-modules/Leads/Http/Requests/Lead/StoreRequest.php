<?php

namespace AppModules\Leads\Http\Requests\Lead;

use App\Http\Requests\CoreRequest;
use App\Traits\CustomFieldsRequestTrait;

class StoreRequest extends CoreRequest
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
        $rules = array();

        $rules['client_name'] = 'required';
        $rules['client_email'] = 'nullable|email:rfc,strict|unique:leads,client_email,null,id,company_id,' . company()->id;

        if (request()->has('create_deal') && request()->create_deal == 'on') {
            $rules['name'] = 'required';
            $rules['pipeline'] = 'required';
            $rules['stage_id'] = 'required';
            $rules['close_date'] = 'required';
            $rules['value'] = 'required';
        }

        // Lead contact and company details (QA TC-0039): letters where a name
        // is asked, a 10-digit Indian number, a 6-digit PIN, and no emojis.
        $text = 'regex:' . \App\Rules\NoEmoji::TEXT_ONLY;
        $mobile = 'regex:' . \App\Rules\NoEmoji::INDIAN_MOBILE;
        $rules['client_name'] = array_merge((array) ($rules['client_name'] ?? 'required'), ['max:100', $text]);
        $rules = \App\Rules\NoEmoji::addTo($rules, ['client_email', 'website', 'address']);
        $rules['company_name'] = ['nullable', 'max:150', 'regex:/^[^0-9]*$/u', new \App\Rules\NoEmoji];
        $rules['website'] = array_merge($rules['website'], ['nullable', 'max:200', 'regex:/^(https?:\/\/)?([\w-]+\.)+[a-z]{2,}(\/\S*)?$/i']);
        $rules['mobile'] = ['nullable', $mobile];
        $rules['office'] = ['nullable', $mobile];
        $rules['state'] = ['nullable', 'max:100', 'regex:/^[\pL\s]+$/u'];
        $rules['city'] = ['nullable', 'max:100', 'regex:/^[\pL\s]+$/u'];
        $rules['postal_code'] = ['nullable', 'digits:6'];

        return $this->customFieldRules($rules);

    }

    public function attributes()
    {
        $attributes = [];

        $attributes = $this->customFieldsAttributes($attributes);

        $attributes['client_name'] = __('app.name');
        $attributes['client_email'] = __('app.email');
        $attributes['name'] = __('modules.deal.dealName');
        $attributes['stage_id'] = __('modules.deal.leadStages');

        return $attributes;
    }

    public function messages()
    {
        return [
            'client_name.regex' => 'The name can have letters and spaces only (no numbers or emojis).',
            'company_name.regex' => 'The company name cannot have numbers.',
            'website.regex' => 'Enter a valid website, e.g. www.example.com.',
            'mobile.regex' => 'Enter a 10-digit Indian mobile number, optionally starting with +91.',
            'office.regex' => 'Enter a 10-digit Indian phone number, optionally starting with +91.',
            'state.regex' => 'The state can have letters and spaces only.',
            'city.regex' => 'The city can have letters and spaces only.',
            'postal_code.digits' => 'The postal code must be exactly 6 digits.',
        ];
    }

}
