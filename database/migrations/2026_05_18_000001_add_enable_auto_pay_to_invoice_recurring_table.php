<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {

    public function up()
    {
        Schema::table('invoice_recurring', function (Blueprint $table) {
            if (!Schema::hasColumn('invoice_recurring', 'enable_auto_pay')) {
                $table->tinyInteger('enable_auto_pay')->default(0)->after('client_can_stop');
            }

            if (!Schema::hasColumn('invoice_recurring', 'payfast_token')) {
                $table->string('payfast_token')->nullable()->after('enable_auto_pay');
            }
        });
    }

    public function down()
    {
        Schema::table('invoice_recurring', function (Blueprint $table) {
            $table->dropColumn(['enable_auto_pay', 'payfast_token']);
        });
    }
};
