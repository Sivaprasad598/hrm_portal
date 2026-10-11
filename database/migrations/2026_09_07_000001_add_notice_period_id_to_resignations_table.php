<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('resignations', function (Blueprint $table) {
            if (!Schema::hasColumn('resignations', 'notice_period_id')) {
                $table->unsignedInteger('notice_period_id')->nullable()->after('notice_period_days');
                $table->foreign('notice_period_id')->references('id')->on('notice_period_settings')->onDelete('set null');
            }
        });
    }

    public function down(): void
    {
        Schema::table('resignations', function (Blueprint $table) {
            $table->dropForeign(['notice_period_id']);
            $table->dropColumn('notice_period_id');
        });
    }
};
