<?php

namespace App\Services\Leaves;

use Illuminate\Support\Facades\DB;

/**
 * Every leave type lists the departments, designations and roles it applies to
 * (leave_types.department / designation / role, JSON lists of ids), and an
 * employee outside those lists cannot apply for it at all.
 *
 * The model observers add a new department, designation or role to every leave
 * type when it is created through its model. Anything created directly in the
 * database (the Excel import, a role made from a designation) has to be added
 * here, or nobody in it can apply for leave.
 */
class LeaveTypeScope
{

    /**
     * @param  'department'|'designation'|'role'  $column
     * @param  array<int, int|string>  $ids
     */
    public static function include(string $column, array $ids): void
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));

        if ($ids === [] || !in_array($column, ['department', 'designation', 'role'], true)) {
            return;
        }

        foreach (DB::table('leave_types')->get(['id', $column]) as $type) {
            $list = json_decode((string) $type->{$column}, true) ?: [];
            $have = array_map('intval', $list);
            $missing = array_diff($ids, $have);

            if ($missing === []) {
                continue;
            }

            DB::table('leave_types')->where('id', $type->id)
                ->update([$column => json_encode(array_values(array_merge($list, array_map('strval', $missing)))), 'updated_at' => now()]);
        }
    }

}
