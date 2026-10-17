<x-cards.notification :notification="$notification" :link="route('biometric.review', ['date' => $notification->data['date']])"
                      image="" :title="'Attendance review: ' . \Carbon\Carbon::parse($notification->data['date'])->format('D d M')"
                      :text="$notification->data['exceptions'] . ' exception(s), ' . ($notification->data['absent'] ?? 0) . ' absent without leave'"
                      :time="$notification->created_at"/>
