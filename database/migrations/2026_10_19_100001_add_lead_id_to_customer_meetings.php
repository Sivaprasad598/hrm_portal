<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{

    /**
     * Meetings are now held with leads (leads table). Older meetings keep their customer.
     */
    public function up(): void
    {
        if (Schema::hasColumn('customer_meetings', 'lead_id')) {
            return;
        }

        Schema::table('customer_meetings', function (Blueprint $table) {
            $table->unsignedBigInteger('customer_id')->nullable()->change();
            $table->unsignedInteger('lead_id')->nullable()->after('customer_id')->index('customer_meetings_lead_idx');

            $table->foreign('lead_id', 'customer_meetings_lead_fk')->references('id')->on('leads')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('customer_meetings', function (Blueprint $table) {
            $table->dropForeign('customer_meetings_lead_fk');
            $table->dropIndex('customer_meetings_lead_idx');
            $table->dropColumn('lead_id');
        });
    }

};
