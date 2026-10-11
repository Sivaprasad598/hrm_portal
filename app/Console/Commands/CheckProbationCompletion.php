<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Models\EmployeeDetails;
use App\Models\User;
use App\Notifications\ProbationReviewPendingAdmin;
use App\Notifications\ProbationReviewPendingEmployee;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CheckProbationCompletion extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'check-probation-completion';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Flag employees whose probation period has completed (joining date + configured probation months) as pending admin review, ready to appear on the Probation Period Requests page.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting probation completion check...');

        Company::active()->select(['id', 'timezone'])->chunk(50, function ($companies) {
            foreach ($companies as $company) {
                $this->processCompany($company);
            }
        });

        $this->info('Probation completion check completed.');
        return Command::SUCCESS;
    }

    /**
     * Check every employee still on probation for this company against the
     * company's configured probation duration, and flag the ones whose
     * calculated completion date has arrived as pending admin review.
     */
    private function processCompany(Company $company): void
    {
        // The most recently configured probation duration is treated as the
        // company's current probation policy (Probation Period Settings is a
        // list so an admin can keep old entries around, but only the latest
        // one drives this calculation).
        $setting = DB::table('probation_period_settings')
            ->where('company_id', $company->id)
            ->orderByDesc('id')
            ->first();

        if (!$setting) {
            return;
        }

        $today = now($company->timezone)->startOfDay();

        $employees = EmployeeDetails::with('user')
            ->where('employee_details.company_id', $company->id)
            ->where('employee_details.employment_status', 'probation')
            ->whereNull('employee_details.probation_review_status')
            ->whereNotNull('employee_details.joining_date')
            ->get();

        if ($employees->isEmpty()) {
            return;
        }

        // Fetched once per company rather than once per employee — every
        // employee flagged below notifies the same set of admins.
        $admins = User::allAdmins($company->id);

        foreach ($employees as $employeeDetails) {
            $this->checkAndFlag($employeeDetails, $setting->months, $today, $admins);
        }
    }

    /**
     * Date of Joining + Probation Period (months) = Probation Completion Date.
     * Once that date arrives, the employee moves into "pending" — an admin
     * still has to Approve (confirms + notifies) or Reject on the Probation
     * Period Requests page before anything else changes. The employee and
     * every admin are notified (database + email) the moment this happens,
     * so nobody has to notice the new row on the Probation Period Requests
     * page on their own.
     */
    private function checkAndFlag(EmployeeDetails $employeeDetails, int $months, Carbon $today, $admins): void
    {
        $completionDate = Carbon::parse($employeeDetails->joining_date)->addMonths($months)->startOfDay();

        if ($completionDate->gt($today)) {
            return;
        }

        try {
            $employeeDetails->probation_review_status = 'pending';
            $employeeDetails->save();
        } catch (\Exception $e) {
            $this->error("Failed to flag probation review for employee_details ID {$employeeDetails->id}: " . $e->getMessage());
            return;
        }

        if (!$employeeDetails->user) {
            return;
        }

        try {
            $employeeDetails->user->notify(new ProbationReviewPendingEmployee($employeeDetails, $completionDate));

            foreach ($admins as $admin) {
                $admin->notify(new ProbationReviewPendingAdmin($employeeDetails, $completionDate));
            }
        } catch (\Exception $e) {
            $this->error("Failed to send probation-pending notifications for employee_details ID {$employeeDetails->id}: " . $e->getMessage());
        }
    }
}
