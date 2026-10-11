<?php

namespace App\Notifications;

use App\Models\EmployeeDetails;
use Carbon\Carbon;

class ProbationReviewPendingAdmin extends BaseNotification
{

    private $employeeDetails;
    private $completionDate;

    public function __construct(EmployeeDetails $employeeDetails, Carbon $completionDate)
    {
        $this->employeeDetails = $employeeDetails;
        $this->completionDate = $completionDate;
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

        $url = route('probation-requests.index');
        $url = getDomainSpecificUrl($url, $this->company);

        $content = __('email.probationReviewPendingAdmin.text') . '<br>'
            . __('app.menu.employeeName') . ':- ' . $this->employeeDetails->user->name . '<br>'
            . __('modules.dashboard.probationDate') . ':- ' . $this->completionDate->translatedFormat($this->company->date_format ?? 'd-m-Y');

        $build
            ->subject(__('email.probationReviewPendingAdmin.subject') . ' ' . config('app.name') . '.')
            ->markdown('mail.email', [
                'url' => $url,
                'content' => $content,
                'themeColor' => $this->company->header_color,
                'actionText' => __('email.probationReviewPendingAdmin.action'),
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
            'completion_date' => $this->completionDate->toDateString(),
        ];
    }

}
