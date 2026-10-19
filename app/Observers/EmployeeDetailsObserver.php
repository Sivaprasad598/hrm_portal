<?php

namespace App\Observers;

use App\Enums\MaritalStatus;
use Illuminate\Support\Carbon;
use App\Models\EmployeeDetails;
use App\Models\EmployeeLeaveQuota;
use App\Events\NewEmployeeAddedEvent;
use App\Events\NewUserSlackEvent;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;

class EmployeeDetailsObserver
{

    public function saving(EmployeeDetails $detail)
    {
        if (!isRunningInConsoleOrSeeding() && auth()->check()) {
            $detail->last_updated_by = user()->id;
        }
    }

    public function creating(EmployeeDetails $detail)
    {
        if (!isRunningInConsoleOrSeeding() && auth()->check()) {
            $detail->added_by = user()->id;
        }

        $detail->company_id = $detail->user->company_id;

        if (is_null($detail->marital_status)) {
            $detail->marital_status = MaritalStatus::Single;
        }

    }

    public function created(EmployeeDetails $detail)
    {
        $settings = company();

        $user = $detail->user;

        Artisan::call('app:recalculate-leaves-quotas ' . $detail->company_id . ' ' . $user->id);

        // The first employment period, from the joining date.
        app(\App\Services\Employment\EmploymentPeriods::class)->syncFromRecord((int) $detail->user_id);

        event(new NewUserSlackEvent($user));

        event(new NewEmployeeAddedEvent($user));


    }

    public function updated(EmployeeDetails $detail)
    {
        if (user() && $detail->isDirty('joining_date'))  {
            Artisan::call('app:recalculate-leaves-quotas ' . $detail->company_id . ' ' . $detail->user_id);
        }

        // The current employment period follows the record: joining and exit
        // dates, designation, department, branch, codes.
        if ($detail->isDirty(['joining_date', 'last_date', 'designation_id', 'department_id', 'branch_id', 'employee_id', 'employee_card_id'])) {
            app(\App\Services\Employment\EmploymentPeriods::class)->syncFromRecord((int) $detail->user_id);
        }

    }

}
