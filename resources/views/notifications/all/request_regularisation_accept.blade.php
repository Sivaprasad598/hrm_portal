<x-cards.notification :notification="$notification" :link="route('attendances.by_request').'?status=accept'"
    :image="user()->image_url"
    :title="__('clan.attendance.attendanceRegularisationAccepted')" :text="$notification->data['heading']"
    :time="$notification->created_at"/>
