<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Notifications\Messages\MailMessage;
use App\Models\ProjectTimeLog;

class ProjectTimelogCreateNotification extends BaseNotification
{

    /**
     * @var User
     */
    private $invite;
    public ProjectTimeLog $timelog;

    /**
     * Create a new notification instance.
     *
     * @return void
     */
    public function __construct(ProjectTimeLog $timelog)
    {
        $this->timelog = $timelog;
        $this->company = $timelog->user->company;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @param mixed $notifiable
     * @return array
     */
    // phpcs:ignore
    public function via($notifiable)
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     *
     * @param mixed $notifiable
     * @return MailMessage
     */
    // phpcs:ignore
    public function toMail($notifiable): MailMessage
    {
        $build = parent::build($notifiable);
        $build
            ->subject(__('email.createProjectTimeLog.subject'))
            ->greeting(__('email.hello') . ' ' . $notifiable->name . '!')
            ->line(__('email.createProjectTimeLog.text') . user()->name . '.')
            ->action(__('email.createProjectTimeLog.action'), route('timelogs.show', $this->timelog->id));

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
            'timelog_id'  => $this->timelog->id,
            'rejected_by' => $this->timelog->rejected_by,
        ];
    }

}
