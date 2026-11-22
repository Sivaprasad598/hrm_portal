<?php

namespace App\Notifications;

use App\Models\JobOffer;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * The offer, emailed to the candidate (not a user of the portal: sent with
 * Notification::route('mail', ...)), with the button to accept or decline it
 * online (App\Services\Recruit\OfferReply).
 */
class RecruitOfferMail extends BaseNotification
{

    public function __construct(private JobOffer $offer, private string $link)
    {
        $this->company = $offer->company;
    }

    public function via($notifiable)
    {
        return ['mail'];
    }

    public static function subject(JobOffer $offer): string
    {
        return 'Offer of employment - ' . ($offer->designation?->name ?: $offer->application->positionLabel())
            . ' - ' . ($offer->company?->company_name ?? config('app.name'));
    }

    /** The lines of the email, also kept on the candidate's timeline as what was sent. @return string[] */
    public static function lines(JobOffer $offer): array
    {
        $company = $offer->company?->company_name ?? config('app.name');
        $format = $offer->company?->date_format ?? 'd-m-Y';
        $branch = $offer->branch_id ? \App\Models\Branch::withoutGlobalScopes()->find($offer->branch_id)?->branch_name : null;

        $lines = ['We are pleased to offer you the position of ' . ($offer->designation?->name ?: $offer->application->positionLabel()) . ' at ' . $company . '.'];
        $lines[] = 'Annual CTC: ' . number_format($offer->annual_ctc);
        $lines[] = 'Joining date: ' . $offer->joining_date->format($format);

        if ($branch) {
            $lines[] = 'Location: ' . $branch;
        }

        if ($offer->reference_no) {
            $lines[] = 'Reference: ' . $offer->reference_no;
        }

        if ($offer->valid_until) {
            $lines[] = 'Please let us know by ' . $offer->valid_until->format($format) . '.';
        }

        $lines[] = 'Use the button below to read the offer and accept or decline it. The link is personal to you.';

        return $lines;
    }

    public function toMail($notifiable): MailMessage
    {
        $build = parent::build($notifiable);
        $candidate = $this->offer->application->candidate;

        $build->subject(self::subject($this->offer))
            ->markdown('mail.email', [
                'url' => $this->link,
                'actionText' => 'View and answer the offer',
                'content' => implode('<br>', array_map('e', self::lines($this->offer))),
                'themeColor' => $this->company?->header_color,
                'notifiableName' => $candidate->name,
            ]);

        parent::resetLocale();

        return $build;
    }

}
