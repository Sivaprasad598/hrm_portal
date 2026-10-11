<?php

namespace App\Notifications;

use App\Models\TaskBlocker;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * Roadblock notifications. One class, five types:
 * - raised:    to the teammate the task is waiting on
 * - reminder:  daily, to the teammate, while the roadblock is open
 * - escalated: to the project admin / reporting manager once the needed-by date has passed
 * - resolved:  to the person who raised it and the task assignees
 * - cancelled: to the teammate, when the roadblock is no longer needed
 */
class TaskBlockerUpdate extends BaseNotification
{

    public const TYPES = ['raised', 'reminder', 'escalated', 'resolved', 'cancelled'];

    private TaskBlocker $blocker;
    private string $type;

    public function __construct(TaskBlocker $blocker, string $type)
    {
        $this->blocker = $blocker;
        $this->type = $type;
        $this->company = $blocker->task->company;
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
        $task = $this->blocker->task;
        $url = getDomainSpecificUrl(route('tasks.show', $task->id) . '?view=roadblocks', $this->company);

        $lines = [
            __('email.taskBlocker.' . $this->type . 'Text', [
                'raisedBy' => $this->blocker->raisedBy?->name ?? '--',
                'blockingUser' => $this->blocker->blockingUser?->name ?? '--',
            ]),
            __('app.task') . ': <strong>' . e($task->heading) . '</strong>' . ($task->task_short_code ? ' (#' . e($task->task_short_code) . ')' : ''),
            __('modules.tasks.roadblockNeed') . ': ' . nl2br(e($this->blocker->need)),
        ];

        if ($this->blocker->needed_by) {
            $lines[] = __('modules.tasks.roadblockNeededBy') . ': ' . $this->blocker->needed_by->translatedFormat($this->company->date_format);
        }

        if ($this->blocker->urgency == 'urgent') {
            $lines[] = '<strong style="color:#d21010">' . __('modules.tasks.roadblockUrgent') . '</strong>';
        }

        if ($this->type == 'resolved' && $this->blocker->resolution_note) {
            $lines[] = __('modules.tasks.roadblockResolutionNote') . ': ' . nl2br(e($this->blocker->resolution_note));
        }

        $build
            ->subject($this->subject() . ' - ' . config('app.name'))
            ->markdown('mail.email', [
                'url' => $url,
                'content' => implode('<br>', $lines),
                'themeColor' => $this->company->header_color,
                'actionText' => __('email.taskBlocker.action'),
                'notifiableName' => $notifiable->name,
            ]);

        parent::resetLocale();

        return $build;
    }

    // phpcs:ignore
    public function toArray($notifiable)
    {
        return [
            'id' => $this->blocker->task_id,
            'blocker_id' => $this->blocker->id,
            'type' => $this->type,
            'heading' => $this->blocker->task->heading,
            'urgency' => $this->blocker->urgency,
            'user_id' => $this->type == 'resolved' ? $this->blocker->blocking_user_id : $this->blocker->raised_by,
        ];
    }

    private function subject(): string
    {
        $prefix = $this->blocker->urgency == 'urgent' && in_array($this->type, ['raised', 'reminder', 'escalated']) ? '[' . __('modules.tasks.roadblockUrgent') . '] ' : '';

        return $prefix . __('email.taskBlocker.' . $this->type . 'Subject', ['task' => $this->blocker->task->heading]);
    }

}
