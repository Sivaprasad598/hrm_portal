<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Payslips made in the Letter Generator (ManualPayslip), kept for reference
 * only: who, which months, what it came to, and the details it was drawn
 * from, so it can be opened again. Not payroll: nothing here is read by
 * payroll, registers, tax or the employee's own payslips.
 */
return new class extends Migration {

    public function up(): void
    {
        if (!Schema::hasTable('generated_payslips')) {
            Schema::create('generated_payslips', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->nullable();
                $table->unsignedInteger('user_id')->nullable();
                $table->string('person_name', 191);
                $table->string('employee_code', 60)->nullable();
                $table->date('month_from');
                $table->date('month_to');
                $table->unsignedTinyInteger('months')->default(1);
                $table->decimal('net_total', 15, 2)->default(0);
                $table->string('details_hash', 64);
                $table->longText('details');
                $table->unsignedInteger('generated_by')->nullable();
                $table->timestamps();

                $table->index(['company_id', 'details_hash']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('generated_payslips');
    }

};
