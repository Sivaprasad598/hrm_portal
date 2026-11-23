<?php

namespace App\Console\Commands;

use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\ProjectType;
use App\Models\ProjectTypeRate;
use App\Models\Task;
use App\Models\TaskboardColumn;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Sample data for the project tracker, until the real projects are entered:
 * projects named "[DEMO] ..." across the Divisions over the next three months (some people on
 * two at once, so the resource planner shows overload), and daily rates for project types that
 * have none.
 *
 *   php artisan tracker:demo            add it (again: nothing is added twice)
 *   php artisan tracker:demo --remove   take every [DEMO] project out (rates stay: edit them in Settings)
 */
class TrackerDemo extends Command
{

    protected $signature = 'tracker:demo {--remove : Remove the [DEMO] projects}';

    protected $description = 'Add (or remove) sample projects for the project tracker';

    private const PROJECTS = [
        [
            'name' => '[DEMO] Hyderabad Metro Pole Survey (GIS)', 'code' => 'DEMO-GIS2', 'type' => 'GIS', 'start' => 3, 'end' => 75, 'units' => 2400,
            'members' => [4650, 4625, 4637, 4641, 4642], 'admin' => 4650,
            'epics' => [
                ['Corridor 1 - Miyapur to Ameerpet', 3, 35, [
                    ['Digitise poles - Corridor 1', [4625, 4637], 'Digitisation', 800, 140, 3, 28],
                    ['QC - Corridor 1', [4650], 'Data capture', 800, 40, 20, 35],
                ]],
                ['Corridor 2 - Ameerpet to LB Nagar', 30, 75, [
                    ['Digitise poles - Corridor 2', [4641, 4642], 'Data capture', 1600, 220, 30, 65],
                    ['QC - Corridor 2', [4650], 'Data capture', 1600, 60, 55, 75],
                ]],
            ],
        ],
        [
            'name' => '[DEMO] Warehouse Steel Structure Detailing', 'code' => 'DEMO-STL1', 'type' => 'Steel detailing', 'start' => -6, 'end' => 50, 'units' => 180,
            'members' => [4632, 4629, 4631, 4690], 'admin' => 4632,
            'epics' => [
                ['Main frames', -6, 25, [
                    ['Model main frames', [4629, 4631], 'Detailing', 60, 120, -6, 14],
                    ['Shop drawings - frames', [4690], 'Modelling', 60, 90, 10, 25],
                ]],
                ['Secondary steel', 20, 50, [
                    ['Model purlins and bracing', [4629], 'Modelling', 70, 80, 20, 38],
                    ['Shop drawings - secondary', [4631, 4690], 'Modelling', 50, 70, 35, 50],
                ]],
            ],
        ],
        [
            'name' => '[DEMO] Hospital Scan to BIM', 'code' => 'DEMO-BIM1', 'type' => 'Scan to BIM', 'start' => 0, 'end' => 60, 'units' => 6,
            'members' => [4580, 4585, 4589, 4591], 'admin' => 4580,
            'epics' => [
                ['Block A', 0, 30, [
                    ['Architecture model - Block A', [4585, 4589], 'Modelling', 2, 150, 0, 22],
                    ['MEP model - Block A', [4591], 'Registration', 1, 90, 8, 30],
                ]],
                ['Block B', 28, 60, [
                    ['Architecture model - Block B', [4585, 4589], 'Registration', 2, 150, 28, 52],
                    ['MEP model - Block B', [4591, 4580], 'Registration', 1, 100, 40, 60],
                ]],
            ],
        ],
    ];

    private const RATES = [
        'GIS' => [['Jr GIS Engineer', 40], ['GIS Engineer', 60], ['Sr GIS Engineer', 80]],
        'Steel detailing' => [['Detailer', 3], ['Sr Eng Detailer', 4]],
        'Scan to BIM' => [['BIM Modeler', 0.1], ['Sr. BIM Modeler', 0.15]],
        'Field survey (Drone / DGPS / LiDAR)' => [['Drone Pilot', 2.5]],
    ];

