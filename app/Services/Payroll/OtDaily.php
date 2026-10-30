<?php

namespace App\Services\Payroll;

use App\Services\Attendance\WorkRules;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Overtime stored day by day (ot_daily), from the payroll day count itself
 * (AttendanceDays), so the stored day and the payslip always agree.
 *
 * Written by the daily attendance run as soon as a day is counted, by payroll
 * when a payslip is issued, and by "php artisan ot:rebuild" for past days. A
 * day counted again (attendance corrected) is written again; a day that no
 * longer has overtime loses its row.
 *
 *   bank_minutes = ot minutes x the day's rate (weekday / week-off / holiday),
 *                  kept within the bank limit if one is set
 */
class OtDaily
{

    /** @param array $count AttendanceDays::forRange() for this employee */
    public function store(int $userId, int $companyId, array $count): int
    {
        $rules = WorkRules::forCompany($companyId);

        if (!$rules || !$rules->s->ot_enabled || ($count['basis'] ?? '') !== 'rules') {
            return 0;
        }

        $written = 0;

        // What is stored for these days already, read once: the payroll run
        // calls this for every employee each time it is opened, and a day that
        // has not changed is left alone rather than deleted or written again.
        $dates = array_column($count['days'], 'date');
        $stored = $dates ? DB::table('ot_daily')->where('user_id', $userId)
            ->whereBetween('date', [min($dates), max($dates)])
            ->get(['date', 'day_type', 'first_in', 'last_out', 'worked_minutes', 'ot_minutes', 'rate', 'bank_minutes', 'note'])
            ->keyBy(fn ($row) => substr((string) $row->date, 0, 10)) : collect();
        $gone = [];

        foreach ($count['days'] as $day) {
            if (($day['status'] ?? '') === 'upcoming' || !$rules->appliesOn($day['date'])) {
                continue;
            }

            $minutes = (int) ($day['ot_minutes'] ?? 0);

            if ($minutes <= 0) {
                if ($stored->has($day['date'])) {
                    $gone[] = $day['date'];
                }

                continue;
            }

            $type = $day['ot_type'] ?? 'weekday';
            $rate = $rules->rate($type);
            $bank = (int) round($minutes * $rate);
            $note = null;

            // The bank limit: what the rest of the balance leaves room for.
            if ($rules->s->ot_bank_cap !== null && (float) $rules->s->ot_bank_cap > 0) {
                $others = $this->balanceMinutes($userId, $day['date']);
                $room = max(0, (int) round((float) $rules->s->ot_bank_cap * 60) - $others);

                if ($bank > $room) {
                    $note = sprintf('%s over the %sh bank limit not banked', self::hm($bank - $room), OtBank::fmt((float) $rules->s->ot_bank_cap));
                    $bank = $room;
                }
            }

            $values = [
                'day_type' => $type,
                'first_in' => $day['first_in'] ? Carbon::parse($day['first_in'])->format('Y-m-d H:i:s') : null,
                'last_out' => $day['last_out'] ? Carbon::parse($day['last_out'])->format('Y-m-d H:i:s') : null,
                'worked_minutes' => (int) ($day['worked_minutes'] ?? 0), 'ot_minutes' => $minutes, 'rate' => $rate,
                'bank_minutes' => $bank, 'note' => $note,
            ];

            if ($this->unchanged($stored->get($day['date']), $values)) {
                continue;
            }

            DB::table('ot_daily')->updateOrInsert(['user_id' => $userId, 'date' => $day['date']],
                $values + ['company_id' => $companyId, 'updated_at' => now(), 'created_at' => now()]);
            $written++;
        }

        if ($gone) {
            DB::table('ot_daily')->where('user_id', $userId)->whereIn('date', $gone)->delete();
        }

        return $written;
    }

    /** Whether the stored row already says exactly this. */
    protected function unchanged(?object $row, array $values): bool
    {
        if (!$row) {
            return false;
        }

        foreach ($values as $key => $value) {
            $old = $row->$key;

            $same = is_numeric($value) && is_numeric($old)
                ? abs((float) $value - (float) $old) < 0.0001
                : (string) $value === (string) $old;

            if (!$same) {
                return false;
            }
        }

        return true;
    }

    /** Count a range for one employee and store its overtime. */
    public function rebuild(int $userId, Carbon $from, Carbon $to): int
    {
        $companyId = (int) DB::table('employee_details')->where('user_id', $userId)->value('company_id');

        return $this->store($userId, $companyId, (new AttendanceDays())->forRange($userId, $from, $to));
    }

    /** The bank in minutes without one day: every other stored day and the ledger. */
    protected function balanceMinutes(int $userId, string $exceptDate): int
    {
        $days = (int) DB::table('ot_daily')->where('user_id', $userId)->where('date', '<>', $exceptDate)->sum('bank_minutes');
        $ledger = (float) DB::table('ot_ledger')->where('user_id', $userId)
            ->where(fn ($q) => $q->where('type', OtBank::ADJUSTED)->orWhereNotNull('payslip_id'))
            ->whereIn('type', [OtBank::ADJUSTED, OtBank::COMPENSATED, OtBank::ENCASHED])->sum('hours');

        return $days + (int) round($ledger * 60);
    }

    public static function hm(int $minutes): string
    {
        return sprintf('%dh %02dm', intdiv($minutes, 60), $minutes % 60);
    }

}
