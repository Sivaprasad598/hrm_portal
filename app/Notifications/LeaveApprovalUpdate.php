<?php

namespace App\Notifications;

/**
 * Leave approval steps, in the bell only (never e-mail): a leave waiting for
 * your approval, or your leave moved on a step.
 */
class LeaveApprovalUpdate extends BaseNotification
{

    public function __construct(private \App\Models\Leave $leave, private string $event, private ?string $detail = null)
    {
        $this->company = \App\Models\Company::find($leave->company_id);
    }

    public function via($notifiable)
    {
        return ['database'];
    }

    //phpcs:ignore
    public function toArray($notifiable)
    {
        $group = $this->leave->unique_id
            ? \App\Models\Leave::where('unique_id', $this->leave->unique_id)->orderBy('leave_date')->get(['leave_date'])
            : collect([$this->leave]);

        return [
            'leave_id' => $this->leave->unique_id ?: $this->leave->id,
            'user_id' => $this->leave->user_id,
            'name' => \App\Models\User::withoutGlobalScopes()->where('id', $this->leave->user_id)->value('name'),
            'from' => optional($group->first()->leave_date)->format('d M Y'),
            'days' => $group->count(),
            'event' => $this->event,     // waiting | approved_step
            'detail' => $this->detail,   // the step, or what happened
        ];
    }

}
