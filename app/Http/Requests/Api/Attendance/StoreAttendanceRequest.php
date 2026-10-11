<?php

namespace App\Http\Requests\Api\Attendance;

use App\Http\Requests\CoreRequest;

class StoreAttendanceRequest extends CoreRequest
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
            'company_id' => 'required|integer|exists:companies,id',
            'user_id' => 'required|integer|exists:users,id',
            'clock_in_time' => 'required|date',
            'clock_in_ip' => 'required|ip',
            'clock_out_time' => 'nullable|date|after_or_equal:clock_in_time',
            'clock_out_ip' => 'nullable|ip',
            'work_from_type' => 'nullable|in:home,office,other',
            'working_from' => 'required_if:work_from_type,other|nullable|string|max:255',
            'location_id' => 'nullable|integer|exists:company_addresses,id',
            'employee_shift_id' => 'nullable|integer|exists:employee_shifts,id',
            'late' => 'nullable|in:yes,no',
            'half_day' => 'nullable|in:yes,no',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
        ];
    }

}
