<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Bell only: asset services, warranties and renewals coming due or overdue
 * (assets:due-reminders). Deliberately no mail channel.
 */
class AssetDueReminder extends Notification
{

    use Queueable;

    /** @param array<int, array{asset_id:int, asset:string, code:string, what:string, due_on:string, days:int}> $items */
    public function __construct(private array $items)
    {
    }

    public function via($notifiable): array
    {
        return ['database'];
    }

    public function toArray($notifiable): array
    {
        return [
            'count' => count($this->items),
            'overdue' => count(array_filter($this->items, fn ($i) => $i['days'] < 0)),
            'items' => array_slice($this->items, 0, 10),
        ];
    }

}
