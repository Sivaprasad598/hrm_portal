<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Which roles may see which sidebar menus.
 *
 * The shipped dump granted to role ids 1, 4 and 5 — ids from the machine it was
 * exported from. This install has 1 (admin), 2 (employee), 3 (client), so those
 * rows would have left every employee with an empty sidebar and the foreign key
 * would have failed to apply. The grants are built by the seeder from the
 * sidebar as it actually renders today instead.
 *
 * Roles already carry company_id, so a grant is implicitly per-company.
 */
return new class extends Migration
{

    public function up(): void
    {
        Schema::create('role_menus', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('role_id');
            $table->unsignedBigInteger('menu_id');
            $table->timestamps();

            $table->foreign('role_id')->references('id')->on('roles')->onDelete('cascade');
            $table->foreign('menu_id')->references('id')->on('sidebar_menus')->onDelete('cascade');
            // Granting the same menu twice is meaningless and makes the
            // assignment screen's counts wrong.
            $table->unique(['role_id', 'menu_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('role_menus');
    }

};
