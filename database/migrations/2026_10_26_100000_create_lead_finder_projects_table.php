<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{

    /**
     * Lead Finder (Leads → Lead Generation Bot): the "Research projects" list of the search form. Administrators add
     * projects with the dropdown's Add button; the chosen project goes to ChatGPT with the search.
     */
    public function up(): void
    {
        Schema::create('lead_finder_projects', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('company_id')->nullable()->index();
            $table->string('name', 191);
            $table->unsignedInteger('added_by')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lead_finder_projects');
    }

};
