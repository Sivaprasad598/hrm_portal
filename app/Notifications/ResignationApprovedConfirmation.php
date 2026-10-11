<?php

namespace App\Notifications;

use App\Models\Resignation;

class ResignationApprovedConfirmation extends BaseNotification
{

    private $resignation;

    public function __construct(Resignation $resignation)
    {
        $this->resignation = $resignation;
        $this->company = $resignation->company;
    }

    /**
     * Confirmation that an admin action succeeded — surfaced in the
     * notification bell only, no email.
     *
     * @param mixed $notifiable
     * @return array
     */
    public function via($notifiable)
    {
        return ['database'];
    }

    /**
     * @param mixed $notifiable
     * @return array
     */
    //phpcs:ignore
    public function toArray($notifiable)
    {
        return [
            'id' => $this->resignation->id,
            'user_id' => $this->resignation->user_id,
            'employee_name' => $this->resignation->employee->name,
        ];
    }

}
