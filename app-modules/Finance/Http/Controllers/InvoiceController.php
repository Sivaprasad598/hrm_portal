<?php

namespace AppModules\Finance\Http\Controllers;

use App\Helper\Reply;
use App\Http\Controllers\AccountBaseController;
use AppModules\Finance\Services\GstInvoice;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Finance -> Invoices (India, GST). The list; a form with the invoice drawn live beside it (like the
 * Letter Generator) whose wording can be changed per invoice; save as a draft or issue (the number
 * is given then); an issued invoice is not edited - it is cancelled, or duplicated into a new one.
 * Super Admin, Admin, CEO, CFO, HR (or view_invoices = all); settings: Super Admin, Admin, CFO. Payments are not part of this yet.
 */
class InvoiceController extends AccountBaseController
{

    public function __construct()
    {
        parent::__construct();
        $this->pageTitle = 'Invoices';

        $this->middleware(function ($request, $next) {
            abort_403(!GstInvoice::canUse());

            return $next($request);
        });
    }

    public function index(Request $request)
    {
        $today = now()->startOfDay()->toDateString();
        $fy = $request->input('fy', GstInvoice::financialYear(now()));
        $base = DB::table('invoices as i')->where('i.company_id', company()->id);

        $query = (clone $base)->leftJoin('projects as p', 'p.id', '=', 'i.project_id')
            ->when($fy !== 'all', fn ($q) => $q->where(fn ($w) => $w->where('i.financial_year', $fy)->orWhere(fn ($d) => $d->whereNull('i.financial_year')->where('i.status', 'draft'))))
            ->when($request->status === 'draft', fn ($q) => $q->where('i.status', 'draft'))
            ->when($request->status === 'issued', fn ($q) => $q->whereIn('i.status', ['unpaid', 'partial']))
            ->when($request->status === 'overdue', fn ($q) => $q->whereIn('i.status', ['unpaid', 'partial'])->where('i.due_date', '<', $today))
            ->when($request->status === 'paid', fn ($q) => $q->where('i.status', 'paid'))
            ->when($request->status === 'cancelled', fn ($q) => $q->where('i.status', 'canceled'))
            ->when($request->client, fn ($q, $c) => $q->where('i.client_id', $c))
            ->when($request->project, fn ($q, $p) => $q->where('i.project_id', $p))
            ->when($request->search, fn ($q, $s) => $q->where(fn ($w) => $w->where('i.invoice_number', 'like', "%$s%")->orWhere('i.details', 'like', '%' . trim(json_encode($s), '"') . '%')))
            ->orderByRaw("i.status = 'draft' desc")->orderByDesc('i.financial_year')->orderByRaw('CAST(i.original_invoice_number AS UNSIGNED) desc')->orderByDesc('i.id')
            ->select('i.*', 'p.project_name');

        $perPage = in_array((int) $request->per_page, [25, 50, 100], true) ? (int) $request->per_page : 25;
        $this->invoices = $query->paginate($perPage)->withQueryString();
        $this->invoices->getCollection()->transform(function ($i) {
            $i->d = json_decode((string) $i->details, true) ?: [];

            return $i;
        });

        $year = (clone $base)->when($fy !== 'all', fn ($q) => $q->where('i.financial_year', $fy));
        $issued = (clone $year)->whereIn('i.status', ['unpaid', 'partial', 'paid']);
        $this->summary = [
            'invoiced' => (float) (clone $issued)->sum('i.total'),
            'count' => (clone $issued)->count(),
            'tax' => (float) (clone $issued)->sum(DB::raw('i.cgst + i.sgst + i.igst')),
            'overdue' => (float) (clone $year)->whereIn('i.status', ['unpaid', 'partial'])->where('i.due_date', '<', $today)->sum('i.total'),
            'overdue_count' => (clone $year)->whereIn('i.status', ['unpaid', 'partial'])->where('i.due_date', '<', $today)->count(),
            'drafts' => (clone $base)->where('i.status', 'draft')->count(),
        ];
        $this->fy = $fy;
        $this->years = collect(range(0, 3))->map(fn ($n) => GstInvoice::financialYear(now()->subYears($n)))->all();
        $this->clients = $this->clientList();
        $this->projects = DB::table('projects')->where('company_id', company()->id)->whereNull('deleted_at')->orderBy('project_name')->get(['id', 'project_name']);
        $this->today = $today;

        return view('invoices.list', $this->data);
    }

