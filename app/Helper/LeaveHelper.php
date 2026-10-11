<?php

namespace App\Helper;

use App\Models\Company;
use App\Models\EmployeeLeaveQuota;
use App\Models\EmployeeLeaveQuotaEvent;
use App\Models\EmployeeShift;
use App\Models\EmployeeShiftSchedule;
use App\Models\Holiday;
use App\Models\Leave;
use App\Models\LeaveType;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Collection;

class LeaveHelper
{
    public static function getCurrentLeaveYearStart(Company $company, User $user): Carbon
    {
        return self::buildQuotaContext($company, $user)['leave_year_start'];
    }

    /**
     * Shared per-request context to avoid repeated date calculations.
     *
     * @return array{leave_year_start: Carbon, as_of: Carbon, leave_year_start_date: string}
     */
    public static function buildQuotaContext(Company $company, User $user): array
    {
        $now = Carbon::now($company->timezone);
        $yearStartMonth = $company->year_starts_from ?: 1;

        if ($company->leaves_start_from == 'year_start') {
            if ($yearStartMonth > $now->month) {
                $leaveYearStart = Carbon::create($now->year, $yearStartMonth, 1, 0, 0, 0, $company->timezone)->subYear();
            } else {
                $leaveYearStart = Carbon::create($now->year, $yearStartMonth, 1, 0, 0, 0, $company->timezone);
            }
        } else {
            $joiningDate = $user->employeeDetail?->joining_date;

            if (is_null($joiningDate)) {
                $leaveYearStart = Carbon::create($now->year, $yearStartMonth, 1, 0, 0, 0, $company->timezone);
            } else {
                $joiningDate = Carbon::parse($joiningDate->format($now->year . '-m-d'), $company->timezone);
                $joinMonth = $joiningDate->month;
                $joinDay = $joiningDate->day;

                if ($joinMonth > $now->month || ($joinMonth == $now->month && $now->day < $joinDay)) {
                    $leaveYearStart = $joiningDate->copy()->subYear();
                } else {
                    $leaveYearStart = $joiningDate;
                }
            }
        }

        return [
            'leave_year_start' => $leaveYearStart,
            'as_of' => $now,
            'leave_year_start_date' => $leaveYearStart->toDateString(),
        ];
    }

    public static function getCarryForwardExpiryDate(LeaveType $leaveType, Carbon $leaveYearStart): ?Carbon
    {
        if ($leaveType->unused_leave !== 'carry forward' || is_null($leaveType->carry_forward_expiry_date)) {
            return null;
        }

        $stored = Carbon::parse($leaveType->carry_forward_expiry_date);
        $expiry = Carbon::create(
            $leaveYearStart->year,
            $stored->month,
            $stored->day,
            23,
            59,
            59,
            $leaveYearStart->timezone
        );

        if ($expiry->lt($leaveYearStart)) {
            $expiry->addYear();
        }

        return $expiry;
    }

    public static function isCarryForwardExpired(LeaveType $leaveType, Company $company, User $user, ?Carbon $asOf = null): bool
    {
        $context = self::buildQuotaContext($company, $user);

        if ($asOf) {
            $context['as_of'] = $asOf;
        }

        return self::isCarryForwardExpiredWithContext($leaveType, $context);
    }

    private static function isCarryForwardExpiredWithContext(LeaveType $leaveType, array $context): bool
    {
        $expiryDate = self::getCarryForwardExpiryDate($leaveType, $context['leave_year_start']);

        if (is_null($expiryDate)) {
            return false;
        }

        return $context['as_of']->gt($expiryDate);
    }

    public static function effectiveCarryForwardLeaves(EmployeeLeaveQuota $leaveQuota, LeaveType $leaveType, Company $company, User $user): float
    {
        if ($leaveQuota->carry_forward_leaves <= 0) {
            return 0;
        }

        if (self::isCarryForwardExpired($leaveType, $company, $user)) {
            return 0;
        }

        return $leaveQuota->carry_forward_leaves;
    }

