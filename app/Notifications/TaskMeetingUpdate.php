<?php

namespace App\Notifications;

use App\Models\Event;
use App\Models\User;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * Task meeting notifications. Types:
 * - invited:     to attendees when a meeting is requested
 * - rescheduled: to attendees when the time / place changes
 * - cancelled:   to attendees
 * - declined:    to the organiser when an attendee declines
 * - minutes:     to attendees when the outcome and follow-up actions are recorded
 */
class TaskMeetingUpdate extends BaseNotification
{

    private Event $meeting;
    private string $type;
    private ?User $actor;

    public function __construct(Event $meeting, string $type, ?User $actor = null)
    {
        $this->meeting = $meeting;
        $this->type = $type;
        $this->actor = $actor;
        $this->company = $meeting->company;
    }

    public function via($notifiable)
    {
        $via = ['database'];

        if ($notifiable->email_notifications && $notifiable->email != '') {
            $via[] = 'mail';
        }

        return $via;
    }

    public function toMail($notifiable): MailMessage
    {
        $build = parent::build($notifiable);
        $task = $this->meeting->task;
        $url = getDomainSpecificUrl(route('tasks.show', $this->meeting->task_id) . '?view=meetings', $this->company);
        $timezone = $this->company->timezone;

        $lines = [
            __('email.taskMeeting.' . $this->type . 'Text', ['name' => $this->actor?->name ?? '--']),
            __('app.task') . ': <strong>' . e($task?->heading) . '</strong>',
            __('modules.tasks.meetingWhen') . ': ' . $this->meeting->start_date_time->copy()->tz($timezone)->translatedFormat($this->company->date_format . ' ' . $this->company->time_format)
                . ' - ' . $this->meeting->end_date_time->copy()->tz($timezone)->translatedFormat($this->company->time_format),
        ];

        if ($this->meeting->event_link) {
            $lines[] = __('modules.tasks.meetingLink') . ': <a href="' . e($this->meeting->event_link) . '">' . e($this->meeting->event_link) . '</a>';
        }
        elseif ($this->meeting->where) {
            $lines[] = __('modules.tasks.meetingLocation') . ': ' . e($this->meeting->where);
        }

        if ($this->type == 'minutes' && $this->meeting->meeting_outcome) {
            $lines[] = __('modules.tasks.meetingOutcome') . ':<br>' . nl2br(e($this->meeting->meeting_outcome));
        }
        elseif ($this->meeting->description && in_array($this->type, ['invited', 'rescheduled'])) {
            $lines[] = __('modules.tasks.meetingAgenda') . ':<br>' . nl2br(e(strip_tags($this->meeting->description)));
        }

        $build
            ->subject(__('email.taskMeeting.' . $this->type . 'Subject', ['meeting' => $this->meeting->event_name]) . ' - ' . config('app.name'))
            ->markdown('mail.email', [
                'url' => $url,
                'content' => implode('<br>', $lines),
                'themeColor' => $this->company->header_color,
                'actionText' => __('email.taskMeeting.action'),
                'notifiableName' => $notifiable->name,
            ]);

        parent::resetLocale();

        return $build;
    }

    // phpcs:ignore
    public function toArray($notifiable)
    {
        return [
            'id' => $this->meeting->task_id,
            'event_id' => $this->meeting->id,
            'type' => $this->type,
            'heading' => $this->meeting->event_name,
            'start' => $this->meeting->start_date_time->toIso8601String(),
            'user_id' => $this->actor?->id,
        ];
    }

}
