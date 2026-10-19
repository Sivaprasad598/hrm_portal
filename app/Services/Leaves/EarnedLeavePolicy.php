<?php

namespace App\Services\Leaves;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Earned Leave, as the leave policy has it:
 *
 *   - it accrues from the joining date (1 a month), like any monthly leave;
 *   - it can be used only after so many years of service (Leave Settings ->
 *     Encashment -> Earned Leave, 2 by default), counted from the joining date
 *     of the current employment;
 *   - it is the leave paid out on leaving, up to a number of days (30), and
 *     only once those years of service are reached.
 *
 * Which leave type is Earned Leave is a setting too, so renaming it changes
 * nothing.
 */
class EarnedLeavePolicy
{

    protected ?object $settings = null;

    public function __construct(?int $companyId = null)
    {
        if (!Schema::hasColumn('leave_settings', 'earned_leave_type_id')) {
            return;
        }

        $this->settings = DB::table('leave_settings')
            ->when($companyId, fn ($q) => $q->where('company_id', $companyId))
            ->first(['earned_leave_type_id', 'earned_leave_min_service_years', 'earned_leave_encash_max_days']);
    }

    public function typeId(): ?int
    {
        return $this->settings?->earned_leave_type_id ? (int) $this->settings->earned_leave_type_id : null;
    }

    public function isEarnedLeave(?int $leaveTypeId): bool
    {
        return $leaveTypeId !== null && $this->typeId() === $leaveTypeId;
    }

    /** Years of service before Earned Leave can be used or paid out. */
    public function minServiceYears(): float
    {
        return (float) ($this->settings->earned_leave_min_service_years ?? 0);
    }

    /** The most Earned Leave paid out on leaving; null for no limit. */
    public function encashMaxDays(): ?float
    {
        $max = $this->settings->earned_leave_encash_max_days ?? null;

        return $max === null || $max === '' ? null : (float) $max;
    }

    /** The first day this employee may use Earned Leave, or null if there is no rule or no joining date. */
    public function usableFrom(int $userId): ?Carbon
    {
        $years = $this->minServiceYears();
        $joined = DB::table('employee_details')->where('user_id', $userId)->value('joining_date');

        if ($years <= 0 || !$joined) {
            return null;
        }

        // Whole months, so 1.5 years is 18 months.
        return Carbon::parse($joined)->startOfDay()->addMonthsNoOverflow((int) round($years * 12));
    }

    public function canUse(int $userId, ?Carbon $on = null): bool
    {
        $from = $this->usableFrom($userId);

        return $from === null || ($on ?: Carbon::today())->gte($from);
    }

    /** Why Earned Leave cannot be used yet, for the person applying. */
    public function refusal(int $userId): string
    {
        $from = $this->usableFrom($userId);

        return 'Earned Leave can be used after ' . self::fmt($this->minServiceYears()) . ' year(s) of service'
            . ($from ? ', from ' . $from->format(company()?->date_format ?? 'd-m-Y') : '') . '.';
    }

    public static function fmt(float $value): string
    {
        return rtrim(rtrim(number_format($value, 2), '0'), '.');
    }

}
