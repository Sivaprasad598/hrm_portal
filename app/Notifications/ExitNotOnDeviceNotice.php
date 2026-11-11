<?php

namespace App\Notifications;

use App\Models\Company;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * Tells admins and HR that somebody clocked out on the website (or the app)
 * but the biometric device has no exit for them that day (AttendanceSync,
 * "Clocked out, not on the device"). Their clock-out was kept; HR checks
 * whether they really left then.
 */
class ExitNotOnDeviceNotice extends BaseNotification
{

    /** @param array{user_id:int, name:string, date:string, where:string, clock_in:string, clock_out:string} $case */
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

        return "{$c['name']} clocked out on {$c['where']} at {$c['clock_out']} on {$day} (in at {$c['clock_in']}),"
            . ' but the biometric device has no exit punch for them. The clock-out was kept. Check whether they really'
            . ' left then, and correct the attendance if not.';
    }

    public function toMail($notifiable): MailMessage
    {
        $build = parent::build($notifiable);

        $build->subject($this->case['name'] . ' clocked out without a device exit - ' . config('app.name'))
            ->markdown('mail.email', [
                'url' => getDomainSpecificUrl(route('biometric.exceptions', ['type' => 'exit_not_on_device']), $this->company),
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
