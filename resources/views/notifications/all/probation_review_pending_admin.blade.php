<x-cards.notification :notification="$notification" :link="route('probation-requests.index')"
                      :image="$notification->data['image_url']" :title="$notification->data['name']"
                      :text="__('email.probationReviewPendingAdmin.subject')"
                      :time="$notification->created_at"/>
