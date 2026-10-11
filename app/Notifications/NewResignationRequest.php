<?php

namespace App\Notifications;

use App\Models\EmailNotificationSetting;
use App\Models\Resignation;
use Illuminate\Notifications\Messages\MailMessage;

class NewResignationRequest extends BaseNotification
{

    private $resignation;
    private $emailSetting;

    public function __construct(Resignation $resignation)
    {
        $this->resignation = $resignation;
        $this->company = $this->resignation->company;
        $this->emailSetting = EmailNotificationSetting::where('company_id', $this->company->id)->where('slug', 'new-resignation-request')->first();
    }

    public function via($notifiable)
    {
        $via = ['database'];

        if ($this->emailSetting && $this->emailSetting->send_email == 'yes' && $notifiable->email_notifications && $notifiable->email != '') {
            array_push($via, 'mail');
        }

        return $via;
    }

    public function toMail($notifiable): MailMessage
    {
        $build = parent::build($notifiable);
        $url = route('resignation-management.index');
        $url = getDomainSpecificUrl($url, $this->company);

        $content = 'New resignation request from: ' . $this->resignation->employee->name . '.'
            . '<br>' . 'Resignation Date: ' . $this->resignation->resignation_date->format($this->company->date_format)
            . '<br>' . 'Reason: ' . $this->resignation->reason;

        $build
            ->subject('New Resignation Request - ' . config('app.name'))
            ->markdown('mail.email', [
                'url' => $url,
                'content' => $content,
                'themeColor' => $this->company->header_color,
                'actionText' => 'View Resignation Requests',
                'notifiableName' => $notifiable->name
            ]);

        parent::resetLocale();

        return $build;
    }

    public function toArray($notifiable)
    {
        return [
            'id' => $this->resignation->id,
            'user_id' => $this->resignation->employee->id,
            'user' => $this->resignation->employee,
        ];
    }

}
