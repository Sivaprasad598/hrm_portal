<?php

namespace App\Notifications;

use App\Models\JobOffer;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * To HR: the candidate answered the offer themselves, from the link in the offer
 * email (App\Services\Recruit\OfferReply).
 */
class RecruitOfferReplyNotice extends BaseNotification
{

    public function __construct(private JobOffer $offer, private string $answer)
    {
        $this->company = $offer->company;
    }

    public function via($notifiable)
    {
        $via = ['database'];

        if ($notifiable->email_notifications && $notifiable->email != '') {
            $via[] = 'mail';
        }

        return $via;
    }

    private function text(): string
    {
        $candidate = $this->offer->application->candidate;

        return $candidate->name . ($this->answer === 'accepted' ? ' accepted' : ' declined') . ' the offer'
            . ($this->offer->reference_no ? ' ' . $this->offer->reference_no : '') . ' online'
            . ($this->answer === 'accepted' ? '. They can now be registered as an employee.' : '.');
    }

    public function toMail($notifiable): MailMessage
    {
        $build = parent::build($notifiable);

        $build->subject(($this->answer === 'accepted' ? 'Offer accepted' : 'Offer declined') . ' - ' . $this->offer->application->candidate->name)
            ->markdown('mail.email', [
                'url' => route('candidates.show', $this->offer->application->candidate_id),
                'actionText' => 'Open the candidate',
                'content' => e($this->text()),
                'themeColor' => $this->company?->header_color,
                'notifiableName' => $notifiable->name,
            ]);

        parent::resetLocale();

        return $build;
    }

    // phpcs:ignore
    public function toArray($notifiable)
    {
        return [
            'id' => $this->offer->application->candidate_id,
            'answer' => $this->answer,
            'candidate' => $this->offer->application->candidate->name,
            'text' => $this->text(),
        ];
    }

}
