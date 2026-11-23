<?php

namespace App\Http\Controllers\Tracker;

use App\Helper\Reply;
use App\Models\Designation;
use App\Models\Project;
use App\Models\ProjectTrackerSetting;
use App\Models\ProjectType;
use App\Models\ProjectTypeRate;
use App\Models\ProjectTypeStage;
use App\Models\Task;
use App\Models\Team;
use App\Services\Tracker\TrackerAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Settings -> Project Tracker: the working hours a day, and the project types (one per
 * production Division) with their unit of work, steps, grace days and daily rates.
 */
class TrackerSettingController extends TrackerBaseController
{

    public function __construct()
    {
        parent::__construct();
        $this->activeSettingMenu = 'project_tracker_settings';

        $this->middleware(function ($request, $next) {
            abort_403(!TrackerAccess::settings());

            return $next($request);
        });
    }

    public function index()
    {
        $this->pageTitle = 'Project Tracker';
        $this->setting = ProjectTrackerSetting::forCompany();
        $this->types = ProjectType::where('company_id', company()->id)->with('stages', 'division', 'rates.designation')
            ->withCount('projects')->orderBy('sort')->orderBy('name')->get();

        return view('project-tracker.settings.index', $this->data);
    }

    /** The working hours a day (8 by default; 7 when the day is shorter). */
    public function hours(Request $request)
    {
        $request->validate(['workday_hours' => 'required|numeric|min:1|max:12'], ['workday_hours.*' => 'Enter the working hours a day, between 1 and 12.']);

        $setting = ProjectTrackerSetting::forCompany();
        $setting->workday_hours = round((float) $request->workday_hours, 2);
        $setting->save();

        return Reply::success('Working hours saved: ' . rtrim(rtrim(number_format($setting->workday_hours, 2), '0'), '.') . ' a day.');
    }

    public function create()
    {
        $this->type = new ProjectType(['unit_kind' => 'count', 'grace_days' => 3, 'active' => true]);
        $this->type->setRelation('stages', collect(array_map(fn ($name, $i) => new ProjectTypeStage(['name' => $name, 'sort' => $i, 'is_done' => $i === 4]),
            ['To do', 'In progress', 'QC', 'Correction', 'Delivered'], range(0, 4))));
        $this->type->setRelation('rates', collect());
        $this->lookups();

        return $this->panel('project-tracker.settings.type-form', 'Add Project Type');
    }

    public function edit($id)
    {
        $this->type = ProjectType::where('company_id', company()->id)->with('stages', 'rates')->findOrFail($id);
        $this->lookups();
        $this->usedStageIds = Task::whereIn('project_stage_id', $this->type->stages->pluck('id'))->distinct()->pluck('project_stage_id')->all();

        return $this->panel('project-tracker.settings.type-form', 'Edit Project Type — ' . $this->type->name);
    }

    public function store(Request $request)
    {
        $type = new ProjectType(['company_id' => company()->id, 'sort' => (int) ProjectType::where('company_id', company()->id)->max('sort') + 1]);

        return $this->save($request, $type, 'Project type added.');
    }

    public function update(Request $request, $id)
    {
        $type = ProjectType::where('company_id', company()->id)->findOrFail($id);

        return $this->save($request, $type, 'Project type saved.');
    }

    public function destroy($id)
    {
        $type = ProjectType::where('company_id', company()->id)->withCount('projects')->findOrFail($id);

        if ($type->projects_count) {
            return Reply::error($type->projects_count . ' project(s) use this type, so it is kept. Switch it off instead (untick "In use").');
        }

        DB::transaction(function () use ($type) {
            ProjectTypeRate::where('project_type_id', $type->id)->delete();
            ProjectTypeStage::where('project_type_id', $type->id)->delete();
            $type->delete();
        });

        return Reply::success('Project type deleted.');
    }