    public static function effectiveLeavesRemaining(EmployeeLeaveQuota $leaveQuota, LeaveType $leaveType, Company $company, User $user): float
    {
        $remaining = $leaveQuota->leaves_remaining;

        if ($leaveQuota->carry_forward_leaves > 0 && self::isCarryForwardExpired($leaveType, $company, $user)) {
            $remaining = max(0, $remaining - $leaveQuota->carry_forward_leaves);
        }

        return $remaining;
    }

    /**
     * Zero out expired carry-forward balance and record a one-time audit event per leave year.
     */
    public static function processExpiredCarryForward(
        EmployeeLeaveQuota $leaveQuota,
        LeaveType $leaveType,
        Company $company,
        User $user
    ): float {
        if ($leaveQuota->carry_forward_leaves <= 0) {
            return 0;
        }

        $context = self::buildQuotaContext($company, $user);

        if (!self::isCarryForwardExpiredWithContext($leaveType, $context)) {
            return $leaveQuota->carry_forward_leaves;
        }

        self::recordCarryForwardExpiry($leaveQuota, $leaveType, $company, $user, $context);

        $leaveQuota->carry_forward_leaves = 0;
        $leaveQuota->save();

        return 0;
    }

    public static function recordCarryForwardExpiry(
        EmployeeLeaveQuota $leaveQuota,
        LeaveType $leaveType,
        Company $company,
        User $user,
        ?array $context = null
    ): ?EmployeeLeaveQuotaEvent {
        $rawCarryForward = $leaveQuota->carry_forward_leaves ?? 0;

        if ($rawCarryForward <= 0) {
            return null;
        }

        $context = $context ?? self::buildQuotaContext($company, $user);
        $expiryDate = self::getCarryForwardExpiryDate($leaveType, $context['leave_year_start']);
        $carryForwardUsed = min($rawCarryForward, $leaveQuota->leaves_used ?? 0);
        $lapsedAmount = max(0, $rawCarryForward - $carryForwardUsed);

        return EmployeeLeaveQuotaEvent::firstOrCreate(
            [
                'user_id' => $leaveQuota->user_id,
                'leave_type_id' => $leaveQuota->leave_type_id,
                'event_type' => EmployeeLeaveQuotaEvent::TYPE_CARRY_FORWARD_EXPIRED,
                'leave_year_start' => $context['leave_year_start_date'],
            ],
            [
                'company_id' => $company->id,
                'amount' => $lapsedAmount,
                'carry_forward_total' => $rawCarryForward,
                'carry_forward_used' => $carryForwardUsed,
                'expired_on' => $expiryDate?->toDateString(),
                'processed_at' => $context['as_of'],
            ]
        );
    }

    /**
     * Precompute display data for all quotas using one shared context (single pass).
     *
     * @param  iterable<EmployeeLeaveQuota>  $quotas
     */
    public static function prepareQuotaDisplayDataBatch(iterable $quotas, Company $company, User $user): void
    {
        $context = self::buildQuotaContext($company, $user);

        foreach ($quotas as $quota) {
            if (!$quota->leaveType) {
                continue;
            }

            $quota->quotaDisplay = self::prepareQuotaDisplayData($quota, $quota->leaveType, $company, $user, $context);
        }
    }

    /**
     * @param  iterable<EmployeeLeaveQuota>  $quotas
     */
    public static function attachExpiryEventsToQuotas(iterable $quotas, User $user, Company $company, ?array $context = null): void
    {
        $quotaList = collect($quotas);

        if ($quotaList->isEmpty()) {
            return;
        }

        $context = $context ?? self::buildQuotaContext($company, $user);
        $leaveTypeIds = $quotaList->pluck('leave_type_id')->unique()->values();
        $leaveYearStartDate = $context['leave_year_start_date'];

        $events = self::fetchExpiryEvents($user->id, $leaveTypeIds);
        $recorded = false;

        foreach ($quotaList as $quota) {
            if (
                !$quota->leaveType
                || ($quota->carry_forward_leaves ?? 0) <= 0
                || !self::isCarryForwardExpiredWithContext($quota->leaveType, $context)
            ) {
                continue;
            }

            if (self::hasExpiryEventForLeaveYear($events, $quota->leave_type_id, $leaveYearStartDate)) {
                continue;
            }

            self::recordCarryForwardExpiry($quota, $quota->leaveType, $company, $user, $context);
            $recorded = true;
        }

        if ($recorded) {
            $events = self::fetchExpiryEvents($user->id, $leaveTypeIds);
        }

        foreach ($quotaList as $quota) {
            if (!isset($quota->quotaDisplay)) {
                continue;
            }

            $display = $quota->quotaDisplay;
            $display['expiry_events'] = $events->get($quota->leave_type_id, collect())->values();
            self::applyExpiredUnusedToDisplay($quota, $display, $context);
        }
    }

