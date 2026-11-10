<?php

namespace App\Http\Controllers\Recruit;

use App\Helper\Reply;
use App\Models\Candidate;
use App\Models\JobApplication;
use App\Models\JobPosition;
use App\Models\RecruitSetting;
use App\Services\Recruit\Pipeline;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Recruitment -> Applicants: every application, as a board by stage or a
 * list, and moving them along (Pipeline does the moving and the timeline).
 */
class JobApplicationController extends RecruitBaseController
{

    public function index(Request $request)
    {
        $this->needView();
        $this->pageTitle = 'Applicants';
        $this->board = $request->get('view', 'board') !== 'list';

        $query = JobApplication::with(['candidate.resume', 'position', 'offer'])
            ->withCount(['interviews as interviews_done' => fn ($q) => $q->where('status', 'completed')])
            ->when($request->position_id === 'unassigned', fn ($q) => $q->whereNull('job_position_id'))
            ->when($request->position_id && $request->position_id !== 'unassigned', fn ($q) => $q->where('job_position_id', $request->position_id))
            ->when($request->source, fn ($q, $s) => $q->where('source', $s))
            ->when($request->search, fn ($q, $s) => $q->whereHas('candidate', fn ($c) => $c->where('name', 'like', "%$s%")
                ->orWhere('email', 'like', "%$s%")->orWhere('mobile', 'like', "%$s%")->orWhere('skills', 'like', "%$s%")));

        $stage = $request->get('stage', 'active');

        if ($this->board) {
            // The board shows the stages still moving; a closed stage asked for is shown on its own.
            $query->when($stage === 'active' || $stage === '', fn ($q) => $q->whereIn('stage', JobApplication::ACTIVE),
                fn ($q) => $q->where('stage', $stage));
            $this->columns = $stage === 'active' || $stage === '' ? JobApplication::ACTIVE : [$stage];
            $this->applications = $query->orderByDesc('stage_changed_at')->limit(600)->get()->groupBy('stage');
        } else {
            $query->when($stage === 'active', fn ($q) => $q->whereIn('stage', JobApplication::ACTIVE))
                ->when($stage && $stage !== 'active' && $stage !== 'all', fn ($q) => $q->where('stage', $stage));
            $this->applications = $query->orderByDesc('id')->paginate(40)->withQueryString();
        }

        $this->stage = $stage;
        $this->progress = $this->roundProgress($this->board ? $this->applications->flatten(1) : $this->applications->getCollection());
        // Applicants by position, for the tabs over the board
        $this->positionCounts = JobApplication::whereIn('stage', JobApplication::ACTIVE)->whereNotNull('job_position_id')
            ->selectRaw('job_position_id, count(*) as n')->groupBy('job_position_id')->pluck('n', 'job_position_id');
        $this->positions = JobPosition::orderByRaw("FIELD(status, 'open', 'on_hold', 'draft', 'closed')")->orderBy('title')->get(['id', 'title', 'code', 'status']);
        $this->unassignedCount = JobApplication::whereNull('job_position_id')->whereIn('stage', JobApplication::ACTIVE)->count();

        return view('recruitment.applications.index', $this->data);
    }

    /**
     * Where each application is in its rounds, for the cards: "Round 2 of 3"
     * and the scores so far. One query for the rounds of all of them.
     *
     * @return array<int, array{at: ?int, of: int, scores: string[]}>
     */
    private function roundProgress($applications): array
    {
        $applications = collect($applications)->filter(fn ($a) => $a->job_position_id);

        if ($applications->isEmpty()) {
            return [];
        }

        $interviews = \App\Models\RecruitInterview::whereIn('job_application_id', $applications->pluck('id'))->whereNotNull('recruit_round_id')
            ->orderBy('id')->get()->groupBy('job_application_id');
        $plans = [];
        $out = [];

        foreach ($applications as $application) {
            $plan = $plans[$application->job_position_id] ??= \App\Models\RecruitRound::planFor($application->job_position_id)->values();

            if ($plan->isEmpty()) {
                continue;
            }

            $mine = $interviews->get($application->id, collect());
            $at = null;
            $scores = [];

            foreach ($plan as $i => $round) {
                $iv = $mine->where('recruit_round_id', $round->id)->last();
                $score = $iv?->score ?? $iv?->marks;

                if ($score !== null) {
                    $scores[] = 'R' . ($i + 1) . ' ' . \App\Models\RecruitRound::num($score) . ($iv->isTest() ? '' : '/5')
                        . ($iv->result === 'passed' ? ' ✓' : ($iv->result === 'failed' ? ' ✗' : ''));
                }

                if ($at === null && !in_array($iv?->result, ['passed', 'waived'], true)) {
                    $at = $i + 1;
                }
            }

            $out[$application->id] = ['at' => $at, 'of' => $plan->count(), 'scores' => $scores,
                'proposed' => $mine->contains('status', 'proposed')];
        }

        return $out;
    }

    /** Put a candidate forward for a position. */
    public function create(Request $request)
    {
        $this->needManage();
        $this->candidate = Candidate::findOrFail($request->candidate_id);
        $taken = $this->candidate->applications()->whereNotIn('stage', ['joined', 'rejected', 'withdrawn'])->pluck('job_position_id')->filter();
        $this->positions = JobPosition::whereIn('status', ['open', 'on_hold'])->whereNotIn('id', $taken)->orderBy('title')->get();

        return $this->panel('recruitment.applications.create', 'Add to Position');
    }

