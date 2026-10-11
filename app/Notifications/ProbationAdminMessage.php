<?php

namespace App\Notifications;

use App\Models\EmployeeDetails;

class ProbationAdminMessage extends BaseNotification
{

    private $employeeDetails;
    private $message;

    public function __construct(EmployeeDetails $employeeDetails, string $message)
    {
        $this->employeeDetails = $employeeDetails;
        $this->message = $message;
        $this->company = $employeeDetails->user->company;
    }

    /**
     * @param mixed $notifiable
     * @return array
     */
    public function via($notifiable)
    {
        return ['database', 'mail'];
    }

    /**
     * @param mixed $notifiable
     * @return \Illuminate\Notifications\Messages\MailMessage
     */
    public function toMail($notifiable)
    {
        $build = parent::build($notifiable);

        $url = route('dashboard');
        $url = getDomainSpecificUrl($url, $this->company);

        $content = __('email.probationAdminMessage.text') . '<br>'
            . __('app.message') . ':- ' . $this->message;

        $build
            ->subject(__('email.probationAdminMessage.subject') . ' ' . config('app.name') . '.')
            ->markdown('mail.email', [
                'url' => $url,
                'content' => $content,
                'themeColor' => $this->company->header_color,
                'actionText' => __('email.probationAdminMessage.action'),
                'notifiableName' => $notifiable->name
            ]);

        parent::resetLocale();

        return $build;
    }

    /**
     * @param mixed $notifiable
     * @return array
     */
    //phpcs:ignore
    public function toArray($notifiable)
    {
        return [
            'user_id' => $this->employeeDetails->user_id,
            'name' => $this->employeeDetails->user->name,
            'image_url' => $this->employeeDetails->user->image_url,
            'message' => $this->message,
        ];
    }

}
