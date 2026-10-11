<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Index the module's primary access path.
 *
 * Almost everything here looks punches up by biometric code over a date range —
 * Employee 360, the attendance sync, the day rebuild, the employee register.
 * The existing indexes cover `(company_id, punch_time)` and `(user_id,
 * punch_time)`, but 172 of 173 codes have no user, so those queries fell back
 * to a full scan: EXPLAIN reported `type=ALL, key=NULL`, reading 13,186 rows to
 * return about twenty, even for a single day.
 *
 * That is survivable at thirteen thousand rows and not at all survivable after
 * a full history backfill, which is roughly 1.6 million.
 *
 * Column order matters: company first to keep tenants apart, then the code
 * being matched, then the time range being scanned within it.
 */
return new class extends Migration
{

    public function up(): void
    {
        if (!Schema::hasTable('iapp_device_transactions')) {
            return;
        }

        if ($this->hasIndex('iapp_trans_code_punch_idx')) {
            return;
        }

        Schema::table('iapp_device_transactions', function (Blueprint $table) {
            $table->index(['company_id', 'emp_code', 'punch_time'], 'iapp_trans_code_punch_idx');
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('iapp_device_transactions') || !$this->hasIndex('iapp_trans_code_punch_idx')) {
            return;
        }

        Schema::table('iapp_device_transactions', function (Blueprint $table) {
            $table->dropIndex('iapp_trans_code_punch_idx');
        });
    }

    private function hasIndex(string $name): bool
    {
        return collect(Schema::getIndexes('iapp_device_transactions'))
            ->contains(fn ($index) => ($index['name'] ?? null) === $name);
    }

};
