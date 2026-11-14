<?php

namespace AppModules\Messages\Listeners;

use AppModules\Messages\Events\NewMentionChatEvent;
use AppModules\Messages\Notifications\NewMentionChat;
use Illuminate\Support\Facades\Notification;

class NewMentionChatListener
{

    /**
     * Handle the event.
     *
     * @param NewMentionChatEvent $event
     * @return void
     */

    public function handle(NewMentionChatEvent $event)
    {
        Notification::send($event->notifyUser, new NewMentionChat($event->userChat));
    }

}