    public function create(Request $request)
    {
        $s = GstInvoice::settings();
        $form = [
            'title' => 'Tax Invoice', 'lines' => [], 'place_of_supply' => $s->state_code, 'client_id' => null, 'project_id' => null,
            'client_name' => '', 'client_address' => '', 'client_gstin' => '', 'client_email' => '', 'po' => '', 'reverse_charge' => false,
            'intro' => '', 'notes' => '', 'terms' => $s->terms, 'bank' => $s->bank, 'signatory' => $s->signatory, 'show_stamp' => false,
            'issue_date' => now()->toDateString(), 'due_date' => now()->addDays($s->due_after)->toDateString(),
        ];

        // Duplicate: start from an invoice already made (new dates, no number)
        if ($request->from && ($from = $this->invoice($request->from))) {
            $form = array_merge(GstInvoice::toForm($from), ['issue_date' => $form['issue_date'], 'due_date' => $form['due_date']]);
            $this->duplicateOf = $from->invoice_number;
        }

        // From a project: its client and a line for it
        if ($request->project && ($project = DB::table('projects')->where('company_id', company()->id)->find($request->project))) {
            $form['project_id'] = $project->id;
            if ($project->client_id) {
                $form = array_merge($form, $this->clientFields((int) $project->client_id));
            }
            if (!$form['lines']) {
                $form['lines'][] = ['name' => $project->project_name, 'description' => '', 'hsn' => '998313', 'unit' => 'Job', 'qty' => 1,
                    'rate' => (float) ($project->project_budget ?? 0), 'gst' => 18];
            }
        }

        if ($request->client) {
            $form = array_merge($form, $this->clientFields((int) $request->client));
        }

        return $this->form($form, null);
    }

    public function edit($id)
    {
        $invoice = $this->invoice($id);
        if ($invoice->status !== 'draft') {
            return redirect()->route('invoices.show', $invoice->id);
        }

        return $this->form(GstInvoice::toForm($invoice), $invoice);
    }

    public function store(Request $request)
    {
        return $this->save($request, null);
    }

    public function update(Request $request, $id)
    {
        $invoice = $this->invoice($id);
        if ($invoice->status !== 'draft') {
            $this->fail('An issued invoice is not changed: cancel it, or duplicate it into a new one.');
        }

        return $this->save($request, $invoice);
    }

    /** The invoice drawn from the form as it stands (the live preview). */
    public function preview(Request $request)
    {
        $in = $this->input($request);
        $c = GstInvoice::compute($in);

        return response(GstInvoice::html($c, ['number' => null, 'issue_date' => $in['issue_date'], 'due_date' => $in['due_date'], 'status' => 'draft',
            'project' => $this->projectName($in['project_id'] ?? null)], false));
    }

    public function show($id)
    {
        $this->invoice = $this->invoice($id);
        $this->d = json_decode((string) $this->invoice->details, true) ?: [];
        $this->pageTitle = $this->invoice->status === 'draft' ? 'Draft invoice' : 'Invoice ' . $this->invoice->invoice_number;
        $this->projectName = $this->projectName($this->invoice->project_id);

        return view('invoices.view', $this->data);
    }

    /** The invoice on its own (the page shows it in a frame). */
    public function sheet($id)
    {
        $invoice = $this->invoice($id);

        return response(GstInvoice::html(GstInvoice::compute(GstInvoice::toForm($invoice)), $this->meta($invoice), false));
    }

    public function download($id)
    {
        $invoice = $this->invoice($id);
        $pdf = GstInvoice::pdf(GstInvoice::compute(GstInvoice::toForm($invoice)), $this->meta($invoice));
        $name = ($invoice->status === 'draft' ? 'Draft-invoice-' . $invoice->id : str_replace('/', '-', $invoice->invoice_number)) . '.pdf';

        return response($pdf->output(), 200, ['Content-Type' => 'application/pdf',
            'Content-Disposition' => (request()->boolean('inline') ? 'inline' : 'attachment') . '; filename="' . $name . '"']);
    }

