<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Project tracker: progress history, employee KPIs and project profitability.
 *
 * - tracker_progress_logs: every change of a tracker item's units done or step - who, when,
 *   how many, and the people on it then (the progress-over-time charts and the KPIs read it)
 * - project_types.kpi_weights: how the KPIs add up to the overall score for that kind of work
 * - project_tracker_settings: how shared overheads are spread (hours / headcount / none) and
 *   which revenue the profit is worked on (earned / billed / received)
 * - expenses_category.cost_type: direct (one project's) or shared (overheads: rent, power ...),
 *   and the starting categories
 *
 * Idempotent.
 */
return new class extends Migration {

    private const CATEGORIES = [
        ['Office rent', 'shared'], ['Electricity', 'shared'], ['Internet', 'shared'], ['Software licences', 'shared'],
        ['Equipment & purchases', 'direct'], ['Travel', 'direct'], ['Field expenses', 'direct'], ['Other', 'direct'],
    ];

    public function up(): void
    {
        if (!Schema::hasTable('tracker_progress_logs')) {
            Schema::create('tracker_progress_logs', function (Blueprint $table) {
                $table->id();
                $table->unsignedInteger('company_id')->index();
                $table->unsignedInteger('project_id')->index();
                $table->unsignedInteger('task_id')->index();
                $table->unsignedInteger('by_user_id')->nullable();
                $table->json('assignee_ids')->nullable();                 // who the work is credited to
                $table->unsignedBigInteger('stage_from')->nullable();
                $table->unsignedBigInteger('stage_to')->nullable();
                $table->decimal('units_before', 12, 2)->nullable();
                $table->decimal('units_after', 12, 2)->nullable();
                $table->decimal('units_delta', 12, 2)->default(0);
                $table->boolean('rework')->default(false);                // sent back to an earlier step
                $table->boolean('completed')->default(false);             // reached the last step
                $table->timestamp('logged_at')->index();
            });
        }

        if (Schema::hasTable('project_types') && !Schema::hasColumn('project_types', 'kpi_weights')) {
            Schema::table('project_types', function (Blueprint $table) {
                $table->json('kpi_weights')->nullable()->after('grace_days');
            });
        }

        Schema::table('project_tracker_settings', function (Blueprint $table) {
            if (!Schema::hasColumn('project_tracker_settings', 'overhead_method')) {
                $table->string('overhead_method', 20)->default('hours')->after('workday_hours');
            }
            if (!Schema::hasColumn('project_tracker_settings', 'revenue_method')) {
                $table->string('revenue_method', 20)->default('earned')->after('overhead_method');
            }
        });

        if (Schema::hasTable('expenses_category')) {
            if (!Schema::hasColumn('expenses_category', 'cost_type')) {
                Schema::table('expenses_category', function (Blueprint $table) {
                    $table->string('cost_type', 10)->default('direct')->after('category_name');
                });
            }

            foreach (DB::table('companies')->pluck('id') as $companyId) {
                foreach (self::CATEGORIES as [$name, $type]) {
                    if (!DB::table('expenses_category')->where('company_id', $companyId)->where('category_name', $name)->exists()) {
                        DB::table('expenses_category')->insert(['company_id' => $companyId, 'category_name' => $name, 'cost_type' => $type,
                            'created_at' => now(), 'updated_at' => now()]);
                    }
                }
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('tracker_progress_logs');

        if (Schema::hasColumn('project_types', 'kpi_weights')) {
            Schema::table('project_types', fn (Blueprint $t) => $t->dropColumn('kpi_weights'));
        }

        foreach (['revenue_method', 'overhead_method'] as $column) {
            if (Schema::hasColumn('project_tracker_settings', $column)) {
                Schema::table('project_tracker_settings', fn (Blueprint $t) => $t->dropColumn($column));
            }
        }

        if (Schema::hasColumn('expenses_category', 'cost_type')) {
            Schema::table('expenses_category', fn (Blueprint $t) => $t->dropColumn('cost_type'));
        }
    }

};
