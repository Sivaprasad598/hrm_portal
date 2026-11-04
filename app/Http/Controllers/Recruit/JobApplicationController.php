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
        $this->positions = JobPosition::orderByRaw("FIELD(status, 'open', 'on_hold', 'draft', 'closed')")->orderBy('title')->get(['id', 'title', 'code', 'status']);
        $this->unassignedCount = JobApplication::whereNull('job_position_id')->whereIn('stage', JobApplication::ACTIVE)->count();

        return view('recruitment.applications.index', $this->data);
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
