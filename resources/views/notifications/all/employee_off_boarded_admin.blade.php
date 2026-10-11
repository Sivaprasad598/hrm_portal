@php
    use App\Models\User;$notificationUser = User::withoutGlobalScopes()->find($notification->data['user_id']);
@endphp

@if ($notificationUser)
    <x-cards.notification :notification="$notification" :link="route('employees.show', $notification->data['user_id'])"
                          :image="$notification->data['image_url']"
                          :title="$notification->data['name']"
                          text="Off-boarded"
                          :time="$notification->created_at"/>
@endif
