<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{

    /**
     * Project Name on a lead contact: chosen from a list that grows with "Add" on the lead form.
     */
    public function up(): void
    {
        if (!Schema::hasTable('lead_projects')) {
            Schema::create('lead_projects', function (Blueprint $table) {
                $table->id();
                $table->unsignedInteger('company_id')->nullable()->index('lead_projects_company_idx');
                $table->string('name', 191);
                $table->unsignedInteger('added_by')->nullable();
                $table->timestamps();

                $table->unique(['company_id', 'name'], 'lead_projects_company_name_unique');
            });
        }

        if (!Schema::hasColumn('leads', 'lead_project_id')) {
            Schema::table('leads', function (Blueprint $table) {
                $table->unsignedBigInteger('lead_project_id')->nullable()->after('source_id')->index('leads_lead_project_idx');

                $table->foreign('lead_project_id', 'leads_lead_project_fk')->references('id')->on('lead_projects')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('leads', 'lead_project_id')) {
            Schema::table('leads', function (Blueprint $table) {
                $table->dropForeign('leads_lead_project_fk');
                $table->dropIndex('leads_lead_project_idx');
                $table->dropColumn('lead_project_id');
            });
        }

        Schema::dropIfExists('lead_projects');
    }

};
