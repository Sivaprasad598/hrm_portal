<x-cards.notification :notification="$notification" :link="\App\Support\ModulePresence::url('events.show', $notification->data['id'])"
    :image="user()->image_url" :title="__('email.newEvent.subject')"
    :text="$notification->data['event_name']" :time="$notification->created_at"/>

