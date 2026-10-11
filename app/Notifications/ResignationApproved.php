<?php

namespace App\Notifications;

use App\Models\Resignation;

class ResignationApproved extends BaseNotification
{

    private $resignation;

    public function __construct(Resignation $resignation)
    {
        $this->resignation = $resignation;
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

        $dateFormat = $this->company->date_format ?? 'd-m-Y';
        $lastWorkingDate = $this->resignation->last_working_date
            ? \Carbon\Carbon::parse($this->resignation->last_working_date)->translatedFormat($dateFormat)
            : '-';
        $noticePeriodLabel = Resignation::noticePeriodLabel($this->resignation->notice_period_id, $this->resignation->notice_period_days);

        $content = __('email.resignationApproved.text') . '<br>'
            . 'Your notice period is ' . $noticePeriodLabel . '.<br>'
            . 'Last Working Date:- ' . $lastWorkingDate;

        $build
            ->subject(__('email.resignationApproved.subject') . ' ' . config('app.name') . '.')
            ->markdown('mail.email', [
                'url' => $url,
                'content' => $content,
                'themeColor' => $this->company->header_color,
                'actionText' => __('email.resignationApproved.action'),
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
            'last_working_date' => $this->resignation->last_working_date,
            'notice_period_label' => Resignation::noticePeriodLabel($this->resignation->notice_period_id, $this->resignation->notice_period_days),
        ];
    }

}
