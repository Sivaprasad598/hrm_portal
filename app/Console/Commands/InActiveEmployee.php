<?php

namespace App\Console\Commands;

use App\Models\EmployeeDetails;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Modules\Onboarding\Entities\OnboardingCompletedTask;

class InActiveEmployee extends Command
{

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'inactive-employee';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'The employee is set to inactive if he exit the company';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $todayDate = Carbon::today();
            
            EmployeeDetails::with('user')
                ->where(function ($query) use ($todayDate) {
                    $query->whereDate('last_date', '<=', $todayDate)
                        ->orWhereDate('notice_period_end_date', '<=', $todayDate);
                })
                ->whereHas('user', function ($query) {
                    $query->where('status', 'active');
                })
                ->chunk(50, function ($employees) use ($todayDate) {
                    foreach ($employees as $employee) {

                        if (module_enabled('Onboarding')) {
                            // Check if offboarding steps are pending
                            $offboardingPending = OnboardingCompletedTask::where('employee_id', $employee->user_id)
                            ->where('type', 'offboard')
                            ->where('status', 'pending')
                            ->exists();
    
                            if (!$offboardingPending) {
                                // All offboarding steps are completed, change status to inactive and disable login
                                $this->deactivateEmployee($employee);
                            }
                        } else {
                            // If Onboarding module is not enabled, change status to inactive without offboarding check
                            $this->deactivateEmployee($employee);
                        }
                    }
                });
    }

    /**
     * Deactivate the employee and destroy their sessions.
     *
     * @param EmployeeDetails $employee
     */
    protected function deactivateEmployee(EmployeeDetails $employee)
    {
        $employee->user->status = 'deactive';
        $employee->user->login = 'disable';
        $employee->user->inactive_date = now();

        if (empty($employee->last_date) && !empty($employee->notice_period_end_date)) {
            $employee->last_date = $employee->notice_period_end_date;
            $employee->save();
        }

        $employee->user->save();

    }

}
