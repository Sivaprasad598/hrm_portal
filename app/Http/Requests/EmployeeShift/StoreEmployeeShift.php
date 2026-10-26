<?php

namespace App\Http\Requests\EmployeeShift;

use Illuminate\Foundation\Http\FormRequest;

class StoreEmployeeShift extends FormRequest
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
        // The simple shift form: fixed timing (start, end, late after) or
        // flexible hours (a full day after so many hours, any time of day).
        $data = [];
        $data['shift_name'] = 'required|max:100';
        $data['shift_short_code'] = 'required|max:10';
        $data['color'] = 'required';
        $data['shift_type'] = 'required|in:strict,flexible';
        $data['clockin_in_day'] = 'nullable|integer|min:0';

        if (!request()->has('office_open_days')) {
            $data['office_open_days'] = 'required';
        }

        if (request()->shift_type == 'flexible') {
            $data['total_shift_hours'] = 'required|numeric|min:1|max:23';
            $data['halfday_shift_hours'] = 'nullable|numeric|min:0.5|max:23';
        }
        else {
            $data['office_start_time'] = 'required';
            $data['office_end_time'] = 'required';
            $data['late_mark_duration'] = 'nullable|integer|min:0|max:240';
        }

        return $data;
    }

}
