<?php

namespace App\Support;

use Illuminate\Support\Facades\Schema;

/**
 * Schema::hasColumn() / hasTable(), answered once per request.
 *
 * Each Schema call reads information_schema. Code that guards an optional
 * column ("closed_by_rejoin_id", "earned_leave_type_id" ...) inside a loop over
 * the employees asked it once per person: Year-End Settlement ran 1,464 of them,
 * Biometric Exceptions 351. The table layout does not change while a request
 * runs, so the first answer stands.
 */
class SchemaCache
{
    /** @var array<string, bool> */
    protected static array $columns = [];

    /** @var array<string, bool> */
    protected static array $tables = [];

    public static function hasColumn(string $table, string $column): bool
    {
        return static::$columns[$table . '.' . $column] ??= Schema::hasColumn($table, $column);
    }

    public static function hasTable(string $table): bool
    {
        return static::$tables[$table] ??= Schema::hasTable($table);
    }

    /** After a migration in the same process (tests, artisan). */
    public static function flush(): void
    {
        static::$columns = [];
        static::$tables = [];
    }
}
