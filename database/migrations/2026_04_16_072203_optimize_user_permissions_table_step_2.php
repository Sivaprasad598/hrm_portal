<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('user_permissions')) {
            return;
        }

        $indexes = $this->getIndexes('user_permissions');

        if (in_array('user_permissions_permission_id_user_id_unique', $indexes, true)) {
            DB::statement("
                ALTER TABLE user_permissions
                DROP INDEX user_permissions_permission_id_user_id_unique,
                ADD UNIQUE KEY user_permissions_user_id_permission_id_unique (user_id, permission_id)
            ");
        } elseif (!in_array('user_permissions_user_id_permission_id_unique', $indexes, true)) {
            DB::statement("
                ALTER TABLE user_permissions
                ADD UNIQUE KEY user_permissions_user_id_permission_id_unique (user_id, permission_id)
            ");
        }

        $indexes = $this->getIndexes('user_permissions');

        if (in_array('user_permissions_user_id_foreign', $indexes, true)) {
            DB::statement("ALTER TABLE user_permissions DROP INDEX user_permissions_user_id_foreign");
        }

        DB::statement("ANALYZE TABLE user_permissions");

        if (Schema::hasTable('permissions')) {
            DB::statement("ANALYZE TABLE permissions");
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('user_permissions')) {
            return;
        }

        $indexes = $this->getIndexes('user_permissions');

        if (in_array('user_permissions_user_id_permission_id_unique', $indexes, true)) {
            DB::statement("
                ALTER TABLE user_permissions
                DROP INDEX user_permissions_user_id_permission_id_unique,
                ADD UNIQUE KEY user_permissions_permission_id_user_id_unique (permission_id, user_id)
            ");
        }

        $indexes = $this->getIndexes('user_permissions');

        if (!in_array('user_permissions_user_id_foreign', $indexes, true)) {
            DB::statement("ALTER TABLE user_permissions ADD INDEX user_permissions_user_id_foreign (user_id)");
        }

        DB::statement("ANALYZE TABLE user_permissions");
    }

    private function getIndexes(string $table): array
    {
        return collect(DB::select("SHOW INDEX FROM `{$table}`"))
            ->pluck('Key_name')
            ->unique()
            ->values()
            ->all();
    }
};