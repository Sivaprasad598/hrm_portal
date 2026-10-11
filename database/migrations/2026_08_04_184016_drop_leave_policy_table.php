<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Leave allocation/carry-forward rules now come from the existing
     * Leave Settings (leave_types) page instead of this separate table.
     */
    public function up(): void
    {
        Schema::dropIfExists('leave_policy');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::create('leave_policy', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('company_id')->index();
            $table->unsignedInteger('leave_type_id')->index();
            $table->decimal('monthly_allocation', 8, 2)->default(0);
            $table->boolean('carry_forward')->default(false);
            $table->unsignedInteger('half_day_conversion_rate')->default(2);
            $table->decimal('fixed_annual_days', 8, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['company_id', 'leave_type_id']);
        });
    }
};
