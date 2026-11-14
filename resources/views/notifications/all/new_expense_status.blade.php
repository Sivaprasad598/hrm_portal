@php
    use App\Models\User;$notificationUser = User::find($notification->data['user_id']);
@endphp

@if ($notificationUser)
    <x-cards.notification :notification="$notification" :link="\App\Support\ModulePresence::url('expenses.show', $notification->data['id'])"
                          :image="$notificationUser->image_url"
                          :title="__('email.expenseStatus.subject')" :text="$notification->data['item_name']"
                          :time="$notification->created_at"/>
@endif
