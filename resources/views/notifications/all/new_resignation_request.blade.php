@php
    use App\Models\User;$notificationUser = User::find($notification->data['user_id']);
@endphp

@if ($notificationUser)
    <x-cards.notification :notification="$notification" :link="route('resignation-management.index')"
                          :image="$notificationUser->image_url"
                          title="New Resignation Request" :text="$notification->data['user']['name']"
                          :time="$notification->created_at"/>
@endif
