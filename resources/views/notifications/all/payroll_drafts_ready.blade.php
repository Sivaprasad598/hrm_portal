<x-cards.notification :notification="$notification" :link="route('payslips.generated')"
                      image="" :title="'Draft payslips ready: ' . $notification->data['period']"
                      :text="$notification->data['created'] . ' to review and approve' . (($notification->data['waiting'] ?? 0) ? ' · ' . $notification->data['waiting'] . ' waiting' : '')"
                      :time="$notification->created_at"/>
