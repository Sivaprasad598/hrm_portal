<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The final settlement takes off what is recorded against the leaver for
 * assets they lost or damaged (Assets -> Take Return -> amount to recover).
 */
return new class extends Migration {

    public function up(): void
    {
        if (!Schema::hasColumn('settlements', 'asset_recovery')) {
            Schema::table('settlements', function (Blueprint $table) {
                $table->decimal('asset_recovery', 12, 2)->default(0)->after('advances_outstanding');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('settlements', 'asset_recovery')) {
            Schema::table('settlements', function (Blueprint $table) {
                $table->dropColumn('asset_recovery');
            });
        }
    }

};
