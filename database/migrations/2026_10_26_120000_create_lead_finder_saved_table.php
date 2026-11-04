<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{

    /**
     * Lead Finder lists of each user:
     *   kind = target          a company saved with "Save target" (sidebar: My Targets)
     *          saved_project   a project saved with "Save project" (sidebar: Saved Projects)
     *          target_project  a project marked with "Target project" (sidebar: Target Projects)
     * item_key identifies the company (its name) or project (company name | project name); data is the company or
     * project as the finder shows it (from the ChatGPT search), so the lists stay after new searches.
     */
    public function up(): void
    {
        Schema::create('lead_finder_saved', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('company_id')->nullable()->index();
            $table->unsignedInteger('user_id');
            $table->string('kind', 20);
            $table->string('item_key', 191);
            $table->longText('data');
            $table->timestamps();

            $table->unique(['user_id', 'kind', 'item_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lead_finder_saved');
    }

};
