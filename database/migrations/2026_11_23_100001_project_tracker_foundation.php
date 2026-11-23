<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Project tracker, phase 1 (on top of the Projects module):
 *
 * - project_tracker_settings: the working hours a day (8, can be 7) used for capacity
 * - project_types: one per production Division - what its work is counted in (hours,
 *   features, km, sq km, sheets ...), its grace days and its stages
 * - project_type_stages: the steps the work goes through (GIS: Capture -> Digitise -> QC ...)
 * - project_type_rates: how much a designation does in a day (60 masts, 2 km ...)
 * - projects: their type, grace days and planned quantity
 * - tasks: Epic / Work item / Task (parent), the stage, units planned and done
 *
 * The starting types (and their stages) are added for the Divisions that exist; they are
 * edited in Settings -> Project Tracker. Idempotent.
 */
return new class extends Migration {

    private const TYPES = [
        ['GIS Department', 'GIS', 'count', 'features', '#16a34a', ['Data capture', 'Digitisation', 'QC', 'Correction', 'Delivered']],
        ['Field Operations Department', 'Field survey (Drone / DGPS / LiDAR)', 'sqkm', 'sq km', '#0ea5e9', ['Planning', 'Field capture', 'Processing', 'QC', 'Delivered']],
        ['Scan to BIM Department', 'Scan to BIM', 'count', 'models', '#7c3aed', ['Registration', 'Modelling', 'QC', 'Correction', 'Delivered']],
        ['Scan to CAD Department', 'Scan to CAD', 'count', 'sheets', '#6366f1', ['Registration', 'Drafting', 'QC', 'Correction', 'Delivered']],
        ['Steel Detailing Department', 'Steel detailing', 'count', 'sheets', '#f59e0b', ['Modelling', 'Detailing', 'Checking', 'Approval', 'Delivered']],
        ['Precast Department', 'Precast', 'count', 'elements', '#a16207', ['Modelling', 'Detailing', 'Checking', 'Approval', 'Delivered']],
        ['Software Development Department', 'Software development', 'hours', 'hours', '#cb6b4f', ['To do', 'In progress', 'Review', 'Testing', 'Done']],
    ];

    public function up(): void
    {
        if (!Schema::hasTable('project_tracker_settings')) {
            Schema::create('project_tracker_settings', function (Blueprint $table) {
                $table->id();
                $table->unsignedInteger('company_id')->index();
                $table->decimal('workday_hours', 4, 2)->default(8);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('project_types')) {
            Schema::create('project_types', function (Blueprint $table) {
                $table->id();
                $table->unsignedInteger('company_id')->index();
                $table->unsignedInteger('team_id')->nullable()->index();   // the Division
                $table->string('name', 120);
                $table->string('unit_kind', 20)->default('hours');            // hours | count | km | sqkm
                $table->string('unit_label', 40)->default('hours');           // what is counted: features, sheets ...
                $table->unsignedSmallInteger('grace_days')->default(0);
                $table->string('color', 20)->nullable();
                $table->text('description')->nullable();
                $table->boolean('active')->default(true);
                $table->unsignedSmallInteger('sort')->default(0);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('project_type_stages')) {
            Schema::create('project_type_stages', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('project_type_id')->index();
                $table->string('name', 80);
                $table->string('color', 20)->nullable();
                $table->boolean('is_done')->default(false);                  // the last step: the work is finished
                $table->unsignedSmallInteger('sort')->default(0);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('project_type_rates')) {
            Schema::create('project_type_rates', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('project_type_id')->index();
                $table->unsignedInteger('designation_id')->index();
                $table->unsignedBigInteger('stage_id')->nullable();           // the rate for one step, or for the whole job
                $table->decimal('units_per_day', 10, 2);
                $table->timestamps();
            });
        }

        Schema::table('projects', function (Blueprint $table) {
            if (!Schema::hasColumn('projects', 'project_type_id')) {
                $table->unsignedBigInteger('project_type_id')->nullable()->index()->after('category_id');
            }
            if (!Schema::hasColumn('projects', 'grace_days')) {
                $table->unsignedSmallInteger('grace_days')->nullable()->after('deadline');
            }
            if (!Schema::hasColumn('projects', 'planned_units')) {
                $table->decimal('planned_units', 12, 2)->nullable()->after('hours_allocated');
            }
        });

        Schema::table('tasks', function (Blueprint $table) {
            if (!Schema::hasColumn('tasks', 'work_type')) {
                $table->string('work_type', 10)->default('task')->after('heading');   // epic | item | task
            }
            if (!Schema::hasColumn('tasks', 'parent_id')) {
                $table->unsignedInteger('parent_id')->nullable()->index()->after('work_type');
            }
            if (!Schema::hasColumn('tasks', 'project_stage_id')) {
                $table->unsignedBigInteger('project_stage_id')->nullable()->index()->after('board_column_id');
            }
            if (!Schema::hasColumn('tasks', 'planned_units')) {
                $table->decimal('planned_units', 12, 2)->nullable()->after('estimate_minutes');
            }
            if (!Schema::hasColumn('tasks', 'done_units')) {
                $table->decimal('done_units', 12, 2)->default(0)->after('planned_units');
            }
        });

        // The starting types, for each company that has the Division
        foreach (DB::table('companies')->pluck('id') as $companyId) {
            if (!DB::table('project_tracker_settings')->where('company_id', $companyId)->exists()) {
                DB::table('project_tracker_settings')->insert(['company_id' => $companyId, 'workday_hours' => 8, 'created_at' => now(), 'updated_at' => now()]);
            }

            foreach (self::TYPES as $i => [$division, $name, $kind, $label, $color, $stages]) {
                $teamId = DB::table('teams')->where('company_id', $companyId)->where('team_name', $division)->value('id');

                if (!$teamId || DB::table('project_types')->where('company_id', $companyId)->where('team_id', $teamId)->exists()) {
                    continue;
                }

                $typeId = DB::table('project_types')->insertGetId(['company_id' => $companyId, 'team_id' => $teamId, 'name' => $name, 'unit_kind' => $kind,
                    'unit_label' => $label, 'grace_days' => 3, 'color' => $color, 'active' => 1, 'sort' => $i, 'created_at' => now(), 'updated_at' => now()]);

                foreach ($stages as $s => $stage) {
                    DB::table('project_type_stages')->insert(['project_type_id' => $typeId, 'name' => $stage, 'is_done' => $s === count($stages) - 1,
                        'sort' => $s, 'created_at' => now(), 'updated_at' => now()]);
                }
            }
        }
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            foreach (['done_units', 'planned_units', 'project_stage_id', 'parent_id', 'work_type'] as $column) {
                if (Schema::hasColumn('tasks', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
        Schema::table('projects', function (Blueprint $table) {
            foreach (['planned_units', 'grace_days', 'project_type_id'] as $column) {
                if (Schema::hasColumn('projects', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
        Schema::dropIfExists('project_type_rates');
        Schema::dropIfExists('project_type_stages');
        Schema::dropIfExists('project_types');
        Schema::dropIfExists('project_tracker_settings');
    }

};
