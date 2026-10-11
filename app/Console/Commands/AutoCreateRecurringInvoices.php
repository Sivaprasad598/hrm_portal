<?php

namespace App\Console\Commands;

use App\Helper\Files;
use App\Models\Company;
use App\Models\CompanyAddress;
use App\Models\Currency;
use App\Models\Invoice;
use App\Models\InvoiceItemImage;
use App\Models\InvoiceItems;
use App\Models\RecurringInvoice;
use App\Models\RecurringInvoiceItemImage;
use App\Models\RecurringInvoiceLog;
use App\Models\User;
use App\Notifications\NewInvoiceRecurring;
use App\Notifications\PayfastPaymentFailed;
use App\Notifications\PayfastSubscriptionSuspended;
use App\Scopes\ActiveScope;
use App\Traits\MakePaymentTrait;
use App\Traits\PaymentGatewayTrait;
use App\Traits\UniversalSearchTrait;
use Illuminate\Console\Command;

class AutoCreateRecurringInvoices extends Command
{

    use UniversalSearchTrait, MakePaymentTrait, PaymentGatewayTrait;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'recurring-invoice-create';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'auto create recurring invoices ';

    /**
     * Execute the console command.
     *
     * @return mixed
     */

    public function handle()
    {


        $recurringInvoices = RecurringInvoice::with(['recurrings'])
            ->where('status', 'active')
            ->whereNotNull('next_invoice_date')
            ->get();

        // Check the count of recurringInvoices and return from here
        if ($recurringInvoices->isEmpty()) {
            $this->info('No Recurring invoice in database');

            return Command::SUCCESS;
        }

        foreach ($recurringInvoices as $recurring) {

            $company = $recurring->company;

            $this->info('Running for company:' . $company->id);

            $totalExistingCount = $recurring->recurrings->count();

            if ($recurring->unlimited_recurring == 1 || ($totalExistingCount < $recurring->billing_cycle)) {

                if ($recurring->issue_date->timezone($company->timezone)->isToday() && $totalExistingCount == 0) {
                    $this->invoiceCreate($recurring);
                    $this->saveNextInvoiceDate($recurring);
                }
                if ($recurring->next_invoice_date->timezone($company->timezone)->isToday()) {

                    $numIterations = $recurring->next_invoice_date->timezone($company->timezone)->diffInDays(now()) + 1;
                    $numIterations = round($numIterations);
                    
                    for ($i = 0; $i < $numIterations; $i++) {
                        
                        if ($totalExistingCount >= $recurring->billing_cycle && $recurring->unlimited_recurring == 0) {
                            break;
                        }

                        $totalExistingCount++;
                        $this->invoiceCreate($recurring, now()->subDays($numIterations - 1)->addDays($i));
                        $this->saveNextInvoiceDate($recurring);
                    }
                }
            }
        }


        return Command::SUCCESS;
    }

