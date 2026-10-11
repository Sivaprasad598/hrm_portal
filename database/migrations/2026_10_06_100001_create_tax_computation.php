<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Working out income tax rather than being told it.
 *
 * Three things are needed and none of them belong in code:
 *
 *   tax_slabs        the rates, which are law and change every budget
 *   tax_settings     the allowances that go with them
 *   tax_declarations what an employee says they have invested
 *
 * The rates are seeded with the figures for the 2026-27 financial year under
 * both regimes, and every one of them is editable on screen. They are a
 * starting point to be checked against the current Finance Act, not an
 * authority — the seeded rows are marked `needs_review` so nobody mistakes
 * them for one.
 */
return new class extends Migration
{

    public function up(): void
    {
        Schema::create('tax_slabs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->nullable();
            $table->string('financial_year', 9);           // "2026-27"
            $table->enum('regime', ['new', 'old']);
            $table->decimal('upto', 14, 2)->nullable();    // null = everything above
            $table->decimal('rate', 5, 2);                 // per cent
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['financial_year', 'regime', 'sort_order']);
        });

        Schema::create('tax_settings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->nullable();
            $table->string('financial_year', 9);
            $table->enum('regime', ['new', 'old']);

            $table->decimal('standard_deduction', 12, 2)->default(0);
            // Section 87A: no tax at all up to this much taxable income.
            $table->decimal('rebate_upto', 14, 2)->default(0);
            $table->decimal('rebate_max', 12, 2)->default(0);
            $table->decimal('cess_pct', 5, 2)->default(4);
            // Surcharge on very high incomes, as a band table.
            $table->json('surcharge_slabs')->nullable();
            // Which reliefs this regime allows at all.
            $table->boolean('allows_hra_exemption')->default(false);
            $table->boolean('allows_chapter_via')->default(false);
            $table->boolean('allows_professional_tax')->default(false);
            $table->boolean('needs_review')->default(true);
            $table->timestamps();

            $table->unique(['company_id', 'financial_year', 'regime'], 'tax_settings_year_regime_unique');
        });

        // What an employee says they have, which the old regime allows against
        // their income. Declared, then proved; the proof flag is what payroll
        // uses once the year is being closed.
        Schema::create('tax_declarations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->nullable();
            $table->unsignedBigInteger('user_id');
            $table->string('financial_year', 9);
            $table->enum('regime', ['new', 'old'])->default('new');

            $table->decimal('section_80c', 12, 2)->default(0);
            $table->decimal('section_80d', 12, 2)->default(0);
            $table->decimal('section_80ccd_1b', 12, 2)->default(0);
            $table->decimal('home_loan_interest', 12, 2)->default(0);
            $table->decimal('other_deductions', 12, 2)->default(0);
            // For the house rent exemption.
            $table->decimal('rent_paid_annual', 12, 2)->default(0);
            $table->boolean('metro_city')->default(false);
            // Tax already paid elsewhere this year, which reduces what is owed here.
            $table->decimal('previous_employer_income', 14, 2)->default(0);
            $table->decimal('previous_employer_tax', 12, 2)->default(0);

            $table->boolean('proofs_received')->default(false);
            $table->string('note', 255)->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'financial_year'], 'declaration_user_year_unique');
        });

        $this->seed();
    }

    /**
     * The 2026-27 figures, to be checked and corrected on screen.
     */
    protected function seed(): void
    {
        $year = '2026-27';
        $now = now();

        $slabs = [
            // The regime that applies unless an employee chooses otherwise.
            ['new', 400000, 0], ['new', 800000, 5], ['new', 1200000, 10],
            ['new', 1600000, 15], ['new', 2000000, 20], ['new', 2400000, 25], ['new', null, 30],
            // The older one, for anybody who opts into it.
            ['old', 250000, 0], ['old', 500000, 5], ['old', 1000000, 20], ['old', null, 30],
        ];

        $rows = [];
        $order = ['new' => 0, 'old' => 0];

        foreach ($slabs as [$regime, $upto, $rate]) {
            $rows[] = [
                'financial_year' => $year,
                'regime' => $regime,
                'upto' => $upto,
                'rate' => $rate,
                'sort_order' => $order[$regime] += 10,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        DB::table('tax_slabs')->insert($rows);

        DB::table('tax_settings')->insert([
            [
                'financial_year' => $year, 'regime' => 'new',
                'standard_deduction' => 75000,
                'rebate_upto' => 1200000, 'rebate_max' => 60000,
                'cess_pct' => 4,
                'surcharge_slabs' => json_encode([
                    ['upto' => 5000000, 'rate' => 0],
                    ['upto' => 10000000, 'rate' => 10],
                    ['upto' => 20000000, 'rate' => 15],
                    ['upto' => null, 'rate' => 25],
                ]),
                'allows_hra_exemption' => false,
                'allows_chapter_via' => false,
                'allows_professional_tax' => false,
                'needs_review' => true,
                'created_at' => $now, 'updated_at' => $now,
            ],
            [
                'financial_year' => $year, 'regime' => 'old',
                'standard_deduction' => 50000,
                'rebate_upto' => 500000, 'rebate_max' => 12500,
                'cess_pct' => 4,
                'surcharge_slabs' => json_encode([
                    ['upto' => 5000000, 'rate' => 0],
                    ['upto' => 10000000, 'rate' => 10],
                    ['upto' => 20000000, 'rate' => 15],
                    ['upto' => 50000000, 'rate' => 25],
                    ['upto' => null, 'rate' => 37],
                ]),
                'allows_hra_exemption' => true,
                'allows_chapter_via' => true,
                'allows_professional_tax' => true,
                'needs_review' => true,
                'created_at' => $now, 'updated_at' => $now,
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('tax_declarations');
        Schema::dropIfExists('tax_settings');
        Schema::dropIfExists('tax_slabs');
    }

};