    public function cancel(Request $request, $id)
    {
        $invoice = $this->invoice($id);
        if (!in_array($invoice->status, ['unpaid', 'partial'], true)) {
            $this->fail('Only an issued invoice can be cancelled.');
        }
        $reason = trim((string) $request->reason);
        if ($reason === '') {
            $this->fail('Say why it is cancelled.');
        }

        DB::table('invoices')->where('id', $invoice->id)->update(['status' => 'canceled', 'cancel_reason' => mb_substr($reason, 0, 500),
            'last_updated_by' => user()->id, 'updated_at' => now()]);

        return Reply::success('Invoice ' . $invoice->invoice_number . ' cancelled. Its number stays used.');
    }

    public function destroy($id)
    {
        $invoice = $this->invoice($id);
        if ($invoice->status !== 'draft') {
            $this->fail('Only a draft can be deleted; an issued invoice is cancelled instead.');
        }

        DB::transaction(function () use ($invoice) {
            DB::table('invoice_items')->where('invoice_id', $invoice->id)->delete();
            DB::table('invoices')->where('id', $invoice->id)->delete();
        });

        return Reply::successWithData('Draft deleted.', ['redirectUrl' => route('invoices.index')]);
    }

    /** A client's billing details, for the form. */
    public function client($id)
    {
        return response()->json($this->clientFields((int) $id));
    }

    public function settingsForm()
    {
        $this->settings = GstInvoice::settings();
        $this->states = GstInvoice::STATES;
        $this->pageTitle = 'Invoice settings';
        $this->view = 'invoices.settings';

        if (request()->ajax()) {
            return Reply::dataOnly(['status' => 'success', 'html' => view('invoices.settings', $this->data)->render(), 'title' => 'Invoice settings']);
        }

        return view('recruitment.page', $this->data);
    }

    public function saveSettings(Request $request)
    {
        abort_403(!(array_intersect(['admin', 'manager_admin', 'cfo'], user_roles()) || user()->permission('manage_finance_setting') === 'all'));
        $gstin = strtoupper(trim((string) $request->gstin));
        if ($gstin !== '' && !preg_match(GstInvoice::GSTIN, $gstin)) {
            $this->fail('The GSTIN is not valid (15 characters, e.g. 37AAACA1234A1Z5).');
        }
        $state = (string) $request->state_code;
        if (!isset(GstInvoice::STATES[$state])) {
            $this->fail('Choose the state the company is registered in.');
        }
        if ($gstin !== '' && substr($gstin, 0, 2) !== $state) {
            $this->fail('The GSTIN starts with ' . substr($gstin, 0, 2) . ', which is ' . (GstInvoice::STATES[substr($gstin, 0, 2)] ?? 'another state') . ', not ' . GstInvoice::STATES[$state] . '.');
        }
        $prefix = strtoupper(preg_replace('/[^A-Za-z0-9-]/', '', (string) $request->prefix)) ?: 'INV';
        $digits = max(1, min(6, (int) $request->digits ?: 3));
        if (strlen($prefix . '/26-27/' . str_repeat('9', $digits)) > 16) {
            $this->fail('With that prefix the number would be longer than the 16 characters GST allows.');
        }

        DB::table('invoice_settings')->updateOrInsert(['company_id' => company()->id], [
            'gst_number' => $gstin ?: null, 'state_code' => $state, 'invoice_prefix' => $prefix, 'invoice_digit' => $digits,
            'due_after' => max(0, min(365, (int) $request->due_after)), 'invoice_terms' => trim((string) $request->terms),
            'bank_details' => trim((string) $request->bank), 'signatory_name' => trim((string) $request->signatory), 'updated_at' => now(),
        ]);

        return Reply::success('Invoice settings saved.');
    }

    // ------------------------------------------------------------------

    private function form(array $form, ?object $invoice)
    {
        $this->formData = $form;
        $this->invoice = $invoice;
        $this->settings = GstInvoice::settings();
        $this->states = GstInvoice::STATES;
        $this->clients = $this->clientList();
        $this->projects = DB::table('projects')->where('company_id', company()->id)->whereNull('deleted_at')->whereNotIn('status', ['canceled'])
            ->orderBy('project_name')->get(['id', 'project_name']);
        $this->pageTitle = $invoice ? 'Edit draft invoice' : 'New invoice';

        return view('invoices.form', $this->data);
    }

