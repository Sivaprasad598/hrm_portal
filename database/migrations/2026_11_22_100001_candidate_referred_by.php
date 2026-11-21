<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Recruitment: the employee who referred a candidate (Source = Referral),
 * picked from the employee list instead of typed in Source Detail.
 * Existing referrals keep their text in source_detail.
 */
return new class extends Migration
{

    public function up(): void
    {
        if (Schema::hasTable('candidates') && !Schema::hasColumn('candidates', 'referred_by')) {
            Schema::table('candidates', function (Blueprint $table) {
                $table->unsignedInteger('referred_by')->nullable()->index()->after('source_detail');   // users.id of the referring employee
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('candidates') && Schema::hasColumn('candidates', 'referred_by')) {
            Schema::table('candidates', function (Blueprint $table) {
                $table->dropIndex(['referred_by']);
                $table->dropColumn('referred_by');
            });
        }
    }

};
