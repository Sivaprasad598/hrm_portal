<?php

namespace App\Notifications;

use App\Models\Resignation;

class ResignationDecisionUndone extends BaseNotification
{

    private $resignation;
    private $previousDecision;

    public function __construct(Resignation $resignation, string $previousDecision)
    {
        $this->resignation = $resignation;
        $this->previousDecision = $previousDecision;
        $this->company = $resignation->company;
    }

    /**
     * @param mixed $notifiable
     * @return array
     */
    public function via($notifiable)
    {
        return ['database', 'mail'];
    }

    /**
     * @param mixed $notifiable
     * @return \Illuminate\Notifications\Messages\MailMessage
     */
    public function toMail($notifiable)
    {
        $build = parent::build($notifiable);

        $url = route('resignations.index');
        $url = getDomainSpecificUrl($url, $this->company);

        $content = __('email.resignationDecisionUndone.text');

        $build
            ->subject(__('email.resignationDecisionUndone.subject') . ' ' . config('app.name') . '.')
            ->markdown('mail.email', [
                'url' => $url,
                'content' => $content,
                'themeColor' => $this->company->header_color,
                'actionText' => __('email.resignationDecisionUndone.action'),
                'notifiableName' => $notifiable->name
            ]);

        parent::resetLocale();

        return $build;
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
            'previous_decision' => $this->previousDecision,
        ];
    }

}
