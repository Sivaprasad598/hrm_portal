<?php

namespace App\Http\Requests\AttendanceRegularisation;

use Illuminate\Foundation\Http\FormRequest;

class StoreAttendanceRegularisation extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
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
        return [
            'clock_in_time' => 'required',
            'other_location'  => 'required_if:work_from_type,==,other',
            'reason' => 'required'
        ];
    }
}
