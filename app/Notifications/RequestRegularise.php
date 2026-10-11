<?php

namespace App\Notifications;

use App\Models\AttendanceRegularisation;
use App\Models\EmailNotificationSetting;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class RequestRegularise extends BaseNotification
{
    use Queueable;

    private $requestRegularisation;
    private $emailSetting;
    /**
     * Create a new notification instance.
     */
    public function __construct(AttendanceRegularisation $requestRegularisation)
    {
        $this->requestRegularisation = $requestRegularisation;
        $this->company = company();

        // When there is company of user.
        if ($this->company) {
            $this->emailSetting = EmailNotificationSetting::where('company_id', $this->company->id)->where('slug', 'user-registrationadded-by-admin')->first();
        }
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        $via = ['database'];

        if (is_null($this->company)) {
            array_push($via, 'mail');
            return $via;
        }

        if ($this->emailSetting->send_email == 'yes' && $notifiable->email_notifications && $notifiable->email != '') {
            array_push($via, 'mail');
        }

        return $via;
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail($notifiable): MailMessage
    {
        $build = parent::build();
        $url = route('attendances.by_request');
        $url = getDomainSpecificUrl($url, $this->company);

        $content = __('clan.attendance.requestRegulariseAttendance') . "<br><br>" .
        __('app.employee') . ':  ' . $this->requestRegularisation->user->name . "<br>" .
        __('app.date') . ':  ' . Carbon::parse($this->requestRegularisation->date)->translatedFormat($this->company->date_format);

        return $build
            ->subject(__('clan.attendance.attendanceRegularisationCreated') . ' - ' . config('app.name') . '.')
            ->markdown('mail.email', [
                'url' => $url,
                'content' => $content,
                'themeColor' => $this->company?->header_color,
                'actionText' => __('clan.attendance.viewRequestRegularise'),
                'notifiableName' => $notifiable->name
            ]);
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray()
    {
        return [
            'id' => $this->requestRegularisation->id,
            'created_at' => $this->requestRegularisation->created_at->format('Y-m-d H:i:s'),
            'heading' => $this->requestRegularisation->user->name
        ];
    }
}
