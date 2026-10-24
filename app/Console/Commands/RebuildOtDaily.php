<?php

namespace App\Console\Commands;

use App\Services\Attendance\WorkRules;
use App\Services\Payroll\OtDaily;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Store (or store again) each employee's daily overtime for a stretch of days
 * - for days before the daily run started writing it, or after changing the
 * Working Hours / Overtime settings. Safe to run again: a day is replaced.
 */
class RebuildOtDaily extends Command
{

    protected $signature = 'ot:rebuild {--from= : first day, Y-m-d (default: when the rules start)} {--to= : last day (default: yesterday)} {--user= : one employee id}';

    protected $description = 'Store daily overtime (ot_daily) from attendance for a range of days';

    public function handle(): int
    {
        @ini_set('memory_limit', '1024M');
        @set_time_limit(0);

        $rules = WorkRules::forCompany((int) DB::table('companies')->value('id'));

        if (!$rules || !$rules->s->ot_enabled) {
            $this->warn('Overtime is switched off (Attendance Settings -> Overtime); nothing to store.');

            return self::SUCCESS;
        }

        $tz = DB::table('companies')->value('timezone') ?: 'Asia/Kolkata';
        $from = Carbon::parse($this->option('from') ?: $rules->s->wh_effective_from, $tz)->startOfDay();
        $to = Carbon::parse($this->option('to') ?: now($tz)->subDay()->toDateString(), $tz)->startOfDay();

        $users = DB::table('users as u')->join('employee_details as ed', 'ed.user_id', '=', 'u.id')
            ->where('u.status', 'active')
            ->when($this->option('user'), fn ($q, $id) => $q->where('u.id', $id))
            ->pluck('u.id');

        $daily = new OtDaily();
        $days = 0;

        foreach ($users as $userId) {
            $days += $daily->rebuild((int) $userId, $from, $to);
        }

        $this->info(sprintf('%d employee(s), %s to %s: %d day(s) with overtime stored.',
            $users->count(), $from->toDateString(), $to->toDateString(), $days));

        return self::SUCCESS;
    }

}
