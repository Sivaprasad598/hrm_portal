<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('branches', function (Blueprint $table) {
            $table->text('address')->nullable()->after('branch_name');
            $table->text('emails')->nullable()->after('address');
            $table->text('phones')->nullable()->after('emails');
            $table->boolean('status')->default(1)->after('phones');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('branches', function (Blueprint $table) {
            $table->dropColumn(['address', 'emails', 'phones', 'status']);
        });
    }
};
