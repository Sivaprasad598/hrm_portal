<?php

namespace App\Services\Recruit;

use App\Models\Event;
use App\Models\EventAttendee;
use App\Models\JobApplication;
use App\Models\RecruitInterview;
use App\Models\User;
use App\Notifications\RecruitCandidateMail;
use App\Notifications\RecruitInterviewNotice;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * Interviews: schedule, reschedule, cancel, and close them (completed /
 * no-show).
 *
 * Each interview is also an Event with the interviewers as attendees, so it
 * is in their calendar, gets the event reminder an hour before and goes to
 * Google Calendar when the company has connected it. The interviewers are
 * told in the portal and by email; the candidate is emailed when HR ticks
 * "email the candidate". Everything lands on the candidate's timeline.
 */
class InterviewScheduler
{

    public function __construct(protected Pipeline $pipeline)
    {
    }

    /**
     * @param array{round: string, mode: string, starts_at: Carbon, duration: int, location: ?string, meeting_link: ?string,
     *              interviewer_ids: int[], notes: ?string} $details  starts_at in UTC
     */
    public function schedule(JobApplication $application, array $details, bool $emailCandidate): RecruitInterview
    {
        $interview = DB::transaction(function () use ($application, $details) {
            $interview = new RecruitInterview($details);
            $interview->company_id = $application->company_id;
            $interview->job_application_id = $application->id;
            $interview->status = 'scheduled';
            $interview->created_by = user()?->id;
            $interview->save();

            $interview->event_id = $this->saveEvent($interview)->id;
            $interview->save();

            return $interview;
        });

        // Scheduling an interview is what puts the application in the Interview stage.
        if (in_array($application->stage, ['new', 'shortlisted', 'on_hold'], true)) {
            $this->pipeline->move($application, 'interview', null, 'Interview scheduled', false);
        }

        $this->pipeline->log($application->candidate_id, $application->id, 'interview',
            $interview->round . ' · ' . $interview->whenText() . ' · ' . $interview->modeLabel()
            . ' · Panel: ' . $interview->interviewers()->pluck('name')->implode(', '));

        $this->tellPanel($interview, $interview->interviewer_ids, 'invited');

        if ($emailCandidate) {
            $this->emailCandidate($interview, 'invited');
        }

        return $interview;
    }

    public function reschedule(RecruitInterview $interview, array $details, bool $emailCandidate): RecruitInterview
    {
        $before = array_map('intval', $interview->interviewer_ids ?: []);
        $interview->fill($details);
        $interview->save();
        $this->saveEvent($interview);

        $now = array_map('intval', $interview->interviewer_ids ?: []);
        $this->tellPanel($interview, array_values(array_diff($now, $before)), 'invited');
        $this->tellPanel($interview, array_values(array_intersect($now, $before)), 'rescheduled');
        $this->tellPanel($interview, array_values(array_diff($before, $now)), 'cancelled');

        $application = $interview->application;
        $this->pipeline->log($application->candidate_id, $application->id, 'interview',
            'Rescheduled: ' . $interview->round . ' · ' . $interview->whenText() . ' · ' . $interview->modeLabel());

        if ($emailCandidate) {
            $this->emailCandidate($interview, 'rescheduled');
        }

        return $interview;
    }

    /** Completed, no-show or cancelled. */
    public function close(RecruitInterview $interview, string $status, ?string $note, bool $emailCandidate = false): void
    {
        $interview->status = $status;
        $interview->status_note = $note;
        $interview->completed_at = $status === 'completed' ? now() : null;
        $interview->save();

        if ($interview->event) {
            $interview->event->status = $status === 'cancelled' ? 'cancelled' : 'completed';
            $interview->event->send_reminder = 'no';
            $interview->event->meeting_outcome = $note;
            $interview->event->completed_at = $status === 'completed' ? now() : null;
            $interview->event->save();
        }

        $application = $interview->application;
        $this->pipeline->log($application->candidate_id, $application->id, 'interview_status',
            $interview->round . ': ' . RecruitInterview::STATUSES[$status][0] . ($note ? ' — ' . $note : ''));

        if ($status === 'cancelled') {
            $this->tellPanel($interview, $interview->interviewer_ids, 'cancelled');

            if ($emailCandidate) {
                $this->emailCandidate($interview, 'cancelled');
            }
        }

        if ($status === 'completed') {
            // Those still to hand in a scorecard are reminded.
            $this->tellPanel($interview, $interview->missingFeedbackIds(), 'feedback');
        }
    }

    protected function saveEvent(RecruitInterview $interview): Event
    {
        $application = $interview->application()->with('candidate', 'position')->first();
        $event = $interview->event_id ? (Event::find($interview->event_id) ?? new Event()) : new Event();

        $event->event_name = 'Interview: ' . $application->candidate->name . ' (' . $interview->round . ')';
        $event->description = 'Position: ' . e($application->positionLabel()) . '<br>Mode: ' . e($interview->modeLabel())
            . ($interview->notes ? '<br>' . nl2br(e($interview->notes)) : '');
        $event->start_date_time = $interview->starts_at;
        $event->end_date_time = $interview->endsAt();
        $event->event_link = $interview->meeting_link;
        $event->where = $interview->location ?: ($interview->meeting_link ? 'Online' : $interview->modeLabel());
        $event->label_color = '#7c3aed';
        $event->repeat = 'no';
        $event->repeat_type = 'day';
        $event->send_reminder = 'yes';
        $event->remind_time = 1;
        $event->remind_type = 'hour';
        $event->note = '';

        if (!$event->exists) {
            $event->host = user()?->id;
            $event->status = 'pending';
        }

        $event->save();

        $ids = array_map('intval', $interview->interviewer_ids ?: []);
        EventAttendee::where('event_id', $event->id)->whereNotIn('user_id', $ids)->delete();

        foreach ($ids as $userId) {
            EventAttendee::firstOrCreate(['event_id' => $event->id, 'user_id' => $userId]);
        }

        return $event;
    }

    public function tellPanel(RecruitInterview $interview, array $userIds, string $type): void
    {
        $userIds = array_values(array_diff(array_map('intval', $userIds), [(int) user()?->id]));

        if (!$userIds) {
            return;
        }

        $users = User::withoutGlobalScopes()->whereIn('id', $userIds)->where('status', 'active')->get();

        try {
            Notification::send($users, new RecruitInterviewNotice($interview, $type));
        } catch (\Throwable $e) {
            logger()->warning('Interview notice not sent', ['interview' => $interview->id, 'error' => $e->getMessage()]);
        }
    }

    /** @return bool whether it went */
    public function emailCandidate(RecruitInterview $interview, string $type): bool
    {
        $candidate = $interview->application->candidate;

        if (!$candidate->email) {
            return false;
        }

        try {
            Notification::route('mail', [$candidate->email => $candidate->name])->notify(new RecruitCandidateMail($interview, $type));
        } catch (\Throwable $e) {
            logger()->warning('Candidate interview email not sent', ['interview' => $interview->id, 'error' => $e->getMessage()]);
            $this->pipeline->log($candidate->id, $interview->job_application_id, 'email', 'Could not email the candidate: ' . mb_substr($e->getMessage(), 0, 200));

            return false;
        }

        $interview->candidate_notified = true;
        $interview->save();

        // What was sent is kept on the timeline, word for word.
        $this->pipeline->log($candidate->id, $interview->job_application_id, 'email',
            'To ' . $candidate->email . ' — ' . RecruitCandidateMail::subject($interview, $type) . "\n" . implode("\n", RecruitCandidateMail::lines($interview, $type)));

        return true;
    }

}
