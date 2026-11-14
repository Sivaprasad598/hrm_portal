<?php

namespace AppModules\Messages\Listeners;

use AppModules\Messages\Events\NewChatEvent;
use App\Models\User;
use AppModules\Messages\Notifications\NewChat;
use App\Scopes\ActiveScope;
use Illuminate\Support\Facades\Notification;

class NewChatListener
{

    /**
     * Handle the event.
     *
     * @param NewChatEvent $event
     * @return void
     */

    public function handle(NewChatEvent $event)
    {
        $notifyUser = User::withoutGlobalScope(ActiveScope::class)->findOrFail($event->userChat->user_id);
        Notification::send($notifyUser, new NewChat($event->userChat));
    }

}
