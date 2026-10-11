<x-cards.notification :notification="$notification" :link="route('attendances.by_request').'?status=reject'"
    :image="user()->image_url"
    :title="__('clan.attendance.attendanceRegularisationRejected')" :text="$notification->data['heading']"
    :time="$notification->created_at"/>