    private static function applyExpiredUnusedToDisplay(
        EmployeeLeaveQuota $quota,
        array $display,
        array $context
    ): void {
        $breakdown = $display['breakdown'];
        $leaveType = $quota->leaveType;
        $supportsCarryForward = $leaveType && $leaveType->unused_leave === 'carry forward';
        $leaveYearStartDate = $context['leave_year_start_date'];
        $regularUnused = $breakdown['unused'] ?? 0;

        $expiredAmount = 0;
        $expiredOn = null;

        $currentEvent = collect($display['expiry_events'] ?? [])
            ->first(fn (EmployeeLeaveQuotaEvent $event) => $event->leave_year_start->toDateString() === $leaveYearStartDate);

        if ($currentEvent) {
            $expiredAmount = $currentEvent->amount;
            $expiredOn = $currentEvent->expired_on;
        } elseif ($supportsCarryForward && ($breakdown['carry_forward_expired'] ?? false)) {
            $rawCarryForward = $display['raw_carry_forward'] ?? 0;
            $carryForwardUsed = min($rawCarryForward, $quota->leaves_used ?? 0);
            $expiredAmount = max(0, $rawCarryForward - $carryForwardUsed);
            $expiredOn = $breakdown['carry_forward_expiry'] ?? null;
        }

        $showLapsedUnused = $regularUnused > 0
            && $leaveType
            && $leaveType->unused_leave === 'lapse';

        $breakdown['expired_unused_leaves'] = $expiredAmount;
        $breakdown['expired_unused_on'] = $expiredOn;
        $breakdown['show_expired_unused'] = $expiredAmount > 0;
        $breakdown['lapsed_unused_leaves'] = $showLapsedUnused ? $regularUnused : 0;
        $breakdown['show_lapsed_unused'] = $showLapsedUnused;
        $breakdown['show_regular_unused'] = $regularUnused > 0 && !$breakdown['show_expired_unused'] && !$showLapsedUnused;

        $display['breakdown'] = $breakdown;
        $quota->quotaDisplay = $display;
    }

    /**
     * @param  iterable<EmployeeLeaveQuota>  $quotas
     */
    public static function enrichQuotasForDisplay(iterable $quotas, User $user, Company $company): void
    {
        $quotaList = collect($quotas);

        if ($quotaList->isEmpty()) {
            return;
        }

        $context = self::buildQuotaContext($company, $user);

        foreach ($quotaList as $quota) {
            if (!$quota->leaveType) {
                continue;
            }

            $quota->quotaDisplay = self::prepareQuotaDisplayData($quota, $quota->leaveType, $company, $user, $context);
        }

        self::attachExpiryEventsToQuotas($quotaList, $user, $company, $context);
    }

    private static function fetchExpiryEvents(int $userId, $leaveTypeIds)
    {
        return EmployeeLeaveQuotaEvent::where('user_id', $userId)
            ->whereIn('leave_type_id', $leaveTypeIds)
            ->where('event_type', EmployeeLeaveQuotaEvent::TYPE_CARRY_FORWARD_EXPIRED)
            ->orderByDesc('processed_at')
            ->get()
            ->groupBy('leave_type_id');
    }

