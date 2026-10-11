<?php

namespace App\Http\Controllers;

use App\DataTables\ClientSubscriptionDataTable;
use App\DataTables\SubscriptionDataTable;
use App\Helper\Reply;
use App\Models\PaymentGatewayCredentials;
use App\Models\RecurringInvoice;
use App\Models\RecurringInvoiceLog;
use App\Models\User;
use Illuminate\Http\Request;

class SubscriptionController extends AccountBaseController
{

    public function __construct()
    {
        parent::__construct();
        $this->pageTitle = 'app.subscriptions';
        $this->middleware(function ($request, $next) {
            abort_403(!in_array('invoices', $this->user->modules));
            abort_403(user()->permission('view_invoices') === 'none');
            abort_403(optional(PaymentGatewayCredentials::first())->payfast_status !== 'active');

            return $next($request);
        });
    }

    // ─────────────────────────────────────────────
    //  ADMIN METHODS
    // ─────────────────────────────────────────────

    public function index(SubscriptionDataTable $dataTable)
    {
        abort_403(!in_array(user()->permission('view_invoices'), ['all', 'added']));

        $companyId = company()->id;

        $base = RecurringInvoice::where('company_id', $companyId)
            ->where('payfast_sub_status', 1);

        $this->statsTotal     = (clone $base)->count();
        $this->statsActive    = (clone $base)->where('status', 'active')->count();
        $this->statsSuspended = (clone $base)->where('status', 'inactive')->count();
        $this->statsFailed    = (clone $base)->where('failed_payment_attempts', '>', 0)->count();

        $currencySymbol           = company()->currency->currency_symbol ?? '';
        $this->statsTotalAmount   = $currencySymbol . number_format((clone $base)->sum('total'), 2);

        $this->clients = User::allClients();

        $this->rotations = ['daily', 'weekly', 'bi-weekly', 'monthly', 'quarterly', 'half-yearly', 'annually'];

        return $dataTable->render('subscriptions.index', $this->data);
    }

    public function cancel($id)
    {
        abort_403(user()->permission('edit_invoices') === 'none');

        $recurring = RecurringInvoice::where('company_id', company()->id)
            ->where('payfast_sub_status', 1)
            ->findOrFail($id);

        $recurring->status                  = 'inactive';
        $recurring->enable_auto_pay         = 0;
        $recurring->failed_payment_attempts = 0;
        $recurring->saveQuietly();

        RecurringInvoiceLog::create([
            'company_id'           => $recurring->company_id,
            'recurring_invoice_id' => $recurring->id,
            'invoice_id'           => null,
            'status'               => 'success',
            'message'              => 'Subscription cancelled by admin. Auto-pay disabled.',
            'response_code'        => 0,
        ]);

        return Reply::success(__('messages.recordSaved'));
    }

    public function reactivate($id)
    {
        abort_403(user()->permission('edit_invoices') === 'none');

        $recurring = RecurringInvoice::where('company_id', company()->id)
            ->where('payfast_sub_status', 1)
            ->where('status', 'inactive')
            ->findOrFail($id);

        if (empty($recurring->payfast_token)) {
            return Reply::error(__('messages.subscriptionReactivateNoToken'));
        }

        $recurring->status                  = 'active';
        $recurring->enable_auto_pay         = 1;
        $recurring->failed_payment_attempts = 0;
        $recurring->saveQuietly();

        RecurringInvoiceLog::create([
            'company_id'           => $recurring->company_id,
            'recurring_invoice_id' => $recurring->id,
            'invoice_id'           => null,
            'status'               => 'success',
            'message'              => 'Subscription re-activated by admin. Auto-pay will resume on next invoice.',
            'response_code'        => 0,
        ]);

        return Reply::success(__('messages.recordSaved'));
    }

    public function logs($id)
    {
        abort_403(!in_array(user()->permission('view_invoices'), ['all', 'added']));

        $this->recurring = RecurringInvoice::where('company_id', company()->id)
            ->findOrFail($id);

        $this->logs = RecurringInvoiceLog::where('recurring_invoice_id', $id)
            ->with('invoice')
            ->latest()
            ->get();

        return view('subscriptions.ajax.logs', $this->data);
    }

    // ─────────────────────────────────────────────
    //  CLIENT-FACING METHODS
    // ─────────────────────────────────────────────

    public function clientIndex(ClientSubscriptionDataTable $dataTable)
    {
        abort_403(!in_array('client', user_roles()));

        $base = RecurringInvoice::where('company_id', company()->id)
            ->where('client_id', user()->id)
            ->where('payfast_sub_status', 1);

        $this->statsTotal     = (clone $base)->count();
        $this->statsActive    = (clone $base)->where('status', 'active')->count();
        $this->statsSuspended = (clone $base)->where('status', 'inactive')->count();

        $currencySymbol           = company()->currency->currency_symbol ?? '';
        $this->statsTotalAmount   = $currencySymbol . number_format((clone $base)->sum('total'), 2);

        $this->rotations = ['daily', 'weekly', 'bi-weekly', 'monthly', 'quarterly', 'half-yearly', 'annually'];

        return $dataTable->render('subscriptions.client.index', $this->data);
    }

    public function clientCancel($id)
    {
        abort_403(!in_array('client', user_roles()));

        $recurring = RecurringInvoice::where('company_id', company()->id)
            ->where('client_id', user()->id)
            ->where('payfast_sub_status', 1)
            ->where('client_can_stop', 1)
            ->findOrFail($id);

        $recurring->status                  = 'inactive';
        $recurring->enable_auto_pay         = 0;
        $recurring->failed_payment_attempts = 0;
        $recurring->saveQuietly();

        RecurringInvoiceLog::create([
            'company_id'           => $recurring->company_id,
            'recurring_invoice_id' => $recurring->id,
            'invoice_id'           => null,
            'status'               => 'success',
            'message'              => 'Subscription cancelled by client. Auto-pay disabled.',
            'response_code'        => 0,
        ]);

        return Reply::success(__('messages.recordSaved'));
    }

    public function clientLogs($id)
    {
        abort_403(!in_array('client', user_roles()));

        $this->recurring = RecurringInvoice::where('company_id', company()->id)
            ->where('client_id', user()->id)
            ->findOrFail($id);

        $this->logs = RecurringInvoiceLog::where('recurring_invoice_id', $id)
            ->with('invoice')
            ->latest()
            ->get();

        return view('subscriptions.ajax.logs', $this->data);
    }

}
