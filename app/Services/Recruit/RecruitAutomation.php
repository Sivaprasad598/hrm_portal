<?php

namespace App\Services\Recruit;

use App\Models\AttendanceSetting;
use App\Models\Company;
use App\Models\JobApplication;
use App\Models\JobOffer;
use App\Models\JobPosition;
use App\Models\RecruitInterview;
use App\Models\RecruitRound;
use App\Models\RecruitSetting;
use App\Models\User;
use App\Notifications\RecruitInterviewNotice;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * The hiring rounds, run by the system once HR has shortlisted a candidate:
 *
 *   Shortlisted -> round 1 slot suggested (HR confirms with one click)
 *   -> held -> marks (test) / scorecards (interview) -> round score
 *   -> at or over the cut-off: next round suggested; under it: Rejected
 *      (no cut-off set: HR decides)
 *   -> last round cleared: Selected, and the offer drafted for HR to send.
 *
 * HR can change any slot or panel, decide or waive a round (also when a
 * scorecard can never come in), and every step is on the candidate's
 * timeline. advance() is safe to call any number of times: it only acts
 * when the application is waiting on the system.
 */
class RecruitAutomation
{

    /** How a round stands, for the screens: [label, badge colour] */
    public const STATES = [
        'not_started' => ['Not started', 'light'],
        'proposed' => ['Slot suggested — confirm', 'info'],
        'scheduled' => ['Scheduled', 'primary'],
        'awaiting' => ['Awaiting score', 'warning'],
        'to_decide' => ['HR to decide', 'warning'],
        'passed' => ['Cleared', 'success'],
        'failed' => ['Not cleared', 'danger'],
        'waived' => ['Waived', 'secondary'],
        'no_show' => ['No-show', 'danger'],
        'cancelled' => ['Cancelled', 'secondary'],
    ];

    public function __construct(protected Pipeline $pipeline)
    {
    }

    public static function stateBadge(string $state): string
    {
        [$label, $class] = self::STATES[$state] ?? [ucfirst($state), 'secondary'];

        return '<span class="badge badge-' . $class . '">' . e($label) . '</span>';
    }

    /** The plan rounds of an application, each with its latest interview. */
    public function rounds(JobApplication $application): Collection
    {
        if (!$application->job_position_id) {
            return collect();
        }

        $plan = RecruitRound::planFor($application->job_position_id, $application->company_id);
        $interviews = RecruitInterview::withoutGlobalScopes()->with('feedback')->where('job_application_id', $application->id)
            ->whereNotNull('recruit_round_id')->orderBy('id')->get();

        return $plan->values()->map(function (RecruitRound $round, int $i) use ($interviews, $plan) {
            $mine = $interviews->where('recruit_round_id', $round->id);
            $latest = $mine->last();

            return (object) [
                'no' => $i + 1,
                'of' => $plan->count(),
                'round' => $round,
                'interview' => $latest,
                'noShows' => $mine->where('status', 'no_show')->count(),
                'state' => $this->state($latest),
            ];
        });
    }

    /** not_started | proposed | scheduled | awaiting | to_decide | passed | failed | waived | no_show | cancelled */
    public function state(?RecruitInterview $interview): string
    {
        if (!$interview) {
            return 'not_started';
        }

        if ($interview->result) {
            return $interview->result;
        }

        return match ($interview->status) {
            'proposed' => 'proposed',
            'no_show' => 'no_show',
            'cancelled' => 'cancelled',
            default => $this->waitingOn($interview) ? ($interview->endsAt()->isPast() || $interview->status === 'completed' ? 'awaiting' : 'scheduled') : 'to_decide',
        };
    }

    /** Whether marks / scorecards are still to come in. */
    public function waitingOn(RecruitInterview $interview): bool
    {
        return $interview->round_type === 'test' ? $interview->marks === null : (bool) $interview->missingFeedbackIds();
    }

