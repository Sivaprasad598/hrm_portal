<?php

namespace App\Notifications;

use App\Models\EmailNotificationSetting;
use App\Models\User;

class NewEmployeeAdmin extends BaseNotification
{

    /**
     * Create a new notification instance.
     *
     * @return void
     */
    private $newUser;
    private $emailSetting;

    public function __construct(User $newUser)
    {
        $this->newUser = $newUser;
        $this->company = $newUser->company;
        $this->emailSetting = EmailNotificationSetting::where('company_id', $this->company->id)->where('slug', 'user-registrationadded-by-admin')->first();
    }

    /**
     * Get the notification's delivery channels.
     *
     * @param mixed $notifiable
     * @return array
     */
    public function via($notifiable)
    {
        $via = ['database'];

        if ($this->emailSetting->send_email == 'yes' && $notifiable->email_notifications && $notifiable->email != '') {
            array_push($via, 'mail');
        }

        if ($this->emailSetting->send_slack == 'yes' && $this->company->slackSetting->status == 'active') {
            $this->slackUserNameCheck($notifiable) ? array_push($via, 'slack') : null;
        }

        if ($this->emailSetting->send_push == 'yes' && push_setting()->beams_push_status == 'active') {
            $pushNotification = new \App\Http\Controllers\DashboardController();
            $pushUsersIds = [[$notifiable->id]];
            $pushNotification->sendPushNotifications($pushUsersIds, __('email.newEmployeeAdmin.subject'), $this->newUser->name);
        }

        return $via;
    }

    /**
     * Get the mail representation of the notification.
     *
     * @param mixed $notifiable
     * @return \Illuminate\Notifications\Messages\MailMessage
     */
    public function toMail($notifiable)
    {
        $build = parent::build($notifiable);

        $url = route('employees.show', $this->newUser->id);
        $url = getDomainSpecificUrl($url, $this->company);

        $content = __('email.newEmployeeAdmin.text') . '<br>' . __('app.name') . ':- ' . $this->newUser->name . '<br>' . __('app.email') . ':- ' . $this->newUser->email;

        $build
            ->subject(__('email.newEmployeeAdmin.subject') . ' ' . config('app.name') . '.')
            ->markdown('mail.email', [
                'url' => $url,
                'content' => $content,
                'themeColor' => $this->company->header_color,
                'actionText' => __('email.newEmployeeAdmin.action'),
                'notifiableName' => $notifiable->name
            ]);

        parent::resetLocale();

        return $build;
    }

    /**
     * Get the array representation of the notification.
     *
     * @param mixed $notifiable
     * @return array
     */
    //phpcs:ignore
    public function toArray($notifiable)
    {
        return [
            'user_id' => $this->newUser->id,
            'name' => $this->newUser->name,
            'image_url' => $this->newUser->image_url,
        ];
    }

    /**
     * Get the Slack representation of the notification.
     *
     * @param mixed $notifiable
     * @return \Illuminate\Notifications\Messages\SlackMessage
     */
    public function toSlack($notifiable)
    {

        try {
            return $this->slackBuild($notifiable)
                ->content('*' . __('email.newEmployeeAdmin.subject') . ' ' . config('app.name') . '!*' . "\n" . __('email.newEmployeeAdmin.text') . "\n" . __('app.name') . ': ' . $this->newUser->name . "\n" . __('app.email') . ': ' . $this->newUser->email);
        } catch (\Exception $e) {
            return $this->slackRedirectMessage('email.newEmployeeAdmin.subject', $notifiable);
        }

    }

}
