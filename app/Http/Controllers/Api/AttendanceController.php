<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Attendance\StoreAttendanceRequest;
use App\Models\Attendance;
use Froiden\RestAPI\ApiResponse;
use Illuminate\Http\Request;

class AttendanceController extends Controller
{

    /**
     * Create a new attendance record.
     *
     * @return \Illuminate\Http\Response
     */
    public function store(StoreAttendanceRequest $request)
    {
        $attendance = Attendance::create([
            'company_id' => $request->company_id,
            'user_id' => $request->user_id,
            'clock_in_time' => $request->clock_in_time,
            'clock_in_ip' => $request->clock_in_ip,
            'clock_out_time' => $request->clock_out_time,
            'clock_out_ip' => $request->clock_out_ip,
            'work_from_type' => $request->work_from_type ?? 'office',
            'working_from' => $request->working_from,
            'location_id' => $request->location_id,
            'employee_shift_id' => $request->employee_shift_id,
            'late' => $request->late ?? 'no',
            'half_day' => $request->half_day ?? 'no',
            'latitude' => $request->latitude,
            'longitude' => $request->longitude,
        ]);

        return ApiResponse::make('Attendance created successfully', ['id' => $attendance->id]);
    }

    /**
     * List attendance records, optionally filtered by user and date range.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $validated = $request->validate([
            'company_id' => 'required|integer|exists:companies,id',
            'user_id' => 'nullable|integer|exists:users,id',
            'from_date' => 'nullable|date',
            'to_date' => 'nullable|date|after_or_equal:from_date',
            'limit' => 'nullable|integer|min:1|max:' . config('api.maxLimit', 1000),
            'offset' => 'nullable|integer|min:0',
        ]);

        $limit = (int) ($validated['limit'] ?? config('api.defaultLimit', 10));
        $offset = (int) ($validated['offset'] ?? 0);

        $query = Attendance::where('company_id', $validated['company_id'])
            ->when(!empty($validated['user_id']), fn ($q) => $q->where('user_id', $validated['user_id']))
            ->when(!empty($validated['from_date']), fn ($q) => $q->whereDate('clock_in_time', '>=', $validated['from_date']))
            ->when(!empty($validated['to_date']), fn ($q) => $q->whereDate('clock_in_time', '<=', $validated['to_date']));

        $total = $query->count();

        $attendances = $query->orderByDesc('clock_in_time')
            ->skip($offset)
            ->take($limit)
            ->get();

        return ApiResponse::make(null, $attendances->toArray(), [
            'total' => $total,
            'limit' => $limit,
            'offset' => $offset,
        ]);
    }

    /**
     * Fetch a single attendance record by id.
     *
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $attendance = Attendance::findOrFail($id);

        return ApiResponse::make(null, $attendance->toArray());
    }

}
