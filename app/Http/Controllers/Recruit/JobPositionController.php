<?php

namespace App\Http\Controllers\Recruit;

use App\Helper\Reply;
use App\Models\Branch;
use App\Models\Designation;
use App\Models\JobApplication;
use App\Models\JobPosition;
use App\Models\RecruitRound;
use App\Models\Team;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Recruitment -> Positions: the openings, each with its candidates by stage.
 */
class JobPositionController extends RecruitBaseController
{

    public function index(Request $request)
    {
        $this->needView();
        $this->pageTitle = 'Positions';

        $this->positions = JobPosition::with('department', 'designation', 'branch')
            ->withCount(['applications as active_count' => fn ($q) => $q->whereIn('stage', JobApplication::ACTIVE)])
            ->withCount(['applications as new_count' => fn ($q) => $q->where('stage', 'new')])
            ->withCount(['applications as interview_count' => fn ($q) => $q->where('stage', 'interview')])
            ->withCount(['applications as joined_count' => fn ($q) => $q->where('stage', 'joined')])
            ->withCount('applications')
            ->when($request->status, fn ($q, $s) => $q->where('status', $s), fn ($q) => $q->where('status', '<>', 'closed'))
            ->when($request->type, fn ($q, $t) => $q->where('employment_type', $t))
            ->when($request->department_id, fn ($q, $d) => $q->where('department_id', $d))
            ->when($request->search, fn ($q, $s) => $q->where(fn ($w) => $w->where('title', 'like', "%$s%")
                ->orWhere('code', 'like', "%$s%")->orWhere('location', 'like', "%$s%")->orWhere('skills', 'like', "%$s%")))
            ->orderByRaw("FIELD(status, 'open', 'on_hold', 'draft', 'closed')")->orderByDesc('id')
            ->paginate(24)->withQueryString();

        $this->departments = Team::orderBy('team_name')->get();
        $this->statusCounts = JobPosition::selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status');

        return view('recruitment.positions.index', $this->data);
    }

    public function create()
    {
        $this->needManage();
        $this->position = new JobPosition(['code' => JobPosition::nextCode(), 'openings' => 1, 'status' => 'open', 'employment_type' => 'full_time']);
        $this->lookups();

        return $this->panel('recruitment.positions.form', 'Add Position');
    }

    public function store(Request $request)
    {
        $this->needManage();
        $data = $this->validated($request);
        $data['company_id'] = company()->id;
        $data['created_by'] = user()->id;
        $rows = $this->ownPlan($request);

        $position = JobPosition::create($data);
        $this->savePlan($position, $rows);

        return Reply::successWithData('Position added.', ['redirectUrl' => route('job-positions.show', $position->id)]);
    }

    public function show($id)
    {
        $this->needView();
        $this->position = JobPosition::with('department', 'designation', 'branch', 'createdBy')->findOrFail($id);
        $this->pageTitle = $this->position->title;

        $this->applications = $this->position->applications()->with('candidate', 'offer')
            ->orderByRaw("FIELD(stage, '" . implode("','", array_keys(JobApplication::STAGES)) . "')")
            ->orderByDesc('id')->get();
        $this->stageCounts = $this->applications->countBy('stage');
        $this->plan = RecruitRound::planFor($this->position->id);
        $this->ownPlan = RecruitRound::where('job_position_id', $this->position->id)->exists();
        $this->roundResults = \App\Models\RecruitInterview::whereIn('job_application_id', $this->applications->pluck('id'))
            ->whereNotNull('recruit_round_id')->whereNotNull('result')->get(['recruit_round_id', 'result'])->groupBy('recruit_round_id');

        return view('recruitment.positions.show', $this->data);
    }

    public function edit($id)
    {
        $this->needManage();
        $this->position = JobPosition::findOrFail($id);
        $this->lookups();

        return $this->panel('recruitment.positions.form', 'Edit Position — ' . $this->position->title);
    }

    public function update(Request $request, $id)
    {
        $this->needManage();
        $position = JobPosition::findOrFail($id);
        $data = $this->validated($request, $position);
        $rows = $this->ownPlan($request);
        $position->update($data);
        $this->savePlan($position, $rows);

        return Reply::successWithData('Position updated.', ['redirectUrl' => route('job-positions.show', $position->id)]);
    }

