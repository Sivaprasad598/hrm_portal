<?php

namespace App\Notifications;

use App\Models\RecruitInterview;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * To the interviewers of a recruitment interview. Types:
 * - invited:     they are on the panel
 * - rescheduled: the time, place or link changed
 * - cancelled:   the interview is off
 * - feedback:    a reminder to hand in their scorecard
 */
class RecruitInterviewNotice extends BaseNotification
{

    private const SUBJECTS = [
        'invited' => 'Interview: :candidate for :position',
        'rescheduled' => 'Interview rescheduled: :candidate for :position',
        'cancelled' => 'Interview cancelled: :candidate for :position',
        'feedback' => 'Scorecard needed: :candidate (:round)',
    ];

    private const INTROS = [
        'invited' => 'You are on the interview panel for this candidate.',
        'rescheduled' => 'This interview has been moved. The new details are below.',
        'cancelled' => 'This interview has been cancelled.',
        'feedback' => 'Please hand in your scorecard for this interview. The candidate cannot be selected until every interviewer has.',
    ];

    public function __construct(private RecruitInterview $interview, private string $type)
    {
        $this->company = $interview->company;
    }

    public function via($notifiable)
    {
        $via = ['database'];

        if ($notifiable->email_notifications && $notifiable->email != '') {
            $via[] = 'mail';
        }

        return $via;
    }

    public function toMail($notifiable): MailMessage
    {
        $build = parent::build($notifiable);
        $application = $this->interview->application;
        $candidate = $application->candidate;

        $lines = [
            self::INTROS[$this->type],
            'Candidate: <strong>' . e($candidate->name) . '</strong>' . ($candidate->total_experience !== null ? ' (' . e($candidate->experienceText()) . ')' : ''),
            'Position: ' . e($application->positionLabel()),
            'Round: ' . e($this->interview->round),
            'When: ' . e($this->interview->whenText()),
            'Mode: ' . e($this->interview->modeLabel()),
        ];

        if ($this->interview->meeting_link) {
            $lines[] = 'Link: <a href="' . e($this->interview->meeting_link) . '">' . e($this->interview->meeting_link) . '</a>';
        } elseif ($this->interview->location) {
            $lines[] = 'Where: ' . e($this->interview->location);
        }

        $build->subject(__(self::SUBJECTS[$this->type], [
            'candidate' => $candidate->name, 'position' => $application->positionLabel(), 'round' => $this->interview->round,
        ]) . ' - ' . config('app.name'))
            ->markdown('mail.email', [
                'url' => $this->type === 'cancelled' ? null : getDomainSpecificUrl(route('recruit-interviews.feedback', $this->interview->id), $this->company),
                'content' => implode('<br>', $lines),
                'themeColor' => $this->company?->header_color,
                'actionText' => $this->type === 'feedback' ? 'Give feedback' : 'Open interview',
                'notifiableName' => $notifiable->name,
            ]);

        parent::resetLocale();

        return $build;
    }

    // phpcs:ignore
    public function toArray($notifiable)
    {
        return [
            'id' => $this->interview->id,
            'type' => $this->type,
            'candidate' => $this->interview->application->candidate->name,
            'round' => $this->interview->round,
            'start' => $this->interview->starts_at->toIso8601String(),
        ];
    }

}
