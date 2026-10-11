<x-cards.notification :notification="$notification" :link="route('dashboard')"
                      :image="$notification->data['image_url']" :title="__('email.probationReviewPendingEmployee.subject')"
                      :text="__('modules.dashboard.probationDate')"
                      :time="$notification->created_at"/>
