@php
    $titles = ['invited' => 'Interview scheduled', 'rescheduled' => 'Interview rescheduled', 'cancelled' => 'Interview cancelled', 'feedback' => 'Scorecard needed'];
    $start = isset($notification->data['start']) ? \Carbon\Carbon::parse($notification->data['start'])->tz(company()->timezone)->format(company()->date_format . ' ' . company()->time_format) : '';
@endphp
<x-cards.notification :notification="$notification"
                      :link="route('recruit-interviews.feedback', $notification->data['id'])"
                      :image="user()->image_url"
                      :title="$titles[$notification->data['type'] ?? 'invited'] ?? 'Interview'"
                      :text="($notification->data['candidate'] ?? '') . ' · ' . ($notification->data['round'] ?? '') . ($start ? ' · ' . $start : '')"
                      :time="$notification->created_at"/>
