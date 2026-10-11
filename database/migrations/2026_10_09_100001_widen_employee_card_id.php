<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Employee Card ID has to hold a biometric enrolment code.
 *
 * The column was varchar(10) and the form demanded digits only. The codes
 * actually enrolled on the devices are neither: of 161 on this installation,
 * **136 are not purely numeric** (ACPL0488, ACPL0322, ACPL-LIDAR) and three are
 * longer than ten characters — ACPL_DRONE001 is thirteen.
 *
 * So the field could not hold the very identifier it exists to carry, and
 * editing an employee whose code had letters failed validation with "must be
 * numeric (max 10 digits)" and refused to move past the first tab.
 *
 * 50 characters is generous for an enrolment code and still short enough to
 * index comfortably.
 */
return new class extends Migration
{

    public function up(): void
    {
        Schema::table('employee_details', function (Blueprint $table) {
            $table->string('employee_card_id', 50)->nullable()->change();
        });
    }

    public function down(): void
    {
        // Narrowing again would truncate any code longer than ten characters,
        // so this only goes back as far as it safely can.
        Schema::table('employee_details', function (Blueprint $table) {
            $table->string('employee_card_id', 10)->nullable()->change();
        });
    }

};
