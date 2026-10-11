@php
    use App\Models\User;$notificationUser = User::find($notification->data['user_id']);
@endphp

@if ($notificationUser)
    <x-cards.notification :notification="$notification" :link="route('resignation-management.show', $notification->data['id'])"
                          :image="$notificationUser->image_url"
                          title="Resignation Approved"
                          :text="'Resignation request for ' . $notification->data['employee_name'] . ' has been approved successfully.'"
                          :time="$notification->created_at"/>
@endif
