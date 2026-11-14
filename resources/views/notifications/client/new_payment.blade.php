<x-cards.notification :notification="$notification"  :link="\App\Support\ModulePresence::url('payments.show', $notification->data['id'])" :image="company()->logo_url"
    :title="__('email.invoices.newPaymentReceived')" :time="$notification->created_at" />