    /**
     * Take the application to its next step, if it is waiting on the system.
     *
     * @return string|null what was done, for the timeline / messages
     */
    public function advance(JobApplication $application): ?string
    {
        $application->refresh();

        if (!in_array($application->stage, ['shortlisted', 'interview'], true)) {
            return null;
        }

        // A closed position takes no one further
        if (JobPosition::withoutGlobalScopes()->where('id', $application->job_position_id)->value('status') === 'closed') {
            return null;
        }

        $rounds = $this->rounds($application);
        // HR moved the application by hand after this happened: their call stands, the system waits
        // (the last stage change reopened it from Rejected / On Hold / Withdrawn, after $when)
        $lastMove = \App\Models\RecruitLog::withoutGlobalScopes()->where('job_application_id', $application->id)
            ->where('action', 'stage')->orderByDesc('id')->first();
        $handMovedSince = fn (?Carbon $when) => $when && $lastMove && in_array($lastMove->from_stage, JobApplication::CLOSING, true)
            && $lastMove->to_stage === $application->stage && Carbon::parse($lastMove->created_at)->gte($when);

        if ($rounds->isEmpty()) {
            return null;
        }

        foreach ($rounds as $item) {
            if (in_array($item->state, ['passed', 'waived'], true)) {
                continue;
            }

            if ($item->state === 'failed') {
                if ($handMovedSince($item->interview->result_at)) {
                    return null;   // HR brought them back: HR decides the round
                }

                // Should have been closed when it failed; close it now.
                $this->reject($application, $item->interview);

                return 'rejected';
            }

            if ($item->state === 'no_show' && $item->noShows >= 2 && !$handMovedSince($item->interview->updated_at)) {
                $this->pipeline->move($application, 'rejected', 'Did not attend', $item->round->name . ': no-show twice', false);

                return 'rejected';
            }

            if (in_array($item->state, ['not_started', 'no_show', 'cancelled'], true)) {
                $interview = $this->propose($application, $item->round, $item->no, $item->of);

                return 'proposed ' . $interview->id;
            }

            // proposed / scheduled / awaiting / to_decide: waiting on HR or the panel
            return null;
        }

        // Every round cleared
        $this->pipeline->move($application, 'selected', null, 'All ' . $rounds->count() . ' rounds cleared', false);
        $offer = $this->draftOffer($application);

        return 'selected' . ($offer ? ', offer drafted' : '');
    }

    /** Suggest a slot for a round: a "proposed" interview, waiting for HR to confirm. */
    public function propose(JobApplication $application, RecruitRound $round, int $no, int $of): RecruitInterview
    {
        $setting = RecruitSetting::forCompany($application->company_id);
        $ids = array_values(array_unique(array_map('intval', $round->interviewer_ids ?: [])));
        $start = $this->suggestSlot($ids, (int) $round->duration, $setting, $application->company_id);

        $interview = RecruitInterview::create([
            'company_id' => $application->company_id,
            'job_application_id' => $application->id,
            'recruit_round_id' => $round->id,
            'round' => $round->name,
            'round_type' => $round->type,
            'max_marks' => $round->isTest() ? $round->max_marks : null,
            'cut_off' => $round->cut_off,
            'mode' => $round->mode,
            'starts_at' => $start,
            'duration' => $round->duration,
            'interviewer_ids' => $ids,
            'status' => 'proposed',
            'created_by' => user()?->id,
        ]);

        $panel = $ids ? User::withoutGlobalScopes()->whereIn('id', $ids)->pluck('name')->implode(', ') : 'no one assigned yet';
        $this->pipeline->log($application->candidate_id, $application->id, 'slot',
            'Round ' . $no . ' of ' . $of . ' — ' . $round->name . ': slot suggested for ' . $interview->whenText() . ' · Panel: ' . $panel
            . '. Waiting for HR to confirm.', null, null, $application->company_id);

        return $interview;
    }

    /** Marks / scorecards are in: the round's score, and its result when there is a cut-off. */
    public function evaluate(RecruitInterview $interview): void
    {
        $interview->refresh();

        if ($interview->result || in_array($interview->status, ['proposed', 'cancelled', 'no_show'], true) || $this->waitingOn($interview)) {
            return;
        }

        if ($interview->round_type === 'test') {
            $score = (float) $interview->marks;
        } else {
            $score = round((float) $interview->feedback()->avg('overall'), 2);
        }

        $interview->score = $score;

        if ($interview->cut_off !== null) {
            $interview->result = $score >= (float) $interview->cut_off ? 'passed' : 'failed';
            $interview->result_at = now();
            $interview->result_note = 'By the ' . ($interview->round_type === 'test' ? 'cut-off' : 'pass mark');
        }

        $interview->save();

        $application = $interview->application()->withoutGlobalScopes()->first();
        $this->pipeline->log($application->candidate_id, $application->id, 'round',
            $interview->round . ': ' . $this->scoreText($interview)
            . ($interview->result ? ' — ' . ($interview->result === 'passed' ? 'cleared' : 'not cleared') : ' — for HR to decide (no cut-off set)'),
            null, null, $interview->company_id);

        $this->afterResult($interview);
    }

