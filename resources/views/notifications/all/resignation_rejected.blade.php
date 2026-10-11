@php
    use App\Models\User;$notificationUser = User::find($notification->data['user_id']);
@endphp

@if ($notificationUser)
    <x-cards.notification :notification="$notification" :link="route('resignations.index')"
                          :image="$notificationUser->image_url"
                          title="Your Resignation Has Been Rejected"
                          :text="$notification->data['remark'] ?? ''"
                          :time="$notification->created_at"/>
@endif
