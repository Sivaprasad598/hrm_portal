<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Models\Team;
use App\Services\Attendance\ShiftResolver;
use Illuminate\Support\Facades\DB;

/**
 * The signed-in employee's own profile.
 */
class ProfileController extends MobileController
{
    public function show()
    {
        $user = $this->me()->load('employeeDetail.designation', 'emergencyContacts');
        $detail = $user->employeeDetail;
        $team = $detail?->department_id ? Team::find($detail->department_id) : null;
        $parent = $team?->parent_id ? Team::find($team->parent_id) : null;
        $manager = $detail?->reporting_to ? DB::table('users')->where('id', $detail->reporting_to)->first(['id', 'name']) : null;
        $date = fn ($v, $format = 'Y-m-d') => $v ? \Carbon\Carbon::parse($v)->format($format) : null;
        $today = now($this->tz())->toDateString();
        $shift = ShiftResolver::shared($user->company_id)->shiftOn($user->id, $today);

        return $this->ok([
            'id' => $user->id,
            'name' => $user->name,
            'employee_id' => $detail?->employee_id,
            'photo_url' => $user->image_url,
            'email' => $user->email,
            'mobile' => $user->mobile ? trim(($user->country_phonecode ? '+' . $user->country_phonecode . ' ' : '') . $user->mobile) : null,
            'gender' => $user->gender,
            'designation' => $detail?->designation?->name,
            // A department with a parent is a sub division of it.
            'division' => $parent ? $parent->team_name : $team?->team_name,
            'sub_division' => $parent ? $team?->team_name : null,
            'reporting_manager' => $manager ? ['id' => $manager->id, 'name' => $manager->name] : null,
            'employment_type' => $detail?->employment_type,
            'date_of_joining' => $date($detail?->joining_date),
            'probation_end_date' => $date($detail?->probation_end_date),
            'date_of_birth' => $date($detail?->date_of_birth),
            'marital_status' => $detail?->marital_status?->value,
            'marriage_date' => $date($detail?->marriage_anniversary_date),
            'address' => $detail?->address,
            'emergency_contacts' => $user->emergencyContacts->map(fn ($c) => [
                'name' => $c->name,
                'relation' => $c->relation,
                'mobile' => $c->mobile,
                'email' => $c->email,
            ])->values(),
            'shift_today' => $shift ? [
                'name' => $shift->shift_name,
                'type' => $shift->shift_type ?? null,
                'start' => $shift->office_start_time ? substr((string) $shift->office_start_time, 0, 5) : null,
                'end' => $shift->office_end_time ? substr((string) $shift->office_end_time, 0, 5) : null,
                'working_day' => ShiftResolver::worksOn($shift, $today),
            ] : null,
        ]);
    }
}