    private static function hasExpiryEventForLeaveYear($events, int $leaveTypeId, string $leaveYearStartDate): bool
    {
        return $events->get($leaveTypeId, collect())
            ->contains(fn (EmployeeLeaveQuotaEvent $event) => $event->leave_year_start->toDateString() === $leaveYearStartDate);
    }

    /**
     * Break down quota into current-year vs carry-forward pools.
     * Carry-forward balance is assumed consumed before current-year allocation.
     */
    public static function getQuotaBreakdown(
        EmployeeLeaveQuota $leaveQuota,
        LeaveType $leaveType,
        Company $company,
        User $user,
        ?array $context = null
    ): array {
        $context = $context ?? self::buildQuotaContext($company, $user);
        $carryForwardExpired = self::isCarryForwardExpiredWithContext($leaveType, $context);
        $rawCarryForward = $leaveQuota->carry_forward_leaves ?? 0;
        $carryForwardAllocated = ($rawCarryForward <= 0 || $carryForwardExpired) ? 0 : $rawCarryForward;
        $hasCarryForward = $carryForwardAllocated > 0;
        $leavesUsed = $leaveQuota->leaves_used ?? 0;

        $currentYearAllocated = max(0, ($leaveQuota->no_of_leaves ?? 0) - ($carryForwardExpired ? 0 : $rawCarryForward));
        $carryForwardUsed = min($carryForwardAllocated, $leavesUsed);
        $currentYearUsed = max(0, $leavesUsed - $carryForwardAllocated);
        $carryForwardRemaining = max(0, $carryForwardAllocated - $leavesUsed);
        $currentYearRemaining = max(0, $currentYearAllocated - $currentYearUsed);

        $totalRemaining = $leaveQuota->leaves_remaining;

        if ($rawCarryForward > 0 && $carryForwardExpired) {
            $totalRemaining = max(0, $totalRemaining - $rawCarryForward);
        }

        $carryForwardExpiry = ($hasCarryForward && $carryForwardRemaining > 0)
            ? self::getCarryForwardExpiryDate($leaveType, $context['leave_year_start'])
            : null;

        return [
            'has_carry_forward' => $hasCarryForward,
            'carry_forward_expired' => $carryForwardExpired && $rawCarryForward > 0,
            'current_year_allocated' => $currentYearAllocated,
            'carry_forward_allocated' => $carryForwardAllocated,
            'current_year_used' => $currentYearUsed,
            'carry_forward_used' => $carryForwardUsed,
            'current_year_remaining' => $currentYearRemaining,
            'carry_forward_remaining' => $carryForwardRemaining,
            'total_remaining' => $totalRemaining,
            'carry_forward_expiry' => $carryForwardExpiry,
            'overutilised' => $leaveQuota->overutilised_leaves ?? 0,
            'unused' => $leaveQuota->unused_leaves ?? 0,
        ];
    }

