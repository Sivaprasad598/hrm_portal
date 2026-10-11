<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * This app's leave-request table is `leaves` (there is no `leave_requests`
     * table), so the requested indexes are added there instead.
     */
    public function up(): void
    {
        Schema::table('leaves', function (Blueprint $table) {
            if (!$this->indexExists('leaves', 'idx_leaves_user_date_status')) {
                $table->index(['user_id', 'leave_date', 'status'], 'idx_leaves_user_date_status');
            }

            if (!$this->indexExists('leaves', 'idx_leaves_user_type_status')) {
                $table->index(['user_id', 'leave_type_id', 'status'], 'idx_leaves_user_type_status');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('leaves', function (Blueprint $table) {
            $table->dropIndex('idx_leaves_user_date_status');
            $table->dropIndex('idx_leaves_user_type_status');
        });
    }

    private function indexExists(string $table, string $indexName): bool
    {
        $indexes = Schema::getIndexes($table);

        foreach ($indexes as $index) {
            if ($index['name'] === $indexName) {
                return true;
            }
        }

        return false;
    }
};