    private function saveNextInvoiceDate($recurring)
    {
        $days = match ($recurring->rotation) {
            'daily' => now()->addDay(),
            'weekly' => now()->addWeek(),
            'bi-weekly' => now()->addWeeks(2),
            'monthly' => now()->addMonth(),
            'quarterly' => now()->addQuarter(),
            'half-yearly' => now()->addMonths(6),
            'annually' => now()->addYear(),
            default => now()->addDay(),
        };

        $days = $days->timezone($recurring->company->timezone);
        $recurring->next_invoice_date = $days->format('Y-m-d');
        $recurring->save();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */

    public function invoiceCreate($invoiceData, $date = null)
    {

        $recurring = $invoiceData;
        $invoiceDate = now()->timezone($recurring->company->timezone);

        if ($date) {
            $invoiceDate = $date->timezone($recurring->company->timezone);
        }

        $defaultAddress = CompanyAddress::where('is_default', 1)->where('company_id', $recurring->company_id)->first();

        $diff = $recurring->issue_date->diffInDays($recurring->due_date);
        $dueDate = now()->addDays($diff)->format('Y-m-d');

        $invoice = new Invoice();
        $invoice->invoice_recurring_id = $recurring->id;
        $invoice->company_id = $recurring->company_id;
        $invoice->project_id = $recurring->project_id ?? null;
        $invoice->client_id = $recurring->client_id ?: null;
        $invoice->invoice_number = Invoice::lastInvoiceNumber($recurring->company_id) + 1;
        $invoice->issue_date = $invoiceDate->format('Y-m-d');
        $invoice->due_date = $dueDate;
        $invoice->sub_total = round($recurring->sub_total, 2);

        $invoice->discount = $recurring->discount;
        $invoice->discount_type = $recurring->discount_type;
        $invoice->total = round($recurring->total, 2);
        $invoice->due_amount = round($recurring->total, 2);
        $invoice->currency_id = $recurring->currency_id;
        $invoice->note = $recurring->note;
        $invoice->show_shipping_address = $recurring->show_shipping_address;
        $invoice->send_status = 1;
        $invoice->company_address_id = $defaultAddress->id;
        $invoice->bank_account_id = $recurring->bank_account_id;
        $invoice->created_at = now()->timezone($recurring->company->timezone);
        $invoice->updated_at = now()->timezone($recurring->company->timezone);
        $invoice->save();

        if ($invoice->show_shipping_address) {
            if ($invoice->project_id != null && $invoice->project_id != '') {
                $client = $invoice->project->clientdetails;
                $client->shipping_address = $invoice->project->client->clientDetails->shipping_address;
                $client->save();
            } elseif ($invoice->client_id != null && $invoice->client_id != '') {
                $client = $invoice->clientdetails;
                $client->shipping_address = $invoice->client->clientDetails->shipping_address;
                $client->save();
            }
        }

        foreach ($recurring->items as $item) {

            $invoiceItem = InvoiceItems::create(
                [
                    'invoice_id' => $invoice->id,
                    'item_name' => $item->item_name,
                    'item_summary' => $item->item_summary,
                    'unit_id' => (!is_null($item->unit_id)) ? $item->unit_id : null,
                    'product_id' => (!is_null($item->product_id)) ? $item->product_id : null,
                    'hsn_sac_code' => (isset($item->hsn_sac_code)) ? $item->hsn_sac_code : null,
                    'type' => 'item',
                    'quantity' => $item->quantity,
                    'unit_price' => $item->unit_price,
                    'amount' => $item->amount,
                    'taxes' => $item->taxes
                ]
            );

            if ($item->recurringInvoiceItemImage) {
                // Add invoice item image
                InvoiceItemImage::create(
                    [
                        'invoice_item_id' => $invoiceItem->id,
                        'filename' => $item->recurringInvoiceItemImage->filename,
                        'hashname' => $item->recurringInvoiceItemImage->hashname,
                        'size' => $item->recurringInvoiceItemImage->size,
                        'external_link' => $item->recurringInvoiceItemImage->external_link
                    ]
                );

                // Copy files here
                if ($item->recurringInvoiceItemImage->filename != '') {

                    $source = public_path(Files::UPLOAD_FOLDER . '/') . RecurringInvoiceItemImage::FILE_PATH . '/' . $item->id . '/' . $item->recurringInvoiceItemImage->hashname;

                    $path = public_path(Files::UPLOAD_FOLDER . '/') . InvoiceItemImage::FILE_PATH . '/' . $invoiceItem->id . '/';

                    $filename = $item->recurringInvoiceItemImage->hashname;

                    if (!file_exists($path)) {
                        mkdir($path, 0777, true);
                    }

                    copy($source, $path . $filename);
                }
            }
        }


        if (($invoice->project && $invoice->project->client_id != null) || $invoice->client_id != null) {
            $clientId = ($invoice->project && $invoice->project->client_id != null) ? $invoice->project->client_id : $invoice->client_id;
            // Notify client
            $notifyUser = User::withoutGlobalScope(ActiveScope::class)->find($clientId);

            if ($notifyUser) {
                $notifyUser->notify(new NewInvoiceRecurring($invoice));
            }
        }

        // Log search
        $this->logSearchEntry($invoice->id, $invoice->invoice_number, 'invoices.show', 'invoice');

        // Auto-pay via PayFast token if enabled, token is stored, and PayFast is still active
        if (
            $recurring->enable_auto_pay == 1 &&
            !empty($recurring->payfast_token) &&
            optional($recurring->company->paymentGatewayCredentials)->payfast_status === 'active'
        ) {
            info('Auto-pay via PayFast token if enabled, token is stored, and PayFast is still active');
            $this->payfastAutoCharge($invoice, $recurring);
        }
    }

    private function payfastAutoCharge(Invoice $invoice, RecurringInvoice $recurring)
    {
        try {
            $company = $recurring->company;
            $this->payfastSet($company->hash);

            $isTesting   = config('payfast.testing');
            $token       = $recurring->payfast_token;

            // PayFast only processes in ZAR. Convert using exchange rates stored in currencies table.
            $chargeAmount    = (float) $invoice->due_amount;
            $invoiceCurrency = $invoice->currency;
            $zarCurrency     = Currency::where('company_id', $company->id)
                ->where('currency_code', 'ZAR')
                ->first();

            if ($zarCurrency && $invoiceCurrency && strtoupper($invoiceCurrency->currency_code) !== 'ZAR') {
                $invoiceRate  = (float) ($invoiceCurrency->exchange_rate ?: 1);
                $zarRate      = (float) ($zarCurrency->exchange_rate ?: 1);
                $chargeAmount = round($chargeAmount * $invoiceRate / $zarRate, 2);
            }

            // PayFast adhoc API expects amount in cents as an integer (e.g. R100.00 → 10000)
            $amount = (int) round($chargeAmount * 100);
            $timestamp   = now()->format('Y-m-d\TH:i:s');
            $merchantId  = config('payfast.merchant.merchant_id');
            $merchantKey = config('payfast.merchant.merchant_key');
            $passphrase  = config('payfast.passphrase');

            // POST body (JSON)
            $body = [
                'amount'           => $amount,
                'item_name'        => 'Invoice ' . $invoice->invoice_number,
                'item_description' => 'Auto recurring payment',
                'm_payment_id'     => 'auto_invoice_' . $invoice->id,
            ];

            // PayFast API signature: merge body + header fields + passphrase,
            // sort alphabetically, URL-encode values, then MD5 the result.
            $signatureData = array_merge($body, [
                'merchant-id' => $merchantId,
                'passphrase'  => $passphrase,
                'timestamp'   => $timestamp,
                'version'     => 'v1',
            ]);
            ksort($signatureData);
            $queryParts = [];

            foreach ($signatureData as $key => $val) {
                $queryParts[] = $key . '=' . str_replace('%20', '+', rawurlencode(trim((string)$val)));
            }

            $signature = md5(implode('&', $queryParts));

            $url = 'https://api.payfast.co.za/subscriptions/' . $token . '/adhoc';

            if ($isTesting) {
                $url .= '?testing=true';
            }

            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'merchant-id: ' . $merchantId,
                'version: v1',
                'timestamp: ' . $timestamp,
                'signature: ' . $signature,
                'Content-Type: application/json',
            ]);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 1);
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            $result = json_decode($response, true);

