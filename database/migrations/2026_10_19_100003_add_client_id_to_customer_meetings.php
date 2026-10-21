<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{

    /**
     * Meetings can also be held with clients (users with the client role), from "Add Meetings" on the Clients page.
     */
    public function up(): void
    {
        if (Schema::hasColumn('customer_meetings', 'client_id')) {
            return;
        }

        Schema::table('customer_meetings', function (Blueprint $table) {
            $table->unsignedInteger('client_id')->nullable()->after('lead_id')->index('customer_meetings_client_idx');

            $table->foreign('client_id', 'customer_meetings_client_fk')->references('id')->on('users')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('customer_meetings', function (Blueprint $table) {
            $table->dropForeign('customer_meetings_client_fk');
            $table->dropIndex('customer_meetings_client_idx');
            $table->dropColumn('client_id');
        });
    }

};
