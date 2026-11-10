<?php

namespace App\Http\Controllers\Recruit;

use App\Models\Candidate;
use App\Models\JobApplication;
use App\Models\JobOffer;
use App\Models\JobPosition;
use App\Models\RecruitInterview;
use App\Models\RecruitLog;
use App\Models\RecruitSetting;

/**
 * Recruitment -> Job Dashboard: what is open, where the candidates are in
 * the pipeline, today's interviews, what is waiting on someone.
 */
class RecruitDashboardController extends RecruitBaseController
{

    public function index()
    {
        $this->needView();
        $this->pageTitle = 'Job Dashboard';

        $this->openPositions = JobPosition::where('status', 'open')->withCount(['applications as joined_count' => fn ($q) => $q->where('stage', 'joined')])
            ->withCount(['applications as active_count' => fn ($q) => $q->whereIn('stage', JobApplication::ACTIVE)])
            ->orderBy('closing_date')->orderByDesc('id')->get();
        $this->openings = $this->openPositions->sum(fn ($p) => max(0, $p->openings - $p->joined_count));

        $this->stageCounts = JobApplication::selectRaw('stage, count(*) as n')->groupBy('stage')->pluck('n', 'stage');
        $this->unassigned = JobApplication::whereNull('job_position_id')->whereIn('stage', JobApplication::ACTIVE)->count();
        $this->newThisWeek = JobApplication::where('created_at', '>=', now()->subDays(7))->count();
        $this->candidateCount = Candidate::count();
        $this->joinedThisMonth = JobApplication::where('stage', 'joined')->where('stage_changed_at', '>=', now()->startOfMonth())->count();

        $this->sources = JobApplication::where('created_at', '>=', now()->subDays(30))
            ->selectRaw('source, count(*) as n')->groupBy('source')->pluck('n', 'source');

        $tz = company()->timezone;
        $this->upcoming = RecruitInterview::with('application.candidate', 'application.position')
            ->where('status', 'scheduled')
            ->where('starts_at', '>=', now()->tz($tz)->startOfDay()->utc())
            ->where('starts_at', '<', now()->tz($tz)->addDays(7)->endOfDay()->utc())
            ->orderBy('starts_at')->limit(12)->get();

        $this->overdueInterviews = RecruitInterview::where('status', 'scheduled')->where('starts_at', '<', now()->subHours(2))->count();

        // Held, but the marks / scorecards are not all in (tests and interviews)
        $automation = app(\App\Services\Recruit\RecruitAutomation::class);
        $this->awaitingFeedback = RecruitInterview::with('feedback', 'application.candidate')
            ->whereIn('status', ['scheduled', 'completed'])->whereNull('result')->where('starts_at', '>=', now()->subDays(60))
            ->where('starts_at', '<', now())->get()
            ->filter(fn ($i) => $i->endsAt()->isPast() && $automation->waitingOn($i))->values();
        // Slots the system suggested, for HR to confirm
        $this->toConfirm = RecruitInterview::with('application.candidate', 'application.position')->where('status', 'proposed')
            ->orderBy('starts_at')->limit(10)->get();
        $this->toConfirmCount = RecruitInterview::where('status', 'proposed')->count();
        // How each round is going (last 90 days): cleared / not cleared, the average score
        $this->roundStats = RecruitInterview::whereIn('result', ['passed', 'failed'])->where('result_at', '>=', now()->subDays(90))
            ->selectRaw("round, round_type, sum(result = 'passed') as passed, sum(result = 'failed') as failed, avg(score) as avg_score, avg(max_marks) as out_of")
            ->groupBy('round', 'round_type')->orderBy('round')->get();

        $this->offersOut = JobOffer::with('application.candidate', 'designation')->where('status', 'sent')->orderBy('valid_until')->get();
        $this->toJoin = JobApplication::with('candidate', 'position', 'offer')->where('stage', 'offer_accepted')->get()
            ->sortBy(fn ($a) => $a->offer?->joining_date)->values();

        $this->recent = RecruitLog::with('doneBy')->join('candidates', 'candidates.id', '=', 'recruit_logs.candidate_id')
            ->orderByDesc('recruit_logs.id')->limit(12)->get(['recruit_logs.*', 'candidates.name as candidate_name']);

        $this->setting = $this->canSettings ? RecruitSetting::forCompany(company()->id) : null;

        return view('recruitment.dashboard', $this->data);
    }

}
