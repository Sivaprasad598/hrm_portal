<?php

namespace App\Console\Commands;

use App\Events\InvoiceReminderAfterEvent;
use App\Events\InvoiceReminderEvent;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\InvoiceSetting;
use App\Services\InvoiceReminderService;
use Illuminate\Console\Command;

class SendInvoiceReminder extends Command
{

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'send-invoice-reminder';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'send invoice reminder to the client before and after due date of invoice';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        Company::active()->select('id', 'timezone')->with(['currency', 'invoiceSetting'])->chunk(50, function ($companies) {
            foreach ($companies as $company) {
                $invoiceSetting = $company->invoiceSetting ?: InvoiceSetting::where('company_id', $company->id)->first();

                if (!$invoiceSetting) {
                    continue;
                }

                $now = now($company->timezone);

                $invoices = Invoice::whereNotNull('due_date')
                    ->where('status', '!=', 'paid')
                    ->where('status', '!=', 'canceled')
                    ->where('status', '!=', 'draft')
                    ->where('company_id', $company->id)
                    ->with('client')
                    ->get();

                foreach ($invoices as $invoice) {
                    $settings = InvoiceReminderService::resolveForInvoice($invoice, $invoiceSetting);

                    if (!InvoiceReminderService::withinScheduleLimit($invoice, $settings, $now)) {
                        continue;
                    }

                    $notifyUser = $invoice->client;

                    if (is_null($notifyUser)) {
                        continue;
                    }

                    $beforeRules = InvoiceReminderService::dueBeforeRules($invoice, $settings, $now);

                    foreach ($beforeRules as $rule) {
                        if (!InvoiceReminderService::withinScheduleLimit($invoice, $settings, $now)) {
                            break;
                        }

                        event(new InvoiceReminderEvent($invoice, $notifyUser, (int) $rule['value']));
                        InvoiceReminderService::markBeforeSent($invoice, $rule, $notifyUser);
                        $invoice->refresh();
                    }

                    if (InvoiceReminderService::shouldSendAfterReminder($invoice, $settings, $now)) {
                        event(new InvoiceReminderAfterEvent($invoice, $notifyUser, (int) ($settings['after_frequency'] ?? 0)));
                        InvoiceReminderService::markAfterSent($invoice, $notifyUser);
                    }
                }
            }
        });

        return Command::SUCCESS;
    }

}
