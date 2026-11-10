<?php

namespace App\Notifications;

use App\Models\Company;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * Tells admins and HR that somebody on approved full-day leave still punched
 * on the biometric device that day (BiometricReconciler, "Punched while on
 * leave"), so they can decide: cancel the leave, or ignore the punches. Leave
 * wins in payroll until somebody changes it.
 */
class PunchedOnLeaveNotice extends BaseNotification
{

    /** @param array{user_id:int, name:string, date:string, leave:string, punches:int, first:?string, last:?string} $case */
    public function __construct(private array $case)
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
        $c = $this->case;
        $day = \Carbon\Carbon::parse($c['date'])->format('D d M Y');
        $times = $c['first'] ? ' (' . $c['first'] . ($c['last'] ? '–' . $c['last'] : '') . ')' : '';

        return "{$c['name']} is on approved {$c['leave']} leave on {$day}, but punched on the biometric device"
            . " {$c['punches']} time(s){$times}. Payroll counts the leave. If they actually worked, cancel the leave;"
            . ' otherwise mark the exception as ignored.';
    }

    public function toMail($notifiable): MailMessage
    {
        $build = parent::build($notifiable);

        $build->subject($this->case['name'] . ' punched while on leave - ' . config('app.name'))
            ->markdown('mail.email', [
                'url' => getDomainSpecificUrl(route('biometric.exceptions'), $this->company),
                'content' => e($this->message()),
                'themeColor' => $this->company?->header_color,
                'actionText' => 'Open biometric exceptions',
                'notifiableName' => $notifiable->name,
            ]);

        parent::resetLocale();

        return $build;
    }

    // phpcs:ignore
    public function toArray($notifiable)
    {
        return $this->case + ['message' => $this->message()];
    }

}