    public function handle(): int
    {
        if ($this->option('remove')) {
            return $this->remove();
        }

        $company = DB::table('companies')->value('id');
        $admin = DB::table('users')->where('email', 'superadmin.test@arowana.test')->value('id') ?? DB::table('users')->value('id');
        $columns = TaskboardColumn::pluck('id', 'slug');

        foreach (self::RATES as $typeName => $rates) {
            $type = ProjectType::where('company_id', $company)->where('name', $typeName)->first();
            if (!$type || $type->rates()->exists()) {
                continue;
            }
            foreach ($rates as [$designation, $perDay]) {
                $designationId = DB::table('designations')->where('name', $designation)->value('id');
                if ($designationId) {
                    ProjectTypeRate::create(['project_type_id' => $type->id, 'designation_id' => $designationId, 'units_per_day' => $perDay]);
                }
            }
            $this->line("rates: $typeName");
        }

        foreach (self::PROJECTS as $p) {
            if (Project::where('project_name', $p['name'])->exists()) {
                $this->line("already there: {$p['name']}");
                continue;
            }

            $type = ProjectType::where('company_id', $company)->where('name', $p['type'])->with('stages')->first();
            $members = array_values(array_filter($p['members'], fn ($id) => DB::table('users')->where('id', $id)->where('status', 'active')->exists()));

            DB::transaction(function () use ($p, $type, $members, $company, $admin, $columns) {
                $project = new Project();
                $project->forceFill([
                    'company_id' => $company, 'project_name' => $p['name'], 'project_short_code' => $p['code'], 'project_summary' => 'Sample project for the tracker.',
                    'start_date' => now()->addDays($p['start'])->toDateString(), 'deadline' => now()->addDays($p['end'])->toDateString(),
                    'status' => $p['start'] > 0 ? 'not started' : 'in progress', 'project_admin' => in_array($p['admin'], $members) ? $p['admin'] : null,
                    'project_type_id' => $type?->id, 'planned_units' => $p['units'], 'calculate_task_progress' => 'true',
                    'added_by' => $admin, 'last_updated_by' => $admin, 'hash' => md5(microtime()),
                ])->save();

                foreach ($members as $uid) {
                    ProjectMember::create(['user_id' => $uid, 'project_id' => $project->id, 'added_by' => $admin]);
                }

                $n = 0;
                foreach ($p['epics'] as [$epicName, $from, $to, $items]) {
                    $epic = $this->task($project, $company, $admin, $columns, ++$n, ['heading' => $epicName, 'work_type' => 'epic', 'start' => $from, 'due' => $to]);

                    foreach ($items as [$heading, $users, $stageName, $units, $hours, $iFrom, $iTo]) {
                        $stage = $type?->stages->firstWhere('name', $stageName) ?? $type?->stages->first();
                        $task = $this->task($project, $company, $admin, $columns, ++$n, [
                            'heading' => $heading, 'work_type' => 'item', 'parent' => $epic->id, 'stage' => $stage?->id, 'units' => $units,
                            'done' => $iFrom < 0 ? round($units * 0.3) : 0, 'hours' => $hours, 'start' => $iFrom, 'due' => $iTo,
                            'column' => $iFrom <= 0 ? ($columns['doing'] ?? null) : ($columns['to_do'] ?? null),
                        ]);
                        $task->users()->sync(array_values(array_intersect($users, $members)));
                    }
                }
            });

            $this->info("added: {$p['name']}");
        }

        $this->history($company, $admin, $columns);

        return self::SUCCESS;
    }

