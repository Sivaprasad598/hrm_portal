<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Final rule (decided 18 Oct 2026): a device code belongs to the employee whose
 * Employee ID it is. The devices were re-enrolled onto Employee IDs on 17 Oct,
 * so the nightly employee sync and the punch matcher link by Employee ID again,
 * with no name check. Replaces 2026_10_18_100007's "only linked by hand".
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('iapp_settings')->update(['employee_match_field' => 'employee_id']);
    }

    public function down(): void
    {
        DB::table('iapp_settings')->update(['employee_match_field' => 'none']);
    }
};
