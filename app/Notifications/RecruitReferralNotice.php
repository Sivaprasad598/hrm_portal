<?php

namespace App\Notifications;

use App\Models\Candidate;
use App\Models\Company;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * To the employee who referred a candidate. Only two moments, and no stage,
 * score or interview detail in between (the referrer has no recruitment access):
 * - added:  HR has recorded the referral
 * - joined: the candidate has joined as an employee
 */
class RecruitReferralNotice extends BaseNotification
{

    private const SUBJECTS = [
        'added' => 'Your referral :candidate has been received',
        'joined' => 'Your referral :candidate has joined',
    ];

    private const INTROS = [
        'added' => 'Thank you for referring :candidate. HR has added them to the hiring process:position.',
        'joined' => 'Good news: :candidate, whom you referred, has joined the company:position. Thank you for the referral.',
    ];

    public function __construct(private Candidate $candidate, private string $type, private ?string $position = null)
    {
        $this->company = Company::find($candidate->company_id);
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
        return __(self::INTROS[$this->type], [
            'candidate' => $this->candidate->name,
            'position' => $this->position ? ($this->type === 'joined' ? ' as ' : ' for ') . $this->position : '',
        ]);
    }

    public function toMail($notifiable): MailMessage
    {
        $build = parent::build($notifiable);

        $build->subject(__(self::SUBJECTS[$this->type], ['candidate' => $this->candidate->name]) . ' - ' . config('app.name'))
            ->markdown('mail.email', [
                'url' => null,
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
            'id' => $this->candidate->id,
            'type' => $this->type,
            'candidate' => $this->candidate->name,
            'text' => $this->text(),
        ];
    }

}
