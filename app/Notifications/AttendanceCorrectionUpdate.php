<?php

namespace App\Notifications;

/**
 * Attendance correction steps, in the bell only (never e-mail): your request
 * moved on to HR, or a request you approved was decided.
 */
class AttendanceCorrectionUpdate extends BaseNotification
{

    public function __construct(private \App\Models\AttendanceRegularisation $request, private string $event, private ?string $detail = null)
    {
        $this->company = \App\Models\Company::find($request->company_id);
    }

    public function via($notifiable)
    {
        return ['database'];
    }

    //phpcs:ignore
    public function toArray($notifiable)
    {
        return [
            'id' => $this->request->id,
            'user_id' => $this->request->user_id,
            'name' => \App\Models\User::withoutGlobalScopes()->where('id', $this->request->user_id)->value('name'),
            'date' => \Carbon\Carbon::parse($this->request->date)->format('d M Y'),
            'event' => $this->event,     // approved_step | decided
            'detail' => $this->detail,
        ];
    }

}
