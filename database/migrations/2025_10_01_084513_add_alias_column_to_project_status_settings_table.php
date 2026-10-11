<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('project_status_settings', function (Blueprint $table) {
            $table->string('alias')->nullable()->after('status_name');
        });

        // Populate alias with status_name values for existing records
        DB::statement('UPDATE project_status_settings SET alias = status_name WHERE alias IS NULL');
        
        // Make alias NOT NULL after populating
        Schema::table('project_status_settings', function (Blueprint $table) {
            $table->string('alias')->nullable(false)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('project_status_settings', function (Blueprint $table) {
            $table->dropColumn('alias');
        });
    }
};
