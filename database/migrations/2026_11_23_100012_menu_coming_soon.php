<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Settings -> Assign Menus -> Modules: a module still being built can be marked "Coming soon"
 * (asked for 2026-11-23). Its menu stays where it is, with a "Soon" tag, and its pages show a
 * Coming soon page instead of the work in progress (App\Services\Access\ComingSoon). The Super
 * Admin still sees the real pages, so the module can be built and checked.
 * Safe to run more than once; down() drops the flag (every module open again).
 */
return new class extends Migration {

    public function up(): void
    {
        if (Schema::hasTable('sidebar_menus') && !Schema::hasColumn('sidebar_menus', 'coming_soon')) {
            Schema::table('sidebar_menus', function (Blueprint $table) {
                $table->boolean('coming_soon')->default(false)->after('is_active');
            });
        }

        $this->forget();
    }

    public function down(): void
    {
        if (Schema::hasTable('sidebar_menus') && Schema::hasColumn('sidebar_menus', 'coming_soon')) {
            Schema::table('sidebar_menus', function (Blueprint $table) {
                $table->dropColumn('coming_soon');
            });
        }

        $this->forget();
    }

    private function forget(): void
    {
        if (class_exists(\App\Services\Access\ComingSoon::class)) {
            \App\Services\Access\ComingSoon::forget();
        }

        if (method_exists(\App\Models\User::class, 'forgetAllMenuCaches')) {
            \App\Models\User::forgetAllMenuCaches();
        }
    }

};