    /** HR's decision on a round: passed, failed, or waived (skipped / a scorecard that will not come). */
    public function decide(RecruitInterview $interview, string $result, string $note): void
    {
        if ($result !== 'waived' && in_array($interview->status, ['proposed', 'scheduled'], true) && $interview->starts_at->isFuture()) {
            throw new \RuntimeException('This round has not been held yet. Clear or fail it once it has taken place, or waive it.');
        }

        $was = $interview->result;
        $interview->result = $result;
        $interview->result_note = $note;
        $interview->result_by = user()?->id;
        $interview->result_at = now();

        if ($interview->score === null && !$this->waitingOn($interview)) {
            $interview->score = $interview->round_type === 'test' ? $interview->marks : round((float) $interview->feedback()->avg('overall'), 2);
        }

        $interview->save();

        if ($result === 'waived' && $interview->status === 'proposed') {
            // never confirmed: nobody was told, nobody is told now
            $interview->status = 'cancelled';
            $interview->status_note = 'Round waived: ' . $note;
            $interview->save();
        } elseif ($result === 'waived' && $interview->status === 'scheduled') {
            app(InterviewScheduler::class)->close($interview, 'cancelled', 'Round waived: ' . $note);
        }

        $application = $interview->application;
        $this->pipeline->log($application->candidate_id, $application->id, 'round',
            $interview->round . ': ' . ucfirst($result) . ' by HR' . ($was && $was !== $result ? ' (was ' . $was . ')' : '') . ' — ' . $note);

        // A candidate the system rejected on this round comes back when HR passes it
        if ($application->stage === 'rejected' && in_array($result, ['passed', 'waived'], true)) {
            $this->pipeline->move($application, 'interview', null, 'Back in after HR decided ' . $interview->round, false);
        }

        $this->afterResult($interview);
    }

    protected function afterResult(RecruitInterview $interview): void
    {
        $application = JobApplication::withoutGlobalScopes()->find($interview->job_application_id);

        if (!$application || !$interview->result) {
            return;
        }

        if ($interview->result === 'failed') {
            $this->reject($application, $interview);

            return;
        }

        $this->advance($application);
    }

    protected function reject(JobApplication $application, RecruitInterview $interview): void
    {
        if (!in_array($application->stage, JobApplication::ACTIVE, true) || in_array($application->stage, ['offered', 'offer_accepted'], true)) {
            return;
        }

        $this->pipeline->move($application, 'rejected', 'Did not clear ' . mb_substr($interview->round, 0, 100), $this->scoreText($interview), false);
    }

    public function scoreText(RecruitInterview $interview): string
    {
        $score = $interview->score ?? ($interview->round_type === 'test' ? $interview->marks : null);

        if ($score === null) {
            return 'no score';
        }

        if ($interview->round_type === 'test') {
            return RecruitRound::num($score) . ($interview->max_marks ? ' / ' . RecruitRound::num($interview->max_marks) : '') . ' marks'
                . ($interview->cut_off !== null ? ' (cut-off ' . RecruitRound::num($interview->cut_off) . ')' : '');
        }

        return RecruitRound::num($score) . ' / 5' . ($interview->cut_off !== null ? ' (pass mark ' . RecruitRound::num($interview->cut_off) . ')' : '');
    }

    /** An offer drafted from the position and the candidate's expectation, for HR to check and send. */
    public function draftOffer(JobApplication $application): ?JobOffer
    {
        if (JobOffer::withoutGlobalScopes()->where('job_application_id', $application->id)->whereIn('status', JobOffer::OPEN)->exists()) {
            return null;
        }

        $offer = JobOffer::create(JobOffer::defaultsFor($application) + [
            'company_id' => $application->company_id, 'job_application_id' => $application->id, 'status' => 'draft', 'created_by' => user()?->id,
        ]);

        $this->pipeline->log($application->candidate_id, $application->id, 'offer',
            'Offer drafted by the system (' . ($offer->reference_no ?: 'no reference') . ', CTC ' . number_format($offer->annual_ctc) . ' per year)'
            . ' — HR to check it, print the letter and mark it sent.', null, null, $application->company_id);

        return $offer;
    }

    // ------------------------------------------------------------------ slots

