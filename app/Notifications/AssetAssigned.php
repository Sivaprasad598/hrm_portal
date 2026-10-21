<?php

namespace App\Notifications;

use App\Models\AssetAssignment;

/** To the employee: an asset has been handed to them. */
class AssetAssigned extends BaseNotification
{

    private $assignment;

    public function __construct(AssetAssignment $assignment)
    {
        $this->assignment = $assignment->loadMissing('asset', 'user');
        $this->company = $this->assignment->user->company;
    }

    /**
     * @param mixed $notifiable
     * @return array
     */
    public function via($notifiable)
    {
        return $notifiable->email ? ['database', 'mail'] : ['database'];
    }

    /**
     * @param mixed $notifiable
     * @return \Illuminate\Notifications\Messages\MailMessage
     */
    public function toMail($notifiable)
    {
        $build = parent::build($notifiable);
        $asset = $this->assignment->asset;
        $dateFormat = $this->company->date_format ?? 'd-m-Y';

        $content = 'The following asset has been assigned to you:<br><br>'
            . '<strong>' . e($asset->name) . '</strong> (' . e($asset->asset_code) . ')<br>'
            . ($asset->serial_number ? 'Serial number: ' . e($asset->serial_number) . '<br>' : '')
            . 'Assigned on: ' . $this->assignment->assigned_on->translatedFormat($dateFormat) . '<br>'
            . ($this->assignment->expected_return_on ? 'Return by: ' . $this->assignment->expected_return_on->translatedFormat($dateFormat) . '<br>' : '')
            . '<br>Please take care of it and return it when asked, or when you leave the company.';

        $url = getDomainSpecificUrl(route('employees.show', $notifiable->id) . '?tab=assets', $this->company);

        $build
            ->subject('Asset assigned: ' . $asset->name . ' (' . $asset->asset_code . ')')
            ->markdown('mail.email', [
                'url' => $url,
                'content' => $content,
                'themeColor' => $this->company->header_color,
                'actionText' => 'View my assets',
                'notifiableName' => $notifiable->name,
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
            'assignment_id' => $this->assignment->id,
            'user_id' => $this->assignment->user_id,
            'asset_name' => $this->assignment->asset->name,
            'asset_code' => $this->assignment->asset->asset_code,
        ];
    }

}
