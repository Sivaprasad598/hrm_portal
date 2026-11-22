<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * When the offer was emailed to the candidate with the link to accept or decline it online
 * (App\Services\Recruit\OfferReply). Idempotent.
 */
return new class extends Migration {

    public function up(): void
    {
        if (Schema::hasTable('job_offers') && !Schema::hasColumn('job_offers', 'emailed_at')) {
            Schema::table('job_offers', function (Blueprint $table) {
                $table->timestamp('emailed_at')->nullable()->after('sent_at');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('job_offers') && Schema::hasColumn('job_offers', 'emailed_at')) {
            Schema::table('job_offers', function (Blueprint $table) {
                $table->dropColumn('emailed_at');
            });
        }
    }

};
