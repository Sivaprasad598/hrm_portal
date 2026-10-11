@php
    $meetingActor = \App\Models\User::withoutGlobalScopes()->find($notification->data['user_id'] ?? null);
    $meetingStart = isset($notification->data['start'])
        ? \Carbon\Carbon::parse($notification->data['start'])->tz(company()->timezone)->translatedFormat(company()->date_format . ' ' . company()->time_format)
        : '';
@endphp
<x-cards.notification :notification="$notification" :link="route('tasks.show', $notification->data['id']) . '?view=meetings'"
                      :image="$meetingActor ? $meetingActor->image_url : ''"
                      :title="__('email.taskMeeting.' . $notification->data['type'] . 'Title')"
                      :text="$notification->data['heading'] . ($meetingStart ? ' · ' . $meetingStart : '')"
                      :time="$notification->created_at"/>
