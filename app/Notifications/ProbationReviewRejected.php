<?php

namespace App\Notifications;

use App\Models\EmployeeDetails;

class ProbationReviewRejected extends BaseNotification
{

    private $employeeDetails;
    private $remark;

    public function __construct(EmployeeDetails $employeeDetails, ?string $remark)
    {
        $this->employeeDetails = $employeeDetails;
        $this->remark = $remark;
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

        $content = __('email.probationReviewRejected.text') . '<br>'
            . 'Remarks:- ' . ($this->remark ?: '-');

        $build
            ->subject(__('email.probationReviewRejected.subject') . ' ' . config('app.name') . '.')
            ->markdown('mail.email', [
                'url' => $url,
                'content' => $content,
                'themeColor' => $this->company->header_color,
                'actionText' => __('email.probationReviewRejected.action'),
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
            'remark' => $this->remark,
        ];
    }

}
