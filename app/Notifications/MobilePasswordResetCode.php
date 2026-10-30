<?php

namespace App\Notifications;

/**
 * The six-digit code the mobile app's "Forgot password" sends: typed into the
 * app with a new password (Api\Mobile\AuthController::resetPassword). The
 * website's reset link (ResetPassword) is unchanged.
 */
class MobilePasswordResetCode extends BaseNotification
{

    public function __construct(public string $code, public int $minutes)
    {
    }

    public function via()
    {
        return ['mail'];
    }

    public function toMail($notifiable)
    {
        $this->company = $notifiable->company;

        return $this->build($notifiable)
            ->subject(__('email.forgetPassword.subject'))
            ->greeting('Hello ' . $notifiable->name . ',')
            ->line('Use this code in the app to set a new password:')
            ->line('**' . $this->code . '**')
            ->line('The code works for ' . $this->minutes . ' minutes, and only once.')
            ->line('If you did not ask for it, ignore this e-mail - your password stays as it is.');
    }

}
