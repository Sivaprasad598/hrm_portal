<?php

namespace App\Services\Employee;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A greeting on the day: birthday, wedding anniversary, work anniversary.
 *
 * Shown once that day, the first time the person opens the app after signing
 * in (celebrations_shown remembers it, so it does not come back on every page
 * or the next sign-in). 29 February is greeted on 28 February in other years.
 */
class Celebrations
{

    /**
     * Today's occasions for the signed-in person, not yet shown - and marked as
     * shown. Empty when there is nothing (or it was already shown today).
     *
     * @return array<int, array{key: string, title: string, text: string, icon: string}>
     */
    public static function takeForToday(): array
    {
        $user = user();

        if (!$user || !$user->employeeDetail || \App\Services\Access\SystemAccounts::is((int) $user->id)
            || !Schema::hasTable('celebrations_shown')) {
            return [];
        }

        $tz = company()?->timezone ?: 'Asia/Kolkata';
        $today = now($tz)->startOfDay();

        if (DB::table('celebrations_shown')->where('user_id', $user->id)->whereDate('date', $today->toDateString())->exists()) {
            return [];
        }

        $occasions = self::occasions($user, $today);

        if ($occasions) {
            DB::table('celebrations_shown')->insertOrIgnore([
                'user_id' => $user->id, 'date' => $today->toDateString(),
                'occasions' => implode(',', array_column($occasions, 'key')), 'created_at' => now(),
            ]);
        }

        return $occasions;
    }

    /** What falls on this day for a person. */
    public static function occasions($user, Carbon $today): array
    {
        $detail = $user->employeeDetail;
        $first = trim(strtok((string) $user->name, ' ')) ?: $user->name;
        $company = company()?->company_name ?: 'the team';
        $out = [];

        if (self::sameDay($detail->date_of_birth ?? null, $today)) {
            $out[] = ['key' => 'birthday', 'icon' => '🎂', 'title' => 'Happy Birthday, ' . $first . '!',
                'text' => 'Wishing you a wonderful year ahead, full of good health and happiness. Everyone at ' . $company . ' is celebrating with you.'];
        }

        if (self::sameDay($detail->marriage_anniversary_date ?? null, $today)) {
            $out[] = ['key' => 'wedding', 'icon' => '💐', 'title' => 'Happy Wedding Anniversary, ' . $first . '!',
                'text' => 'Wishing you both many more years of love and togetherness.'];
        }

        if (($joined = self::date($detail->joining_date ?? null)) && self::sameDay($joined, $today)) {
            $years = $joined->year > 0 ? $today->year - $joined->year : 0;

            if ($years >= 1) {
                $out[] = ['key' => 'work', 'icon' => '🎉', 'title' => 'Happy Work Anniversary!',
                    'text' => $years . ' ' . ($years === 1 ? 'year' : 'years') . ' with ' . $company . ' today. Thank you for everything you bring to the team, ' . $first . '.'];
            }
        }

        return $out;
    }

    /** Same day and month (29 Feb counts on 28 Feb in a year without it). */
    protected static function sameDay($value, Carbon $today): bool
    {
        $date = self::date($value);

        if (!$date || $date->gt($today)) {
            return false;
        }

        if ($date->month === 2 && $date->day === 29 && !$today->isLeapYear()) {
            return $today->month === 2 && $today->day === 28;
        }

        return $date->month === $today->month && $date->day === $today->day;
    }

    protected static function date($value): ?Carbon
    {
        if (!$value) {
            return null;
        }

        try {
            return $value instanceof Carbon ? $value->copy()->startOfDay() : Carbon::parse($value)->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }

}
