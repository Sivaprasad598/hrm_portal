<x-cards.notification :notification="$notification"  :link="\App\Support\ModulePresence::url('recurring-invoices.show', $notification->data['id'])" :image="company()->logo_url"
    :title="__('email.invoiceRecurringStatus.subject')" :text="$notification->data['event_name']"
    :time="$notification->created_at" />