    public function destroy($id)
    {
        $this->needManage();
        $position = JobPosition::withCount('applications')->findOrFail($id);

        if ($position->applications_count) {
            return Reply::error('This position has candidates, so it is kept for the record. Close it instead.');
        }

        $position->delete();

        return Reply::successWithData('Position deleted.', ['redirectUrl' => route('job-positions.index')]);
    }

    /** null: the position follows the default plan; else its own rounds. */
    private function ownPlan(Request $request): ?array
    {
        if ($request->boolean('use_default_plan')) {
            return null;
        }

        $rows = $this->planRows($request);

        if (!$rows) {
            $this->fail('Add at least one round, or tick "Use the default plan".');
        }

        return $rows;
    }

    private function savePlan(JobPosition $position, ?array $rows): void
    {
        $own = RecruitRound::where('job_position_id', $position->id)->pluck('id')->all();

        try {
            if ($rows === null) {
                // Back to the default plan: not while candidates are in this position's own rounds
                RecruitRound::assertNotInUse($own, $position->id);
                RecruitRound::whereIn('id', $own)->delete();
            } else {
                if (!$own) {
                    // Its own plan from now: not while candidates are in the default plan's rounds here
                    RecruitRound::assertNotInUse(RecruitRound::planFor(null, $position->company_id)->pluck('id')->all(), $position->id);
                }

                RecruitRound::savePlan($position->id, $rows, $position->company_id);
            }
        } catch (\RuntimeException $e) {
            $this->fail($e->getMessage());
        }

        app(\App\Services\Recruit\InterviewScheduler::class)->forgetMenus(collect($rows ?? [])->pluck('interviewer_ids')->flatten()->all());
    }

    private function lookups(): void
    {
        $id = $this->position->id ?? null;
        $this->ownPlan = $id && RecruitRound::where('job_position_id', $id)->exists();
        $this->plan = RecruitRound::planFor($id);
        $this->employees = $this->employees();
        $this->departments = Team::orderBy('team_name')->get();
        $this->designations = Designation::orderBy('name')->get();
        $this->branches = Branch::orderBy('branch_name')->get();
    }

    private function validated(Request $request, ?JobPosition $position = null): array
    {
        $request->validate([
            'title' => 'required|string|max:191',
            'code' => ['required', 'string', 'max:20', Rule::unique('job_positions', 'code')->where('company_id', company()->id)->ignore($position?->id)],
            'department_id' => 'nullable|integer',
            'designation_id' => 'nullable|integer',
            'branch_id' => 'nullable|integer',
            'openings' => 'required|integer|min:1|max:500',
            'employment_type' => ['required', Rule::in(array_keys(JobPosition::TYPES))],
            'experience_min' => 'nullable|numeric|min:0|max:60',
            'experience_max' => 'nullable|numeric|min:0|max:60|gte:experience_min',
            'salary_min' => 'nullable|numeric|min:0',
            'salary_max' => 'nullable|numeric|min:0|gte:salary_min',
            'location' => 'nullable|string|max:191',
            'skills' => 'nullable|string|max:2000',
            'description' => 'nullable|string|max:20000',
            'status' => ['required', Rule::in(array_keys(JobPosition::STATUSES))],
        ], [
            'code.unique' => 'Another position already has this code.',
            'experience_max.gte' => 'The maximum experience cannot be below the minimum.',
            'salary_max.gte' => 'The maximum salary cannot be below the minimum.',
        ]);

        $data = $request->only(['title', 'code', 'openings', 'employment_type', 'location', 'skills', 'description', 'status']);

        foreach (['department_id', 'designation_id', 'branch_id'] as $key) {
            $data[$key] = $request->input($key) ?: null;
        }

        foreach (['experience_min', 'experience_max', 'salary_min', 'salary_max'] as $key) {
            $data[$key] = $request->filled($key) ? $request->input($key) : null;
        }

        $data['closing_date'] = $this->date($request->closing_date, 'closing date', false);

        return $data;
    }

}
