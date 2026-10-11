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
        if (Schema::hasTable('emp_final_declaration')) {
            return;
        }

        Schema::create('emp_final_declaration', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('user_id')->index();
            $table->string('reported_duty_day')->nullable();
            $table->string('reported_duty_month')->nullable();
            $table->string('reported_duty_year')->nullable();
            $table->string('candidate_signature')->nullable();
            $table->string('hr_signature')->nullable();
            $table->longText('hr_comments')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('emp_final_declaration');
    }
};
