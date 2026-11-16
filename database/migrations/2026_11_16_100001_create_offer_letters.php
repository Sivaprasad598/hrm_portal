<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Offer letters for employees (OfferLetter service):
 *  - offer_letter_settings: the letter's wording, header / footer and numbering,
 *    one row per company, edited in Settings -> Offer Letter Settings;
 *  - offer_letters: each letter issued - its reference number (0025/2026),
 *    the employee and who issued it, so a reprint keeps its number and the
 *    next one is suggested from the last;
 *  - employee_details.notice_period_setting_id: the notice period chosen when
 *    the employee is registered (from Settings -> Notice Period Settings).
 * Each step checks first, so running it again changes nothing.
 */
return new class extends Migration {

    public function up(): void
    {
        if (!Schema::hasTable('offer_letter_settings')) {
            Schema::create('offer_letter_settings', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->nullable()->unique();
                $table->longText('content')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('offer_letters')) {
            Schema::create('offer_letters', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->nullable();
                $table->unsignedInteger('user_id');
                $table->string('reference_no', 40);
                $table->unsignedInteger('seq')->default(0);
                $table->unsignedSmallInteger('year')->nullable();
                $table->date('letter_date');
                $table->unsignedInteger('generated_by')->nullable();
                $table->timestamps();

                $table->unique(['company_id', 'reference_no']);
                $table->index(['company_id', 'year', 'seq']);
                $table->index('user_id');
            });
        }

        if (Schema::hasTable('employee_details') && !Schema::hasColumn('employee_details', 'notice_period_setting_id')) {
            Schema::table('employee_details', function (Blueprint $table) {
                $table->unsignedBigInteger('notice_period_setting_id')->nullable()->after('notice_period_end_date');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('employee_details', 'notice_period_setting_id')) {
            Schema::table('employee_details', function (Blueprint $table) {
                $table->dropColumn('notice_period_setting_id');
            });
        }

        Schema::dropIfExists('offer_letters');
        Schema::dropIfExists('offer_letter_settings');
    }

};
