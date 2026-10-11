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
        Schema::create('attendance_regularisations', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('company_id')->unsigned()->nullable();
            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade')->onUpdate('cascade');
            $table->unsignedInteger('user_id')->index('attendances_user_id_foreign');
            $table->foreign(['user_id'])->references(['id'])->on('users')->onUpdate('CASCADE')->onDelete('CASCADE');
            $table->date('date');
            $table->dateTime('clock_in_time')->index();
            $table->dateTime('clock_out_time')->nullable()->index();
            $table->string('working_from')->default('office');
            $table->string('other_location')->nullable();
            $table->enum('status', ['pending', 'accept', 'reject'])->default('pending');
            $table->unsignedBigInteger('employee_shift_id')->nullable()->index('attendances_employee_shift_id_foreign');
            $table->foreign(['employee_shift_id'])->references(['id'])->on('employee_shifts')->onUpdate('CASCADE')->onDelete('SET NULL');
            $table->text('reject_reason')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attendance_regularisations');
    }
};
