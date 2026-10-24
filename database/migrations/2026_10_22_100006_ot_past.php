<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Past overtime (OtPast): what the OT bank held before the system counted it.
 * Mostly one row per month (hours earned, used for LOP, encashed); a month can
 * instead be entered day by day. Every row sits before the go-live date, so it
 * never overlaps the days the system stores itself (ot_daily).
 */
return new class extends Migration {

    public function up(): void
    {
        if (Schema::hasTable('ot_past')) {
            return;
        }

        Schema::create('ot_past', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('company_id')->nullable()->index();
            $table->unsignedInteger('user_id')->index();
            $table->unsignedSmallInteger('year');                  // the salary month it belongs to
            $table->unsignedTinyInteger('month');
            $table->date('date')->nullable();                      // set on a day-wise row
            $table->date('ref_date')->index();                     // the day, or the month's last day
            $table->decimal('ot_hours', 8, 2)->default(0);         // banked (after the rate)
            $table->decimal('raw_hours', 8, 2)->nullable();        // as worked, for reference
            $table->decimal('used_hours', 8, 2)->default(0);       // used to cover LOP
            $table->decimal('used_days', 6, 2)->nullable();
            $table->decimal('encashed_hours', 8, 2)->default(0);
            $table->decimal('encashed_amount', 12, 2)->nullable();
            $table->string('note', 255)->nullable();
            $table->string('source', 10)->default('manual');       // manual | import
            $table->unsignedInteger('created_by')->nullable();
            $table->unsignedInteger('updated_by')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'year', 'month']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ot_past');
    }

};
