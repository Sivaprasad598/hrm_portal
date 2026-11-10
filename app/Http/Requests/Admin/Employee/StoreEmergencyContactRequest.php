<?php

namespace App\Http\Requests\Admin\Employee;

use App\Http\Requests\CoreRequest;
use App\Rules\NoEmoji;

class StoreEmergencyContactRequest extends CoreRequest
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
        $text = 'regex:' . NoEmoji::TEXT_ONLY;

        return [
            'name' => ['required', 'max:50', $text],
            'email' => ['nullable', 'max:100', 'email:rfc,strict', new NoEmoji],
            'mobile' => ['required', 'regex:' . NoEmoji::INDIAN_MOBILE],
            'relationship' => ['required', 'max:50', $text],
            'address' => ['nullable', 'max:500', new NoEmoji],
        ];
    }

    public function messages()
    {
        return [
            'name.regex' => 'The name can have letters and spaces only (no numbers or emojis).',
            'relationship.regex' => 'The relationship can have letters and spaces only (no numbers or emojis).',
            'mobile.regex' => 'Enter a 10-digit Indian mobile number, optionally starting with +91.',
        ];
    }

}
