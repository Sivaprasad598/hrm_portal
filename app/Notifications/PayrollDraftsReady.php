<?php

namespace App\Notifications;

use App\Models\Company;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * Sent to the admins when the automatic run has issued draft payslips for a
 * finished pay cycle, so somebody reviews, approves and pays them.
 */
class PayrollDraftsReady extends BaseNotification
{

    public function __construct(
        private string $period,
        private int $created,
        private array $waiting = []
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

        $lines = [
            sprintf('%d draft payslip(s) have been generated for %s from the attendance on record.', $this->created, e($this->period)),
            'Review them, approve, and mark them paid on Payslips Issued.',
        ];

        if ($this->waiting !== []) {
            $lines[] = '<strong>Waiting</strong> (tried again automatically each morning):<br>'
                . implode('<br>', array_map('e', array_slice($this->waiting, 0, 15)));
        }

        $build->subject(sprintf('Draft payslips ready: %s - %s', $this->period, config('app.name')))
            ->markdown('mail.email', [
                'url' => getDomainSpecificUrl(route('payslips.generated'), $this->company),
                'content' => implode('<br><br>', $lines),
                'themeColor' => $this->company?->header_color,
                'actionText' => 'Review payslips',
                'notifiableName' => $notifiable->name,
            ]);

        parent::resetLocale();

        return $build;
    }

    // phpcs:ignore
    public function toArray($notifiable)
    {
        return [
            'period' => $this->period,
            'created' => $this->created,
            'waiting' => count($this->waiting),
        ];
    }

}