    private function save(Request $request, ProjectType $type, string $message)
    {
        $request->validate([
            'name' => 'required|string|max:120',
            'team_id' => 'nullable|integer',
            'unit_kind' => ['required', Rule::in(array_keys(ProjectType::UNIT_KINDS))],
            'unit_label' => 'nullable|required_if:unit_kind,count|string|max:40',
            'grace_days' => 'required|integer|min:0|max:365',
            'color' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'description' => 'nullable|string|max:2000',
            'stage_name' => 'required|array',
            'stage_name.*' => 'nullable|string|max:80',
            'rate_designation' => 'nullable|array',
            'rate_units' => 'nullable|array',
        ], [
            'name.required' => 'Give the project type a name.',
            'unit_label.required_if' => 'Say what is counted (features, sheets, models ...).',
            'stage_name.min' => 'A project type needs at least two steps.',
            'color.regex' => 'Pick a colour.',
        ]);

        // the steps, in order: an id keeps an existing step (and the tasks at it), no id is a new one
        $rows = [];
        foreach ((array) $request->stage_name as $i => $name) {
            if (trim((string) $name) !== '') {
                $rows[] = ['id' => (int) ($request->stage_id[$i] ?? 0) ?: null, 'name' => trim($name), 'color' => $request->stage_color[$i] ?? null];
            }
        }

        if (count($rows) < 2) {
            $this->fail('A project type needs at least two steps.');
        }

        if (count(array_unique(array_map(fn ($r) => mb_strtolower($r['name']), $rows))) !== count($rows)) {
            $this->fail('Two steps have the same name.');
        }

        $keep = array_filter(array_column($rows, 'id'));

        if ($type->exists) {
            $dropped = ProjectTypeStage::where('project_type_id', $type->id)->whereNotIn('id', $keep ?: [0])->pluck('name', 'id');
            $inUse = $dropped->isNotEmpty() ? Task::whereIn('project_stage_id', $dropped->keys())->count() : 0;

            if ($inUse) {
                $this->fail('Tasks are at "' . $dropped->implode('", "') . '", so it cannot be taken out. Move those tasks to another step first.');
            }
        }

        // the daily rates: one per designation (and step)
        $rates = [];
        foreach ((array) $request->rate_designation as $i => $designationId) {
            $units = (float) ($request->rate_units[$i] ?? 0);

            if ((int) $designationId && $units > 0) {
                $stageKey = $request->rate_stage[$i] ?? '';
                $rates[(int) $designationId . '|' . $stageKey] = ['designation_id' => (int) $designationId, 'stage_key' => $stageKey, 'units' => round($units, 2)];
            }
        }

        DB::transaction(function () use ($request, $type, $rows, $keep, $rates) {
            $type->fill([
                'name' => trim($request->name),
                'team_id' => $request->team_id ?: null,
                'unit_kind' => $request->unit_kind,
                'unit_label' => $request->unit_kind === 'hours' ? 'hours' : (trim((string) $request->unit_label) ?: ($request->unit_kind === 'km' ? 'km' : 'sq km')),
                'grace_days' => (int) $request->grace_days,
                'color' => $request->color ?: null,
                'description' => $request->description ?: null,
                'active' => $request->boolean('active'),
            ]);
            $type->save();

            ProjectTypeStage::where('project_type_id', $type->id)->whereNotIn('id', $keep ?: [0])->delete();

            $stageIds = [];
            foreach ($rows as $i => $row) {
                $stage = $row['id'] ? ProjectTypeStage::where('project_type_id', $type->id)->find($row['id']) : null;
                $stage = $stage ?: new ProjectTypeStage(['project_type_id' => $type->id]);
                $stage->fill(['name' => $row['name'], 'sort' => $i, 'is_done' => $i === count($rows) - 1,
                    'color' => preg_match('/^#[0-9a-fA-F]{6}$/', (string) $row['color']) ? $row['color'] : null]);
                $stage->save();
                // a rate can name a step by its position in the form ("n2") or by its id
                $stageIds['n' . $i] = $stage->id;
                $stageIds[(string) $stage->id] = $stage->id;
            }

            ProjectTypeRate::where('project_type_id', $type->id)->delete();
            foreach ($rates as $rate) {
                ProjectTypeRate::create(['project_type_id' => $type->id, 'designation_id' => $rate['designation_id'],
                    'stage_id' => $rate['stage_key'] !== '' ? ($stageIds[$rate['stage_key']] ?? null) : null, 'units_per_day' => $rate['units']]);
            }
        });

        return Reply::successWithData($message, ['redirectUrl' => route('project-tracker-settings.index')]);
    }

    private function lookups(): void
    {
        $this->divisions = Team::where('company_id', company()->id)->whereNull('parent_id')->orderBy('team_name')->get(['id', 'team_name']);
        $this->designations = Designation::where('company_id', company()->id)->orderBy('name')->get(['id', 'name']);
        $this->usedStageIds = $this->usedStageIds ?? [];
    }

}