    /**
     * The first time from (now + the lead time) when the whole panel is free for
     * $duration minutes: inside office hours on an office day, not a holiday,
     * nobody on leave, no other meeting or interview. UTC.
     */
    public function suggestSlot(array $userIds, int $duration, RecruitSetting $setting, ?int $companyId): Carbon
    {
        $company = Company::find($companyId);
        $tz = $company?->timezone ?: config('app.timezone');
        $shift = AttendanceSetting::withoutGlobalScopes()->where('company_id', $companyId)->first()?->shift;
        $dayStart = $shift?->office_start_time ?: '09:30:00';
        $dayEnd = $shift?->office_end_time ?: '18:30:00';
        $halfMark = $shift?->halfday_mark_time ?: '14:00:00';
        $openDays = array_map('intval', json_decode((string) ($shift?->office_open_days ?? ''), true) ?: [1, 2, 3, 4, 5, 6]);
        $duration = max(15, $duration);

        $from = now()->tz($tz)->addHours(max(0, (int) ($setting->slot_lead_hours ?? 24)));
        $from = $this->ceilToHalfHour($from);
        $horizon = max(1, (int) ($setting->slot_horizon_days ?? 14));

        for ($d = 0; $d <= $horizon; $d++) {
            $date = $from->copy()->startOfDay()->addDays($d);
            $dow = $date->dayOfWeek;   // 0 = Sunday

            if (!in_array($dow, $openDays, true) && !($dow === 0 && in_array(7, $openDays, true))) {
                continue;
            }

            if (DB::table('holidays')->where('company_id', $companyId)->whereDate('date', $date->toDateString())->exists()) {
                continue;
            }

            $open = Carbon::parse($date->toDateString() . ' ' . $dayStart, $tz);
            $close = Carbon::parse($date->toDateString() . ' ' . $dayEnd, $tz);
            $busy = $this->busy($userIds, $date, $tz, $open, $close, Carbon::parse($date->toDateString() . ' ' . $halfMark, $tz));

            if ($busy === null) {
                continue;   // someone is on leave all day
            }

            $t = $from->gt($open) ? $from->copy() : $open->copy();

            while ($t->copy()->addMinutes($duration)->lte($close)) {
                $end = $t->copy()->addMinutes($duration);
                $clash = collect($busy)->first(fn ($b) => $t->lt($b[1]) && $end->gt($b[0]));

                if (!$clash) {
                    return $t->utc();
                }

                $t->addMinutes(30);
            }
        }

        return $from->utc();
    }

    /** The panel's busy times that day, or null when one of them is away all day. */
    protected function busy(array $userIds, Carbon $date, string $tz, Carbon $open, Carbon $close, Carbon $halfMark): ?array
    {
        if (!$userIds) {
            return [];
        }

        $busy = [];
        $day = $date->toDateString();

        foreach (DB::table('leaves')->whereIn('user_id', $userIds)->whereDate('leave_date', $day)
            ->whereIn('status', ['approved', 'pending'])->get(['duration', 'half_day_type']) as $leave) {
            if ($leave->duration !== 'half day') {
                return null;
            }

            $busy[] = $leave->half_day_type === 'second_half' ? [$halfMark, $close] : [$open, $halfMark];
        }

        $dayFromUtc = Carbon::parse($day, $tz)->startOfDay()->utc();
        $dayToUtc = Carbon::parse($day, $tz)->endOfDay()->utc();

        foreach (DB::table('events')->join('event_attendees', 'event_attendees.event_id', '=', 'events.id')
            ->whereIn('event_attendees.user_id', $userIds)->where('events.status', '<>', 'cancelled')
            ->where('events.start_date_time', '<', $dayToUtc)->where('events.end_date_time', '>', $dayFromUtc)
            ->get(['events.start_date_time', 'events.end_date_time']) as $event) {
            $busy[] = [Carbon::parse($event->start_date_time, 'UTC')->tz($tz), Carbon::parse($event->end_date_time, 'UTC')->tz($tz)];
        }

        // Slots already suggested (not yet events) count as taken too
        foreach (RecruitInterview::withoutGlobalScopes()->where('status', 'proposed')
            ->whereBetween('starts_at', [$dayFromUtc, $dayToUtc])->get(['starts_at', 'duration', 'interviewer_ids']) as $other) {
            if (array_intersect(array_map('intval', $other->interviewer_ids ?: []), $userIds)) {
                $busy[] = [$other->starts_at->copy()->tz($tz), $other->starts_at->copy()->addMinutes($other->duration)->tz($tz)];
            }
        }

        return $busy;
    }

    protected function ceilToHalfHour(Carbon $time): Carbon
    {
        $time = $time->copy()->second(0);
        $extra = $time->minute % 30;

        return $extra ? $time->addMinutes(30 - $extra) : $time;
    }

    // ------------------------------------------------------------------ the 15-minute run

