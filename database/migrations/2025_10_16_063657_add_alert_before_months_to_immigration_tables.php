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
        Schema::table('passport_details', function (Blueprint $table) {
            $table->integer('alert_before_months')->default(0)->after('file');
        });

        Schema::table('visa_details', function (Blueprint $table) {
            $table->integer('alert_before_months')->default(0)->after('file');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('passport_details', function (Blueprint $table) {
            $table->dropColumn('alert_before_months');
        });

        Schema::table('visa_details', function (Blueprint $table) {
            $table->dropColumn('alert_before_months');
        });
    }
};