    /**
     * History, so the charts, the KPIs and the profit timeline have something to show: a GIS project
     * of the last four months (time logged, progress recorded, items finished on time and late, a
     * budget, purchases), budgets on the other [DEMO] projects, and the office's monthly overheads.
     */
    private function history(int $company, int $admin, $columns): void
    {
        $currency = DB::table('companies')->where('id', $company)->value('currency_id');

        // budgets (the contract value) on the [DEMO] projects
        foreach (['[DEMO] Kachiguda OHE Mast Digitisation' => 120000, '[DEMO] HRM Portal - Project Tracker' => 250000,
                  '[DEMO] Hyderabad Metro Pole Survey (GIS)' => 380000, '[DEMO] Warehouse Steel Structure Detailing' => 300000,
                  '[DEMO] Hospital Scan to BIM' => 420000] as $name => $budget) {
            DB::table('projects')->where('project_name', $name)->whereNull('project_budget')->update(['project_budget' => $budget, 'currency_id' => $currency]);
        }

        // the office's shared costs, each month for the last five
        $categories = DB::table('expenses_category')->where('company_id', $company)->pluck('id', 'category_name');
        for ($i = 5; $i >= 0; $i--) {
            $day = now()->startOfMonth()->subMonths($i)->addDays(4)->toDateString();
            foreach (['Office rent' => 180000, 'Electricity' => 32000 + $i * 1500, 'Internet' => 9500, 'Software licences' => 45000] as $what => $amount) {
                if (!isset($categories[$what]) || DB::table('expenses')->where('category_id', $categories[$what])->where('purchase_date', $day)->where('description', '[DEMO]')->exists()) {
                    continue;
                }
                DB::table('expenses')->insert(['company_id' => $company, 'item_name' => $what . ' - ' . Carbon::parse($day)->format('M Y'), 'purchase_date' => $day,
                    'purchase_from' => 'Demo vendor', 'price' => $amount, 'currency_id' => $currency, 'default_currency_id' => $currency, 'exchange_rate' => 1,
                    'category_id' => $categories[$what], 'status' => 'approved', 'user_id' => $admin, 'description' => '[DEMO]', 'added_by' => $admin,
                    'created_at' => now(), 'updated_at' => now()]);
            }
        }

        $name = '[DEMO] Vizag Port Utility Mapping (GIS)';
        if (Project::where('project_name', $name)->exists()) {
            $this->line("already there: $name");
            return;
        }

        $type = ProjectType::where('company_id', $company)->where('name', 'GIS')->with('stages')->first();
        $stage = fn ($n) => $type?->stages->firstWhere('name', $n)?->id;
        $members = array_values(array_filter([4650, 4638, 4639, 4651, 4653], fn ($id) => DB::table('users')->where('id', $id)->where('status', 'active')->exists()));

        DB::transaction(function () use ($name, $company, $admin, $columns, $type, $stage, $members, $currency, $categories) {
            $project = new Project();
            $project->forceFill([
                'company_id' => $company, 'project_name' => $name, 'project_short_code' => 'DEMO-GIS3', 'project_summary' => 'Sample project with history for the tracker.',
                'start_date' => now()->subDays(120)->toDateString(), 'deadline' => now()->addDays(10)->toDateString(), 'status' => 'in progress',
                'project_admin' => $members[0] ?? null, 'project_type_id' => $type?->id, 'planned_units' => 3000, 'project_budget' => 260000,
                'currency_id' => $currency, 'calculate_task_progress' => 'true', 'added_by' => $admin, 'last_updated_by' => $admin, 'hash' => md5(microtime()),
            ])->save();

            foreach ($members as $uid) {
                ProjectMember::create(['user_id' => $uid, 'project_id' => $project->id, 'added_by' => $admin]);
            }

            $n = 0;
            $epic = $this->task($project, $company, $admin, $columns, ++$n, ['heading' => 'Port zone utilities', 'work_type' => 'epic', 'start' => -120, 'due' => 10]);
            // [heading, people, units, hours, from, to, finished-on (days ago) or null, done so far]
            $items = [
                ['Water lines - Zone 1', [4638, 4639], 600, 160, -118, -85, -86, 600],
                ['Storm drains - Zone 1', [4651], 400, 110, -100, -70, -64, 400],
                ['Power cables - Zone 2', [4638, 4653], 700, 190, -80, -40, -42, 700],
                ['Telecom ducts - Zone 2', [4639, 4651], 500, 140, -60, -20, -12, 500],
                ['Gas lines - Zone 3', [4653, 4638], 500, 130, -35, 5, null, 320],
                ['Street lights - Zone 3', [4639], 300, 80, -15, 10, null, 90],
            ];

            foreach ($items as [$heading, $people, $units, $hours, $from, $to, $doneOn, $done]) {
                $people = array_values(array_intersect($people, $members));
                $task = $this->task($project, $company, $admin, $columns, ++$n, [
                    'heading' => $heading, 'work_type' => 'item', 'parent' => $epic->id, 'stage' => $doneOn !== null ? $stage('Delivered') : $stage('Digitisation'),
                    'units' => $units, 'done' => $done, 'hours' => $hours, 'start' => $from, 'due' => $to,
                    'column' => $doneOn !== null ? ($columns['completed'] ?? null) : ($columns['doing'] ?? null),
                ]);
                $task->forceFill(['completed_on' => $doneOn !== null ? now()->addDays($doneOn)->toDateTimeString() : null])->save();
                $task->users()->sync($people);

                // time logged: on working days through the item's life (to its finish, or to today)
                $last = min($doneOn ?? 0, 0);
                $days = collect(range($from, $last))->filter(fn ($d) => now()->addDays($d)->dayOfWeekIso <= 6)->values();
                $perDay = $days->count() ? $hours * ($doneOn !== null ? 1.05 : 0.7) / $days->count() / max(1, count($people)) : 0;
                foreach ($days as $d) {
                    foreach ($people as $uid) {
                        $start = now()->addDays($d)->setTime(10, 0);
                        $minutes = (int) round($perDay * 60);
                        DB::table('project_time_logs')->insert(['company_id' => $company, 'project_id' => $project->id, 'task_id' => $task->id, 'user_id' => $uid,
                            'start_time' => $start, 'end_time' => $start->copy()->addMinutes($minutes), 'memo' => 'Demo work', 'total_hours' => intdiv($minutes, 60),
                            'total_minutes' => $minutes, 'total_break_minutes' => 0, 'approved' => 1, 'added_by' => $admin, 'last_updated_by' => $admin,
                            'created_at' => now(), 'updated_at' => now()]);
                    }
                }

                // progress recorded week by week, with one round of rework on the drains
                $weeks = max(1, intdiv(($last - $from), 7));
                $sofar = 0;
                for ($w = 1; $w <= $weeks; $w++) {
                    $add = round($done / $weeks, 2);
                    $at = now()->addDays($from + $w * 7)->min(now())->setTime(17, 0);
                    DB::table('tracker_progress_logs')->insert(['company_id' => $company, 'project_id' => $project->id, 'task_id' => $task->id, 'by_user_id' => $people[0] ?? $admin,
                        'assignee_ids' => json_encode($people), 'stage_from' => $stage('Digitisation'), 'stage_to' => $stage('Digitisation'),
                        'units_before' => $sofar, 'units_after' => $sofar + $add, 'units_delta' => $add, 'rework' => 0, 'completed' => 0, 'logged_at' => $at]);
                    $sofar += $add;
                }
                if ($heading === 'Storm drains - Zone 1') {
                    DB::table('tracker_progress_logs')->insert(['company_id' => $company, 'project_id' => $project->id, 'task_id' => $task->id, 'by_user_id' => 4650,
                        'assignee_ids' => json_encode($people), 'stage_from' => $stage('QC'), 'stage_to' => $stage('Correction'), 'units_before' => $done, 'units_after' => $done,
                        'units_delta' => 0, 'rework' => 1, 'completed' => 0, 'logged_at' => now()->addDays($doneOn - 4)]);
                }
                if ($doneOn !== null) {
                    DB::table('tracker_progress_logs')->insert(['company_id' => $company, 'project_id' => $project->id, 'task_id' => $task->id, 'by_user_id' => 4650,
                        'assignee_ids' => json_encode($people), 'stage_from' => $stage('QC'), 'stage_to' => $stage('Delivered'), 'units_before' => $done, 'units_after' => $done,
                        'units_delta' => 0, 'rework' => 0, 'completed' => 1, 'logged_at' => now()->addDays($doneOn)]);
                }
            }

            // what the project bought
            foreach ([['Drone survey batteries', 'Equipment & purchases', 85000, -110], ['Field visit - Vizag port', 'Travel', 42000, -95],
                      ['GPS rover rental', 'Field expenses', 60000, -60], ['Plotter paper and prints', 'Other', 12000, -20]] as [$item, $cat, $price, $day]) {
                DB::table('expenses')->insert(['company_id' => $company, 'item_name' => $item, 'purchase_date' => now()->addDays($day)->toDateString(),
                    'purchase_from' => 'Demo vendor', 'price' => $price, 'currency_id' => $currency, 'default_currency_id' => $currency, 'exchange_rate' => 1,
                    'project_id' => $project->id, 'category_id' => $categories[$cat] ?? null, 'status' => 'approved', 'user_id' => $admin,
                    'description' => '[DEMO]', 'added_by' => $admin, 'created_at' => now(), 'updated_at' => now()]);
            }
        });

        $this->info("added: $name (with four months of history)");
    }

