<?php

namespace App\Services\Payroll;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Past overtime: the OT bank as it was kept by hand before the system counted
 * it, so every employee starts with what they already hold.
 *
 *   monthly (the usual)  one row per salary month: hours banked, used for LOP, encashed
 *   day by day           a month entered as days instead (hours banked only)
 *
 * A month is one or the other, never both. Everything sits before the go-live
 * date - the day the system starts storing overtime itself (Working Hours,
 * "applies from") - so nothing is counted twice. Hours are bank hours, as the
 * system keeps them: overtime already times its rate.
 *
 * Each row's net (banked - used - encashed) is part of the balance and of
 * what every later period brings forward (OtBank).
 */
class OtPast
{

    public const FIELDS = ['ot_hours', 'raw_hours', 'used_hours', 'used_days', 'encashed_hours', 'encashed_amount', 'note'];

    /** The first day the system counts overtime; past rows must be before it. */
    public static function goLive(?int $companyId = null): Carbon
    {
        $from = DB::table('attendance_settings')->when($companyId, fn ($q) => $q->where('company_id', $companyId))->value('wh_effective_from');

        return $from ? Carbon::parse($from)->startOfDay() : PayPeriod::containing(now())->start->copy()->startOfDay();
    }

    /** Net hours of the past rows (before a date, or all). */
    public static function net(int $userId, ?string $before = null): float
    {
        return round((float) DB::table('ot_past')->where('user_id', $userId)
            ->when($before, fn ($q) => $q->where('ref_date', '<', $before))
            ->sum(DB::raw('ot_hours - used_hours - encashed_hours')), 2);
    }

    public function rows(int $userId)
    {
        return DB::table('ot_past as p')->leftJoin('users as c', 'c.id', '=', 'p.created_by')->leftJoin('users as u', 'u.id', '=', 'p.updated_by')
            ->where('p.user_id', $userId)->orderByDesc('p.year')->orderByDesc('p.month')->orderBy('p.date')
            ->get(['p.*', 'c.name as created_name', 'u.name as updated_name']);
    }

    /** One year's monthly figures for the entry grid, keyed by month. */
    public function year(int $userId, int $year): array
    {
        $rows = DB::table('ot_past')->where('user_id', $userId)->where('year', $year)->get();
        $grid = [];

        foreach (range(1, 12) as $m) {
            $monthly = $rows->where('month', $m)->whereNull('date')->first();
            $days = $rows->where('month', $m)->whereNotNull('date');
            $grid[$m] = ['row' => $monthly, 'day_count' => $days->count(), 'day_hours' => (float) $days->sum('ot_hours')];
        }

        return $grid;
    }

    /** Why a month cannot take past figures, or null. */
    public function monthProblem(int $userId, int $companyId, int $month, int $year): ?string
    {
        $period = PayPeriod::forMonth($month, $year);
        $goLive = self::goLive($companyId);

        if ($period->end->gte($goLive)) {
            return 'From ' . $goLive->format('d M Y') . ' the system counts overtime itself.';
        }

        if (DB::table('ot_daily')->where('user_id', $userId)->whereBetween('date', [$period->start->toDateString(), $period->end->toDateString()])->exists()) {
            return 'The system already holds overtime for this month.';
        }

        return null;
    }

