<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('employee_monthly_balance')) {
            return;
        }

        Schema::create('employee_monthly_balance', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('user_id');
            $table->date('month_year');
            $table->decimal('cl_allocated', 3, 1)->default(1.0);
            $table->decimal('sl_allocated', 3, 1)->default(1.0);
            $table->decimal('cl_used_total', 3, 1)->default(0);
            $table->decimal('sl_used_total', 3, 1)->default(0);
            $table->integer('half_days_used')->default(0);
            $table->decimal('cl_pending', 3, 1)->default(0);
            $table->decimal('sl_pending', 3, 1)->default(0);
            $table->decimal('carry_forward_cl', 3, 1)->default(0);
            $table->decimal('lop_days', 3, 1)->default(0);
            $table->decimal('remaining_cl', 3, 1)->default(0);
            $table->decimal('remaining_sl', 3, 1)->default(0);
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->unique(['user_id', 'month_year'], 'unique_user_month');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade')->onUpdate('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employee_monthly_balance');
    }
};
