<?php

namespace App\Http\Controllers\Recruit;

use App\Helper\Reply;
use App\Models\JobApplication;
use App\Models\JobPosition;
use App\Models\RecruitInterview;
use App\Models\RecruitInterviewFeedback;
use App\Models\RecruitSetting;
use App\Services\Recruit\InterviewScheduler;
use App\Services\Recruit\Pipeline;
use App\Services\Recruit\RecruitAccess;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Recruitment -> Interviews: scheduling, rescheduling, closing (completed,
 * no-show, cancelled) and the interviewers' scorecards.
 *
 * An interviewer needs no recruitment permission to open the interview they
 * are on and hand in their scorecard (feedbackForm / feedback).
 */
class RecruitInterviewController extends RecruitBaseController
{

    public function index(Request $request)
    {
        $this->needView();
        $this->pageTitle = 'Interviews';
        $tz = company()->timezone;
        $when = $request->get('when', 'upcoming');

        $this->interviews = RecruitInterview::with(['application.candidate', 'application.position', 'feedback'])
            ->when($when === 'today', fn ($q) => $q->whereBetween('starts_at', [now()->tz($tz)->startOfDay()->utc(), now()->tz($tz)->endOfDay()->utc()]))
            ->when($when === 'upcoming', fn ($q) => $q->where('starts_at', '>=', now()->tz($tz)->startOfDay()->utc()))
            ->when($when === 'past', fn ($q) => $q->where('starts_at', '<', now()->tz($tz)->startOfDay()->utc()))
            ->when($when === 'feedback', fn ($q) => $q->where('status', 'completed'))
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->when($request->mine, fn ($q) => $q->whereJsonContains('interviewer_ids', (int) user()->id))
            ->when($request->position_id, fn ($q, $p) => $q->whereHas('application', fn ($a) => $a->where('job_position_id', $p)))
            ->orderBy('starts_at', in_array($when, ['past', 'feedback'], true) ? 'desc' : 'asc')
            ->paginate(40)->withQueryString();

        if ($when === 'feedback') {
            $this->interviews->setCollection($this->interviews->getCollection()->filter(fn ($i) => $i->missingFeedbackIds())->values());
        }

        $this->when = $when;
        $this->positions = JobPosition::orderBy('title')->get(['id', 'title', 'code']);
        $this->interviewerNames = \App\Models\User::withoutGlobalScopes()
            ->whereIn('id', $this->interviews->getCollection()->pluck('interviewer_ids')->flatten()->unique()->filter())->pluck('name', 'id');

        return view('recruitment.interviews.index', $this->data);
    }

    public function create(Request $request)
    {
        $this->needManage();
        $this->application = JobApplication::with('candidate', 'position')->findOrFail($request->application);

        if (!in_array($this->application->stage, ['new', 'shortlisted', 'interview', 'on_hold'], true)) {
            return Reply::error('Interviews can be scheduled while the application is New, Shortlisted, at Interview or On Hold.');
        }

        $this->interview = new RecruitInterview(['duration' => 30, 'mode' => 'in_person',
            'starts_at' => now()->tz(company()->timezone)->addDay()->setTime(11, 0)->utc()]);
        $done = $this->application->interviews()->where('status', 'completed')->count();
        $this->rounds = RecruitSetting::forCompany(company()->id)->rounds();
        $this->interview->round = $this->rounds[$done] ?? ($this->rounds[0] ?? 'Interview');
        $this->employees = $this->employees();

        return $this->panel('recruitment.interviews.form', 'Schedule Interview');
    }

    public function store(Request $request, InterviewScheduler $scheduler)
    {
        $this->needManage();
        $application = JobApplication::with('candidate')->findOrFail($request->job_application_id);
        $details = $this->validated($request);

        try {
            $interview = $scheduler->schedule($application, $details, $request->boolean('email_candidate'));
        } catch (\RuntimeException $e) {
            return Reply::error($e->getMessage());
        }

        $message = 'Interview scheduled for ' . $interview->whenText() . '.';

        if ($request->boolean('email_candidate')) {
            $message .= $interview->candidate_notified ? ' The candidate has been emailed.' : ' The candidate could not be emailed (no email on file, or mail failed).';
        }

        return Reply::successWithData($message, ['redirectUrl' => route('candidates.show', $application->candidate_id)]);
    }

