<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('permissions')) {
            return;
        }

        DB::statement("SET SESSION innodb_lock_wait_timeout = 120");

        $lockName = 'permissions_dedupe_and_unique_index';
        $gotLock = DB::selectOne("SELECT GET_LOCK(?, 30) AS l", [$lockName]);

        if (!$gotLock || (int) $gotLock->l !== 1) {
            throw new RuntimeException('Could not acquire migration lock for permissions cleanup.');
        }

        try {
            $this->ensureNonUniqueNameIndex();
            $this->assertNoUnknownReferencingTables();
            $this->dedupePermissions();
            $this->ensureUniqueNameIndex();

            DB::statement("ANALYZE TABLE permissions");

            if (Schema::hasTable('user_permissions')) {
                DB::statement("ANALYZE TABLE user_permissions");
            }

            if (Schema::hasTable('permission_role')) {
                DB::statement("ANALYZE TABLE permission_role");
            }
        } finally {
            DB::selectOne("SELECT RELEASE_LOCK(?)", [$lockName]);
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('permissions')) {
            return;
        }

        $indexes = $this->getIndexes('permissions');

        if (in_array('permissions_name_unique', $indexes, true)) {
            DB::statement("ALTER TABLE permissions DROP INDEX permissions_name_unique");
        }

        $indexes = $this->getIndexes('permissions');

        if (!in_array('permissions_name_index', $indexes, true)) {
            DB::statement("ALTER TABLE permissions ADD INDEX permissions_name_index (name)");
        }

        DB::statement("ANALYZE TABLE permissions");
    }

    private function ensureNonUniqueNameIndex(): void
    {
        $indexes = $this->getIndexes('permissions');

        if (!in_array('permissions_name_index', $indexes, true) && !in_array('permissions_name_unique', $indexes, true)) {
            DB::statement("ALTER TABLE permissions ADD INDEX permissions_name_index (name)");
        }
    }

    private function assertNoUnknownReferencingTables(): void
    {
        $refs = DB::select("
            SELECT TABLE_NAME, COLUMN_NAME
            FROM information_schema.KEY_COLUMN_USAGE
            WHERE REFERENCED_TABLE_SCHEMA = DATABASE()
              AND REFERENCED_TABLE_NAME = 'permissions'
        ");

        $allowed = [
            'user_permissions' => 'permission_id',
            'permission_role' => 'permission_id',
        ];

        foreach ($refs as $ref) {
            $table = $ref->TABLE_NAME;
            $column = $ref->COLUMN_NAME;

            if (!isset($allowed[$table]) || $allowed[$table] !== $column) {
                throw new RuntimeException(
                    "Unknown referencing table found for permissions.id: {$table}.{$column}. " .
                    "Handle it explicitly before running this migration."
                );
            }
        }
    }

    private function dedupePermissions(): void
    {
        $duplicates = DB::table('permissions')
            ->select('name', DB::raw('MIN(id) as keep_id'), DB::raw('COUNT(*) as total'))
            ->groupBy('name')
            ->havingRaw('COUNT(*) > 1')
            ->orderBy('name')
            ->get();

        foreach ($duplicates as $duplicate) {
            $name = $duplicate->name;
            $keepId = (int) $duplicate->keep_id;

            $duplicateIds = DB::table('permissions')
                ->where('name', $name)
                ->where('id', '!=', $keepId)
                ->orderBy('id')
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->values()
                ->all();

            foreach ($duplicateIds as $duplicateId) {
                DB::transaction(function () use ($duplicateId, $keepId) {
                    $this->mergeUserPermissions($duplicateId, $keepId);
                    $this->mergePermissionRole($duplicateId, $keepId);

                    DB::table('permissions')
                        ->where('id', $duplicateId)
                        ->delete();
                }, 3);
            }
        }
    }

    private function mergeUserPermissions(int $oldPermissionId, int $keepPermissionId): void
    {
        if (!Schema::hasTable('user_permissions')) {
            return;
        }

        DB::statement("
            DELETE up_old
            FROM user_permissions up_old
            INNER JOIN user_permissions up_keep
                ON up_old.user_id = up_keep.user_id
               AND up_keep.permission_id = ?
            WHERE up_old.permission_id = ?
        ", [$keepPermissionId, $oldPermissionId]);

        DB::table('user_permissions')
            ->where('permission_id', $oldPermissionId)
            ->update(['permission_id' => $keepPermissionId]);
    }

    private function mergePermissionRole(int $oldPermissionId, int $keepPermissionId): void
    {
        if (!Schema::hasTable('permission_role')) {
            return;
        }

        DB::statement("
            DELETE pr_old
            FROM permission_role pr_old
            INNER JOIN permission_role pr_keep
                ON pr_old.role_id = pr_keep.role_id
               AND pr_keep.permission_id = ?
            WHERE pr_old.permission_id = ?
        ", [$keepPermissionId, $oldPermissionId]);

        DB::table('permission_role')
            ->where('permission_id', $oldPermissionId)
            ->update(['permission_id' => $keepPermissionId]);
    }

    private function ensureUniqueNameIndex(): void
    {
        $remainingDuplicates = DB::table('permissions')
            ->select('name')
            ->groupBy('name')
            ->havingRaw('COUNT(*) > 1')
            ->exists();

        if ($remainingDuplicates) {
            throw new RuntimeException('Duplicate permission names still exist. Unique index not added.');
        }

        $indexes = $this->getIndexes('permissions');

        if (in_array('permissions_name_unique', $indexes, true)) {
            return;
        }

        if (in_array('permissions_name_index', $indexes, true)) {
            DB::statement("ALTER TABLE permissions DROP INDEX permissions_name_index");
        }

        DB::statement("ALTER TABLE permissions ADD UNIQUE KEY permissions_name_unique (name)");
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