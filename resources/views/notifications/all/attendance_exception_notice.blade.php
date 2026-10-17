<x-cards.notification :notification="$notification" :link="route('attendances.index')"
                      image="" :title="'Your attendance on ' . \Carbon\Carbon::parse($notification->data['date'])->format('D d M')"
                      :text="\Illuminate\Support\Str::limit($notification->data['message'], 90)"
                      :time="$notification->created_at"/>
