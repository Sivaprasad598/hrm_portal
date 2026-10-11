<?php

namespace App\Listeners;

use App\Events\ProjectNoteUpdateEvent;
use Illuminate\Support\Facades\Notification;
use App\Notifications\ProjectNoteUpdated;

class ProjectNoteUpdateListener
{
    /**
     * Handle the event.
     *
     * @param ProjectNoteUpdateEvent $event
     * @return void
     */
    public function handle(ProjectNoteUpdateEvent $event)
    {
        Notification::send($event->notifyUser, new ProjectNoteUpdated($event->project, $event->projectNote));
    }
}
