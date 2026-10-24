<?php

namespace App\Notifications;

/**
 * Salary revisions, in the bell only (never e-mail): waiting for your approval,
 * or your revision was approved / sent back.
 */
class SalaryRevisionUpdate extends BaseNotification
{

    public function __construct(private object $batch, private string $event, private ?string $detail = null)
    {
        $this->company = \App\Models\Company::find($batch->company_id);
    }

    public function via($notifiable)
    {
        return ['database'];
    }

    //phpcs:ignore
    public function toArray($notifiable)
    {
        return [
            'batch_id' => $this->batch->id,
            'name' => $this->batch->name,
            'event' => $this->event,       // pending | approved | rejected
            'detail' => $this->detail,     // the approver level, or the reason
            'count' => \Illuminate\Support\Facades\DB::table('salary_revisions')->where('batch_id', $this->batch->id)->count(),
        ];
    }

}