    public function store(Request $request, Pipeline $pipeline)
    {
        $this->needManage();
        $request->validate(['candidate_id' => 'required|integer', 'job_position_id' => 'required|integer'], ['job_position_id.required' => 'Choose the position.']);

        $candidate = Candidate::findOrFail($request->candidate_id);
        $position = JobPosition::findOrFail($request->job_position_id);

        if ($candidate->applications()->where('job_position_id', $position->id)->whereNotIn('stage', ['joined', 'rejected', 'withdrawn'])->exists()) {
            return Reply::error($candidate->name . ' is already being considered for ' . $position->title . '.');
        }

        // An unassigned application (an email naming no position) becomes this one rather than a second.
        $application = $candidate->applications()->whereNull('job_position_id')->whereIn('stage', JobApplication::ACTIVE)->first();

        if ($application) {
            $application->job_position_id = $position->id;
            $application->save();
            $pipeline->log($candidate->id, $application->id, 'applied', 'Assigned to ' . $position->label());
        } else {
            $application = JobApplication::create([
                'company_id' => company()->id, 'candidate_id' => $candidate->id, 'job_position_id' => $position->id,
                'stage' => 'new', 'stage_changed_at' => now(), 'source' => $candidate->source, 'created_by' => user()->id,
            ]);
            $pipeline->log($candidate->id, $application->id, 'applied', 'Put forward for ' . $position->label() . ' by ' . user()->name, null, 'new');
        }

        return Reply::successWithData($candidate->name . ' added to ' . $position->title . '.', ['redirectUrl' => route('candidates.show', $candidate->id)]);
    }

    /** Move to another stage, and / or set the position of an unassigned application. */
    /**
     * One applicant at a glance, from a board card: who they are, what they
     * bring, how the rounds went, with Shortlist / Reject / Move right there
     * - so HR screens the New column without leaving the board.
     */
    public function quick($id)
    {
        $this->needView();
        $this->application = JobApplication::with('candidate.resume', 'position')->findOrFail($id);
        $this->candidate = $this->application->candidate;
        $this->progress = $this->roundProgress([$this->application])[$this->application->id] ?? null;
        $this->choices = Pipeline::choices($this->application);
        $this->canManage = \App\Services\Recruit\RecruitAccess::can('manage');
        $this->otherApplications = JobApplication::with('position')->where('candidate_id', $this->candidate->id)
            ->where('id', '<>', $this->application->id)->latest('id')->get();
        $this->fmt = company()->date_format;

        return $this->panel('recruitment.applications.quick', $this->candidate->name);
    }

    /**
     * The same panel from Resumes, where a row is a person: their application
     * that is still moving, else the latest one. Someone with no application at
     * all goes to their full profile.
     */
    public function candidateQuick($candidateId)
    {
        $this->needView();

        $application = JobApplication::where('candidate_id', $candidateId)
            ->orderByRaw('CASE WHEN stage IN (' . implode(',', array_fill(0, count(JobApplication::ACTIVE), '?')) . ') THEN 0 ELSE 1 END', JobApplication::ACTIVE)
            ->latest('id')
            ->first();

        if (!$application) {
            return \App\Helper\Reply::redirect(route('candidates.show', $candidateId));
        }

        return $this->quick($application->id);
    }

    public function moveForm(Request $request, $id)
    {
        $this->needManage();
        $this->application = JobApplication::with('candidate', 'position')->findOrFail($id);
        $this->choices = Pipeline::choices($this->application);
        $this->preset = in_array($request->to, $this->choices, true) ? $request->to : null;
        $this->reasons = RecruitSetting::forCompany(company()->id)->rejectionReasons();
        $this->positions = JobPosition::whereIn('status', ['open', 'on_hold'])->orderBy('title')->get();

        return $this->panel('recruitment.applications.move', 'Move Application');
    }

    public function move(Request $request, $id, Pipeline $pipeline)
    {
        $this->needManage();
        $application = JobApplication::with('candidate', 'position')->findOrFail($id);
        $request->validate(['stage' => 'nullable|string', 'reason' => 'nullable|string|max:191', 'notes' => 'nullable|string|max:5000',
            'job_position_id' => 'nullable|integer']);

        $messages = [];

        if ($request->filled('job_position_id') && (int) $request->job_position_id !== (int) $application->job_position_id
            && !in_array($application->stage, ['offered', 'offer_accepted', 'joined'], true)) {
            $position = JobPosition::findOrFail($request->job_position_id);
            $from = $application->positionLabel();
            $application->job_position_id = $position->id;
            $application->save();
            $application->setRelation('position', $position);
            $pipeline->log($application->candidate_id, $application->id, 'applied', 'Position: ' . $from . ' → ' . $position->label());
            $messages[] = 'Position set to ' . $position->title;
        }

        if ($request->filled('stage') && $request->stage !== $application->stage) {
            try {
                $pipeline->move($application, $request->stage, $request->reason, $request->notes);
            } catch (RuntimeException $e) {
                return Reply::error($e->getMessage());
            }

            $messages[] = 'Moved to ' . JobApplication::STAGES[$request->stage][0];
        } elseif ($request->filled('notes')) {
            $pipeline->log($application->candidate_id, $application->id, 'note', trim($request->notes));
            $messages[] = 'Note added';
        }

        if (!$messages) {
            return Reply::error('Choose a stage to move to, or a position.');
        }

        // Back to the page the panel was opened over (board, list, profile); the profile when it was opened on its own.
        $back = url()->previous();

        return Reply::successWithData(implode('. ', $messages) . '.', [
            'redirectUrl' => str_contains($back, '/move') ? route('candidates.show', $application->candidate_id) : $back,
        ]);
    }

}
