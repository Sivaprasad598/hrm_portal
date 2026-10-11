<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A photograph and a place for each clock-OUT, matching what clock-in already
 * records.
 *
 * `clock_out_photo` was added alongside `clock_in_photo` and has never been
 * written, so only the place and the coordinates are new here. They cannot
 * share `latitude`/`longitude`: that pair is where the person clocked in, and
 * overwriting it on the way out would destroy the morning's record.
 *
 * `clock_out_place` mirrors `clock_in_place`, which until now was declared and
 * never filled. Both are a snapshot in words of where the person said they
 * were — the chosen office and the work-from type, read at the moment of the
 * punch. That matters because `clock_out_time_location_id` is a foreign key:
 * rename or delete a company address next year and every historical row starts
 * reporting the new name, or nothing. The snapshot does not move.
 *
 * Settings are deliberately NOT duplicated. `attendance_settings.clock_in_photo`
 * and `.clock_in_location` govern both ends of the day.
 */
return new class extends Migration
{

    public function up(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            // Same precision as the clock-in pair they sit beside.
            $table->decimal('clock_out_latitude', 10, 8)->nullable()->after('photo_requirement');
            $table->decimal('clock_out_longitude', 11, 8)->nullable()->after('clock_out_latitude');
            $table->string('clock_out_place', 255)->nullable()->after('clock_out_longitude');
            // What the setting was at the time, so a clock-out with no picture
            // can say why rather than looking like something failed.
            $table->enum('clock_out_photo_requirement', ['off', 'optional', 'mandatory'])
                ->nullable()->after('clock_out_place');
        });

        // Every clock-out recorded before today happened with nothing asked for.
        DB::table('attendances')
            ->whereNotNull('clock_out_time')
            ->whereNull('clock_out_photo_requirement')
            ->update(['clock_out_photo_requirement' => 'off']);
    }

    public function down(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->dropColumn([
                'clock_out_latitude',
                'clock_out_longitude',
                'clock_out_place',
                'clock_out_photo_requirement',
            ]);
        });
    }

};
