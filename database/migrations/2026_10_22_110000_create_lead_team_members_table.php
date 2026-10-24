<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{

    /**
     * Lead contact "Assign Team": the employees working a lead, picked by department and designation.
     * One row per employee per lead; the unique key stops the same employee being added twice.
     */
    public function up(): void
    {
        if (!Schema::hasTable('lead_team_members')) {
            Schema::create('lead_team_members', function (Blueprint $table) {
                $table->id();
                $table->unsignedInteger('company_id')->nullable()->index('lead_team_members_company_idx');
                $table->unsignedInteger('lead_id');
                $table->unsignedInteger('user_id');
                $table->unsignedInteger('added_by')->nullable();
                $table->timestamps();

                $table->unique(['lead_id', 'user_id'], 'lead_team_members_lead_user_unique');
                $table->foreign('lead_id', 'lead_team_members_lead_fk')->references('id')->on('leads')->cascadeOnDelete();
                $table->foreign('user_id', 'lead_team_members_user_fk')->references('id')->on('users')->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('lead_team_members');
    }

};
