<x-cards.notification :notification="$notification" :link="route('biometric.exceptions')"
                      :image="user()->image_url"
                      :title="($notification->data['name'] ?? 'Someone') . ' punched while on leave'"
                      :text="\Carbon\Carbon::parse($notification->data['date'])->format('D d M') . ' · ' . ($notification->data['leave'] ?? 'Leave') . ' leave · ' . ($notification->data['punches'] ?? 0) . ' punch(es)' . (!empty($notification->data['first']) ? ' from ' . $notification->data['first'] : '')"
                      :time="$notification->created_at"/>
