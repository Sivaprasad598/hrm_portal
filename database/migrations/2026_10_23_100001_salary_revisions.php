<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Salary revisions (hikes, promotions) with CFO -> CEO approval and letters.
 *
 *   salary_revision_batches   an appraisal cycle, or one employee's revision:
 *                             effective date, status, who approved when
 *   salary_revisions          one employee in it: CTC before / after, designation
 *                             change, arrears, letter number
 *   salary_revision_logs      every submit / approve / reject, for the audit
 *   salary_revision_settings  letter number series and signatory
 *
 * Menu: HR -> Salary Revisions, for HR, Admin, CFO and CEO.
 */
return new class extends Migration {

    public function up(): void
    {
        if (!Schema::hasTable('salary_revision_batches')) {
            Schema::create('salary_revision_batches', function (Blueprint $table) {
                $table->id();
                $table->unsignedInteger('company_id')->nullable()->index();
                $table->string('name', 150);
                $table->string('kind', 10)->default('single');          // single | cycle
                $table->date('effective_date');
                $table->date('applies_from');                            // first salary month it is paid in
                $table->string('status', 20)->default('draft')->index(); // draft, pending_cfo, pending_ceo, approved, rejected
                $table->text('note')->nullable();
                $table->unsignedInteger('created_by')->nullable();
                $table->timestamp('submitted_at')->nullable();
                $table->unsignedInteger('cfo_by')->nullable();
                $table->timestamp('cfo_at')->nullable();
                $table->unsignedInteger('ceo_by')->nullable();
                $table->timestamp('ceo_at')->nullable();
                $table->unsignedInteger('rejected_by')->nullable();
                $table->timestamp('rejected_at')->nullable();
                $table->text('reject_reason')->nullable();
                $table->timestamp('released_at')->nullable();             // letters visible to the employees
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('salary_revisions')) {
            Schema::create('salary_revisions', function (Blueprint $table) {
                $table->id();
                $table->unsignedInteger('company_id')->nullable()->index();
                $table->unsignedBigInteger('batch_id')->index();
                $table->unsignedInteger('user_id')->index();
                $table->string('type', 20)->default('appraisal');        // appraisal, promotion, special, correction
                $table->decimal('old_ctc', 14, 2)->default(0);
                $table->decimal('new_ctc', 14, 2)->default(0);
                $table->decimal('old_basic_pct', 6, 2)->nullable();
                $table->decimal('new_basic_pct', 6, 2)->nullable();      // null: as before
                $table->unsignedBigInteger('old_designation_id')->nullable();
                $table->unsignedBigInteger('new_designation_id')->nullable(); // null: no change
                $table->unsignedInteger('old_department_id')->nullable();
                $table->unsignedInteger('new_department_id')->nullable();
                $table->string('reason', 500)->nullable();
                $table->decimal('arrears_amount', 12, 2)->nullable();
                $table->string('arrears_note', 255)->nullable();
                $table->unsignedBigInteger('arrears_component_id')->nullable();
                $table->string('letter_no', 60)->nullable();
                $table->date('letter_date')->nullable();
                $table->timestamp('applied_at')->nullable();             // the employee's record carries it
                $table->unsignedInteger('created_by')->nullable();
                $table->unsignedInteger('updated_by')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('salary_revision_logs')) {
            Schema::create('salary_revision_logs', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('batch_id')->index();
                $table->string('action', 20);                            // created, submitted, approved_cfo, approved_ceo, rejected, withdrawn, released
                $table->unsignedInteger('user_id')->nullable();
                $table->text('note')->nullable();
                $table->timestamp('created_at')->nullable();
            });
        }

        if (!Schema::hasTable('salary_revision_settings')) {
            Schema::create('salary_revision_settings', function (Blueprint $table) {
                $table->id();
                $table->unsignedInteger('company_id')->nullable()->unique();
                $table->string('letter_prefix', 40)->default('HR/SR');
                $table->string('signatory_name', 120)->nullable();
                $table->string('signatory_designation', 120)->nullable();
                $table->string('hr_contact', 191)->nullable();
                $table->timestamps();
            });

            foreach (DB::table('companies')->pluck('id') as $companyId) {
                DB::table('salary_revision_settings')->insert(['company_id' => $companyId, 'letter_prefix' => 'HR/SR',
                    'signatory_designation' => 'Head - Human Resources', 'created_at' => now(), 'updated_at' => now()]);
            }
        }

        // HR -> Salary Revisions
        $hr = DB::table('sidebar_menus')->whereNull('parent_id')->where('slug', 'hr')->first();

        if ($hr && !DB::table('sidebar_menus')->where('route', 'salary-revisions.index')->exists()) {
            $id = DB::table('sidebar_menus')->insertGetId([
                'company_id' => $hr->company_id, 'parent_id' => $hr->id, 'name' => 'Salary Revisions', 'slug' => 'salary-revisions',
                'route' => 'salary-revisions.index', 'level' => 1,
                'sort_order' => (int) DB::table('sidebar_menus')->where('parent_id', $hr->id)->max('sort_order') + 1,
                'is_active' => 1, 'created_at' => now(), 'updated_at' => now(),
            ]);

            foreach (DB::table('roles')->whereIn('name', ['admin', 'hr', 'manager_admin', 'ceo', 'cfo'])->pluck('id') as $roleId) {
                DB::table('role_menus')->insertOrIgnore(['role_id' => $roleId, 'menu_id' => $id, 'created_at' => now(), 'updated_at' => now()]);
            }
        }
    }

    public function down(): void
    {
        $ids = DB::table('sidebar_menus')->where('route', 'salary-revisions.index')->pluck('id');
        DB::table('role_menus')->whereIn('menu_id', $ids)->delete();
        DB::table('sidebar_menus')->whereIn('id', $ids)->delete();

        Schema::dropIfExists('salary_revision_settings');
        Schema::dropIfExists('salary_revision_logs');
        Schema::dropIfExists('salary_revisions');
        Schema::dropIfExists('salary_revision_batches');
    }

};
