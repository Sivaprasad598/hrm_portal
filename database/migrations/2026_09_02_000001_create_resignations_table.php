<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('resignations')) {
            Schema::create('resignations', function (Blueprint $table) {
                $table->id();
                $table->unsignedInteger('user_id');
                $table->unsignedInteger('company_id');
                $table->enum('exit_type', ['resignation', 'retirement', 'other'])->default('resignation');
                $table->string('reason')->nullable();
                $table->date('resignation_date')->nullable();
                $table->integer('notice_period_days')->nullable();
                $table->date('notice_start_date')->nullable();
                $table->date('last_working_date')->nullable();
                $table->enum('notice_served', ['yes', 'no'])->nullable();
                $table->text('remarks')->nullable();
                $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
                $table->text('admin_remarks')->nullable();
                $table->unsignedInteger('reviewed_by')->nullable();
                $table->timestamp('reviewed_at')->nullable();
                $table->timestamps();

                $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('resignations');
    }
};
