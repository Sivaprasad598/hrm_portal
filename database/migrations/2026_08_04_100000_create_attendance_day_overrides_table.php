<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Manual admin overrides made from the attendance calendar's "Assign
     * Status" panel (CL/SL/LOP/Holiday/Present/Absent/On Leave/WFH/Half Day/
     * Late/Day Off). Kept as its own table rather than writing into
     * `attendances`/`leaves`/`holidays` directly, since those tables already
     * have their own specific semantics (clock-in/out records, leave
     * applications, company-wide holiday definitions) that a one-off manual
     * override for a single employee/day shouldn't be forced into.
     */
    public function up(): void
    {
        if (Schema::hasTable('attendance_day_overrides')) {
            return;
        }

        Schema::create('attendance_day_overrides', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('company_id')->index();
            $table->unsignedInteger('user_id')->index();
            $table->date('override_date');
            $table->string('status', 20);
            $table->unsignedInteger('created_by')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'override_date'], 'ado_user_date_unique');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attendance_day_overrides');
    }
};
