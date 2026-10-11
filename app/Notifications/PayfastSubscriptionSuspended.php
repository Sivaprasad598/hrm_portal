<?php

namespace App\Notifications;

use App\Models\Invoice;
use App\Models\RecurringInvoice;

class PayfastSubscriptionSuspended extends BaseNotification
{

    private Invoice $invoice;
    private RecurringInvoice $recurring;

    public function __construct(Invoice $invoice, RecurringInvoice $recurring)
    {
        $this->invoice   = $invoice;
        $this->recurring = $recurring;
        $this->company   = $invoice->company;
    }

    public function via($notifiable): array
    {
        return ($notifiable->email_notifications && $notifiable->email != '')
            ? ['mail', 'database']
            : ['database'];
    }

    public function toMail($notifiable)
    {
        $build = parent::build($notifiable);

        $url = getDomainSpecificUrl(
            route('recurring-invoices.show', $this->recurring->id),
            $this->company
        );

        $clientName = optional($this->recurring->client)->name ?? '--';

        $content = __('email.payfastSubscriptionSuspended.text')
            . '<br>' . __('app.client') . ': ' . $clientName
            . '<br>' . __('app.invoiceNumber') . ': ' . $this->invoice->invoice_number
            . '<br>' . __('app.amount') . ': ' . $this->invoice->currency->currency_symbol . number_format($this->invoice->due_amount, 2)
            . '<br>' . __('email.payfastSubscriptionSuspended.failedAttempts') . ': 2';

        $build
            ->subject(__('email.payfastSubscriptionSuspended.subject') . ' - ' . config('app.name'))
            ->markdown('mail.email', [
                'url'            => $url,
                'content'        => $content,
                'themeColor'     => $this->company->header_color,
                'actionText'     => __('email.payfastSubscriptionSuspended.action'),
                'notifiableName' => $notifiable->name,
            ]);

        parent::resetLocale();

        return $build;
    }

    public function toArray($notifiable): array
    {
        return [
            'id'               => $this->recurring->id,
            'invoice_number'   => $this->invoice->invoice_number,
            'type'             => 'payfast_subscription_suspended',
        ];
    }

}
