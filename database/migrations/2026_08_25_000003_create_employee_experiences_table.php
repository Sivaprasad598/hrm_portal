<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('employee_experiences')) {
            Schema::create('employee_experiences', function (Blueprint $table) {
                $table->id();
                $table->unsignedInteger('user_id');
                $table->integer('serial_no')->default(1);
                $table->string('company_name', 100)->nullable();
                $table->string('company_location', 150)->nullable();
                $table->string('designation', 100)->nullable();
                $table->date('joining_date')->nullable();
                $table->date('leaving_date')->nullable();
                $table->timestamps();
                $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_experiences');
    }
};
