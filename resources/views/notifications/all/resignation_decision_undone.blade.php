@php
    use App\Models\User;$notificationUser = User::find($notification->data['user_id']);
@endphp

@if ($notificationUser)
    <x-cards.notification :notification="$notification" :link="route('resignations.index')"
                          :image="$notificationUser->image_url"
                          title="Your Resignation Is Pending Review Again"
                          :text="'Previous decision (' . ucfirst($notification->data['previous_decision']) . ') has been reversed'"
                          :time="$notification->created_at"/>
@endif