    public function edit($id)
    {
        $this->needManage();
        $this->interview = RecruitInterview::with('application.candidate', 'application.position')->findOrFail($id);

        if ($this->interview->status !== 'scheduled') {
            return Reply::error('Only a scheduled interview can be rescheduled.');
        }

        $this->application = $this->interview->application;
        $this->rounds = RecruitSetting::forCompany(company()->id)->rounds();
        $this->employees = $this->employees();

        return $this->panel('recruitment.interviews.form', 'Reschedule Interview');
    }

    public function update(Request $request, $id, InterviewScheduler $scheduler)
    {
        $this->needManage();
        $interview = RecruitInterview::with('application')->findOrFail($id);

        if ($interview->status !== 'scheduled') {
            return Reply::error('Only a scheduled interview can be rescheduled.');
        }

        $scheduler->reschedule($interview, $this->validated($request), $request->boolean('email_candidate'));

        return Reply::successWithData('Interview updated: ' . $interview->whenText() . '.', ['redirectUrl' => route('candidates.show', $interview->application->candidate_id)]);
    }

    public function closeForm(Request $request, $id)
    {
        $this->needManage();
        $this->interview = RecruitInterview::with('application.candidate')->findOrFail($id);
        $this->preset = $request->status;

        return $this->panel('recruitment.interviews.close', 'Interview Outcome');
    }

    public function close(Request $request, $id, InterviewScheduler $scheduler)
    {
        $this->needManage();
        $interview = RecruitInterview::with('application.candidate', 'event')->findOrFail($id);
        $request->validate([
            'status' => ['required', Rule::in(['completed', 'no_show', 'cancelled'])],
            'note' => 'nullable|string|max:5000|required_if:status,no_show|required_if:status,cancelled',
        ], ['note.required_if' => 'Say what happened (it is kept on the candidate\'s record).']);

        if ($interview->status !== 'scheduled') {
            return Reply::error('This interview is already ' . strtolower(RecruitInterview::STATUSES[$interview->status][0]) . '.');
        }

        if ($request->status === 'completed' && $interview->starts_at->isFuture()) {
            return Reply::error('This interview has not started yet. Mark it completed once it has taken place.');
        }

        $scheduler->close($interview, $request->status, $request->note, $request->boolean('email_candidate'));

        return Reply::successWithData('Interview marked ' . strtolower(RecruitInterview::STATUSES[$request->status][0]) . '.',
            ['redirectUrl' => route('candidates.show', $interview->application->candidate_id)]);
    }

    /** The interview and its scorecards; the interviewer's own scorecard to fill in. */
    public function feedbackForm($id)
    {
        $this->interview = RecruitInterview::with(['application.candidate.resume', 'application.position', 'feedback.user'])->findOrFail($id);
        $isInterviewer = $this->interview->isInterviewer(user()->id);
        abort_403(!$isInterviewer && !RecruitAccess::can('view'));

        $this->isInterviewer = $isInterviewer;
        $this->mine = $this->interview->feedback->firstWhere('user_id', user()->id);
        $this->questions = RecruitSetting::forCompany($this->interview->company_id)->questions();
        // An interviewer sees the others' scorecards once they have given their own, so theirs is their own view.
        $this->showScorecards = RecruitAccess::can('view') || $this->mine;
        $this->canGive = $isInterviewer && !$this->mine && $this->interview->status !== 'cancelled'
            && ($this->interview->status === 'completed' || $this->interview->starts_at->isPast());
        $this->pageTitle = 'Interview — ' . $this->interview->application->candidate->name;

        return view('recruitment.interviews.feedback', $this->data);
    }

