<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Holidays limited to departments or designations that no longer exist (the
 * Excel import re-created them with new ids) applied to nobody: the attendance
 * grid showed Gandhi Jayanthi as Absent while payroll paid it as a holiday.
 * Such a list is cleared, so the holiday is everyone's again. A list that still
 * names an existing department or designation is left as it is.
 */
return new class extends Migration {

    public function up(): void
    {
        $teams = DB::table('teams')->pluck('id')->map(fn ($id) => (int) $id)->all();
        $designations = DB::table('designations')->pluck('id')->map(fn ($id) => (int) $id)->all();

        foreach (DB::table('holidays')->get(['id', 'department_id_json', 'designation_id_json']) as $holiday) {
            $update = [];

            foreach (['department_id_json' => $teams, 'designation_id_json' => $designations] as $column => $existing) {
                $list = json_decode((string) $holiday->{$column}, true);

                if (is_array($list) && $list !== [] && !array_intersect(array_map('intval', $list), $existing)) {
                    $update[$column] = null;
                }
            }

            if ($update) {
                DB::table('holidays')->where('id', $holiday->id)->update($update);
            }
        }
    }

    public function down(): void
    {
        // Data only.
    }

};
