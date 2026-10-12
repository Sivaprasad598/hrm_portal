{{-- Customers → Meetings: scheduled / 15-minute reminder / ended (opens Meeting Result) --}}
<x-cards.notification :notification="$notification"
                      :link="($notification->data['kind'] ?? '') === 'ended'
                          ? route('customers.meetings.result', $notification->data['id'])
                          : route('customers.index') . '#meetings'"
                      :image="user()->image_url" :title="$notification->data['title'] ?? 'Customer Meeting'"
                      :text="$notification->data['text'] ?? ''" :time="$notification->created_at"/>
