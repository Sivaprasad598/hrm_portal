{{-- Lead Team → Team Chat: opens the lead's Team Chat on the conversation the message is in --}}
@php
    $chatConversation = $notification->data['conversation'] ?? 'group';
    $chatLink = route('lead-contact.show', $notification->data['lead_id'] ?? 0)
        . '?tab=team&view=chat&with=' . urlencode((string) $chatConversation);
@endphp
<x-cards.notification :notification="$notification" :link="$chatLink"
                      :image="user()->image_url" :title="$notification->data['title'] ?? 'Team Chat'"
                      :text="$notification->data['text'] ?? ''" :time="$notification->created_at"/>
