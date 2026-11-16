<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Relieving cum experience letters (RelievingLetter): one row per letter
 * issued to an employee who has left - no reference number, as the company's
 * letter has none; the date and who issued it, so it can be reopened.
 * The wording lives with the offer letter's in offer_letter_settings.
 */
return new class extends Migration {

    public function up(): void
    {
        if (!Schema::hasTable('relieving_letters')) {
            Schema::create('relieving_letters', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->nullable();
                $table->unsignedInteger('user_id');
                $table->unsignedBigInteger('employee_exit_id')->nullable();
                $table->date('letter_date');
                $table->unsignedInteger('generated_by')->nullable();
                $table->timestamps();

                $table->index('user_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('relieving_letters');
    }

};
