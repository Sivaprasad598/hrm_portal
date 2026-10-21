<x-cards.notification :notification="$notification"
                      :link="route('employees.show', $notification->data['user_id']) . '?tab=assets'"
                      :image="user()->image_url"
                      title="Asset assigned to you"
                      :text="($notification->data['asset_name'] ?? '') . ' (' . ($notification->data['asset_code'] ?? '') . ')'"
                      :time="$notification->created_at"/>
