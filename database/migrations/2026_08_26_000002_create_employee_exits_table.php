<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('employee_exits')) {
            Schema::create('employee_exits', function (Blueprint $table) {
                $table->id();
                $table->unsignedInteger('user_id');
                $table->unsignedInteger('company_id');
                $table->enum('exit_type', ['resignation', 'termination', 'contract_end', 'retirement', 'absconded', 'other'])->nullable();
                $table->text('exit_reason')->nullable();
                $table->date('resignation_date')->nullable();
                $table->integer('notice_period_days')->nullable();
                $table->date('last_working_date')->nullable();
                $table->enum('exit_interview_status', ['pending', 'completed', 'waived'])->default('pending');
                $table->enum('asset_clearance_status', ['pending', 'completed'])->default('pending');
                $table->enum('finance_clearance_status', ['pending', 'completed'])->default('pending');
                $table->enum('hr_clearance_status', ['pending', 'completed'])->default('pending');
                $table->enum('final_settlement_status', ['pending', 'completed'])->default('pending');
                $table->date('offboarding_date')->nullable();
                $table->text('remarks')->nullable();
                $table->unsignedInteger('exit_initiated_by')->nullable();
                $table->date('exit_initiated_date')->nullable();
                $table->unsignedInteger('created_by')->nullable();
                $table->unsignedInteger('updated_by')->nullable();
                $table->timestamps();

                $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_exits');
    }
};
