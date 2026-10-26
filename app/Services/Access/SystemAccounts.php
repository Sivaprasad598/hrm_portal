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

    public static function reset(): void
    {
        static::$ids = null;
    }

}
