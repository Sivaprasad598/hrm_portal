<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * What is owed when somebody leaves, worked out once and then frozen.
 *
 * A settlement is a statement, not a running figure: it is drafted, agreed and
 * paid, and from the moment it is agreed the numbers behind it stop moving even
 * if the salary or the leave balance later changes. That is the whole point of
 * signing one.
 */
return new class extends Migration
{

    public function up(): void
    {
        Schema::create('settlements', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->nullable();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('resignation_id')->nullable();

            $table->date('last_working_day');
            $table->date('joined_on')->nullable();
            $table->decimal('years_served', 6, 2)->default(0);

            // What is owed.
            $table->decimal('salary_days', 6, 2)->default(0);
            $table->decimal('salary_due', 12, 2)->default(0);
            $table->decimal('leave_days', 6, 2)->default(0);
            $table->decimal('leave_encashment', 12, 2)->default(0);
            $table->decimal('gratuity', 12, 2)->default(0);
            $table->decimal('bonus_and_other', 12, 2)->default(0);
            $table->decimal('total_payable', 12, 2)->default(0);

            // What comes off it.
            $table->decimal('notice_shortfall_days', 6, 2)->default(0);
            $table->decimal('notice_recovery', 12, 2)->default(0);
            $table->decimal('advances_outstanding', 12, 2)->default(0);
            $table->decimal('other_recovery', 12, 2)->default(0);
            $table->decimal('statutory_deductions', 12, 2)->default(0);
            $table->decimal('total_deductions', 12, 2)->default(0);

            $table->decimal('net_payable', 12, 2)->default(0);

            // draft while it is being argued over, agreed once both sides
            // accept it, paid when the money has gone.
            $table->enum('status', ['draft', 'agreed', 'paid', 'cancelled'])->default('draft');
            $table->json('workings')->nullable();
            $table->string('note', 500)->nullable();

            $table->unsignedBigInteger('prepared_by')->nullable();
            $table->timestamp('agreed_at')->nullable();
            $table->unsignedBigInteger('agreed_by')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->unsignedBigInteger('paid_by')->nullable();
            $table->string('payment_reference', 100)->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
        });

        // Gratuity is payable after a qualifying number of years, at a rate per
        // year on the last drawn basic. Both are law, so both are settings.
        Schema::table('payroll_calculations', function (Blueprint $table) {
            $table->decimal('gratuity_years', 4, 1)->nullable()->after('period_start_day');
            $table->decimal('gratuity_days_per_year', 4, 1)->nullable()->after('gratuity_years');
            $table->decimal('gratuity_ceiling', 12, 2)->nullable()->after('gratuity_days_per_year');
        });

        DB::table('payroll_calculations')->update([
            'gratuity_years' => 5,
            'gratuity_days_per_year' => 15,
            'gratuity_ceiling' => 2000000,
        ]);
    }

    public function down(): void
    {
        Schema::table('payroll_calculations', function (Blueprint $table) {
            $table->dropColumn(['gratuity_years', 'gratuity_days_per_year', 'gratuity_ceiling']);
        });

        Schema::dropIfExists('settlements');
    }

};
