<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('notice_period_settings', 'name')) {
            Schema::table('notice_period_settings', function (Blueprint $table) {
                $table->string('name')->nullable()->after('company_id');
            });
        }
    }

    public function down(): void
    {
        Schema::table('notice_period_settings', function (Blueprint $table) {
            $table->dropColumn('name');
        });
    }
};