    /**
     * Precompute breakdown and edit-form helpers for the leave quota UI.
     */
    public static function prepareQuotaDisplayData(
        EmployeeLeaveQuota $leaveQuota,
        LeaveType $leaveType,
        Company $company,
        User $user,
        ?array $context = null
    ): array {
        $context = $context ?? self::buildQuotaContext($company, $user);
        $breakdown = self::getQuotaBreakdown($leaveQuota, $leaveType, $company, $user, $context);
        $supportsCarryForward = $leaveType->unused_leave === 'carry forward';
        $rawCarryForward = $leaveQuota->carry_forward_leaves ?? 0;

        $carryForwardExpiry = $supportsCarryForward
            ? self::getCarryForwardExpiryDate($leaveType, $context['leave_year_start'])
            : null;

        if ($supportsCarryForward && is_null($breakdown['carry_forward_expiry'])) {
            $breakdown['carry_forward_expiry'] = $carryForwardExpiry;
        }

        $breakdown['supports_carry_forward'] = $supportsCarryForward;
        $breakdown['total_allocated'] = $breakdown['current_year_allocated'] + $breakdown['carry_forward_allocated'];

        $remainingExpiring = 0;
        $remainingNonExpiring = $breakdown['total_remaining'];

        if (
            $supportsCarryForward
            && !$breakdown['carry_forward_expired']
            && $breakdown['carry_forward_expiry']
            && $breakdown['carry_forward_remaining'] > 0
        ) {
            $remainingExpiring = $breakdown['carry_forward_remaining'];
            $remainingNonExpiring = $breakdown['current_year_remaining'];
        }

        $breakdown['remaining_expiring'] = $remainingExpiring;
        $breakdown['remaining_non_expiring'] = $remainingNonExpiring;
        $breakdown['show_remaining_split'] = $remainingExpiring > 0;

        $periodAllocated = max(0, ($leaveQuota->no_of_leaves ?? 0) - ($breakdown['carry_forward_expired'] ? 0 : $rawCarryForward));

        $accrualHint = $leaveType->isUnlimited()
            ? __('modules.leaves.unlimitedLeaveHint')
            : ($leaveType->leavetype === 'monthly'
                ? __('modules.leaves.monthlyAccrualHint', ['count' => $leaveType->no_of_leaves])
                : __('modules.leaves.yearlyAccrualHint', ['count' => $leaveType->no_of_leaves]));

        if ($leaveType->isUnlimited()) {
            $breakdown['overutilised'] = 0;
            $breakdown['is_unlimited'] = true;
            $breakdown['total_remaining'] = 0;
            $breakdown['current_year_allocated'] = 0;
            $breakdown['total_allocated'] = 0;
            $breakdown['show_remaining_split'] = false;
            $supportsCarryForward = false;
            $periodAllocated = 0;
        }

        return [
            'breakdown' => $breakdown,
            'period_allocated' => $periodAllocated,
            'raw_carry_forward' => $rawCarryForward,
            'supports_carry_forward' => $supportsCarryForward,
            'is_locked' => ($leaveQuota->leave_type_impact ?? 0) == 1,
            'accrual_hint' => $accrualHint,
            'expiry_events' => collect(),
        ];
    }

    /**
     * Recalculate remaining and overutilised balances after a manual quota update.
     */
    public static function recalculateRemainingAfterUpdate(EmployeeLeaveQuota $leaveQuota, float $totalAllocated): void
    {
        if ($leaveQuota->leaveType?->isUnlimited()) {
            $leaveQuota->leaves_remaining = 0;
            $leaveQuota->overutilised_leaves = 0;

            return;
        }

        $leavesUsed = $leaveQuota->leaves_used ?? 0;
        $remaining = $totalAllocated - $leavesUsed;

        if ($remaining >= 0) {
            $leaveQuota->leaves_remaining = $remaining;
            $leaveQuota->overutilised_leaves = 0;
        } else {
            $leaveQuota->leaves_remaining = 0;
            $leaveQuota->overutilised_leaves = abs($remaining);
        }
    }

    public static function resolveWorkingDay(User $user, Carbon $date): bool
    {
        $schedule = EmployeeShiftSchedule::where('user_id', $user->id)
            ->whereDate('date', $date->format('Y-m-d'))
            ->first();

        if ($schedule?->shift) {
            $officeOpenDays = $schedule->shift->office_open_days;
        } else {
            $attendanceSetting = attendance_setting();
            $defaultShift = $attendanceSetting
                ? EmployeeShift::find($attendanceSetting->default_employee_shift)
                : null;
            $officeOpenDays = $defaultShift?->office_open_days ?? '["1","2","3","4","5"]';
        }

        $openDays = array_map('intval', json_decode($officeOpenDays, true) ?: []);

        if ($openDays === []) {
            return false;
        }

        return in_array((int) $date->format('N'), $openDays, true);
    }

    public static function resolveHolidays(User $user, array $dateStrings): Collection
    {
        $employee = $user->employeeDetails ?? $user->employeeDetail;

        if (!$employee) {
            return collect();
        }

        return Holiday::whereIn('date', $dateStrings)
            ->applicableTo($employee)
            ->pluck('date')
            ->map(fn ($date) => Carbon::parse($date)->format('Y-m-d'));
    }

