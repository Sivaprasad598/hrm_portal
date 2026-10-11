<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('employee_exit_histories')) {
            Schema::create('employee_exit_histories', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('exit_id')->nullable();
                $table->unsignedInteger('user_id');
                $table->unsignedInteger('company_id');
                $table->string('previous_status', 30)->nullable();
                $table->string('new_status', 30)->nullable();
                $table->string('action', 100);
                $table->unsignedInteger('changed_by');
                $table->text('notes')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_exit_histories');
    }
};
