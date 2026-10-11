<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A photograph and a place for each clock-in.
 *
 * Coordinates were already on `attendances` and already stored when sent, but
 * nothing asked the browser for them unless a radius check was switched on, so
 * every one of the ten thousand rows had none. The photo is new.
 *
 * Three states rather than two, because "off" and "optional" are different
 * things: off asks for nothing, optional asks but lets somebody past, mandatory
 * refuses the clock-in without one.
 */
return new class extends Migration
{

    public function up(): void
    {
        Schema::table('attendance_settings', function (Blueprint $table) {
            $table->enum('clock_in_photo', ['off', 'optional', 'mandatory'])
                ->default('off')->after('save_current_location');
            // Asking for the place is separate from asking for the picture.
            $table->enum('clock_in_location', ['off', 'optional', 'mandatory'])
                ->default('optional')->after('clock_in_photo');
        });

        Schema::table('attendances', function (Blueprint $table) {
            // Where the file sits under the public disk.
            $table->string('clock_in_photo', 255)->nullable()->after('longitude');
            $table->string('clock_out_photo', 255)->nullable()->after('clock_in_photo');
            // What the coordinates resolved to, so a row is readable without a map.
            $table->string('clock_in_place', 255)->nullable()->after('clock_out_photo');
            // What the setting was at the time, so a row with no photo can say
            // why rather than looking like a failure.
            $table->enum('photo_requirement', ['off', 'optional', 'mandatory'])
                ->nullable()->after('clock_in_place');
        });

        // Nothing was captured before today, so every existing row says so.
        DB::table('attendances')->whereNull('photo_requirement')->update(['photo_requirement' => 'off']);
    }

    public function down(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->dropColumn(['clock_in_photo', 'clock_out_photo', 'clock_in_place', 'photo_requirement']);
        });

        Schema::table('attendance_settings', function (Blueprint $table) {
            $table->dropColumn(['clock_in_photo', 'clock_in_location']);
        });
    }

};
