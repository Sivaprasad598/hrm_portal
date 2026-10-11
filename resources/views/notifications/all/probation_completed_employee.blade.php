<x-cards.notification :notification="$notification" :link="route('dashboard')"
                      :image="$notification->data['image_url']" :title="__('email.probationCompletedEmployee.subject')"
                      :text="__('modules.dashboard.probationDate')"
                      :time="$notification->created_at"/>