    public static function calculateLeaveDates(Collection $calendarDates, User $user, LeaveType $leaveType): Collection
    {
        return self::calculateLeaveDatesFromResolvers(
            $calendarDates,
            $leaveType,
            fn (Carbon $date) => self::resolveWorkingDay($user, $date),
            fn (array $dateStrings) => self::resolveHolidays($user, $dateStrings)
        );
    }

    public static function calculateLeaveDatesFromResolvers(
        Collection $calendarDates,
        LeaveType $leaveType,
        callable $isWorkingDay,
        callable $resolveHolidayDates
    ): Collection {
        $calendarDates = $calendarDates
            ->map(fn ($date) => Carbon::parse($date)->startOfDay())
            ->unique(fn (Carbon $date) => $date->format('Y-m-d'))
            ->values();

        if ($calendarDates->isEmpty()) {
            return collect();
        }

        $holidayLookup = collect($resolveHolidayDates($calendarDates->map->format('Y-m-d')->all()))
            ->map(fn ($date) => Carbon::parse($date)->format('Y-m-d'))
            ->flip();

        $anchorWorkingDays = $calendarDates
            ->filter(fn (Carbon $date) => $isWorkingDay($date) && !$holidayLookup->has($date->format('Y-m-d')))
            ->values();

        if ($anchorWorkingDays->isEmpty()) {
            return collect();
        }

        if (!$leaveType->sandwich_leave) {
            return $anchorWorkingDays;
        }

        return self::expandSandwichSpanFromResolvers(
            $anchorWorkingDays->min(),
            $anchorWorkingDays->max(),
            $resolveHolidayDates,
            (bool) $leaveType->sandwich_include_holidays
        );
    }

    public static function expandSandwichWithExistingLeaves(
        Collection $dates,
        User $user,
        LeaveType $leaveType,
        ?string $excludeUniqueId = null,
        bool $isHalfDay = false
    ): Collection {
        if ($isHalfDay || !$leaveType->sandwich_leave || $dates->isEmpty()) {
            return $dates;
        }

        $existingLeaveDates = Leave::query()
            ->where('user_id', $user->id)
            ->where('leave_type_id', $leaveType->id)
            ->whereIn('status', ['approved', 'pending'])
            ->where('duration', '!=', 'half day')
            ->when($excludeUniqueId, fn ($query) => $query->where('unique_id', '!=', $excludeUniqueId))
            ->pluck('leave_date')
            ->map(fn ($date) => Carbon::parse($date)->format('Y-m-d'))
            ->all();

        return self::expandSandwichWithAnchorDates(
            $dates,
            $existingLeaveDates,
            $user,
            $leaveType,
            fn (Carbon $date) => self::resolveWorkingDay($user, $date),
            fn (array $dateStrings) => self::resolveHolidays($user, $dateStrings)
        );
    }

    public static function expandSandwichWithAnchorDates(
        Collection $dates,
        array $existingLeaveDates,
        User $user,
        LeaveType $leaveType,
        callable $isWorkingDay,
        callable $resolveHolidayDates
    ): Collection {
        if (!$leaveType->sandwich_leave || $dates->isEmpty()) {
            return $dates;
        }

        $newDateStrings = $dates->map(fn (Carbon $date) => $date->format('Y-m-d'))->all();
        $anchorDateStrings = array_values(array_unique(array_merge($newDateStrings, $existingLeaveDates)));
        sort($anchorDateStrings);

        $connectedAnchors = self::connectedAnchorDatesFromResolvers(
            $anchorDateStrings,
            $isWorkingDay,
            $resolveHolidayDates
        );

        $relevantAnchors = collect($connectedAnchors)
            ->filter(fn (array $group) => collect($group)->intersect($newDateStrings)->isNotEmpty())
            ->flatten()
            ->unique()
            ->values();

        if ($relevantAnchors->isEmpty()) {
            return $dates;
        }

        $spanStart = Carbon::parse($relevantAnchors->min())->startOfDay();
        $spanEnd = Carbon::parse($relevantAnchors->max())->startOfDay();

        return self::expandSandwichSpanFromResolvers(
            $spanStart,
            $spanEnd,
            $resolveHolidayDates,
            (bool) $leaveType->sandwich_include_holidays
        );
    }

