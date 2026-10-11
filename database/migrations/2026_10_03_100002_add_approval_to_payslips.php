<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Approval before a payslip counts, and a lock once it is paid.
 *
 * A payroll run used to issue finished payslips the moment the button was
 * pressed, with nothing between a mistake and the employee. Payslips are now
 * drafts until somebody approves them, and once marked paid they cannot be
 * revoked or deleted — a payment that has left the bank is not something the
 * software should pretend never happened.
 *
 * Kept separate from `status` (active / revoked / superseded), which is the
 * record's own lifecycle and is read in a dozen places.
 */
return new class extends Migration
{

    public function up(): void
    {
        Schema::table('payslips', function (Blueprint $table) {
            $table->string('approval_status', 20)->default('draft')->after('status');
            $table->unsignedBigInteger('approved_by')->nullable()->after('approval_status');
            $table->timestamp('approved_at')->nullable()->after('approved_by');
            $table->unsignedBigInteger('paid_by')->nullable()->after('approved_at');
            $table->timestamp('paid_at')->nullable()->after('paid_by');
            $table->string('payment_reference', 100)->nullable()->after('paid_at');

            $table->index(['approval_status', 'period']);
        });

        // Payslips issued before this existed were final when they were made,
        // so they start as approved rather than suddenly becoming drafts.
        DB::table('payslips')->update(['approval_status' => 'approved']);
    }

    public function down(): void
    {
        Schema::table('payslips', function (Blueprint $table) {
            $table->dropIndex(['approval_status', 'period']);
            $table->dropColumn(['approval_status', 'approved_by', 'approved_at', 'paid_by', 'paid_at', 'payment_reference']);
        });
    }

};
