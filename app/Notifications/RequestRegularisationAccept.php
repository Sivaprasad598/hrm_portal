<?php

namespace App\Notifications;

use App\Models\AttendanceRegularisation;
use App\Models\EmailNotificationSetting;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class RequestRegularisationAccept extends BaseNotification
{
    use Queueable;

    /**
     * Create a new notification instance.
     */

    private $attendanceRegularisation;
    private $emailSetting;

    public function __construct(AttendanceRegularisation $attendanceRegularisation)
    {
        $this->attendanceRegularisation = $attendanceRegularisation;
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
    public function via($notifiable)
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
        $url = route('attendances.index');
        $url = getDomainSpecificUrl($url, $this->company);

        $content = __('clan.attendance.requestAccepted');

        $content = __('clan.attendance.requestAccepted') . "<br><br>" .
        __('app.employee') . ':  ' . $this->attendanceRegularisation->user->name . "<br>" .
        __('app.date') . ':  ' . Carbon::parse($this->attendanceRegularisation->date)->translatedFormat($this->company->date_format);


        return $build
            ->subject(__('clan.attendance.attendanceRegularisationAccepted') . ' - ' . config('app.name') . '.')
            ->markdown('mail.email', [
                'url' => $url,
                'content' => $content,
                'themeColor' => $this->company?->header_color,
                'actionText' => __('clan.attendance.viewAttendance'),
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
            'id' => $this->attendanceRegularisation->id,
            'created_at' => $this->attendanceRegularisation->created_at->format('Y-m-d H:i:s'),
            'heading' => $this->attendanceRegularisation->user->name
        ];
    }
}
