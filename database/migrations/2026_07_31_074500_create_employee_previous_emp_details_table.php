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
        Schema::create('employee_previous_emp_details', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('user_id')->index();
            $table->string('total_work_experience')->nullable();
            $table->string('previous_company_name')->nullable();
            $table->string('company_location')->nullable();
            $table->string('last_held_designation')->nullable();
            $table->date('date_of_joining')->nullable();
            $table->date('date_of_leaving')->nullable();
            $table->string('employee_id')->nullable();
            $table->string('pf_account_number')->nullable();
            $table->string('esi_ip_number')->nullable();
            $table->string('personal_account_number')->nullable();
            $table->string('ifsc_code')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employee_previous_emp_details');
    }
};