            if ($httpCode === 200 && isset($result['status']) && strtolower($result['status']) === 'success') {
                $invoice->status     = 'paid';
                $invoice->due_amount = 0;
                $invoice->save();

                // Reset failure counter on success
                $recurring->failed_payment_attempts = 0;
                $recurring->saveQuietly();

                RecurringInvoiceLog::create([
                    'company_id'           => $recurring->company_id,
                    'recurring_invoice_id' => $recurring->id,
                    'invoice_id'           => $invoice->id,
                    'status'               => 'success',
                    'message'              => 'Auto-pay successful',
                    'response_code'        => $httpCode,
                ]);

                $this->makePayment('Payfast', $invoice->total, $invoice, 'auto_invoice_' . $invoice->id, 'complete');
                $this->info('Auto-pay successful for invoice #' . $invoice->invoice_number);
            } else {
                $failReason = $response;
                $this->error('Auto-pay failed for invoice #' . $invoice->invoice_number . ' (HTTP ' . $httpCode . '): ' . $failReason);

                $this->handlePaymentFailure($invoice, $recurring, $failReason, $httpCode);
            }
        } catch (\Throwable $e) {
            $this->error('Auto-pay exception for invoice #' . $invoice->invoice_number . ': ' . $e->getMessage());
            $this->handlePaymentFailure($invoice, $recurring, $e->getMessage(), 0);
        }
    }

    private function handlePaymentFailure(Invoice $invoice, RecurringInvoice $recurring, string $reason, int $httpCode = 0): void
    {
        $recurring->failed_payment_attempts = ($recurring->failed_payment_attempts ?? 0) + 1;
        $recurring->saveQuietly();

        RecurringInvoiceLog::create([
            'company_id'           => $recurring->company_id,
            'recurring_invoice_id' => $recurring->id,
            'invoice_id'           => $invoice->id,
            'status'               => 'failed',
            'message'              => $reason,
            'response_code'        => $httpCode,
        ]);

        // Notify the client about the failed payment
        $client = $recurring->client ?? optional($invoice->project)->client ?? null;

        if ($client) {
            $client->notify(new PayfastPaymentFailed($invoice, $recurring, $reason));
            $this->info('Payment failure notification sent to client for invoice #' . $invoice->invoice_number);
        }

        // After 2 consecutive failures: suspend the subscription and notify all company admins
        if ($recurring->failed_payment_attempts >= 2) {
            $recurring->status             = 'inactive';
            $recurring->saveQuietly();

            $this->warn('Subscription #' . $recurring->id . ' suspended after ' . $recurring->failed_payment_attempts . ' consecutive failed attempts.');

            $admins = User::withoutGlobalScope(ActiveScope::class)
                ->where('company_id', $recurring->company_id)
                ->whereHas('roles', fn ($q) => $q->where('name', 'admin'))
                ->get();

            foreach ($admins as $admin) {
                $admin->notify(new PayfastSubscriptionSuspended($invoice, $recurring));
            }

            $this->info('Suspension notification sent to ' . $admins->count() . ' admin(s).');
        }
    }
}