    private function task(Project $project, int $company, int $admin, $columns, int $n, array $a): Task
    {
        $task = new Task();
        $task->forceFill([
            'company_id' => $company, 'project_id' => $project->id, 'heading' => $a['heading'], 'task_short_code' => $project->project_short_code . '-' . $n,
            'work_type' => $a['work_type'], 'parent_id' => $a['parent'] ?? null, 'project_stage_id' => $a['stage'] ?? null,
            'planned_units' => $a['units'] ?? null, 'done_units' => $a['done'] ?? 0,
            'estimate_hours' => (int) ($a['hours'] ?? 0), 'estimate_minutes' => 0, 'priority' => 'medium',
            'start_date' => now()->addDays($a['start'])->toDateString(), 'due_date' => now()->addDays($a['due'])->toDateString(),
            'board_column_id' => $a['column'] ?? ($columns['incomplete'] ?? $columns->first()),
            'created_by' => $admin, 'added_by' => $admin, 'last_updated_by' => $admin, 'hash' => md5(microtime() . $n),
        ])->save();

        return $task;
    }

    private function remove(): int
    {
        $ids = Project::withTrashed()->where('project_name', 'like', '[DEMO]%')->pluck('id');
        $taskIds = DB::table('tasks')->whereIn('project_id', $ids)->pluck('id');

        DB::transaction(function () use ($ids, $taskIds) {
            if (\Illuminate\Support\Facades\Schema::hasTable('task_blockers')) {
                DB::table('task_blockers')->whereIn('task_id', $taskIds)->delete();
            }
            DB::table('project_time_logs')->whereIn('project_id', $ids)->delete();
            DB::table('tracker_progress_logs')->whereIn('project_id', $ids)->delete();
            DB::table('expenses')->where('description', '[DEMO]')->delete();
            DB::table('task_users')->whereIn('task_id', $taskIds)->delete();
            DB::table('tasks')->whereIn('id', $taskIds)->delete();
            DB::table('project_milestones')->whereIn('project_id', $ids)->delete();
            DB::table('project_members')->whereIn('project_id', $ids)->delete();
            foreach (['project_activity', 'project_settings', 'project_user_notes', 'pinned'] as $t) {
                if (\Illuminate\Support\Facades\Schema::hasTable($t) && \Illuminate\Support\Facades\Schema::hasColumn($t, 'project_id')) {
                    DB::table($t)->whereIn('project_id', $ids)->delete();
                }
            }
            DB::table('projects')->whereIn('id', $ids)->delete();
        });

        $this->info('removed ' . $ids->count() . ' [DEMO] project(s) and ' . $taskIds->count() . ' task(s)');

        return self::SUCCESS;
    }

}
