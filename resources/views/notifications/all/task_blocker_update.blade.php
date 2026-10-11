@php
    $blockerUser = \App\Models\User::withoutGlobalScopes()->find($notification->data['user_id'] ?? null);
    $urgent = ($notification->data['urgency'] ?? '') == 'urgent' && in_array($notification->data['type'], ['raised', 'reminder', 'escalated']);
@endphp
<x-cards.notification :notification="$notification" :link="route('tasks.show', $notification->data['id']) . '?view=roadblocks'"
                      :image="$blockerUser ? $blockerUser->image_url : ''"
                      :title="($urgent ? '🚩 ' : '') . __('email.taskBlocker.' . $notification->data['type'] . 'Title')"
                      :text="$notification->data['heading']"
                      :time="$notification->created_at"/>
