<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE leave_types MODIFY leavetype ENUM('monthly', 'yearly', 'unlimited') NULL");
    }

    public function down(): void
    {
        DB::table('leave_types')
            ->where('leavetype', 'unlimited')
            ->update(['leavetype' => 'yearly']);

        DB::statement("ALTER TABLE leave_types MODIFY leavetype ENUM('monthly', 'yearly') NULL");
    }
};
