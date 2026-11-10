<?php

namespace App\Console\Commands;

use App\Models\EmployeeDetails;
use App\Models\Holiday;
use App\Services\Leaves\DayOffLeaves;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Once, for leave already on a day that became a holiday or a Day Off before
 * that was handled (App\Services\Leaves\DayOffLeaves does it from now on).
 * Run with --dry-run first to see what would change.
 */
class ReleaseDayOffLeaves extends Command
{
    protected $signature = 'leaves:release-day-off {--dry-run : List them without changing anything}';

    protected $description = 'Withdraw approved or pending leave that falls on a holiday or a Day Off, and fix the balances';

    public function handle(): int
    {
        $found = [];

        // Leave on a Day Off on the employee's own roster
        $dayOff = DB::table('leaves as l')
            ->join('employee_shift_schedules as s', function ($join) {
                $join->on('s.user_id', '=', 'l.user_id')->whereRaw('DATE(s.date) = DATE(l.leave_date)');
            })
            ->join('employee_shifts as e', 'e.id', '=', 's.employee_shift_id')
            ->whereIn('l.status', ['approved', 'pending'])
            ->whereRaw('LOWER(TRIM(e.shift_name)) = ?', [DayOffLeaves::DAY_OFF])
            ->get(['l.id', 'l.user_id', DB::raw('DATE(l.leave_date) as day')]);

        foreach ($dayOff as $row) {
            $found[$row->day . '|' . $row->user_id] = [$row->user_id, $row->day, 'a Day Off'];
        }

        // Leave on a holiday that covers the employee
        $onHolidays = DB::table('leaves as l')
            ->join('holidays as h', function ($join) {
                $join->on('h.company_id', '=', 'l.company_id')->whereRaw('DATE(h.date) = DATE(l.leave_date)');
            })
            ->whereIn('l.status', ['approved', 'pending'])
            ->get(['l.id', 'l.user_id', 'h.id as holiday_id', 'h.occassion', DB::raw('DATE(l.leave_date) as day')]);

        foreach ($onHolidays as $row) {
            $detail = EmployeeDetails::withoutGlobalScopes()->where('user_id', $row->user_id)->first();

            if ($detail && Holiday::withoutGlobalScopes()->whereKey($row->holiday_id)->applicableTo($detail)->exists()) {
                $found[$row->day . '|' . $row->user_id] = [$row->user_id, $row->day, 'the holiday "' . ($row->occassion ?: 'Holiday') . '"'];
            }
        }

        if (!$found) {
            $this->info('No leave falls on a holiday or a Day Off.');

            return self::SUCCESS;
        }

        $names = DB::table('users')->whereIn('id', array_column($found, 0))->pluck('name', 'id');
        $this->table(['Employee', 'Date', 'Because of'], array_map(fn ($f) => [$names[$f[0]] ?? $f[0], $f[1], $f[2]], array_values($found)));

        if ($this->option('dry-run')) {
            $this->info(count($found) . ' day(s) would be withdrawn (dry run, nothing changed).');

            return self::SUCCESS;
        }

        $done = 0;

        foreach ($found as [$userId, $day, $why]) {
            $done += DayOffLeaves::release([$userId], $day, $why);
        }

        $this->info($done . ' leave day(s) withdrawn and balances recalculated.');

        return self::SUCCESS;
    }
}
