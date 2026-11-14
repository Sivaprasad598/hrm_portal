<x-cards.notification :notification="$notification"  :link="\App\Support\ModulePresence::url('orders.show', $notification->data['id'])" :image="company()->logo_url"
    :title="__('email.order.subject')" :text="$notification->data['order_number']"
    :time="$notification->created_at" />
