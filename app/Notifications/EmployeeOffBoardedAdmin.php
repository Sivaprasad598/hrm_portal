<?php

namespace App\Notifications;

use App\Models\EmployeeExit;
use App\Models\User;

class EmployeeOffBoardedAdmin extends BaseNotification
{

    private $exit;
    private $employeeUser;

    public function __construct(EmployeeExit $exit)
    {
        $this->exit = $exit;
        // The employee's account is already deactivated by the time this
        // fires (offboard() flips it just before notifying), and the
        // default User query excludes deactivated accounts — bypass that
        // scope rather than let $exit->employee resolve to null.
        $this->employeeUser = User::withoutGlobalScopes()->find($exit->user_id);
        $this->company = $this->employeeUser->company;
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

        $url = route('employees.show', $this->exit->user_id);
        $url = getDomainSpecificUrl($url, $this->company);

        $dateFormat = $this->company->date_format ?? 'd-m-Y';
        $lastDay = $this->exit->offboarding_date
            ? \Carbon\Carbon::parse($this->exit->offboarding_date)->translatedFormat($dateFormat)
            : now()->translatedFormat($dateFormat);

        $content = __('email.employeeOffBoardedAdmin.text') . '<br>'
            . __('app.menu.employeeName') . ':- ' . $this->employeeUser->name . '<br>'
            . 'Last Working Date:- ' . $lastDay;

        $build
            ->subject(__('email.employeeOffBoardedAdmin.subject') . ' ' . config('app.name') . '.')
            ->markdown('mail.email', [
                'url' => $url,
                'content' => $content,
                'themeColor' => $this->company->header_color,
                'actionText' => __('email.employeeOffBoardedAdmin.action'),
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
            'exit_id' => $this->exit->id,
            'user_id' => $this->exit->user_id,
            'name' => $this->employeeUser->name,
            'image_url' => $this->employeeUser->image_url,
            'offboarding_date' => $this->exit->offboarding_date,
        ];
    }

}
