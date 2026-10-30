<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Enums\MaritalStatus;
use App\Helper\Files;
use App\Models\EmployeeDetails;
use App\Models\Team;
use App\Services\Access\AssignedModules;
use App\Services\Attendance\ShiftResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * The signed-in employee's own profile.
 *
 * Update changes only the personal details an employee keeps up to date on
 * the website's Profile page, and only the fields sent. Name, e-mail (the
 * sign-in), department, designation, manager and dates of employment stay
 * with HR.
 */
class ProfileController extends MobileController
{
    /** Module => [stable key for the app, name to show], in the order the app lists them. */
    const MODULES = [
        'attendance' => ['attendance', 'Attendance'],
        'leaves' => ['leave_management', 'Leave Management'],
        'payroll' => ['payroll', 'Payroll'],
        'assets' => ['asset_management', 'Asset Management'],
        'holidays' => ['holidays', 'Holidays'],
        'events' => ['events', 'Events'],
        'biometric' => ['biometric', 'Biometric'],
        'employees' => ['employee_management', 'Employee Management'],
        'notices' => ['notice_board', 'Notice Board'],
        'appreciation' => ['appreciation', 'Appreciation'],
    ];

    public function update(Request $request)
    {
        $tz = $this->tz();
        $request->validate([
            'mobile' => 'nullable|numeric|digits_between:6,15',
            'country_phonecode' => 'nullable|integer|min:1|max:9999',
            'gender' => 'nullable|in:male,female,others',
            'date_of_birth' => 'sometimes|required|date_format:Y-m-d|before_or_equal:' . now($tz)->toDateString(),
            'marital_status' => ['nullable', Rule::in(array_map(fn ($c) => $c->value, MaritalStatus::cases()))],
            'marriage_date' => 'nullable|required_if:marital_status,married|date_format:Y-m-d|before_or_equal:' . now($tz)->toDateString(),
            'address' => 'nullable|string|max:500',
            'photo' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
            'remove_photo' => 'nullable|boolean',
        ]);

        $user = $this->me();
        $detail = EmployeeDetails::where('user_id', $user->id)->firstOrFail();

        // A wedding date only goes with "married": sent now, or already stored.
        $status = $request->exists('marital_status') ? $request->marital_status : $detail->marital_status?->value;

        if ($request->filled('marriage_date') && $status !== 'married') {
            $message = 'Send marital_status = married with the marriage date.';

            return $this->fail($message, 422, ['marriage_date' => [$message]]);
        }

        foreach (['mobile', 'country_phonecode', 'gender'] as $field) {
            if ($request->exists($field)) {
                $user->{$field} = $request->input($field);
            }
        }

        if ($request->boolean('remove_photo') && $user->image) {
            Files::deleteFile($user->image, 'avatar');
            $user->image = null;
        }

        if ($request->hasFile('photo')) {
            Files::deleteFile($user->image, 'avatar');
            $user->image = Files::uploadLocalOrS3($request->file('photo'), 'avatar', 300);
        }

        foreach (['date_of_birth' => 'date_of_birth', 'address' => 'address', 'marital_status' => 'marital_status', 'marriage_date' => 'marriage_anniversary_date'] as $field => $column) {
            if ($request->exists($field)) {
                $detail->{$column} = $request->input($field);
            }
        }

        // Not married: no wedding date.
        if ($request->exists('marital_status') && $request->marital_status !== 'married') {
            $detail->marriage_anniversary_date = null;
        }

        DB::transaction(function () use ($user, $detail) {
            $user->save();
            $detail->save();
        });

        return $this->show('Profile updated.');
    }

    public function show(string $message = 'OK')
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
            'mobile_number' => $user->mobile,               // as stored, for the edit form
            'country_phonecode' => $user->country_phonecode,
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
            'modules' => $this->modules(),
        ], $message);
    }

    /**
     * The modules this person has: the ones behind their web sidebar, plus what
     * every employee gets in the app - own attendance and payslips, and the
     * holidays and events on the calendar - where the module is switched on.
     */
    private function modules(): array
    {
        $enabled = user_modules();
        $have = array_flip(AssignedModules::forUser());
        $have['payroll'] = true;   // own payslips

        foreach (['attendance', 'holidays', 'events'] as $module) {
            if (in_array($module, $enabled, true)) {
                $have[$module] = true;
            }
        }

        $known = array_intersect_key(self::MODULES, $have);
        $other = array_diff_key($have, self::MODULES);
        ksort($other);

        return collect(array_merge(array_keys($known), array_keys($other)))
            ->map(fn ($module) => [
                'key' => self::MODULES[$module][0] ?? $module,
                'name' => self::MODULES[$module][1] ?? ucwords(str_replace(['_', '-'], ' ', $module)),
            ])->values()->all();
    }
}
