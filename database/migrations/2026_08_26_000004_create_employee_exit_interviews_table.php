<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('employee_exit_interviews')) {
            Schema::create('employee_exit_interviews', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('exit_id')->unique();
                $table->date('interview_date')->nullable();
                $table->unsignedInteger('interviewer_id')->nullable();
                $table->text('reason_for_leaving')->nullable();
                $table->tinyInteger('overall_experience')->nullable()->comment('1-5 rating');
                $table->text('management_feedback')->nullable();
                $table->tinyInteger('job_satisfaction')->nullable()->comment('1-5 rating');
                $table->text('compensation_feedback')->nullable();
                $table->text('work_environment_feedback')->nullable();
                $table->text('career_growth_feedback')->nullable();
                $table->text('suggestions')->nullable();
                $table->enum('rehire_eligibility', ['eligible', 'not_eligible', 'review_required'])->nullable();
                $table->text('additional_comments')->nullable();
                $table->timestamps();

                $table->foreign('exit_id')->references('id')->on('employee_exits')->onDelete('cascade');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_exit_interviews');
    }
};
