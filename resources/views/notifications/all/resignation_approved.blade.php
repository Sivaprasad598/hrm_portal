@php
    use App\Models\User;$notificationUser = User::find($notification->data['user_id']);
@endphp

@if ($notificationUser)
    <x-cards.notification :notification="$notification" :link="route('resignations.index')"
                          :image="$notificationUser->image_url"
                          title="Your Resignation Has Been Approved"
                          :text="'Your resignation request has been approved. Your notice period is ' . ($notification->data['notice_period_label'] ?? ($notification->data['last_working_date'] ?? '--')) . '.'"
                          :time="$notification->created_at"/>
@endif
