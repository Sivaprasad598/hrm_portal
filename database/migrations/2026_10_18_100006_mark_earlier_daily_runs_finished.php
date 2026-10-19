<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Days the old daytime routine finished before the night schedule existed have
 * no "built" or "notified" mark. Left as they were, the first night would see
 * them as unfinished: rebuild them and send their notifications a second time.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('attendance_daily_runs')->where('status', 'reviewed')->whereNull('built_at')
            ->update(['built_at' => DB::raw('COALESCE(reviewed_at, updated_at)')]);

        DB::table('attendance_daily_runs')->where('status', 'reviewed')->whereNull('reconciled_at')
            ->update(['reconciled_at' => DB::raw('COALESCE(reviewed_at, updated_at)')]);

        DB::table('attendance_daily_runs')->where('status', 'reviewed')->whereNull('notified_at')
            ->update(['notified_at' => DB::raw('COALESCE(reviewed_at, updated_at)')]);
    }

    public function down(): void
    {
        // Nothing to undo: the marks only describe what had already happened.
    }
};
