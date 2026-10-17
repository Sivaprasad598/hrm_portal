<?php

namespace App\Notifications;

use App\Models\AttendanceReview;
use App\Models\Company;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * Tells an employee about their own exception from yesterday — no attendance
 * and no leave, a missing exit punch, or too few hours — so they can apply for
 * leave or ask HR to correct it before payroll counts the day.
 */
class AttendanceExceptionNotice extends BaseNotification
{

    public function __construct(private AttendanceReview $review)
    {
        $this->company = Company::first();
    }

    public function via($notifiable)
    {
        $via = ['database'];

        if ($notifiable->email_notifications && $notifiable->email != '') {
            $via[] = 'mail';
        }

        return $via;
    }

    public function message(): string
    {
        $day = $this->review->date->format('D d M');
        $flags = $this->review->flags ?? [];

        return match (true) {
            $this->review->status === AttendanceReview::ABSENT => "No attendance was recorded for you on {$day} and there is no leave for that day. If you were away, please apply for leave; if you were at work, ask HR to correct your attendance.",
            $this->review->status === AttendanceReview::LEAVE_PENDING => "Your leave for {$day} has not been approved yet, and no attendance was recorded. Please follow it up with your manager.",
            in_array('missing_exit', $flags, true) || in_array('single_punch', $flags, true) => "Your exit was not recorded on {$day}, so your hours for that day may be incomplete. Please ask HR to correct it if needed.",
            default => sprintf('Only %dh %02dm was recorded for you on %s, which is not a full day. Please ask HR if this is not right.',
                intdiv($this->review->presence_minutes, 60), $this->review->presence_minutes % 60, $day),
        };
    }

    public function toMail($notifiable): MailMessage
    {
        $build = parent::build($notifiable);

        $build->subject('Your attendance on ' . $this->review->date->format('d M Y') . ' - ' . config('app.name'))
            ->markdown('mail.email', [
                'url' => getDomainSpecificUrl(route('attendances.index'), $this->company),
                'content' => e($this->message()),
                'themeColor' => $this->company?->header_color,
                'actionText' => 'View my attendance',
                'notifiableName' => $notifiable->name,
            ]);

        parent::resetLocale();

        return $build;
    }

    // phpcs:ignore
    public function toArray($notifiable)
    {
        return [
            'date' => $this->review->date->toDateString(),
            'status' => $this->review->status,
            'message' => $this->message(),
        ];
    }

}
