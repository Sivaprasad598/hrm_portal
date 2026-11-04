<x-cards.notification :notification="$notification" :link="route('attendances.by_request') . '?waiting=me'"
    :image="user()->image_url"
    :title="'Attendance correction waiting for your approval'" :text="$notification->data['heading']"
    :time="$notification->created_at"/>
