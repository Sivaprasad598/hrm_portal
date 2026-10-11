<?php

namespace App\Notifications;

use App\Models\Designation;
use App\Models\EmailNotificationSetting;
use App\Models\Promotion;
use App\Models\Team;

class PromotionUpdated extends BaseNotification
{

    private $promotion;
    private $emailSetting;
    protected $previousDesignation;
    protected $previousDepartment;
    protected $previousDesignationId;
    protected $previousDepartmentId;

    /**
     * Create a new notification instance.
     */
    public function __construct(Promotion $promotion, $previousDesignationId, $previousDepartmentId)
    {
        $this->promotion = $promotion;
        $this->previousDesignationId = $previousDesignationId;
        $this->previousDepartmentId = $previousDepartmentId;

        $this->previousDesignation = Designation::find($this->previousDesignationId);
        $this->previousDepartment = Team::find($this->previousDepartmentId);

        $this->company = $this->promotion->company;
        $this->emailSetting = EmailNotificationSetting::where('company_id', $this->promotion->company_id)->where('slug', 'event-notification')->first();
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        $via = ['database'];

        if ($this->emailSetting->send_email == 'yes' && $notifiable->email_notifications && $notifiable->email != '') {
            array_push($via, 'mail');
        }

        if ($this->emailSetting->send_slack == 'yes' && $this->company->slackSetting->status == 'active') {
            $this->slackUserNameCheck($notifiable) ? array_push($via, 'slack') : null;
        }

        return $via;
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail($notifiable)
    {
        $currentDesignation = $this->promotion->currentDesignation->name;
        $previousDesignation = $this->previousDesignation->name;

        $currentDepartment = $this->promotion->currentDepartment->team_name;
        $previousDepartment = $this->previousDepartment->team_name;

        if($this->promotion->promotion == 1){
            $subject = '🚀 ' . __('email.incrementPromotion.subject') . ' ' . $notifiable->name;
        }else{
            $subject = '🚀 ' . __('email.decrementPromotion.subject') . ' ' . $notifiable->name;
        }

        if ($currentDesignation == $previousDesignation) {
            $designation = $currentDesignation;
        }
        else {
            $designation = __('app.from'). ' <b>' .$previousDesignation . ' </b>' . __('app.to') . ' <b>' . $currentDesignation. ' </b>';
        }

        if ($currentDepartment == $previousDepartment) {
            if($this->promotion->promotion == 1){
                $department = __('email.incrementPromotion.in') . ' ' . __('email.incrementPromotion.same');
            }else{
                $department = __('email.decrementPromotion.in') . ' ' . __('email.decrementPromotion.same');
            }
        }
        else {
            $department = __('app.from') . ' <b>' .$previousDepartment . ' </b>' . __('app.to') . ' <b>' . $currentDepartment. ' </b>';
        }

        if($this->promotion->promotion == 1){
            $content = __('email.incrementPromotion.updateText') . ' ' . $designation . ' ' .
                $department . ' ' . __('app.menu.teams') . '! 🎊 <br><br>' .
                __('email.incrementPromotion.text2') . '<br><br>';
        }else{
            $content = __('email.decrementPromotion.updateText') . ' ' . $designation . ' ' .
                $department . ' ' . __('app.menu.teams') . '! <br><br>' .
                __('email.decrementPromotion.text2') . '<br><br>';
        }

        $build = parent::build($notifiable);
        $build->subject($subject)
            ->markdown('mail.email', [
                'content' => $content,
                'themeColor' => $this->company->header_color,
                'notifiableName' => $notifiable->name
            ]);

        parent::resetLocale();

        return $build;
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $currentDesignation = $this->promotion->currentDesignation->name;
        $previousDesignation = $this->previousDesignation->name;
        $currentDepartment = $this->promotion->currentDepartment->team_name;
        $previousDepartment = $this->previousDepartment->team_name;

        if ($currentDesignation == $previousDesignation) {
            $designation = $currentDesignation;
        }
        else {
            $designation = __('app.from'). ' <b>' .$previousDesignation . ' </b>' . __('app.to') . ' <b>' . $currentDesignation. ' </b>';
        }

        if ($currentDepartment == $previousDepartment) {
            if($this->promotion->promotion == 1){
                $department = __('email.incrementPromotion.in') . ' ' . __('email.incrementPromotion.same');
            }else{
                $department = __('email.decrementPromotion.in') . ' ' . __('email.decrementPromotion.same');
            }
        }
        else {
            $department = __('app.from') .' <b>' .$previousDepartment . ' </b>' . __('app.to') . ' <b>' . $currentDepartment. ' </b>';
        }

        if($this->promotion->promotion == 1){
            $subject = __('email.incrementPromotion.subject');
            $content = __('email.incrementPromotion.updateText') . ' ' . $designation . ' ' . $department . ' ' . __('app.menu.teams') . '! 🎊';
        }else{
            $subject = __('email.decrementPromotion.subject');
            $content = __('email.decrementPromotion.updateText') . ' ' . $designation . ' ' . $department . ' ' . __('app.menu.teams') . '!';
        }

        return [
            'id' => $this->promotion->id,
            'created_at' => $this->promotion->created_at->format('Y-m-d H:i:s'),
            'title' => $subject . ' ' . $notifiable->name,
            'heading' => $content,
        ];
    }

    public function toSlack($notifiable)
    {
        try {
            $currentDesignation = $this->promotion->currentDesignation->name;
            $previousDesignation = $this->previousDesignation->name;
            $currentDepartment = $this->promotion->currentDepartment->team_name;
            $previousDepartment = $this->previousDepartment->team_name;

            $notifiableName = __('email.hello') . ' ' . $notifiable->name;
            if($this->promotion->promotion == 1){
                $subject = '🚀 ' . __('email.incrementPromotion.subject') . ' ' . $notifiable->name;
            }else{
                $subject = '🚀 ' . __('email.decrementPromotion.subject') . ' ' . $notifiable->name;
            }

            if ($currentDesignation == $previousDesignation) {
                $designation = $currentDesignation;
            }
            else {
                $designation = __('app.from'). ' *' .$previousDesignation . '* ' . __('app.to') . ' *' . $currentDesignation. '* ';
            }

            if ($currentDepartment == $previousDepartment) {
                if($this->promotion->promotion == 1){
                    $department = __('email.incrementPromotion.in') . ' ' . __('email.incrementPromotion.same');
                }else{
                    $department = __('email.decrementPromotion.in') . ' ' . __('email.decrementPromotion.same');
                }
            }
            else {
                $department = __('app.from') . ' *' .$previousDepartment . '* ' . __('app.to') . ' *' . $currentDepartment. '* ';
            }

            if($this->promotion->promotion == 1){
                $content = __('email.incrementPromotion.updateText') . ' ' . $designation . ' ' . $department . ' ' . __('app.menu.teams') . '! 🎊 ' . "\n\n" .
                    __('email.incrementPromotion.text2') . "\n\n";
            }else{
                $content = __('email.decrementPromotion.updateText') . ' ' . $designation . ' ' . $department . ' ' . __('app.menu.teams') . '!' . "\n\n" .
                    __('email.decrementPromotion.text2') . "\n\n";
            }

            return $this->slackBuild($notifiable)
                ->content($subject . "\n\n" . $notifiableName . "\n\n" . $content);

        } catch (\Exception $e) {
            if($this->promotion->promotion == 1){
                return $this->slackRedirectMessage('email.incrementPromotion.subject', $notifiable);
            }else{
                return $this->slackRedirectMessage('email.decrementPromotion.subject', $notifiable);
            }
        }
    }

}
