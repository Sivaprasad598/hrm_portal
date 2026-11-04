@php
    $d = $notification->data;
    [$title, $text] = ($d['event'] ?? '') === 'decided'
        // to the manager who approved it, once HR decides
        ? ['Attendance correction you approved is decided', ($d['name'] ?? '') . ' · ' . ($d['date'] ?? '') . ': ' . ($d['detail'] ?? '')]
        : ['Your attendance correction moved on', ($d['date'] ?? '') . ': ' . ($d['detail'] ?? '')];
@endphp

<x-cards.notification :notification="$notification"
                      :link="route('attendances.by_request')"
                      :image="user()->image_url"
                      :title="$title"
                      :text="$text"
                      :time="$notification->created_at"/>
