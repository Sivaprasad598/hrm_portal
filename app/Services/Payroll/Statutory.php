<?php

namespace App\Services\Payroll;

use App\Support\SchemaCache;
use Illuminate\Support\Facades\DB;

/**
 * Whether PF, ESI and Professional Tax apply to an employee (employee_details.pf_applicable / esi_applicable /
 * pt_applicable, on unless switched off) and how their salary is paid (salary_paid_by: bank | cash).
 * Read once per request for everybody, so payroll does not ask per employee.
 */
class Statutory
{

    public const PAID_BY = ['bank' => 'Bank transfer', 'cash' => 'Cash (by hand)'];

    /** @var array<int, object>|null */
    protected static ?array $rows = null;

    /** @return array{pf: bool, esi: bool, pt: bool} */
    public static function flags(int $userId): array
    {
        $r = self::rows()[$userId] ?? null;

        return [
            'pf' => $r === null || (bool) $r->pf_applicable,
            'esi' => $r === null || (bool) $r->esi_applicable,
            'pt' => $r === null || (bool) $r->pt_applicable,
        ];
    }

    public static function paidBy(int $userId): string
    {
        return self::rows()[$userId]->salary_paid_by ?? 'bank';
    }

    /** "No PF · No ESI" for the screens, or '' when everything applies. */
    public static function label(int $userId): string
    {
        $off = array_keys(array_filter(self::flags($userId), fn ($on) => !$on));

        return $off ? implode(' · ', array_map(fn ($k) => 'No ' . strtoupper($k), $off)) : '';
    }

    public static function exempt(int $userId): bool
    {
        return in_array(false, self::flags($userId), true);
    }

    public static function forget(): void
    {
        self::$rows = null;
    }

    /** @return array<int, object> */
    protected static function rows(): array
    {
        if (self::$rows === null) {
            self::$rows = SchemaCache::hasColumn('employee_details', 'pf_applicable')
                ? DB::table('employee_details')
                    ->where(fn ($q) => $q->where('pf_applicable', 0)->orWhere('esi_applicable', 0)->orWhere('pt_applicable', 0)->orWhere('salary_paid_by', 'cash'))
                    ->get(['user_id', 'pf_applicable', 'esi_applicable', 'pt_applicable', 'salary_paid_by'])->keyBy('user_id')->all()
                : [];
        }

        return self::$rows;
    }

}
