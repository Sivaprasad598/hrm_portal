<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Biometric storage, cleaned up (the database was growing by hundreds of MB a day):
 *
 *  - iapp_day_writes: what each employee-day was last written from (a fingerprint of its punches),
 *    so a pull rewrites only the days whose punches changed.
 *  - iapp_attendance_links: the "replaced" backup copies (one full copy of every session on every
 *    pull) are deleted; no more are made. The "created" rows (which attendance came from the
 *    device) stay.
 *  - iapp_device_transactions.raw_payload: the copy of the device's raw row is cleared; every field
 *    used is in its own column. No more are stored.
 *  - the space is given back (OPTIMIZE TABLE).
 *
 * In chunks, so a large table is never locked for long. Idempotent: safe to run again.
 */
return new class extends Migration {

    public function up(): void
    {
        if (!Schema::hasTable('iapp_day_writes')) {
            Schema::create('iapp_day_writes', function (Blueprint $table) {
                $table->unsignedBigInteger('user_id');
                $table->date('attendance_date');
                $table->char('fingerprint', 32);
                $table->timestamp('written_at')->nullable();
                $table->primary(['user_id', 'attendance_date']);
            });
        }

        if (Schema::hasTable('iapp_attendance_links')) {
            do {
                $ids = DB::table('iapp_attendance_links')->where('link_type', 'replaced')->limit(5000)->pluck('id');

                if ($ids->isNotEmpty()) {
                    DB::table('iapp_attendance_links')->whereIn('id', $ids)->delete();
                }
            } while ($ids->count() === 5000);

            $this->optimize('iapp_attendance_links');
        }

        if (Schema::hasTable('iapp_device_transactions') && Schema::hasColumn('iapp_device_transactions', 'raw_payload')) {
            $max = (int) DB::table('iapp_device_transactions')->max('id');

            for ($from = 0; $from <= $max; $from += 10000) {
                DB::table('iapp_device_transactions')->whereBetween('id', [$from, $from + 9999])->whereNotNull('raw_payload')
                    ->update(['raw_payload' => null]);
            }

            $this->optimize('iapp_device_transactions');
        }
    }

    protected function optimize(string $table): void
    {
        try {
            DB::statement('OPTIMIZE TABLE `' . $table . '`');
        } catch (\Throwable $e) {
            // not every host allows it; the rows are gone either way
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('iapp_day_writes');
    }

};
