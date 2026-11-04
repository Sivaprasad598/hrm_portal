<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{

    /**
     * lead_finder_projects also holds the Lead Finder's own buying signals now (the "Buying signal" dropdown's Add
     * button): type = project (Research projects) | signal (Buying signal). A name is unique per company and type.
     */
    public function up(): void
    {
        Schema::table('lead_finder_projects', function (Blueprint $table) {
            $table->string('type', 20)->default('project')->after('company_id');
        });

        Schema::table('lead_finder_projects', function (Blueprint $table) {
            $table->dropUnique(['company_id', 'name']);
            $table->unique(['company_id', 'type', 'name']);
        });
    }

    public function down(): void
    {
        Schema::table('lead_finder_projects', function (Blueprint $table) {
            $table->dropUnique(['company_id', 'type', 'name']);
        });

        Schema::table('lead_finder_projects', function (Blueprint $table) {
            $table->dropColumn('type');
            $table->unique(['company_id', 'name']);
        });
    }

};
