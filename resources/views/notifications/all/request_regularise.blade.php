<x-cards.notification :notification="$notification" :link="route('attendances.by_request')"
    :image="user()->image_url"
    :title="__('clan.attendance.attendanceRegularisationCreated')" :text="$notification->data['heading']"
    :time="$notification->created_at"/>
