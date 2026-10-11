<?php

namespace App\Notifications;

use App\Models\Invoice;
use App\Models\RecurringInvoice;

class PayfastPaymentFailed extends BaseNotification
{

    private Invoice $invoice;
    private RecurringInvoice $recurring;
    private string $failReason;

    public function __construct(Invoice $invoice, RecurringInvoice $recurring, string $failReason = '')
    {
        $this->invoice   = $invoice;
        $this->recurring = $recurring;
        $this->company   = $invoice->company;
        $this->failReason = $failReason;
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
            route('front.invoice', $this->invoice->hash),
            $this->company
        );

        $content = __('email.payfastPaymentFailed.text')
            . '<br>' . __('app.invoiceNumber') . ': ' . $this->invoice->invoice_number
            . '<br>' . __('app.amount') . ': ' . $this->invoice->currency->currency_symbol . number_format($this->invoice->due_amount, 2);

        $build
            ->subject(__('email.payfastPaymentFailed.subject') . ' (' . $this->invoice->invoice_number . ') - ' . config('app.name'))
            ->markdown('mail.email', [
                'url'            => $url,
                'content'        => $content,
                'themeColor'     => $this->company->header_color,
                'actionText'     => __('email.payfastPaymentFailed.action'),
                'notifiableName' => $notifiable->name,
            ]);

        parent::resetLocale();

        return $build;
    }

    public function toArray($notifiable): array
    {
        return [
            'id'             => $this->invoice->id,
            'invoice_number' => $this->invoice->invoice_number,
            'type'           => 'payfast_payment_failed',
        ];
    }

}
