@php
    $first = $notification->data['items'][0] ?? null;
    $count = (int) ($notification->data['count'] ?? 0);
    $overdue = (int) ($notification->data['overdue'] ?? 0);
    $text = $first
        ? $first['what'] . ': ' . $first['asset'] . ' (' . $first['code'] . ') '
            . ($first['days'] < 0 ? abs($first['days']) . ' day(s) overdue' : ($first['days'] === 0 ? 'today' : 'in ' . $first['days'] . ' day(s)'))
            . ($count > 1 ? ' and ' . ($count - 1) . ' more' : '')
        : '';
@endphp

<x-cards.notification :notification="$notification"
                      :link="route('assets.index')"
                      :image="user()->image_url"
                      :title="($overdue ? 'Assets overdue: ' : 'Assets coming due: ') . $count"
                      :text="$text"
                      :time="$notification->created_at"/>
