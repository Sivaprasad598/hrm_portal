<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Holiday;
use App\Models\Leave;
use App\Models\User;
use App\Models\Company;
use App\Models\ProjectTimeLog;
use App\Events\TimeTrackerReminderEvent;
use Carbon\Carbon;

class SendTimeTracker extends Command
{

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'send-time-tracker';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'send time tracker';

    /**
     *
     */

    public function handle()
    {

        $companies = Company::active()->select(['companies.id as id', 'timezone', 'time'])
            ->join('log_time_for', 'log_time_for.company_id', '=', 'companies.id')
            ->where('tracker_reminder', 1)
            ->get();

        if ($companies->isEmpty()) {
            $this->error('No Company with tracker_reminder enabled');

            return Command::SUCCESS;
        }

        $currentDay = now()->format('Y-m-d');

        foreach ($companies as $company) {

            $startDateTime = Carbon::parse($currentDay . ' ' . $company->time);
            $currentDateTime = now()->timezone($company->timezone);

            if ($currentDateTime->format('H:i') == $startDateTime->format('H:i')) {

                // Check if there's a holiday for the current day and company
                $holiday = Holiday::where('company_id', $company->id)
                    ->where('date', $currentDay)
                    ->exists();

                if ($holiday) {
                    continue;
                }

                $employeeIds = User::allEmployees(null, false, null, $company->id)->pluck('id');

                if ($employeeIds->isEmpty()) {
                    continue;
                }

                $idList = $employeeIds->all();

                $onLeave = Leave::where('leave_date', $currentDay)
                    ->where('status', 'approved')
                    ->whereIn('user_id', $idList)
                    ->pluck('user_id');
                $onLeaveSet = array_flip($onLeave->all());

                $loggedIn = ProjectTimeLog::whereDate('start_time', $currentDay)
                    ->whereIn('user_id', $idList)
                    ->pluck('user_id');
                $loggedSet = array_flip($loggedIn->all());

                $users = User::whereIn('id', $idList)->get()->keyBy('id');

                foreach ($idList as $employeeId) {
                    if (isset($onLeaveSet[$employeeId]) || isset($loggedSet[$employeeId])) {
                        continue;
                    }

                    $user = $users->get($employeeId);

                    if ($user && $user->email_notifications) {
                        event(new TimeTrackerReminderEvent($user));
                    }
                }
            }
        }

        return Command::SUCCESS;
    }

}
