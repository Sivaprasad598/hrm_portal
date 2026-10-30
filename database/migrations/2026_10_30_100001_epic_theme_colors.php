<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The Epic HR look's colours, chosen under Settings > Theme Settings: primary,
 * accent, dark and strip, as JSON. Empty means the template's own blue and green.
 * On global settings so the sign-in page, which has no company yet, uses them too.
 */
return new class extends Migration {

    public function up(): void
    {
        if (!Schema::hasColumn('global_settings', 'epic_colors')) {
            Schema::table('global_settings', function (Blueprint $table) {
                $table->text('epic_colors')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('global_settings', 'epic_colors')) {
            Schema::table('global_settings', function (Blueprint $table) {
                $table->dropColumn('epic_colors');
            });
        }
    }
};
