@php
    $d = $notification->data;
    [$title, $text] = match ($d['event'] ?? '') {
        'pending' => ['Salary revision awaiting your approval', ($d['name'] ?? '') . ' · ' . ($d['count'] ?? 0) . ' employee(s) · ' . ($d['detail'] ?? '') . ' approval'],
        'approved' => ['Salary revision approved', ($d['name'] ?? '') . ': the letters are ready'],
        default => ['Salary revision sent back', ($d['name'] ?? '') . (!empty($d['detail']) ? ': ' . $d['detail'] : '')],
    };
@endphp

<x-cards.notification :notification="$notification"
                      :link="route('salary-revisions.show', $d['batch_id'] ?? 0)"
                      :image="user()->image_url"
                      :title="$title"
                      :text="$text"
                      :time="$notification->created_at"/>
