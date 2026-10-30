<?php

namespace App\Services\Employee;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A greeting on the day: birthday, wedding anniversary, work anniversary.
 *
 * Each occasion is shown once that day, on the first page the person opens
 * (celebrations_shown remembers which, so it does not come back on every page
 * or the next sign-in; one added later that day still shows). 29 February is greeted on 28 February in other years.
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

        // Only a page someone actually opens uses up the greeting; a script or
        // test rendering pages from the command line must not.
        if (app()->runningInConsole()) {
            return [];
        }

        if (!$user || !$user->employeeDetail || \App\Services\Access\SystemAccounts::is((int) $user->id)
            || !Schema::hasTable('celebrations_shown')) {
            return [];
        }

        $tz = company()?->timezone ?: 'Asia/Kolkata';
        $today = now($tz)->startOfDay();

        // Each occasion once a day: one greeted already is not greeted again,
        // but one that turns up later the same day (a date entered since) is.
        $shownRow = DB::table('celebrations_shown')->where('user_id', $user->id)
            ->whereDate('date', $today->toDateString())->first(['id', 'occasions']);
        $shown = $shownRow ? array_filter(explode(',', (string) $shownRow->occasions)) : [];

        $occasions = array_values(array_filter(self::occasions($user, $today), fn ($o) => !in_array($o['key'], $shown, true)));

        if ($occasions) {
            $keys = implode(',', array_merge($shown, array_column($occasions, 'key')));

            $shownRow
                ? DB::table('celebrations_shown')->where('id', $shownRow->id)->update(['occasions' => $keys])
                : DB::table('celebrations_shown')->insertOrIgnore([
                    'user_id' => $user->id, 'date' => $today->toDateString(), 'occasions' => $keys, 'created_at' => now(),
                ]);
        }

        return $occasions;
    }

    /** What falls on this day for a person. */
    public static function occasions($user, Carbon $today): array
    {
        // Read fresh: the signed-in user is kept in the session with the details
        // it had at sign-in, so a date changed since would otherwise be missed.
        $detail = \App\Models\EmployeeDetails::where('user_id', $user->id)
            ->first(['date_of_birth', 'marriage_anniversary_date', 'joining_date']);

        if (!$detail) {
            return [];
        }

        $first = trim(strtok((string) $user->name, ' ')) ?: $user->name;
        $company = (company() ?: null)?->company_name ?: 'the team';
        $out = [];

        if (self::sameDay($detail->date_of_birth, $today)) {
            $out[] = ['key' => 'birthday', 'icon' => '🎂', 'title' => 'Happy Birthday, ' . $first . '!',
                'text' => 'Wishing you a wonderful year ahead, full of good health and happiness. Everyone at ' . $company . ' is celebrating with you.'];
        }

        if (self::sameDay($detail->marriage_anniversary_date, $today)) {
            $years = $today->year - self::date($detail->marriage_anniversary_date)->year;
            $out[] = $years >= 1
                ? ['key' => 'wedding', 'icon' => '💐', 'title' => 'Happy Wedding Anniversary, ' . $first . '!',
                    'text' => ($years === 1 ? 'One year' : $years . ' years') . ' together today. Wishing you both many more years of love and togetherness.']
                : ['key' => 'wedding', 'icon' => '💐', 'title' => 'Congratulations on your wedding, ' . $first . '!',
                    'text' => 'Wishing you both a lifetime of love and happiness. Everyone at ' . $company . ' sends their best wishes.'];
        }

        if (self::sameDay($detail->joining_date, $today)) {
            $years = $today->year - self::date($detail->joining_date)->year;
            $out[] = $years >= 1
                ? ['key' => 'work', 'icon' => '🎉', 'title' => 'Happy Work Anniversary, ' . $first . '!',
                    'text' => $years . ' ' . ($years === 1 ? 'year' : 'years') . ' with ' . $company . ' today. Thank you for everything you bring to the team.']
                : ['key' => 'work', 'icon' => '👋', 'title' => 'Welcome to ' . $company . ', ' . $first . '!',
                    'text' => 'Your first day with us. We are glad to have you on the team.'];
        }

        return $out;
    }

    /** Same day and month (29 Feb counts on 28 Feb in a year without it). */
    protected static function sameDay($value, Carbon $today): bool
    {
        $date = self::date($value);

        // Calendar dates compared, not instants: the stored date is midnight in
        // the app's time zone, "today" midnight in the company's.
        if (!$date || $date->toDateString() > $today->toDateString()) {
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
            return Carbon::parse($value instanceof Carbon ? $value->toDateString() : substr((string) $value, 0, 10))->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }

}
