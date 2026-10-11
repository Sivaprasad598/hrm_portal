<?php

namespace App\Notifications;

use App\Http\Controllers\InvoiceController;
use App\Models\GlobalSetting;
use App\Services\InvoiceReminderService;
use Illuminate\Support\Facades\App;
use Illuminate\Support\HtmlString;

class InvoiceReminderAfter extends BaseNotification
{

    private $invoice;
    private $reminderSettings;

    /**
     * Create a new notification instance.
     *
     * @return void
     */
    public function __construct($invoice)
    {
        $this->invoice = $invoice;
        $this->company = $this->invoice->company;
        $this->reminderSettings = InvoiceReminderService::resolveForInvoice($this->invoice);
    }

    /**
     * Get the notification's delivery channels.
     *
     * @param mixed $notifiable
     * @return array
     */
    public function via($notifiable)
    {
        $via = [];

        if ($notifiable->email != '') {
            $via = ['mail'];
        }

        return $via;
    }

    /**
     * Get the mail representation of the notification.
     *
     * @param mixed $notifiable
     * @return \Illuminate\Notifications\Messages\MailMessage
     */
    public function toMail($notifiable)
    {
        $build = parent::build($notifiable);
        $invoice_number = $this->invoice->invoice_number;

        $url = null;
        $actionText = null;

        if (!empty($this->reminderSettings['include_payment_link'])) {
            $url = url()->temporarySignedRoute('front.invoice', now()->addDays(GlobalSetting::SIGNED_ROUTE_EXPIRY), $this->invoice->hash);
            $url = getDomainSpecificUrl($url, $this->company);
            $actionText = __('email.invoiceReminder.action');
        }

        if (!empty($this->reminderSettings['include_pdf'])) {
            $invoiceController = new InvoiceController();

            if ($pdfOption = $invoiceController->domPdfObjectForDownload($this->invoice->id)) {
                $build->attachData($pdfOption['pdf']->output(), $pdfOption['fileName'] . '.pdf');
            }
        }

        App::setLocale($notifiable->locale ?? $this->company->locale ?? 'en');

        $content = __('email.invoiceReminderAfter.text') . ' ' . $this->invoice->due_date->toFormattedDateString() . '<br>' . new HtmlString($invoice_number) . '<br>' . __('email.messages.confirmMessage') . '<br>' . __('email.messages.referenceMessage');

        $build
            ->subject(__('email.invoiceReminder.subject') . ' - ' . config('app.name'))
            ->markdown('mail.email', [
                'url' => $url,
                'content' => $content,
                'themeColor' => $this->company->header_color,
                'actionText' => $actionText,
                'notifiableName' => $notifiable->name
            ]);

        parent::resetLocale();

        return $build;
    }

    /**
     * Get the array representation of the notification.
     *
     * @param mixed $notifiable
     * @return array
     */
    public function toArray($notifiable)
    {
        return $notifiable->toArray();
    }

}
