<?php

namespace App\Services\Recruit;

use App\Models\JobApplication;
use App\Models\JobPosition;
use App\Models\RecruitLog;
use RuntimeException;

/**
 * Moving an application through the hiring stages, and the timeline that
 * records it.
 *
 *   New → Shortlisted → Interview → Selected → Offered → Offer Accepted → Joined
 *   Rejected / On Hold / Withdrawn from any stage still moving (a reason is required)
 *
 * HR moves an application by hand up to Selected. Offered and Offer Accepted
 * follow the offer (JobOfferController), Joined follows the Add Employee save
 * (CandidateConversion), and Interview is also set when one is scheduled.
 * Selected needs a completed interview with every scorecard in.
 */
class Pipeline
{

    /** The stages HR can choose on "Move to". */
    public const MANUAL = ['new', 'shortlisted', 'interview', 'selected', 'rejected', 'on_hold', 'withdrawn'];

    /** Stages that follow an offer */
    public const OFFER_STAGES = ['offered', 'offer_accepted'];

    /** @return string[] the stages this application can be moved to by hand */
    public static function choices(JobApplication $application): array
    {
        if ($application->stage === 'joined') {
            return [];
        }

        // With an offer out or accepted, only closing it is a hand move (withdraw the offer to go back)
        if (in_array($application->stage, self::OFFER_STAGES, true)) {
            return ['rejected', 'withdrawn'];
        }

        return array_values(array_filter(self::MANUAL, fn ($stage) => $stage !== $application->stage));
    }

    /**
     * @throws RuntimeException when the move is not allowed
     */
    public function move(JobApplication $application, string $to, ?string $reason = null, ?string $notes = null, bool $byHand = true): void
    {
        $from = $application->stage;

        if ($from === $to) {
            return;
        }

        if (!isset(JobApplication::STAGES[$to])) {
            throw new RuntimeException('Unknown stage.');
        }

        if ($from === 'joined') {
            throw new RuntimeException('This candidate has already joined; the application is closed.');
        }

        if ($byHand && in_array($from, self::OFFER_STAGES, true) && !in_array($to, ['rejected', 'withdrawn'], true)) {
            throw new RuntimeException('There is an offer out for this application. Withdraw the offer first to move it back.');
        }

        if ($byHand && !in_array($to, self::MANUAL, true)) {
            throw new RuntimeException('"' . JobApplication::STAGES[$to][0] . '" is set by the offer or the joining, not by hand.');
        }

        if (in_array($to, JobApplication::CLOSING, true) && trim((string) $reason) === '') {
            throw new RuntimeException('Give the reason for moving this application to ' . JobApplication::STAGES[$to][0] . '.');
        }

        if ($to === 'selected') {
            $this->assertReadyToSelect($application);
        }

        $application->stage = $to;
        $application->stage_reason = in_array($to, JobApplication::CLOSING, true) ? mb_substr(trim((string) $reason), 0, 191) : null;
        $application->stage_changed_at = now();
        $application->save();

        $text = trim(implode(' — ', array_filter([trim((string) $reason), trim((string) $notes)])));
        $this->log($application->candidate_id, $application->id, 'stage', $text ?: null, $from, $to, $application->company_id);

        if ($to === 'joined' && $application->job_position_id) {
            $this->closeWhenFilled($application->position);
        }

        // Out of the running (rejected, on hold, withdrawn): slots suggested and not yet confirmed go
        if (in_array($to, JobApplication::CLOSING, true)) {
            \App\Models\RecruitInterview::withoutGlobalScopes()->where('job_application_id', $application->id)->where('status', 'proposed')
                ->update(['status' => 'cancelled', 'status_note' => 'Slot dropped: application ' . JobApplication::STAGES[$to][0], 'updated_at' => now()]);
        }

        // HR shortlisted (or moved to Interview by hand): the system suggests the next round's slot
        if ($to === 'shortlisted' || ($to === 'interview' && $byHand)) {
            try {
                app(RecruitAutomation::class)->advance($application);
            } catch (\Throwable $e) {
                logger()->warning('Could not suggest the first round', ['application' => $application->id, 'error' => $e->getMessage()]);
            }
        }
    }

    /** Selected needs a completed interview, and every interviewer's scorecard for each one completed. */
    public function assertReadyToSelect(JobApplication $application): void
    {
        // A position with a round plan: every round cleared or waived
        $rounds = app(RecruitAutomation::class)->rounds($application);

        if ($rounds->isNotEmpty()) {
            $open = $rounds->filter(fn ($r) => !in_array($r->state, ['passed', 'waived'], true));

            if ($open->isNotEmpty()) {
                throw new RuntimeException('Rounds still to clear: ' . $open->map(fn ($r) => $r->round->name)->implode(', ')
                    . '. Each round is cleared by its score, or decided / waived by HR.');
            }

            return;
        }

        $completed = $application->interviews()->with('feedback')->where('status', 'completed')->get();

        if ($completed->isEmpty()) {
            throw new RuntimeException('Complete at least one interview before selecting the candidate.');
        }

        foreach ($completed as $interview) {
            if ($missing = $interview->missingFeedbackIds()) {
                $names = \App\Models\User::withoutGlobalScopes()->whereIn('id', $missing)->pluck('name')->implode(', ');

                throw new RuntimeException('Waiting for the scorecard from ' . $names . ' (' . $interview->round . '). '
                    . 'The candidate can be selected once every interviewer has given feedback.');
            }
        }
    }

    public function log(int $candidateId, ?int $applicationId, string $action, ?string $notes = null,
        ?string $from = null, ?string $to = null, ?int $companyId = null): RecruitLog
    {
        return RecruitLog::create([
            'company_id' => $companyId ?? (company() ? company()->id : null),
            'candidate_id' => $candidateId,
            'job_application_id' => $applicationId,
            'action' => $action,
            'from_stage' => $from,
            'to_stage' => $to,
            'notes' => $notes !== null ? mb_substr($notes, 0, 5000) : null,
            'done_by' => user()?->id,
            'created_at' => now(),
        ]);
    }

    /** A position is closed once as many candidates have joined as it had openings. */
    public function closeWhenFilled(?JobPosition $position): void
    {
        if (!$position || $position->status === 'closed') {
            return;
        }

        $joined = $position->applications()->where('stage', 'joined')->count();

        if ($joined >= max(1, (int) $position->openings)) {
            $position->status = 'closed';
            $position->save();
        }
    }

}
