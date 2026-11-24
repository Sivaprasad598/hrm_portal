<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The "relieving cum experience letter" becomes two letters, as the company issues
 * them: a Relieving Letter and an Experience Letter (Letter Generator, Letter
 * Settings, and both issued together from Exited Employees).
 *
 *  - relieving_letters.kind: relieving | experience (every letter so far: relieving)
 *  - relieving_letters.pair_id: the other letter of the two issued together
 *  - Letter Settings saved with the old combined wording: that wording is dropped,
 *    so the new Relieving Letter wording applies (anything else saved is kept).
 *
 * Idempotent: safe to run again.
 */
return new class extends Migration {

    protected const OLD_KEYS = ['relieving_title', 'relieving_greeting', 'relieving_paragraphs', 'relieving_closing',
        'relieving_signoff', 'relieving_date_format'];

    public function up(): void
    {
        if (!Schema::hasTable('relieving_letters')) {
            return;
        }

        Schema::table('relieving_letters', function (Blueprint $table) {
            if (!Schema::hasColumn('relieving_letters', 'kind')) {
                $table->string('kind', 20)->default('relieving')->after('source')->index();
            }

            if (!Schema::hasColumn('relieving_letters', 'pair_id')) {
                $table->unsignedBigInteger('pair_id')->nullable()->after('kind');
            }
        });

        if (!Schema::hasTable('offer_letter_settings')) {
            return;
        }

        foreach (DB::table('offer_letter_settings')->get(['id', 'content']) as $row) {
            $content = json_decode((string) $row->content, true);

            if (!is_array($content) || ($content['relieving_title'] ?? null) !== 'Relieving cum Experience Letter') {
                continue;   // already the new wording, or never saved
            }

            DB::table('offer_letter_settings')->where('id', $row->id)->update([
                'content' => json_encode(array_diff_key($content, array_flip(self::OLD_KEYS)), JSON_UNESCAPED_UNICODE),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('relieving_letters') && Schema::hasColumn('relieving_letters', 'kind')) {
            Schema::table('relieving_letters', function (Blueprint $table) {
                $table->dropIndex(['kind']);
                $table->dropColumn(['kind', 'pair_id']);
            });
        }
    }

};
