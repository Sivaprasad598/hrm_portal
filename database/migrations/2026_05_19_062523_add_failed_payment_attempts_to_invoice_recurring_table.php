<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('invoice_recurring', function (Blueprint $table) {
            $table->unsignedTinyInteger('failed_payment_attempts')->default(0)->after('payfast_token');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoice_recurring', function (Blueprint $table) {
            $table->dropColumn('failed_payment_attempts');
        });
    }
};
