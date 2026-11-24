<?php

namespace App\Services\Access;

use Illuminate\Support\Facades\DB;

/**
 * The Super Admin login is the system's owner account, not a member of staff.
 * It keeps every permission, but appears in no list of people: not the
 * employee list, attendance, leave, payroll, OT, salary revisions, assets,
 * imports or the nightly jobs. Day-to-day access belongs to the Admin role.
 *
 * Worksuite's own screens pick staff by the Employee role, which the account
 * no longer holds; the screens that pick people by their employee record use
 * ids() to leave it out.
 */
class SystemAccounts
{

    protected static ?array $ids = null;

    /** @return array<int, int> users holding the Super Admin (admin) role; [0] when none, so whereNotIn stays valid */
    public static function ids(): array
    {
        return static::$ids ??= (DB::table('role_user as ru')->join('roles as r', 'r.id', '=', 'ru.role_id')
            ->where('r.name', 'admin')->pluck('ru.user_id')->map(fn ($id) => (int) $id)->unique()->values()->all() ?: [0]);
    }

    public static function is(?int $userId): bool
    {
        return $userId !== null && in_array($userId, static::ids(), true);
    }

    /*
    | Test sign-ins (users.is_test_account, 2026-11-24): kept out of everybody's view of the company -
    | lists, pickers, registers, counts, dashboards, birthdays and the nightly jobs. Test accounts see
    | one another, so a flow can be tried end to end (Test HR approves Test Employee's leave); the
    | Super Admin sees them only in HR -> Employees (EmployeesDataTable), tagged TEST.
    */

    protected static ?array $testIds = null;

    /** @return array<int, int> the test sign-ins ([] when none, or before the flag exists) */
    public static function testIds(): array
    {
        return static::$testIds ??= (\Illuminate\Support\Facades\Schema::hasColumn('users', 'is_test_account')
            ? DB::table('users')->where('is_test_account', true)->pluck('id')->map(fn ($id) => (int) $id)->all()
            : []);
    }

    public static function isTest(?int $userId): bool
    {
        return $userId !== null && in_array($userId, static::testIds(), true);
    }

    /** Whether the person looking is a test sign-in (then the other test accounts are visible to them). */
    public static function viewerIsTest(): bool
    {
        return static::isTest(user()?->id ? (int) user()->id : null);
    }

    /**
     * Everyone to leave out of a list of people for whoever is looking: the Super Admin login, and the
     * test sign-ins unless a test sign-in is looking. With nobody signed in (the nightly jobs, imports
     * run from the console) the test accounts are left out too. [0] when empty, so whereNotIn stays valid.
     *
     * @return array<int, int>
     */
    public static function hidden(): array
    {
        $ids = array_values(array_filter(static::ids()));

        if (!static::viewerIsTest()) {
            $ids = array_merge($ids, static::testIds());
        }

        return array_values(array_unique($ids)) ?: [0];
    }

    /** Never counted, for anyone (the Super Admin and test sign-ins too): the Super Admin login and the test sign-ins. @return array<int, int> */
    public static function notCounted(): array
    {
        return array_values(array_unique(array_merge(array_filter(static::ids()), static::testIds()))) ?: [0];
    }

    /** The test sign-ins to leave out for whoever is looking (none for a test sign-in). @return array<int, int> */
    public static function hiddenTests(): array
    {
        return static::viewerIsTest() ? [0] : (static::testIds() ?: [0]);
    }

    /**
     * A notifications query without the ones about a test sign-in (their leave, their attendance
     * correction...), unless a test sign-in is reading. Those carry the person's id (data.user_id)
     * or their name (data.heading).
     */
    public static function withoutTestNotifications($query)
    {
        if (static::viewerIsTest() || !static::testIds()) {
            return $query;
        }

        $ids = implode(',', array_map('intval', static::testIds()));
        $names = DB::table('users')->whereIn('id', static::testIds())->pluck('name')->all();

        return $query->whereRaw("COALESCE(CAST(JSON_UNQUOTE(JSON_EXTRACT(data, '$.user_id')) AS UNSIGNED), 0) NOT IN ($ids)")
            ->where(fn ($q) => $q->whereRaw("JSON_EXTRACT(data, '$.heading') IS NULL")
                ->orWhereNotIn(DB::raw("JSON_UNQUOTE(JSON_EXTRACT(data, '$.heading'))"), $names ?: ['']));
    }

    /**
     * Whether a notification is about a test sign-in: the person it names, or a record of theirs
     * (a leave, an attendance correction, a payslip...) among its public properties.
     */
    public static function notificationIsAboutTest(object $notification): bool
    {
        if (!static::testIds()) {
            return false;
        }

        foreach (get_object_vars($notification) as $value) {
            if ($value instanceof \App\Models\User && static::isTest((int) $value->id)) {
                return true;
            }

            if ($value instanceof \Illuminate\Database\Eloquent\Model && isset($value->user_id) && static::isTest((int) $value->user_id)) {
                return true;
            }
        }

        return false;
    }

    public static function reset(): void
    {
        static::$ids = null;
        static::$testIds = null;
    }

}
