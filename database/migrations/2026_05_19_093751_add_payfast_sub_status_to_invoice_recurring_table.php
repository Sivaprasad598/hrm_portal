<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoice_recurring', function (Blueprint $table) {
            // 1 = active subscription on PayFast, 0 = cancelled/not yet set
            $table->tinyInteger('payfast_sub_status')->default(0)->after('payfast_token');
        });
    }

    public function down(): void
    {
        Schema::table('invoice_recurring', function (Blueprint $table) {
            $table->dropColumn('payfast_sub_status');
        });
    }
};
