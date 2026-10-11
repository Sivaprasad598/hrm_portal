<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * When a payslip was sent to the employee, and where.
 *
 * Without this there is no way to tell who has had theirs, so a second run of
 * "email everyone" would send duplicates with nobody any the wiser.
 */
return new class extends Migration
{

    public function up(): void
    {
        Schema::table('payslips', function (Blueprint $table) {
            $table->timestamp('sent_at')->nullable()->after('generated_at');
            $table->string('sent_to', 191)->nullable()->after('sent_at');
            $table->unsignedBigInteger('sent_by')->nullable()->after('sent_to');
        });
    }

    public function down(): void
    {
        Schema::table('payslips', function (Blueprint $table) {
            $table->dropColumn(['sent_at', 'sent_to', 'sent_by']);
        });
    }

};
