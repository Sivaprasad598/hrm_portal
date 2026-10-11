<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Which design a payslip is printed in.
 *
 * Two things are needed, and they are not the same thing:
 *
 *   payslip_template_settings  what new payslips will be printed in
 *   payslips.template          what this payslip was printed in
 *
 * The second is the point. A payslip is a document somebody was given; it has
 * to keep looking the way it looked when it was issued. Without a column on the
 * payslip, changing the setting would silently restyle every slip ever issued,
 * including ones already e-mailed and filed.
 *
 * Existing payslips are left null and fall back to the setting when rendered —
 * they were all issued in the classic design, which is what the setting starts
 * as, so they come out unchanged.
 */
return new class extends Migration
{

    public function up(): void
    {
        if (!Schema::hasTable('payslip_template_settings')) {
            Schema::create('payslip_template_settings', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->nullable()->index();
                $table->string('template', 40)->default('classic');
                $table->unsignedBigInteger('updated_by')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasColumn('payslips', 'template')) {
            Schema::table('payslips', function (Blueprint $table) {
                // Null means "whatever the setting says", which is how every
                // payslip issued before this migration behaves.
                $table->string('template', 40)->nullable()->after('payslip_number');
            });
        }

        // One row per company, starting on the design they already have.
        foreach (DB::table('companies')->pluck('id') as $companyId) {
            DB::table('payslip_template_settings')->insertOrIgnore([
                'company_id' => $companyId,
                'template' => 'classic',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('payslips', 'template')) {
            Schema::table('payslips', function (Blueprint $table) {
                $table->dropColumn('template');
            });
        }

        Schema::dropIfExists('payslip_template_settings');
    }

};
