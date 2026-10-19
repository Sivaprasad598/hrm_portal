<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Employee onboarding: documents behind the joining checklist and the
 * declaration, the resume, the monthly salary beside the CTC, the bank the
 * IFSC belongs to, and the AI settings that read a resume into the form.
 *
 *  - employee_docs.category / checklist_key: which part of onboarding a file
 *    belongs to (resume, checklist, declaration_candidate, declaration_hr, or
 *    empty for an ordinary document). Every file stays in the one documents
 *    table, so the employee's Documents tab shows them all.
 *  - users.monthly_salary: CTC / 12, saved with the CTC.
 *  - employee_details.bank_name / bank_branch / ifsc_verified_at: what the
 *    IFSC directory said about the code when it was entered.
 *  - ai_settings: one row per company - on/off, which provider, and an
 *    encrypted key and model per provider (OpenAI, Claude, Gemini), so
 *    switching provider keeps the other keys.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('employee_docs', function (Blueprint $table) {
            if (!Schema::hasColumn('employee_docs', 'category')) {
                $table->string('category', 30)->nullable()->after('name')->index();
            }

            if (!Schema::hasColumn('employee_docs', 'checklist_key')) {
                $table->string('checklist_key', 50)->nullable()->after('category');
            }

            if (!Schema::hasColumn('employee_docs', 'mime')) {
                $table->string('mime', 100)->nullable()->after('size');
            }
        });

        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'monthly_salary')) {
                $table->decimal('monthly_salary', 12, 2)->nullable()->after('employee_ctc');
            }
        });

        Schema::table('employee_details', function (Blueprint $table) {
            if (!Schema::hasColumn('employee_details', 'bank_name')) {
                $table->string('bank_name', 150)->nullable()->after('ifsc_code');
            }

            if (!Schema::hasColumn('employee_details', 'bank_branch')) {
                $table->string('bank_branch', 255)->nullable()->after('bank_name');
            }

            if (!Schema::hasColumn('employee_details', 'ifsc_verified_at')) {
                $table->timestamp('ifsc_verified_at')->nullable()->after('bank_branch');
            }
        });

        if (!Schema::hasTable('ai_settings')) {
            Schema::create('ai_settings', function (Blueprint $table) {
                $table->id();
                $table->unsignedInteger('company_id')->nullable()->unique();
                $table->boolean('enabled')->default(false);
                $table->string('provider', 20)->default('openai');   // openai | anthropic | gemini
                $table->text('openai_key')->nullable();               // encrypted
                $table->string('openai_model', 100)->nullable();
                $table->text('anthropic_key')->nullable();            // encrypted
                $table->string('anthropic_model', 100)->nullable();
                $table->text('gemini_key')->nullable();               // encrypted
                $table->string('gemini_model', 100)->nullable();
                $table->timestamp('last_tested_at')->nullable();
                $table->string('last_test_result', 255)->nullable();
                $table->unsignedInteger('updated_by')->nullable();
                $table->timestamps();
            });
        }

        // Existing employees: monthly salary from the CTC already on file
        \Illuminate\Support\Facades\DB::statement(
            'UPDATE users SET monthly_salary = ROUND(employee_ctc / 12, 2) WHERE employee_ctc > 0 AND monthly_salary IS NULL'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_settings');

        Schema::table('employee_details', function (Blueprint $table) {
            $table->dropColumn(['bank_name', 'bank_branch', 'ifsc_verified_at']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('monthly_salary');
        });

        Schema::table('employee_docs', function (Blueprint $table) {
            $table->dropColumn(['category', 'checklist_key', 'mime']);
        });
    }
};
