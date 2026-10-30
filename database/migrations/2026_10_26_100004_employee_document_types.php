<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * employee_docs.doc_type: what a document is (Aadhaar, PAN, SSC, resume ...),
 * chosen when it is uploaded, so the Documents tab can show them grouped and
 * the profile can show when the resume was uploaded. Files from before carry
 * none; their type is read from the onboarding category (App\Services\Employee\DocumentTypes).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('employee_docs', 'doc_type')) {
            Schema::table('employee_docs', function (Blueprint $table) {
                $table->string('doc_type', 40)->nullable()->after('checklist_key')->index();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('employee_docs', 'doc_type')) {
            Schema::table('employee_docs', function (Blueprint $table) {
                $table->dropIndex(['doc_type']);
                $table->dropColumn('doc_type');
            });
        }
    }
};