    public function feedback(Request $request, $id, Pipeline $pipeline)
    {
        $interview = RecruitInterview::with('application.candidate')->findOrFail($id);
        abort_403(!$interview->isInterviewer(user()->id));

        if ($interview->status === 'cancelled' || ($interview->status === 'scheduled' && $interview->starts_at->isFuture())) {
            return Reply::error('The scorecard can be given once the interview has taken place.');
        }

        if (RecruitInterviewFeedback::where('recruit_interview_id', $interview->id)->where('user_id', user()->id)->exists()) {
            return Reply::error('You have already given your scorecard for this interview.');
        }

        $questions = RecruitSetting::forCompany($interview->company_id)->questions();
        $request->validate([
            'ratings' => 'nullable|array',
            'ratings.*' => 'nullable|integer|min:1|max:5',
            'overall' => 'required|integer|min:1|max:5',
            'recommendation' => ['required', Rule::in(array_keys(RecruitInterviewFeedback::RECOMMENDATIONS))],
            'comments' => 'required|string|min:5|max:5000',
        ], ['overall.required' => 'Give an overall rating.', 'recommendation.required' => 'Choose your recommendation.',
            'comments.required' => 'Write a few words on why.']);

        $ratings = [];

        foreach ($questions as $i => $question) {
            if ($value = (int) ($request->input('ratings')[$i] ?? 0)) {
                $ratings[$question] = $value;
            }
        }

        RecruitInterviewFeedback::create([
            'recruit_interview_id' => $interview->id, 'user_id' => user()->id, 'ratings' => $ratings,
            'overall' => (int) $request->overall, 'recommendation' => $request->recommendation, 'comments' => trim($request->comments),
        ]);

        $application = $interview->application;
        $pipeline->log($application->candidate_id, $application->id, 'feedback', user()->name . ' (' . $interview->round . '): '
            . RecruitInterviewFeedback::RECOMMENDATIONS[$request->recommendation][0] . ', ' . (int) $request->overall . '/5 — '
            . mb_substr(trim($request->comments), 0, 500), null, null, $interview->company_id);

        // The interview has evidently happened: close it if HR has not yet.
        if ($interview->status === 'scheduled') {
            app(InterviewScheduler::class)->close($interview, 'completed', 'Closed when the first scorecard came in.');
        }

        return Reply::successWithData('Thank you - your scorecard is saved.', ['redirectUrl' => route('recruit-interviews.feedback', $interview->id)]);
    }

    /**
     * @return array{round: string, mode: string, starts_at: Carbon, duration: int, location: ?string, meeting_link: ?string,
     *               interviewer_ids: int[], notes: ?string}
     */
    private function validated(Request $request): array
    {
        $request->validate([
            'round' => 'required|string|max:100',
            'mode' => ['required', Rule::in(array_keys(RecruitInterview::MODES))],
            'interview_date' => 'required|date_format:' . company()->date_format,
            'start_time' => 'required',
            'duration' => 'required|integer|in:' . implode(',', RecruitInterview::DURATIONS),
            'interviewer_ids' => 'required|array|min:1',
            'interviewer_ids.*' => 'integer',
            'meeting_link' => 'nullable|url|max:191|required_if:mode,video',
            'location' => 'nullable|string|max:191',
            'notes' => 'nullable|string|max:5000',
        ], [
            'interviewer_ids.required' => 'Choose at least one interviewer.',
            'meeting_link.required_if' => 'Add the meeting link (Google Meet, Zoom, Teams ...) for a video interview.',
            'meeting_link.url' => 'The meeting link must be a full web address (https://...).',
        ]);

        try {
            $start = Carbon::createFromFormat(company()->date_format . ' ' . company()->time_format,
                $request->interview_date . ' ' . $request->start_time, company()->timezone)->setTimezone('UTC');
        } catch (\Throwable) {
            $this->fail('The time is not valid.');
        }

        return [
            'round' => trim($request->round),
            'mode' => $request->mode,
            'starts_at' => $start,
            'duration' => (int) $request->duration,
            'location' => $request->location ?: null,
            'meeting_link' => $request->mode === 'video' ? $request->meeting_link : null,
            'interviewer_ids' => array_values(array_unique(array_map('intval', $request->interviewer_ids))),
            'notes' => $request->notes ?: null,
        ];
    }

}