    private function save(Request $request, ?object $invoice)
    {
        $in = $this->input($request);
        $issuing = $request->input('action') === 'issue';
        $c = GstInvoice::compute($in);
        $problems = GstInvoice::problems($c, $in, $issuing);
        if (!$problems && $in['due_date'] < $in['issue_date']) {
            $problems[] = 'The due date cannot be before the invoice date.';
        }
        if ($problems) {
            $this->fail(implode(' ', $problems));
        }

        $issueDate = Carbon::parse($in['issue_date']);
        $clientId = $in['client_id'] && DB::table('users')->where('company_id', company()->id)->where('id', $in['client_id'])->exists() ? (int) $in['client_id'] : null;
        $projectId = $in['project_id'] && DB::table('projects')->where('company_id', company()->id)->where('id', $in['project_id'])->exists() ? (int) $in['project_id'] : null;
        $currency = DB::table('currencies')->where('company_id', company()->id)->where('currency_code', 'INR')->value('id') ?? company()->currency_id;

        $row = [
            'company_id' => company()->id, 'project_id' => $projectId, 'client_id' => $clientId,
            'issue_date' => $issueDate->toDateString(), 'due_date' => $in['due_date'],
            'sub_total' => $c['sub_total'], 'discount' => 0, 'discount_type' => 'fixed', 'total' => $c['total'], 'due_amount' => $c['total'],
            'currency_id' => $currency, 'default_currency_id' => $currency, 'exchange_rate' => 1, 'calculate_tax' => 'after_discount',
            'note' => $c['notes'] ?: null, 'send_status' => 0,
            'place_of_supply' => $c['place_of_supply'], 'supply_type' => $c['supply_type'], 'cgst' => $c['cgst'], 'sgst' => $c['sgst'], 'igst' => $c['igst'],
            'details' => json_encode(array_diff_key($c, array_flip(['lines', 'summary'])), JSON_UNESCAPED_UNICODE),
            'last_updated_by' => user()->id, 'updated_at' => now(),
        ];

        $id = DB::transaction(function () use ($row, $invoice, $c, $issuing, $issueDate) {
            if ($issuing) {
                // the number, in the year of the invoice date; locked against two people issuing at once
                DB::table('invoices')->where('company_id', company()->id)->where('financial_year', GstInvoice::financialYear($issueDate))->lockForUpdate()->get(['id']);
                [$number, $seq] = GstInvoice::nextNumber($issueDate);
                $row += ['status' => 'unpaid', 'invoice_number' => $number, 'custom_invoice_number' => $number, 'original_invoice_number' => (string) $seq,
                    'financial_year' => GstInvoice::financialYear($issueDate), 'issued_at' => now()];
            } elseif (!$invoice) {
                $row += ['status' => 'draft', 'invoice_number' => 'DRAFT-' . strtoupper(Str::random(10)), 'financial_year' => null];
            }

            if ($invoice) {
                DB::table('invoices')->where('id', $invoice->id)->update($row);
                $id = $invoice->id;
            } else {
                $id = DB::table('invoices')->insertGetId($row + ['hash' => md5(microtime() . Str::random(8)), 'added_by' => user()->id, 'created_by' => user()->id,
                    'created_at' => now()]);
            }

            DB::table('invoice_items')->where('invoice_id', $id)->delete();
            foreach ($c['lines'] as $i => $l) {
                DB::table('invoice_items')->insert([
                    'invoice_id' => $id, 'item_name' => $l['name'], 'item_summary' => $l['description'] ?: null, 'type' => 'item',
                    'quantity' => $l['qty'], 'unit_price' => $l['rate'], 'amount' => $l['taxable'], 'hsn_sac_code' => $l['hsn'] ?: null,
                    'gst_rate' => $l['gst'], 'tax_amount' => $c['title'] === 'Bill of Supply' ? 0 : $l['tax'], 'unit_label' => $l['unit'] ?: null,
                    'field_order' => $i + 1, 'created_at' => now(), 'updated_at' => now(),
                ]);
            }

            return $id;
        });

        // remember the client's state, for their next invoice
        if ($clientId && \Illuminate\Support\Facades\Schema::hasColumn('client_details', 'state_code')) {
            DB::table('client_details')->where('user_id', $clientId)->update(['state_code' => $c['place_of_supply'],
                'gst_number' => DB::raw('COALESCE(NULLIF(gst_number, \'\'), ' . DB::getPdo()->quote($c['bill_to']['gstin'] ?: '') . ')')]);
        }

        $saved = DB::table('invoices')->find($id);
        $message = $issuing ? 'Invoice ' . $saved->invoice_number . ' issued.' : 'Saved as a draft.';

        return Reply::successWithData($message, ['redirectUrl' => route('invoices.show', $id)]);
    }

