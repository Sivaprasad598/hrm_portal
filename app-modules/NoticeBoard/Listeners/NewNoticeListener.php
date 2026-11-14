<?php

namespace AppModules\NoticeBoard\Listeners;

use AppModules\NoticeBoard\Events\NewNoticeEvent;
use AppModules\NoticeBoard\Notifications\NewNotice;
use AppModules\NoticeBoard\Notifications\NoticeUpdate;
use Illuminate\Support\Facades\Notification;

class NewNoticeListener
{

    /**
     * Handle the event.
     *
     * @param NewNoticeEvent $event
     * @return void
     */

    public function handle(NewNoticeEvent $event)
    {
        if (isset($event->action) && $event->action == 'update') {
            Notification::send($event->notifyUser, new NoticeUpdate($event->notice));
        }

        else {
            Notification::send($event->notifyUser, new NewNotice($event->notice));
        }
    }

}
