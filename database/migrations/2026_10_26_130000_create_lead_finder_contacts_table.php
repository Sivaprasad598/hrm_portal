<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{

    /**
     * Lead Finder → Contacts: companies added from their company page ("Add to contacts") and the details of the
     * conversations with them ("Add contact details": the person, notes, follow-up, projects discussed).
     * Shared by the administrators of the company; added_by shows who added each entry.
     */
    public function up(): void
    {
        Schema::create('lead_finder_contacts', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('company_id')->nullable()->index();
            $table->unsignedInteger('added_by')->nullable();
            $table->string('company_key', 191)->index();   // the company's name, normalised
            $table->longText('company');                    // the company as the finder shows it (JSON)
            $table->string('contact_name', 150)->nullable();
            $table->string('designation', 150)->nullable();
            $table->string('phone', 60)->nullable();
            $table->string('email', 191)->nullable();
            $table->text('notes')->nullable();
            $table->boolean('follow_up')->default(false);
            $table->date('follow_up_date')->nullable();
            $table->text('projects')->nullable();           // [{name, details}] when projects were discussed (JSON)
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lead_finder_contacts');
    }

};
