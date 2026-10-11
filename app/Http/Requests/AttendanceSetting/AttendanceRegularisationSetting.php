<?php

namespace App\Http\Requests\AttendanceSetting;

use Illuminate\Foundation\Http\FormRequest;

class AttendanceRegularisationSetting extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = [
            'times_adjustment_allowed' => 'nullable|in:1,2,3',
            'last_days' => 'required_if:times_adjustment_allowed,1|nullable|integer',
            'adjustment_total_times' => 'required_if:times_adjustment_allowed,2|nullable|integer',
            'before_day_of_month' => 'required_if:times_adjustment_allowed,3|nullable|integer',
        ];

        return $rules;

    }

    public function messages()
    {
        return [
            'last_days.required_if' => 'The above field is required.',
            'adjustment_total_times.required_if' => 'The above field is required.',
            'before_day_of_month.required_if' => 'The above field is required.',
        ];
    }

}
