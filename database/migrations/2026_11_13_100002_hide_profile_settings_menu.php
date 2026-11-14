<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Settings -> "Profile Settings" off the Settings menu, for everyone.
 *
 * The entry is switched off (is_active = 0), not deleted: down() brings it back.
 * Looked up by slug / route, not by id, so it is the same on every server.
 */
return new class extends Migration {

    public function up(): void
    {
        $this->setActive(0);
    }

    public function down(): void
    {
        $this->setActive(1);
    }

    private function setActive(int $on): void
    {
        if (!Schema::hasTable('sidebar_menus')) {
            return;
        }

        DB::table('sidebar_menus')
            ->where(fn ($q) => $q->where('slug', 'profile-settings-index')->orWhere('route', 'profile-settings.index'))
            ->update(['is_active' => $on, 'updated_at' => now()]);

        // Menus are cached per person.
        if (method_exists(\App\Models\User::class, 'forgetAllMenuCaches')) {
            \App\Models\User::forgetAllMenuCaches();
        }
    }

};
