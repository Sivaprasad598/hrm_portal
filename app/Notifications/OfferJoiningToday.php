<?php

namespace App\Notifications;

/**
 * Someone with an offer letter joins today (offer-letters:joining-reminders):
 * the bell only, for HR / the administrators and whoever issued the letter.
 */
class OfferJoiningToday extends BaseNotification
{

    public function __construct(private object $letter)
    {
        $this->company = \App\Models\Company::find($letter->company_id);
    }

    public function via($notifiable)
    {
        return ['database'];
    }

    //phpcs:ignore
    public function toArray($notifiable)
    {
        return [
            'offer_letter_id' => $this->letter->id,
            'name' => $this->letter->person_name,
            'designation' => $this->letter->designation,
            'location' => $this->letter->location,
            'reference_no' => $this->letter->reference_no,
            'joining_date' => $this->letter->joining_date,
        ];
    }

}
