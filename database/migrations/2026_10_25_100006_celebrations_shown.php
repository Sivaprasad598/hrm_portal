<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The birthday / wedding anniversary / work anniversary greeting is shown once
 * on the day, at the first sign-in: one row per person per day it was shown.
 */
return new class extends Migration
{

    public function up(): void
    {
        if (!Schema::hasTable('celebrations_shown')) {
            Schema::create('celebrations_shown', function (Blueprint $table) {
                $table->id();
                $table->unsignedInteger('user_id');
                $table->date('date');
                $table->string('occasions', 100);
                $table->timestamp('created_at')->nullable();
                $table->unique(['user_id', 'date']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('celebrations_shown');
    }

};
