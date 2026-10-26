@php
    $d = $notification->data;
    $what = ($d['days'] ?? 1) > 1 ? ($d['days'] . ' days from ' . ($d['from'] ?? '')) : ($d['from'] ?? '');
    [$title, $text] = ($d['event'] ?? '') === 'waiting'
        ? ['Leave waiting for your approval', ($d['name'] ?? '') . ' · ' . $what . ' · ' . ($d['detail'] ?? '') . ' step']
        : ['Your leave moved on', $what . ': ' . ($d['detail'] ?? '')];
@endphp

<x-cards.notification :notification="$notification"
                      :link="route('leaves.show', $d['leave_id'] ?? 0)"
                      :image="user()->image_url"
                      :title="$title"
                      :text="$text"
                      :time="$notification->created_at"/>
