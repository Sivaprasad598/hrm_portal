<x-cards.notification :notification="$notification" :link="route('dashboard')"
                      :image="$notification->data['image_url']" :title="__('email.probationAdminMessage.subject')"
                      :text="$notification->data['message']"
                      :time="$notification->created_at"/>
