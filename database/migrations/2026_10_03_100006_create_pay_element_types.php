<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The kinds of extra pay the company uses: overtime, a bonus, arrears, a
 * salary advance, a loan being recovered — and whatever else is added later.
 *
 * Each is a credit (paid on top) or a debit (taken off). Having them on a list
 * rather than typed in by hand means the same thing is called the same thing
 * every month, so a year's overtime can be added up.
 *
 * The amounts themselves stay where they were: `payslip_components` for one
 * period, `payroll_arrangements` for something recovered over several. Both
 * gain a pointer to the type that produced them.
 */
return new class extends Migration
{

    public function up(): void
    {
        Schema::create('pay_element_types', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->nullable();
            $table->string('name', 100);
            // credit: paid on top. debit: taken off. The payslip's own wording.
            $table->enum('kind', ['earning', 'deduction']);
            // A standing arrangement makes sense for an advance or a loan, not
            // for overtime; this decides which tab offers the type.
            $table->boolean('allows_arrangement')->default(false);
            $table->decimal('default_amount', 12, 2)->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('note', 255)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'name']);
            $table->index(['is_active', 'kind']);
        });

        Schema::table('payslip_components', function (Blueprint $table) {
            // Which type it came from. The label is still written on the line,
            // so renaming a type never rewrites a payslip already issued.
            $table->unsignedBigInteger('element_type_id')->nullable()->after('kind');
            $table->index('element_type_id');
        });

        Schema::table('payroll_arrangements', function (Blueprint $table) {
            $table->unsignedBigInteger('element_type_id')->nullable()->after('kind');
        });

        // An open-ended arrangement — a monthly allowance, a recovery that runs
        // until it is stopped — has no total to work towards.
        DB::statement('ALTER TABLE payroll_arrangements MODIFY total_amount DECIMAL(12,2) NULL');

        $company = DB::table('users')->whereNotNull('company_id')->value('company_id');

        $seed = [
            ['Overtime', 'earning', false],
            ['Bonus', 'earning', false],
            ['Arrears', 'earning', false],
            ['Incentive', 'earning', false],
            ['Reimbursement', 'earning', false],
            ['Salary Advance', 'deduction', true],
            ['Loan Recovery', 'deduction', true],
            ['Other Deduction', 'deduction', false],
        ];

        $order = 0;
        $rows = [];

        foreach ($seed as [$name, $kind, $arrangement]) {
            $rows[] = [
                'company_id' => $company,
                'name' => $name,
                'kind' => $kind,
                'allows_arrangement' => $arrangement,
                'is_active' => true,
                'sort_order' => $order += 10,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        DB::table('pay_element_types')->insert($rows);
    }

    public function down(): void
    {
        Schema::table('payslip_components', function (Blueprint $table) {
            $table->dropIndex(['element_type_id']);
            $table->dropColumn('element_type_id');
        });

        Schema::table('payroll_arrangements', function (Blueprint $table) {
            $table->dropColumn('element_type_id');
        });

        Schema::dropIfExists('pay_element_types');
    }

};