    public static function previewLeaveDays(
        Collection $calendarDates,
        User $user,
        LeaveType $leaveType,
        bool $isHalfDay = false
    ): array {
        if ($isHalfDay) {
            $count = $calendarDates->count() * 0.5;

            return [
                'workingDays' => $count,
                'sandwichDays' => 0,
                'totalDays' => $count,
            ];
        }

        $resolvedDates = self::calculateLeaveDates($calendarDates, $user, $leaveType);
        $resolvedDates = self::expandSandwichWithExistingLeaves($resolvedDates, $user, $leaveType);

        $workingOnly = self::calculateLeaveDates($calendarDates, $user, self::cloneLeaveTypeWithSandwich($leaveType, false));
        $sandwichDays = max(0, $resolvedDates->count() - $workingOnly->count());

        return [
            'workingDays' => $workingOnly->count(),
            'sandwichDays' => $sandwichDays,
            'totalDays' => $resolvedDates->count(),
        ];
    }

    private static function expandSandwichSpanFromResolvers(
        Carbon $start,
        Carbon $end,
        callable $resolveHolidayDates,
        bool $includeHolidays
    ): Collection {
        $span = collect(CarbonPeriod::create($start, $end))
            ->map(fn (Carbon $date) => $date->copy()->startOfDay());

        if ($includeHolidays) {
            return $span->values();
        }

        $holidayLookup = collect($resolveHolidayDates($span->map->format('Y-m-d')->all()))
            ->map(fn ($date) => Carbon::parse($date)->format('Y-m-d'))
            ->flip();

        return $span
            ->filter(fn (Carbon $date) => !$holidayLookup->has($date->format('Y-m-d')))
            ->values();
    }

    private static function connectedAnchorDatesFromResolvers(
        array $anchorDateStrings,
        callable $isWorkingDay,
        callable $resolveHolidayDates
    ): array {
        if ($anchorDateStrings === []) {
            return [];
        }

        $groups = [];
        $currentGroup = [$anchorDateStrings[0]];

        for ($index = 1; $index < count($anchorDateStrings); $index++) {
            $previous = Carbon::parse($anchorDateStrings[$index - 1])->startOfDay();
            $current = Carbon::parse($anchorDateStrings[$index])->startOfDay();

            if (self::gapIsSandwichableFromResolvers($previous, $current, $isWorkingDay, $resolveHolidayDates)) {
                $currentGroup[] = $anchorDateStrings[$index];
                continue;
            }

            $groups[] = $currentGroup;
            $currentGroup = [$anchorDateStrings[$index]];
        }

        $groups[] = $currentGroup;

        return $groups;
    }

    private static function gapIsSandwichableFromResolvers(
        Carbon $start,
        Carbon $end,
        callable $isWorkingDay,
        callable $resolveHolidayDates
    ): bool {
        if ($end->lte($start)) {
            return true;
        }

        $gapDates = collect(CarbonPeriod::create($start->copy()->addDay(), $end->copy()->subDay()))
            ->map(fn (Carbon $date) => $date->copy()->startOfDay());

        if ($gapDates->isEmpty()) {
            return true;
        }

        $holidayLookup = collect($resolveHolidayDates($gapDates->map->format('Y-m-d')->all()))
            ->map(fn ($date) => Carbon::parse($date)->format('Y-m-d'))
            ->flip();

        foreach ($gapDates as $gapDate) {
            $isHoliday = $holidayLookup->has($gapDate->format('Y-m-d'));
            $isWorkingDayResult = $isWorkingDay($gapDate);

            if ($isWorkingDayResult && !$isHoliday) {
                return false;
            }
        }

        return true;
    }

    private static function cloneLeaveTypeWithSandwich(LeaveType $leaveType, bool $sandwichLeave): LeaveType
    {
        $clone = clone $leaveType;
        $clone->sandwich_leave = $sandwichLeave;

        return $clone;
    }
}
