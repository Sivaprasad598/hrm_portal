<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{

    /**
     * Project (lead_projects) a meeting is about, chosen on the meeting form.
     */
    public function up(): void
    {
        if (Schema::hasColumn('customer_meetings', 'lead_project_id')) {
            return;
        }

        Schema::table('customer_meetings', function (Blueprint $table) {
            $table->unsignedBigInteger('lead_project_id')->nullable()->after('client_id')->index('customer_meetings_lead_project_idx');

            $table->foreign('lead_project_id', 'customer_meetings_lead_project_fk')->references('id')->on('lead_projects')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('customer_meetings', function (Blueprint $table) {
            $table->dropForeign('customer_meetings_lead_project_fk');
            $table->dropIndex('customer_meetings_lead_project_idx');
            $table->dropColumn('lead_project_id');
        });
    }

};
