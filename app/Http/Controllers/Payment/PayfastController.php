<?php

namespace App\Http\Controllers\Payment;

use Config;
use Billow\Payfast;
use App\Helper\Reply;
use App\Models\Order;
use App\Models\Invoice;
use App\Models\Currency;
use Illuminate\Http\Request;
use App\Traits\MakePaymentTrait;
use App\Traits\PaymentGatewayTrait;
use App\Http\Controllers\Controller;
use App\Models\GlobalSetting;
use App\Models\RecurringInvoice;
use App\Models\RecurringInvoiceLog;
use App\Traits\MakeOrderInvoiceTrait;

class PayfastController extends Controller
{

    use MakePaymentTrait, MakeOrderInvoiceTrait, PaymentGatewayTrait;

    public function __construct()
    {
        parent::__construct();
        $this->pageTitle = __('app.payfast');
    }

    public function paymentWithPayfastPublic(Request $request)
    {
        $enableAutoPay = false;
        $recurringInvoiceId = null;

        switch ($request->type) {
        case 'invoice':
            $invoice = Invoice::findOrFail($request->id);
            $company = $invoice->company;
            $client = $invoice->client_id ? $invoice->client : $invoice->project->client;
            $description = __('app.invoice') . ' ' . $invoice->invoice_number;
            $amount = $invoice->amountDue();

            if ($invoice->invoice_recurring_id) {
                $recurringInvoice = \App\Models\RecurringInvoice::find($invoice->invoice_recurring_id);
                $gatewayCredentials = $invoice->company->paymentGatewayCredentials;

                if (
                    $recurringInvoice &&
                    $recurringInvoice->enable_auto_pay == 1 &&
                    empty($recurringInvoice->payfast_token) &&
                    optional($gatewayCredentials)->payfast_status === 'active'
                ) {
                    $enableAutoPay = true;
                    $recurringInvoiceId = $recurringInvoice->id;
                }
            }
            break;

        case 'order':
            $order = Order::findOrFail($request->id);
            $company = $order->company;
            $client = $order->client;
            $description = __('app.order') . ' ' . $order->order_number;
            $amount = $order->total;
            break;

        default:
            return Reply::error(__('messages.paymentTypeNotFound'));
        }

        $this->payfastSet($company->hash);

        try {
            Config::set('payfast.merchant.return_url', route('payfast.callback', [$request->id, $request->type, 'success']));
            Config::set('payfast.merchant.cancel_url', route('payfast.callback', [$request->id, $request->type, 'cancel']));
            Config::set('payfast.merchant.notify_url', route('payfast.webhook', [$company->hash]));

            // PayFast only processes in ZAR. Convert the invoice/order amount to ZAR
            // using the exchange rates stored in the currencies table.
            // exchange_rate convention: 1 unit of that currency = exchange_rate units of
            // the company base currency. So:
            //   amount_in_base = amount × invoice_currency.exchange_rate
            //   amount_in_zar  = amount_in_base ÷ zar_currency.exchange_rate
            $invoiceCurrency = isset($invoice) ? $invoice->currency : ($order->currency ?? null);
            $zarCurrency     = Currency::where('company_id', $company->id)
                ->where('currency_code', 'ZAR')
                ->first();

            if ($zarCurrency && $invoiceCurrency && strtoupper($invoiceCurrency->currency_code) !== 'ZAR') {
                $invoiceRate = (float) ($invoiceCurrency->exchange_rate ?: 1);
                $zarRate     = (float) ($zarCurrency->exchange_rate ?: 1);
                $amount      = round($amount * $invoiceRate / $zarRate, 2);
                info([$amount,$zarRate, $invoiceRate]);
            }

            // For tokenization (subscription_type=2) strip frequency, cycles, and
            // recurring_amount — per PayFast docs those fields are only for
            // subscription_type=1 (recurring billing). Sending them causes a
            // 400 "recurring_amount outside limits" error.
            $payfast = new class($enableAutoPay) extends Payfast {
                private bool $isTokenization;

                public function __construct(bool $isTokenization)
                {
                    parent::__construct();
                    $this->isTokenization = $isTokenization;
                }

                public function paymentVars()
                {
                    $vars = parent::paymentVars();

                    if ($this->isTokenization) {
                        unset($vars['frequency'], $vars['cycles'], $vars['recurring_amount']);
                    }

                    return $vars;
                }
            };

            $payfast->setBuyer($client->name, '', $client->email);
            $payfast->setAmount($amount);
            $payfast->setItem($request->type, $description);
            $payfast->setMerchantReference($request->type . '_' . $request->id);
            $payfast->setCustomStr1($request->type);
            $payfast->setCustomInt1($request->id);

            if ($enableAutoPay && $recurringInvoiceId) {
                $payfast->setSubscriptionType(2);
                $payfast->setCustomInt2($recurringInvoiceId);
                $payfast->setCustomStr2('enable_auto_pay');
            }

            // Return the payment form.
            return Reply::successWithData(__('modules.payfast.redirectMessage'), ['form' => $payfast->paymentForm(false)]);

        } catch (\Throwable $th) {

            return Reply::error($th->getMessage());
        }

    }

