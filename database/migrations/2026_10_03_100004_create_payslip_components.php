<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pay that is not part of the standard salary: overtime, a bonus, arrears, an
 * advance being recovered, a loan instalment.
 *
 * Two tables. One holds what is owed or recovered in a particular month, which
 * is what reaches the payslip. The other holds a standing arrangement — a loan
 * of so much, so much a month — from which those monthly lines are drawn, so
 * the balance left is always known.
 */
return new class extends Migration
{

    public function up(): void
    {
        Schema::create('payslip_components', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->nullable();
            $table->unsignedBigInteger('user_id');
            // Which pay period it belongs to. The payslip is attached once issued.
            $table->date('period_start');
            $table->unsignedBigInteger('payslip_id')->nullable();

            $table->enum('kind', ['earning', 'deduction']);
            $table->string('label', 100);
            $table->decimal('amount', 12, 2);
            // Where it came from: a standing arrangement, or entered by hand.
            $table->unsignedBigInteger('arrangement_id')->nullable();
            $table->string('note', 255)->nullable();

            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'period_start']);
            $table->index('payslip_id');
        });

        Schema::create('payroll_arrangements', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->nullable();
            $table->unsignedBigInteger('user_id');

            $table->enum('kind', ['earning', 'deduction'])->default('deduction');
            $table->string('label', 100);                 // "Salary advance", "Laptop loan"
            $table->decimal('total_amount', 12, 2);       // what was advanced or is owed
            $table->decimal('instalment', 12, 2);         // taken each month
            $table->decimal('recovered', 12, 2)->default(0);
            $table->date('starts_on');
            $table->date('ends_on')->nullable();
            $table->enum('status', ['active', 'completed', 'cancelled'])->default('active');
            $table->string('note', 255)->nullable();

            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payslip_components');
        Schema::dropIfExists('payroll_arrangements');
    }

};
