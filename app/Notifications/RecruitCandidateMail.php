<?php

namespace App\Notifications;

use App\Models\RecruitInterview;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * Email to a candidate (not a user of the portal: sent with
 * Notification::route('mail', ...)) about their interview. Types:
 * invited, rescheduled, cancelled.
 */
class RecruitCandidateMail extends BaseNotification
{

    public function __construct(private RecruitInterview $interview, private string $type)
    {
        $this->company = $interview->company;
    }

    public function via($notifiable)
    {
        return ['mail'];
    }

    public static function subject(RecruitInterview $interview, string $type): string
    {
        $position = $interview->application->positionLabel();

        return match ($type) {
            'rescheduled' => 'Your interview has been rescheduled - ' . $position,
            'cancelled' => 'Your interview has been cancelled - ' . $position,
            default => 'Interview invitation - ' . $position,
        };
    }

    /** The lines of the email, also kept on the candidate's timeline as what was sent. @return string[] */
    public static function lines(RecruitInterview $interview, string $type): array
    {
        $company = $interview->company?->company_name ?? config('app.name');
        $position = $interview->application->position?->title;

        $lines = [match ($type) {
            'rescheduled' => 'Your interview' . ($position ? ' for the position of ' . $position : '') . ' with ' . $company . ' has been moved. The new details are below.',
            'cancelled' => 'We are sorry - your interview' . ($position ? ' for the position of ' . $position : '') . ' with ' . $company
                . ' has been cancelled. We will be in touch if a new time is arranged.',
            default => 'Thank you for your interest in ' . $company . '. We would like to invite you to an interview'
                . ($position ? ' for the position of ' . $position : '') . '.',
        }];

        if ($type !== 'cancelled') {
            $lines[] = 'Round: ' . $interview->round;
            $lines[] = 'Date and time: ' . $interview->whenText();
            $lines[] = 'Mode: ' . $interview->modeLabel();

            if ($interview->meeting_link) {
                $lines[] = 'Meeting link: ' . $interview->meeting_link;
            }

            if ($interview->location) {
                $lines[] = ($interview->mode === 'phone' ? 'Phone' : 'Venue') . ': ' . $interview->location;
            }

            $lines[] = 'Please reply to this email to confirm, or if you need another time.';
        }

        return $lines;
    }

    public function toMail($notifiable): MailMessage
    {
        $build = parent::build($notifiable);
        $candidate = $this->interview->application->candidate;

        $build->subject(self::subject($this->interview, $this->type))
            ->markdown('mail.email', [
                'url' => $this->type !== 'cancelled' ? $this->interview->meeting_link : null,
                'content' => implode('<br>', array_map(fn ($line) => preg_replace('~(https?://\S+)~', '<a href="$1">$1</a>', e($line)),
                    self::lines($this->interview, $this->type))),
                'themeColor' => $this->company?->header_color,
                'actionText' => 'Join the meeting',
                'notifiableName' => $candidate->name,
            ]);

        parent::resetLocale();

        return $build;
    }

}