    /** The form's fields, dates as Y-m-d. */
    private function input(Request $request): array
    {
        $in = $request->only(['title', 'lines', 'place_of_supply', 'client_id', 'project_id', 'client_name', 'client_address', 'client_gstin', 'client_email',
            'po', 'reverse_charge', 'intro', 'notes', 'terms', 'bank', 'signatory', 'show_stamp']);
        $fmt = company()->date_format;
        foreach (['issue_date', 'due_date'] as $f) {
            $in[$f] = rescue(fn () => $request->filled($f) ? Carbon::createFromFormat($fmt, $request->input($f))->toDateString() : null, null, false);
        }

        return $in;
    }

    private function invoice($id): object
    {
        $invoice = DB::table('invoices')->where('company_id', company()->id)->where('id', $id)->first();
        abort_if(!$invoice, 404);

        return $invoice;
    }

    private function meta(object $invoice): array
    {
        return ['number' => $invoice->status === 'draft' ? null : $invoice->invoice_number, 'issue_date' => $invoice->issue_date,
            'due_date' => $invoice->due_date, 'status' => $invoice->status, 'project' => $this->projectName($invoice->project_id)];
    }

    private function projectName($id): ?string
    {
        return $id ? DB::table('projects')->where('id', $id)->value('project_name') : null;
    }

    /** Clients (Clients module): their name, company and GST details. */
    private function clientList()
    {
        return DB::table('users as u')->join('role_user as ru', 'ru.user_id', '=', 'u.id')->join('roles as r', 'r.id', '=', 'ru.role_id')
            ->leftJoin('client_details as cd', 'cd.user_id', '=', 'u.id')
            ->where('u.company_id', company()->id)->where('r.name', 'client')
            ->orderByRaw('COALESCE(NULLIF(cd.company_name, \'\'), u.name)')
            ->get(['u.id', 'u.name', 'u.email', 'cd.company_name', 'cd.gst_number']);
    }

    private function clientFields(int $userId): array
    {
        $c = DB::table('users as u')->leftJoin('client_details as cd', 'cd.user_id', '=', 'u.id')->where('u.company_id', company()->id)->where('u.id', $userId)
            ->first(['u.id', 'u.name', 'u.email', 'cd.company_name', 'cd.gst_number', 'cd.address', 'cd.city', 'cd.state', 'cd.postal_code',
                \Illuminate\Support\Facades\Schema::hasColumn('client_details', 'state_code') ? 'cd.state_code' : DB::raw('NULL as state_code')]);
        if (!$c) {
            return [];
        }
        $gstin = strtoupper(trim((string) $c->gst_number));
        $address = trim(implode("\n", array_filter([trim((string) $c->address), trim(implode(', ', array_filter([$c->city, $c->state])) . ($c->postal_code ? ' - ' . $c->postal_code : ''))])));

        return array_filter([
            'client_id' => $c->id,
            'client_name' => $c->company_name ?: $c->name,
            'client_address' => $address,
            'client_gstin' => $gstin,
            'client_email' => $c->email,
            'place_of_supply' => GstInvoice::stateOf($gstin) ?? $c->state_code ?? null,
        ], fn ($v) => $v !== null && $v !== '');
    }

    private function fail(string $message): never
    {
        abort(response()->json(['status' => 'fail', 'message' => $message, 'errors' => []], 422));
    }

}
