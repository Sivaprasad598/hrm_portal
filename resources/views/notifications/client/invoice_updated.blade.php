<x-cards.notification :notification="$notification"  :link="\App\Support\ModulePresence::url('invoices.show', $notification->data['id'])" :image="company()->logo_url"
    :title="__('email.invoice.updateSubject')" :text="$notification->data['invoice_number']"
    :time="$notification->created_at" />
