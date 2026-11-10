<?php

namespace App\Http\Controllers\Recruit;

use App\Helper\Reply;
use App\Models\JobApplication;
use App\Models\JobPosition;
use App\Models\RecruitInterview;
use App\Models\RecruitInterviewFeedback;
use App\Models\RecruitRound;
use App\Models\RecruitSetting;
use App\Services\Recruit\InterviewScheduler;
use App\Services\Recruit\Pipeline;
use App\Services\Recruit\RecruitAutomation;
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
            ->when($when === 'feedback', fn ($q) => $q->whereIn('id', $this->awaitingIds()))
            ->when($when === 'confirm', fn ($q) => $q->where('status', 'proposed'))
            ->when($when !== 'confirm' && !$request->status, fn ($q) => $q->where('status', '<>', 'proposed'))
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->when($request->mine, fn ($q) => $q->whereJsonContains('interviewer_ids', (int) user()->id))
            ->when($request->position_id, fn ($q, $p) => $q->whereHas('application', fn ($a) => $a->where('job_position_id', $p)))
            ->orderBy('starts_at', in_array($when, ['past', 'feedback'], true) ? 'desc' : 'asc')
            ->paginate(40)->withQueryString();

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

        if (!in_array($this->interview->status, ['scheduled', 'proposed'], true)) {
            return Reply::error('Only a scheduled interview can be rescheduled.');
        }

        $this->application = $this->interview->application;
        $this->rounds = RecruitSetting::forCompany(company()->id)->rounds();
        $this->employees = $this->employees();

        return $this->panel('recruitment.interviews.form', $this->interview->status === 'proposed' ? 'Change Slot & Confirm' : 'Reschedule Interview');
    }

    public function update(Request $request, $id, InterviewScheduler $scheduler)
    {
        $this->needManage();
        $interview = RecruitInterview::with('application')->findOrFail($id);

        if (!in_array($interview->status, ['scheduled', 'proposed'], true)) {
            return Reply::error('Only a scheduled interview can be rescheduled.');
        }

        $wasProposed = $interview->status === 'proposed';

        try {
            $scheduler->reschedule($interview, $this->validated($request), $request->boolean('email_candidate'));
        } catch (\RuntimeException $e) {
            return Reply::error($e->getMessage());
        }

        return Reply::successWithData(($wasProposed ? 'Confirmed: ' : 'Interview updated: ') . $interview->whenText() . '.',
            ['redirectUrl' => route('candidates.show', $interview->application->candidate_id)]);
    }

    /** One click: the slot the system suggested becomes the interview. */
    public function confirm(Request $request, $id, InterviewScheduler $scheduler)
    {
        $this->needManage();
        $interview = RecruitInterview::with('application')->findOrFail($id);

        try {
            $scheduler->confirm($interview, $request->boolean('email_candidate'));
        } catch (\RuntimeException $e) {
            return Reply::error($e->getMessage());
        }

        return Reply::successWithData($interview->round . ' confirmed for ' . $interview->whenText() . '. The panel has been told.',
            ['redirectUrl' => url()->previous() ?: route('candidates.show', $interview->application->candidate_id)]);
    }

    /** A test round's marks, by whoever conducts it or HR. */
    public function marks(Request $request, $id, RecruitAutomation $automation, Pipeline $pipeline)
    {
        $interview = RecruitInterview::with('application.candidate')->findOrFail($id);
        abort_403(!$interview->isInterviewer(user()->id) && !RecruitAccess::can('manage'));

        if (!$interview->isTest()) {
            return Reply::error('Marks are for a test round. Interview rounds take scorecards.');
        }

        if (in_array($interview->status, ['proposed', 'cancelled', 'no_show'], true) || ($interview->status === 'scheduled' && $interview->starts_at->isFuture())) {
            return Reply::error('The marks can be entered once the test has taken place.');
        }

        if ($interview->marks !== null) {
            return Reply::error('The marks for this test are already in (' . $automation->scoreText($interview) . '). HR can change the decision on the candidate\'s page.');
        }

        $max = (float) ($interview->max_marks ?: 100);
        $request->validate(['marks' => 'required|numeric|min:0|max:' . $max, 'comments' => 'nullable|string|max:5000'],
            ['marks.required' => 'Enter the marks.', 'marks.max' => 'The test is out of ' . RecruitRound::num($max) . '.']);

        $interview->marks = (float) $request->marks;
        $interview->status_note = trim((string) $request->comments) ?: $interview->status_note;
        $interview->save();

        if ($interview->status === 'scheduled') {
            app(InterviewScheduler::class)->close($interview, 'completed', 'Marks entered by ' . user()->name
                . (trim((string) $request->comments) !== '' ? ': ' . trim($request->comments) : ''));
        }

        $application = $interview->application;
        $pipeline->log($application->candidate_id, $application->id, 'feedback', user()->name . ' (' . $interview->round . '): '
            . RecruitRound::num($interview->marks) . ' / ' . RecruitRound::num($max) . ' marks'
            . ($request->comments ? ' — ' . mb_substr(trim($request->comments), 0, 500) : ''), null, null, $interview->company_id);

        $automation->evaluate($interview);
        $interview->refresh();

        return Reply::successWithData('Marks saved: ' . $automation->scoreText($interview)
            . ($interview->result ? ' — ' . strtolower(RecruitInterview::RESULTS[$interview->result][0]) . '.' : '. HR decides this round.'),
            ['redirectUrl' => route('recruit-interviews.feedback', $interview->id)]);
    }

    public function decideForm($id)
    {
        $this->needManage();
        $this->interview = RecruitInterview::with('application.candidate', 'feedback')->findOrFail($id);
        $this->scoreText = app(RecruitAutomation::class)->scoreText($this->interview);

        return $this->panel('recruitment.interviews.decide', 'Decide Round — ' . $this->interview->round);
    }

    /** HR passes, fails or waives a round (also overriding the cut-off). */
    public function decide(Request $request, $id, RecruitAutomation $automation)
    {
        $this->needManage();
        $interview = RecruitInterview::with('application')->findOrFail($id);
        $request->validate([
            'result' => ['required', Rule::in(array_keys(RecruitInterview::RESULTS))],
            'note' => 'required|string|min:3|max:2000',
        ], ['result.required' => 'Choose the decision.', 'note.required' => 'Say why (it is kept on the candidate\'s record).']);

        if ($interview->status === 'proposed' && $request->result !== 'waived') {
            return Reply::error('This round has not been held yet. Confirm the slot, or waive the round.');
        }

        if (in_array($interview->application->stage, ['offered', 'offer_accepted', 'joined'], true)) {
            return Reply::error('The candidate is past the rounds (an offer is out). Withdraw the offer first.');
        }

        try {
            $automation->decide($interview, $request->result, trim($request->note));
        } catch (\RuntimeException $e) {
            return Reply::error($e->getMessage());
        }

        return Reply::successWithData($interview->round . ': ' . strtolower(RecruitInterview::RESULTS[$request->result][0]) . '.',
            ['redirectUrl' => route('candidates.show', $interview->application->candidate_id)]);
    }

    /** My Space -> My Interviews: the rounds this employee takes. */
    public function mine(Request $request)
    {
        $this->pageTitle = 'My Interviews';
        $me = (int) user()->id;

        $all = RecruitInterview::withoutGlobalScopes()->with(['application.candidate', 'application.position', 'feedback'])
            ->where('company_id', company()->id)->whereIn('status', ['scheduled', 'completed'])
            ->whereJsonContains('interviewer_ids', $me)->orderBy('starts_at')->get();

        $mineToDo = fn ($i) => !$i->result && ($i->isTest() ? $i->marks === null : !$i->feedback->contains('user_id', $me))
            && ($i->status === 'completed' || $i->starts_at->isPast());

        $this->toDo = $all->filter($mineToDo)->values();
        $this->upcoming = $all->filter(fn ($i) => $i->status === 'scheduled' && $i->starts_at->isFuture())->values();
        $this->done = $all->reject($mineToDo)->reject(fn ($i) => $i->status === 'scheduled' && $i->starts_at->isFuture())
            ->sortByDesc('starts_at')->take(50)->values();
        $this->automation = app(RecruitAutomation::class);

        return view('recruitment.interviews.mine', $this->data);
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

        // A plan round that did not happen: the system suggests the next try now (or closes after a second no-show)
        if ($interview->recruit_round_id && $request->status !== 'completed') {
            app(RecruitAutomation::class)->advance($interview->application);
        }

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
        $this->questions = $this->questionsFor($this->interview);
        $this->automation = app(RecruitAutomation::class);
        $this->canMarks = $this->interview->isTest() && $this->interview->marks === null
            && ($isInterviewer || RecruitAccess::can('manage'))
            && ($this->interview->status === 'completed' || ($this->interview->status === 'scheduled' && $this->interview->starts_at->isPast()));
        // An interviewer sees the others' scorecards once they have given their own, so theirs is their own view.
        $this->showScorecards = RecruitAccess::can('view') || $this->mine;
        $this->canGive = $isInterviewer && !$this->mine && !$this->interview->isTest() && !in_array($this->interview->status, ['cancelled', 'proposed', 'no_show'], true)
            && ($this->interview->status === 'completed' || $this->interview->starts_at->isPast());
        $this->pageTitle = 'Interview — ' . $this->interview->application->candidate->name;

        return view('recruitment.interviews.feedback', $this->data);
    }

    public function feedback(Request $request, $id, Pipeline $pipeline)
    {
        $interview = RecruitInterview::with('application.candidate')->findOrFail($id);
        abort_403(!$interview->isInterviewer(user()->id));

        if ($interview->isTest()) {
            return Reply::error('This is a test round: enter the marks instead.');
        }

        if (in_array($interview->status, ['cancelled', 'proposed', 'no_show'], true) || ($interview->status === 'scheduled' && $interview->starts_at->isFuture())) {
            return Reply::error('The scorecard can be given once the interview has taken place.');
        }

        if (RecruitInterviewFeedback::where('recruit_interview_id', $interview->id)->where('user_id', user()->id)->exists()) {
            return Reply::error('You have already given your scorecard for this interview.');
        }

        $questions = $this->questionsFor($interview);
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

        // The last scorecard in: the round's score, and the next step
        app(RecruitAutomation::class)->evaluate($interview);

        return Reply::successWithData('Thank you - your scorecard is saved.', ['redirectUrl' => route('recruit-interviews.feedback', $interview->id)]);
    }

    /** The round's own scorecard questions, else the usual ones. */
    private function questionsFor(RecruitInterview $interview): array
    {
        $own = $interview->recruit_round_id ? RecruitRound::withoutGlobalScopes()->find($interview->recruit_round_id)?->questionList() : [];

        return $own ?: RecruitSetting::forCompany($interview->company_id)->questions();
    }

    /** Interviews held whose result waits on marks / scorecards. @return int[] */
    private function awaitingIds(): array
    {
        $automation = app(RecruitAutomation::class);

        return RecruitInterview::with('feedback')->whereIn('status', ['scheduled', 'completed'])->whereNull('result')
            ->where('starts_at', '<', now())->where('starts_at', '>=', now()->subDays(60))->get()
            ->filter(fn ($i) => $i->endsAt()->isPast() && $automation->waitingOn($i))->pluck('id')->all();
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
            'interviewer_ids' => array_values(array_intersect(array_unique(array_map('intval', $request->interviewer_ids)),
                $this->employees()->pluck('id')->map(fn ($id) => (int) $id)->all())) ?: $this->fail('Choose at least one interviewer from the employees.'),
            'notes' => $request->notes ?: null,
        ];
    }

}
