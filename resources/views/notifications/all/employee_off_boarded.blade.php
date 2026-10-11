@php
    use App\Models\User;$notificationUser = User::withoutGlobalScopes()->find($notification->data['user_id']);
@endphp

@if ($notificationUser)
    <x-cards.notification :notification="$notification" :link="route('dashboard')"
                          :image="$notificationUser->image_url"
                          title="You Have Been Off-Boarded"
                          :text="$notification->data['offboarding_date'] ? \Carbon\Carbon::parse($notification->data['offboarding_date'])->translatedFormat(company()->date_format) : ''"
                          :time="$notification->created_at"/>
@endif
