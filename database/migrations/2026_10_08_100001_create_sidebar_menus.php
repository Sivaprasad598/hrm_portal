<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The sidebar, as data rather than as 488 lines of Blade.
 *
 * Deliberately NOT called `menus`: Worksuite created a table of that name in
 * its 2018 base migration (menu_name / translate_name / module / setting_menu)
 * and App\Models\Menu still maps to it. It happens to be empty here, but taking
 * the name would either collide on import or quietly destroy a vendor table.
 *
 * company_id is carried even though this install has one company, because every
 * other table in the app has it and retro-fitting a tenant key to a live menu
 * tree is far more expensive than carrying it from the start.
 */
return new class extends Migration
{

    public function up(): void
    {
        Schema::create('sidebar_menus', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->nullable()->index();
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->string('name', 191);
            $table->string('slug', 191);
            $table->string('route', 191)->nullable();
            $table->string('icon', 191)->nullable();
            // 0 = top level, 1 = submenu, 2 = child. Held as a column as well as
            // implied by parent_id so the management screen can order and query
            // without walking the tree.
            $table->tinyInteger('level')->default(0);
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            // The permission key and module this item sits behind in the
            // hand-written sidebar, so a role grant can never reveal a page the
            // employee's permissions refuse. Null means "no extra condition".
            $table->string('permission_key', 100)->nullable();
            $table->string('permission_rule', 191)->nullable();
            $table->string('module', 60)->nullable();
            $table->timestamps();

            $table->foreign('parent_id')->references('id')->on('sidebar_menus')->onDelete('cascade');
            // Two companies may each have a "Dashboard"; one company may not.
            $table->unique(['company_id', 'slug'], 'sidebar_menus_company_slug_unique');
            $table->index(['parent_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sidebar_menus');
    }

};