    public function handleGatewayCallback($id, $type, $status)
    {

        switch ($type) {
        case 'invoice':
            $invoice = Invoice::findOrFail($id);

            if ($invoice->status != 'paid') {

                $invoice->status = $status == 'success' ? 'paid' : 'unpaid';
                $invoice->save();
                $this->makePayment('Payfast', $invoice->amountDue(), $invoice, 'payfast_' . $invoice->id, ($status == 'success' ? 'complete' : 'failed'));
            }

            return redirect(url()->temporarySignedRoute('front.invoice', now()->addDays(GlobalSetting::SIGNED_ROUTE_EXPIRY), $invoice->hash));

        case 'order':
            $order = Order::findOrFail($id);
            $invoice = $this->makeOrderInvoice($order, ($status == 'success' ? 'completed' : 'failed'));
            $this->makePayment('Payfast', $invoice->amountDue(), $invoice, 'payfast_' . $invoice->id, (($status == 'success') ? 'complete' : 'failed'));

            return redirect()->route('orders.show', $id);
        }


        return redirect()->route('dashboard');
    }

    public function handleGatewayWebhook(Request $request, $companyHash)
    {
        \Log::info('PayFast ITN received', $request->all());

        $this->payfastSet($companyHash);

        switch ($request->custom_str1) {
        case 'invoice':
            $invoice = Invoice::findOrFail($request->custom_int1);
            $invoice->status = ($request->payment_status == 'COMPLETE') ? 'paid' : 'unpaid';
            $invoice->save();

            // Save token and log the first payment when auto-pay is being set up.
            // PayFast may resend the same ITN on later days — skip duplicate success logs.
            if (
                $request->custom_str2 === 'enable_auto_pay' &&
                $request->custom_int2
            ) {
                $recurringInvoice = RecurringInvoice::find($request->custom_int2);

                if ($recurringInvoice) {
                    $isComplete = $request->payment_status == 'COMPLETE';
                    $firstPaymentAlreadyLogged = RecurringInvoiceLog::where('recurring_invoice_id', $recurringInvoice->id)
                        ->where('invoice_id', $invoice->id)
                        ->where('status', 'success')
                        ->exists();

                    if (
                        $isComplete
                        && !empty($request->token)
                        && $recurringInvoice->enable_auto_pay == 1
                        && empty($recurringInvoice->payfast_token)
                    ) {
                        $recurringInvoice->payfast_token      = $request->token;
                        $recurringInvoice->payfast_sub_status = 1;
                        $recurringInvoice->saveQuietly();
                        \Log::info('PayFast auto-pay token saved', [
                            'recurring_id' => $recurringInvoice->id,
                            'token'        => $request->token,
                        ]);
                    }

                    if (!$firstPaymentAlreadyLogged) {
                        RecurringInvoiceLog::create([
                            'company_id'           => $recurringInvoice->company_id,
                            'recurring_invoice_id' => $recurringInvoice->id,
                            'invoice_id'           => $invoice->id,
                            'status'               => $isComplete ? 'success' : 'failed',
                            'message'              => $isComplete
                                ? 'First payment received via PayFast. Token registered for auto-pay.'
                                : 'First payment failed via PayFast. Status: ' . $request->payment_status,
                            'response_code'        => $isComplete ? 200 : 0,
                        ]);
                    }
                }
            }

            try {
                $this->makePayment('Payfast', $request->amount_gross, $invoice, $request->m_payment_id, (($request->payment_status == 'COMPLETE') ? 'complete' : 'failed'));
            } catch (\Throwable $e) {
                \Log::warning('PayFast makePayment error (non-fatal): ' . $e->getMessage());
            }
            break;

        case 'order':
            $order = Order::findOrFail($request->custom_int1);
            $invoice = $this->makeOrderInvoice($order, ($request->payment_status == 'COMPLETE' ? 'completed' : 'failed'));

            try {
                $this->makePayment('Payfast', $request->amount_gross, $invoice, $request->m_payment_id, (($request->payment_status == 'COMPLETE') ? 'complete' : 'failed'));
            } catch (\Throwable $e) {
                \Log::warning('PayFast makePayment error (non-fatal): ' . $e->getMessage());
            }
            break;
        }

        return response()->json(['status' => 'success']);
    }

}