    /**
     * Scorecard reminders and HR alerts, offers past their date, and any
     * application still waiting on the system. @return array<string, int>
     */
    public function run(): array
    {
        $done = ['reminded' => 0, 'escalated' => 0, 'expired' => 0, 'advanced' => 0];
        $scheduler = app(InterviewScheduler::class);

        $settings = RecruitSetting::withoutGlobalScopes()->get()->keyBy('company_id');

        // 1. Scorecards / marks not in after the interview
        $due = RecruitInterview::withoutGlobalScopes()->with('feedback', 'application')
            ->whereIn('status', ['scheduled', 'completed'])->whereNull('result')
            ->where('starts_at', '<', now())->where('starts_at', '>', now()->subDays(30))->get()
            ->filter(fn ($i) => $i->endsAt()->isPast() && $this->waitingOn($i));

        foreach ($due as $interview) {
            $setting = $settings[$interview->company_id] ?? RecruitSetting::forCompany($interview->company_id);
            $ended = $interview->endsAt();
            $who = $interview->round_type === 'test' ? ($interview->interviewer_ids ?: []) : $interview->missingFeedbackIds();

            if ((!$interview->reminded_at || $interview->reminded_at->lte(now()->subHours(max(1, (int) $setting->reminder_hours))))) {
                $scheduler->tellPanel($interview, $who, 'feedback');
                $interview->reminded_at = now();
                $interview->save();
                $done['reminded']++;
            }

            if (!$interview->escalated_at && $ended->lte(now()->subHours(max(1, (int) $setting->escalate_hours)))) {
                $hr = $this->recruiters($interview->company_id);

                if ($hr->isNotEmpty()) {
                    try {
                        Notification::send($hr, new RecruitInterviewNotice($interview, 'overdue'));
                    } catch (\Throwable $e) {
                        logger()->warning('Overdue scorecard alert not sent', ['interview' => $interview->id, 'error' => $e->getMessage()]);
                    }
                }

                $interview->escalated_at = now();
                $interview->save();
                $done['escalated']++;
            }
        }

        // 2. Offers sent and not answered by their "valid until" date
        foreach (JobOffer::withoutGlobalScopes()->with('application')->where('status', 'sent')->whereNotNull('valid_until')
            ->whereDate('valid_until', '<', now()->toDateString())->get() as $offer) {
            $offer->status = 'expired';
            $offer->responded_at = now();
            $offer->save();
            $application = $offer->application;
            $this->pipeline->log($application->candidate_id, $application->id, 'offer',
                'Offer ' . ($offer->reference_no ?: '') . ' expired: not answered by ' . $offer->valid_until->format('d M Y'), null, null, $offer->company_id);

            if ($application->stage === 'offered') {
                $this->pipeline->move($application, 'selected', null, 'Offer expired — make a new one or close', false);
            }

            $done['expired']++;
        }

        // 3. Anyone waiting on the system (a round decided, a slot to suggest again). Only applications
        //    whose rounds have started (when HR shortlisted them): candidates already in Shortlisted /
        //    Interview before the rounds came in are not picked up on their own.
        $started = RecruitInterview::withoutGlobalScopes()->whereNotNull('recruit_round_id')->distinct()->pluck('job_application_id');

        foreach (JobApplication::withoutGlobalScopes()->whereIn('id', $started)->whereIn('stage', ['shortlisted', 'interview'])
            ->whereNotNull('job_position_id')->get() as $application) {
            try {
                if ($this->advance($application)) {
                    $done['advanced']++;
                }
            } catch (\Throwable $e) {
                logger()->warning('Recruitment automation step failed', ['application' => $application->id, 'error' => $e->getMessage()]);
            }
        }

        return $done;
    }

    /** Who runs recruitment: the Super Admins and anyone who may manage it. */
    public function recruiters(?int $companyId): Collection
    {
        $ids = DB::table('role_user')->join('roles', 'roles.id', '=', 'role_user.role_id')
            ->where('roles.company_id', $companyId)->where('roles.name', 'admin')->pluck('role_user.user_id')->all();

        // ... and whoever has "Manage recruitment" = All (Settings > Access), e.g. HR
        $managers = DB::table('user_permissions')
            ->join('permissions', 'permissions.id', '=', 'user_permissions.permission_id')
            ->join('permission_types', 'permission_types.id', '=', 'user_permissions.permission_type_id')
            ->join('users', 'users.id', '=', 'user_permissions.user_id')
            ->where('permissions.name', 'manage_recruitment')->where('permission_types.name', 'all')
            ->where('users.company_id', $companyId)->pluck('user_permissions.user_id')->all();
        $ids = array_values(array_unique(array_merge($ids, $managers)));

        return User::withoutGlobalScopes()->whereIn('id', $ids)->where('status', 'active')->get();
    }

}