    /**
     * Save a year of monthly figures: a filled month is written (replacing what
     * was there), an emptied one is removed. All or nothing.
     *
     * @param array $months month => [ot_hours, raw_hours, used_hours, used_days, encashed_hours, encashed_amount, note]
     */
    public function saveYear(int $userId, int $companyId, int $year, array $months, string $source = 'manual'): int
    {
        $errors = [];
        $written = 0;

        DB::beginTransaction();

        try {
            foreach ($months as $m => $values) {
                $m = (int) $m;
                $label = Carbon::create($year, $m, 1)->format('M Y');
                $v = $this->clean($values);
                $existing = DB::table('ot_past')->where('user_id', $userId)->where('year', $year)->where('month', $m)->whereNull('date')->first();

                if ($v === null) {
                    if ($existing) {
                        DB::table('ot_past')->where('id', $existing->id)->delete();
                        $written++;
                    }

                    continue;
                }

                if (is_string($v)) {
                    $errors[] = "$label: $v";
                    continue;
                }

                if ($problem = $this->monthProblem($userId, $companyId, $m, $year)) {
                    $errors[] = "$label: $problem";
                    continue;
                }

                if (DB::table('ot_past')->where('user_id', $userId)->where('year', $year)->where('month', $m)->whereNotNull('date')->exists()) {
                    $errors[] = "$label: entered day by day - change it there.";
                    continue;
                }

                $this->write($existing?->id, $userId, $companyId, $year, $m, null, PayPeriod::forMonth($m, $year)->end->toDateString(), $v, $source);
                $written++;
            }

            if ($errors) {
                throw new RuntimeException(implode(' ', $errors));
            }

            $this->checkBalance($userId);
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        return $written;
    }

    /** A day of past overtime (banked hours), added or changed. */
    public function saveDay(int $userId, int $companyId, string $date, array $values, ?int $id = null, string $source = 'manual'): void
    {
        $day = Carbon::parse($date)->startOfDay();
        $goLive = self::goLive($companyId);
        $period = PayPeriod::containing($day);
        $v = $this->clean(array_intersect_key($values, array_flip(['ot_hours', 'raw_hours', 'note'])));

        if ($v === null || is_string($v) || $v['ot_hours'] <= 0) {
            throw new RuntimeException(is_string($v) ? $v : 'Give the hours banked that day.');
        }

        if ($day->gte($goLive)) {
            throw new RuntimeException('From ' . $goLive->format('d M Y') . ' the system counts overtime itself.');
        }

        if (DB::table('ot_daily')->where('user_id', $userId)->where('date', $day->toDateString())->exists()) {
            throw new RuntimeException('The system already holds overtime for ' . $day->format('d M Y') . '.');
        }

        $others = DB::table('ot_past')->where('user_id', $userId)->where('year', $period->year)->where('month', $period->month)
            ->when($id, fn ($q) => $q->where('id', '<>', $id));

        if ((clone $others)->whereNull('date')->exists()) {
            throw new RuntimeException(Carbon::create($period->year, $period->month, 1)->format('M Y') . ' is entered as a monthly figure - change it there.');
        }

        if ((clone $others)->where('date', $day->toDateString())->exists()) {
            throw new RuntimeException($day->format('d M Y') . ' is already entered.');
        }

        DB::transaction(function () use ($id, $userId, $companyId, $period, $day, $v, $source) {
            $this->write($id, $userId, $companyId, $period->year, $period->month, $day->toDateString(), $day->toDateString(), $v, $source);
            $this->checkBalance($userId);
        });
    }

    public function delete(int $id): void
    {
        $row = DB::table('ot_past')->where('id', $id)->first();

        if (!$row) {
            throw new RuntimeException('That entry is no longer there.');
        }

        DB::transaction(function () use ($row) {
            DB::table('ot_past')->where('id', $row->id)->delete();
            $this->checkBalance((int) $row->user_id);
        });
    }

    /**
     * Turn entered values into numbers: null when the row is empty, a message
     * when something is wrong, else the values.
     */
    public function clean(array $values): array|string|null
    {
        $out = [];

        foreach (self::FIELDS as $f) {
            $raw = $values[$f] ?? null;

            if ($f === 'note') {
                $out[$f] = trim((string) $raw) !== '' ? mb_substr(trim((string) $raw), 0, 255) : null;
                continue;
            }

            $raw = is_string($raw) ? trim(str_replace(',', '', $raw)) : $raw;

            if ($raw === null || $raw === '') {
                $out[$f] = null;
                continue;
            }

            $hours = self::hours($raw);

            if ($hours === null || $hours < 0 || $hours > 99999) {
                return 'enter hours as numbers (12.5 or 12:30), not below zero.';
            }

            $out[$f] = $hours;
        }

        $numbers = array_filter([$out['ot_hours'], $out['used_hours'], $out['encashed_hours']], fn ($x) => $x !== null && $x != 0);

        if (!$numbers) {
            return $out['raw_hours'] || $out['used_days'] || $out['encashed_amount'] ? 'give the hours banked, used or encashed.' : null;
        }

        return $out;
    }

    /** "12.5", "12:30" or 12.5 -> 12.5 */
    public static function hours($v): ?float
    {
        if (is_int($v) || is_float($v)) {
            return round((float) $v, 2);
        }

        if (preg_match('/^(\d+):([0-5]\d)$/', (string) $v, $m)) {
            return round($m[1] + $m[2] / 60, 2);
        }

        return is_numeric($v) ? round((float) $v, 2) : null;
    }

    protected function write(?int $id, int $userId, int $companyId, int $year, int $month, ?string $date, string $refDate, array $v, string $source): void
    {
        $row = [
            'company_id' => $companyId, 'user_id' => $userId, 'year' => $year, 'month' => $month, 'date' => $date, 'ref_date' => $refDate,
            'ot_hours' => $v['ot_hours'] ?? 0, 'raw_hours' => $v['raw_hours'] ?? null,
            'used_hours' => $v['used_hours'] ?? 0, 'used_days' => $v['used_days'] ?? null,
            'encashed_hours' => $v['encashed_hours'] ?? 0, 'encashed_amount' => $v['encashed_amount'] ?? null,
            'note' => $v['note'] ?? null, 'updated_by' => $this->by(), 'updated_at' => now(),
        ];

        if ($id) {
            DB::table('ot_past')->where('id', $id)->update($row);
        } else {
            DB::table('ot_past')->insert($row + ['source' => $source, 'created_by' => $this->by(), 'created_at' => now()]);
        }
    }

    protected function checkBalance(int $userId): void
    {
        $balance = (new OtBank())->balance($userId);

        if ($balance < -0.001) {
            throw new RuntimeException('That would leave the OT bank below zero (' . OtBank::fmt($balance) . 'h).');
        }
    }

    protected function by(): ?int
    {
        return function_exists('user') ? user()?->id : null;
    }

}
