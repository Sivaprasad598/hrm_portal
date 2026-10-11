<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{

    /**
     * Widen payfast_token from VARCHAR(191) to TEXT so it can store the full
     * Laravel-encrypted payload (~256 chars).  Any truncated values already in
     * the column are corrupt and cannot be decrypted, so we null them out —
     * affected customers will be prompted to re-authorise on their next invoice.
     *
     * We also null out payfast_sub_status for those rows so the subscription
     * manager correctly shows them as needing a new token.
     */
    public function up(): void
    {
        // Widen the column first.
        Schema::table('invoice_recurring', function (Blueprint $table) {
            $table->text('payfast_token')->nullable()->change();
        });

        // Clear any previously truncated (< 200 char) encrypted tokens — a valid
        // Laravel encrypted UUID is always >= 200 characters.
        DB::table('invoice_recurring')
            ->whereNotNull('payfast_token')
            ->whereRaw('CHAR_LENGTH(payfast_token) < 200')
            ->update([
                'payfast_token'    => null,
                'payfast_sub_status' => 0,
                'enable_auto_pay'  => 0,
            ]);
    }

    public function down(): void
    {
        Schema::table('invoice_recurring', function (Blueprint $table) {
            $table->string('payfast_token')->nullable()->change();
        });
    }

};
