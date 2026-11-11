<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Payroll Settings -> "Use biometric attendance for payroll".
 *
 * On (the default, as before): payroll waits until the device feed reaches the
 * end of the pay cycle and pushes any device days into ERP attendance first.
 * Off: payroll pays from the attendance already in the system (the daily
 * biometric sync, web / app clock-ins, HR entries) and never waits on the
 * device. Either way someone with no attendance at all is not paid until it is
 * entered or they are marked "Attendance exempt".
 */
return new class extends Migration {

    public function up(): void
    {
        if (!Schema::hasTable('payroll_options')) {
            Schema::create('payroll_options', function (Blueprint $table) {
                $table->id();
                $table->unsignedInteger('company_id')->nullable()->unique();
                $table->boolean('use_biometric_attendance')->default(true);
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_options');
    }

};
