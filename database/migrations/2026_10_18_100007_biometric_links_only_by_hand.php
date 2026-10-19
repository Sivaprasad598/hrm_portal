<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Device codes are not the HR employee numbers at this company: of 89 codes
 * shared between the devices and the HR master sheet, 46 belong to a different
 * person on the device. Matching punches or enrolments to employees by an ID
 * that merely looks the same would hand people somebody else's attendance, so
 * the fallback match is switched off: a code belongs to whoever it is linked to.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('iapp_settings')->update(['employee_match_field' => 'none']);
    }

    public function down(): void
    {
        DB::table('iapp_settings')->update(['employee_match_field' => 'employee_card_id']);
    }
};
