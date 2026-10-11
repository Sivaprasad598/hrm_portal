<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leave_types', function (Blueprint $table) {
            if (!Schema::hasColumn('leave_types', 'carry_forward_expiry_date')) {
                $table->date('carry_forward_expiry_date')->nullable()->after('unused_leave');
            }
        });
    }

    public function down(): void
    {
        Schema::table('leave_types', function (Blueprint $table) {
            if (Schema::hasColumn('leave_types', 'carry_forward_expiry_date')) {
                $table->dropColumn('carry_forward_expiry_date');
            }
        });
    }
};
