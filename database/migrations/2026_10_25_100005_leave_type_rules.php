<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Rules per leave type, set on the leave type itself (Leave Settings -> Leave
 * type -> Rules):
 *
 *   doc_required      none / always / over_days: a supporting document
 *   doc_min_days      ... when the leave is longer than this many days
 *   doc_label         what the document is called ("Medical certificate")
 *   doc_later_days    the document may follow, within this many days after the
 *                     leave (null: attach when applying); not approved until it does
 *   backdate_days     may be applied up to this many days after (null: no limit, 0: never)
 *   notice_days       has to be applied at least this many days before (null: none)
 *   max_days          most days in one application (null: no limit)
 */
return new class extends Migration
{

    public function up(): void
    {
        Schema::table('leave_types', function (Blueprint $table) {
            if (!Schema::hasColumn('leave_types', 'doc_required')) {
                $table->string('doc_required', 12)->default('none')->after('over_utilization');
                $table->decimal('doc_min_days', 5, 1)->nullable()->after('doc_required');
                $table->string('doc_label', 100)->nullable()->after('doc_min_days');
                $table->unsignedSmallInteger('doc_later_days')->nullable()->after('doc_label');
                $table->unsignedSmallInteger('backdate_days')->nullable()->after('doc_later_days');
                $table->unsignedSmallInteger('notice_days')->nullable()->after('backdate_days');
                $table->decimal('max_days', 5, 1)->nullable()->after('notice_days');
            }
        });
    }

    public function down(): void
    {
        Schema::table('leave_types', function (Blueprint $table) {
            $table->dropColumn(['doc_required', 'doc_min_days', 'doc_label', 'doc_later_days', 'backdate_days', 'notice_days', 'max_days']);
        });
    }

};
