<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employee_shifts', function (Blueprint $table) {
            $table->integer('late_mark_duration')->default(0)->change();
        });

    }

    public function down(): void
    {
        Schema::table('employee_shifts', function (Blueprint $table) {
            $table->tinyInteger('late_mark_duration')->default(0)->change();
        });

    }
};
