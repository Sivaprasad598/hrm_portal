<?php

namespace App\Notifications;

use App\Models\AttendanceReview;
use App\Models\Company;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * The morning summary for HR and admins: yesterday's attendance exceptions,
 * counted by kind, with a link to the Daily Review for that date.
 */
class AttendanceDailyDigest extends BaseNotification
{

    /**
     * @param  array<string, int>  $summary  status => count, plus 'exceptions'
     */
    public function __construct(
        private string $date,
        private array $summary,
        private bool $deviceComplete = true
    ) {
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

    public function toMail($notifiable): MailMessage
    {
        $build = parent::build($notifiable);
        $day = \Carbon\Carbon::parse($this->date)->format('D d M Y');

        $lines = [sprintf('<strong>%d</strong> attendance exception(s) on %s.', $this->summary['exceptions'] ?? 0, $day)];

        foreach (AttendanceReview::STATUSES as $status => [$label, , $attention]) {
            if (($this->summary[$status] ?? 0) > 0) {
                $lines[] = sprintf('%s: %d', $label, $this->summary[$status]);
            }
        }

        if (!$this->deviceComplete) {
            $lines[] = '<strong style="color:#b45309">The device could not be reached for this day, so the review is based on what was already on record. It will be re-run when the device answers.</strong>';
        }

        $build->subject(sprintf('Attendance review %s: %d exception(s) - %s', $day, $this->summary['exceptions'] ?? 0, config('app.name')))
            ->markdown('mail.email', [
                'url' => getDomainSpecificUrl(route('biometric.review', ['date' => $this->date]), $this->company),
                'content' => implode('<br>', $lines),
                'themeColor' => $this->company?->header_color,
                'actionText' => 'Open the Daily Review',
                'notifiableName' => $notifiable->name,
            ]);

        parent::resetLocale();

        return $build;
    }

    // phpcs:ignore
    public function toArray($notifiable)
    {
        return [
            'date' => $this->date,
            'exceptions' => $this->summary['exceptions'] ?? 0,
            'absent' => $this->summary[AttendanceReview::ABSENT] ?? 0,
            'device_complete' => $this->deviceComplete,
        ];
    }

}
