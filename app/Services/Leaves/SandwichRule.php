<?php

namespace App\Services\Leaves;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The sandwich rule (Leave Settings): off days - weekly offs, holidays, roster
 * Day Offs - lying between the days of one leave request count as leave. Off
 * days at either end of the request are still left out, and off days merely
 * next to a leave are never pulled in.
 *
 * Switched off (the default), every off day in a request is left out.
 */
class SandwichRule
{
    protected static ?bool $on = null;

    public static function enabled(): bool
    {
        return self::$on ??= Schema::hasColumn('leave_settings', 'sandwich_rule')
            && (bool) DB::table('leave_settings')->value('sandwich_rule');
    }

    /** For tests and after saving the setting within one process. */
    public static function forget(): void
    {
        self::$on = null;
    }

    /**
     * The dates of a request that are booked as leave.
     *
     * @param  Collection  $dates   every date of the request, in order (Carbon)
     * @param  callable    $isOff   fn (Carbon $date): bool - a weekly off, holiday or Day Off
     */
    public static function leaveDays(Collection $dates, callable $isOff): Collection
    {
        $dates = $dates->values();
        $working = $dates->reject(fn ($d) => $isOff($d))->values();

        if (!self::enabled() || $working->count() < 2) {
            return $working;
        }

        // Off days strictly between the first and the last working day are sandwiched.
        $first = $working->first()->toDateString();
        $last = $working->last()->toDateString();

        return $dates->filter(fn ($d) => !$isOff($d) || ($d->toDateString() > $first && $d->toDateString() < $last))->values();
    }
}
