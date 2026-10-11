@php
    use App\Models\User;$notificationUser = User::find($notification->data['user_id']);
@endphp

@if ($notificationUser)
    <x-cards.notification :notification="$notification" :link="route('resignations.index')"
                          :image="$notificationUser->image_url"
                          title="New Message on Your Resignation" :text="$notification->data['user']['name']"
                          :time="$notification->created_at"/>
@endif
