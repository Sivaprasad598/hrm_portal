<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{

    /**
     * Meeting title, shown on the meeting card and used for the calendar event / Teams meeting name.
     */
    public function up(): void
    {
        if (Schema::hasColumn('customer_meetings', 'title')) {
            return;
        }

        Schema::table('customer_meetings', function (Blueprint $table) {
            $table->string('title')->nullable()->after('lead_id');
        });
    }

    public function down(): void
    {
        Schema::table('customer_meetings', function (Blueprint $table) {
            $table->dropColumn('title');
        });
    }

};
