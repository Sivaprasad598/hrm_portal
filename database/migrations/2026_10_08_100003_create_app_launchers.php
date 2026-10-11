<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tiles for the app launcher in the topbar.
 *
 * Only the tile's appearance and order live here. Whether a given person sees a
 * tile is still decided by the permission checks already in
 * sections/app-launcher.blade.php, keyed by slug — this table never widens
 * access, it only describes what a permitted tile looks like.
 */
return new class extends Migration
{

    public function up(): void
    {
        Schema::create('app_launchers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->nullable()->index();
            $table->string('name', 191);
            // The key the permission map in app-launcher.blade.php looks up.
            $table->string('slug', 191);
            $table->string('icon', 191);
            $table->string('color', 50)->default('#4f46e5');
            $table->string('route', 191)->nullable();
            $table->string('url', 191)->nullable();
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->string('target', 20)->default('_self');
            $table->timestamps();

            $table->unique(['company_id', 'slug'], 'app_launchers_company_slug_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('app_launchers');
    }

};
