<x-cards.notification :notification="$notification" :link="route('biometric.exceptions', ['type' => 'exit_not_on_device'])"
                      :image="user()->image_url"
                      :title="($notification->data['name'] ?? 'Someone') . ' clocked out, no device exit'"
                      :text="\Carbon\Carbon::parse($notification->data['date'])->format('D d M') . ' · out at ' . ($notification->data['clock_out'] ?? '?') . ' on ' . ($notification->data['where'] ?? 'the website') . ' · no exit punch'"
                      :time="$notification->created_at"/>
