<?php

namespace App\Services\Payroll;

use App\Support\SchemaCache;
use Illuminate\Support\Facades\DB;

/**
 * The two payroll calculation switches, company-wide and per employee for a pay period:
 *  - deduct_late_comings: late comings cost days (Payroll Settings, payroll_options)
 *  - prorate_statutory:   PF / ESI reduced to the days paid (Payroll Settings, payroll_calculations)
 * An employee's own setting for a period (Payroll Run, payroll_employee_switches) wins over the
 * company's. Read once per period for everybody (one query), so the run does not ask per employee.
 */
class PayrollSwitches
{

    public const KEYS = ['deduct_late_comings', 'prorate_statutory'];

    /** @var array<string, array<int, object>> period_start => user_id => row */
    protected static array $own = [];

    /** @var array<string, array<int, float>> period_start => user_id => LOP days HR set */
    protected static array $lop = [];

    /** The company's setting. */
    public static function company(string $key, ?int $companyId = null): bool
    {
        if ($key === 'deduct_late_comings') {
            return AttendanceGate::deductLateComings($companyId);
        }

        $value = DB::table('payroll_calculations')->orderByDesc('id')->value('prorate_statutory');

        return $value === null ? true : (bool) $value;
    }

    /** The employee's own settings for the period, [] when they follow the company. */
    public static function own(int $userId, string $periodStart): array
    {
        $row = self::periodRows($periodStart)[$userId] ?? null;

        if (!$row) {
            return [];
        }

        $out = [];
        foreach (self::KEYS as $key) {
            if ($row->{$key} !== null) {
                $out[$key] = (bool) $row->{$key};
            }
        }

        return $out;
    }

    /** What applies to this employee for the period: their own setting, else the company's. */
    public static function applies(string $key, int $userId, string $periodStart, ?int $companyId = null): bool
    {
        return self::own($userId, $periodStart)[$key] ?? self::company($key, $companyId);
    }

    /** Only the employee's own setting (null: none, the calculator uses the company's). */
    public static function ownValue(string $key, int $userId, string $periodStart): ?bool
    {
        return self::own($userId, $periodStart)[$key] ?? null;
    }

    /** LOP days HR uploaded for the employee and period (null: attendance decides). */
    public static function hrLop(int $userId, string $periodStart, string $periodEnd): ?float
    {
        if (!isset(self::$lop[$periodStart])) {
            self::$lop[$periodStart] = SchemaCache::hasTable('payroll_lop_days')
                ? DB::table('payroll_lop_days')->where('period_start', $periodStart)->where('period_end', $periodEnd)
                    ->pluck('lop_days', 'user_id')->map(fn ($v) => (float) $v)->all()
                : [];
        }

        return self::$lop[$periodStart][$userId] ?? null;
    }

    /**
     * Set (true / false) or clear (null: back to the company setting) an employee's own setting.
     */
    public static function setOwn(int $userId, PayPeriod $cycle, string $key, ?bool $on, ?int $actorId, ?int $companyId): void
    {
        $where = ['user_id' => $userId, 'period_start' => $cycle->start->toDateString()];
        $row = DB::table('payroll_employee_switches')->where($where)->first();

        if ($row) {
            DB::table('payroll_employee_switches')->where('id', $row->id)
                ->update([$key => $on, 'updated_by' => $actorId, 'updated_at' => now()]);
        } elseif ($on !== null) {
            DB::table('payroll_employee_switches')->insert($where + [
                'company_id' => $companyId, 'period_end' => $cycle->end->toDateString(), $key => $on,
                'updated_by' => $actorId, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        // Nothing of their own left: the row goes.
        DB::table('payroll_employee_switches')->where($where)
            ->whereNull('deduct_late_comings')->whereNull('prorate_statutory')->delete();

        self::forget();
    }

    public static function forget(): void
    {
        self::$own = [];
        self::$lop = [];
    }

    /** @return array<int, object> */
    protected static function periodRows(string $periodStart): array
    {
        if (!isset(self::$own[$periodStart])) {
            self::$own[$periodStart] = SchemaCache::hasTable('payroll_employee_switches')
                ? DB::table('payroll_employee_switches')->where('period_start', $periodStart)->get()->keyBy('user_id')->all()
                : [];
        }

        return self::$own[$periodStart];
    }

}
