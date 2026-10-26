<?php

namespace App\Http\Requests\User;

use App\Http\Requests\CoreRequest;

class UpdateProfile extends CoreRequest
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
        $setting = company();
        return [
            'email' => 'required|email:rfc,strict|unique:users,email,'.$this->route('profile').',id,company_id,' . company()->id,
            'name'  => 'required|max:50',
            'password'  => 'nullable|min:8|max:50',
            'image' => 'image|max:2048',
            'mobile' => 'nullable|numeric',
            // Staff keep their date of birth (birthday greeting); clients and the Super Admin login do not have one.
            'date_of_birth' => [\Illuminate\Validation\Rule::requiredIf(fn () => !in_array('client', user_roles())
                    && user()->employeeDetail && !\App\Services\Access\SystemAccounts::is((int) user()->id)),
                'nullable', 'date_format:' . $setting->date_format, 'before_or_equal:' . now($setting->timezone)->format($setting->date_format)],
            'marriage_anniversary_date' => 'nullable|required_if:marital_status,married|date_format:"' . $setting->date_format . '"|before_or_equal:' . now($setting->timezone)->toDateString(),
            'twitter_id' => 'nullable|unique:users,twitter_id,' . $this->route('profile'),
        ];
    }

    public function messages()
    {
        return [
          'image.image' => 'Profile picture should be an image'
        ];
    }

}
