@extends('layouts.app')

{{-- Finance -> Invoices (InvoiceController::index): the financial year at a glance, then the invoices
     (drafts first). Filters at the top right apply at once. --}}
@section('content')
@include('recruitment.partials.styles')
@php
    $money = fn ($v) => '₹' . \App\Services\Payroll\SalaryRevisionLetter::inr($v);
    $states = \AppModules\Finance\Services\GstInvoice::STATES;
@endphp
<div class="content-wrapper" data-swap-links>

    <form method="GET" action="{{ route('invoices.index') }}" id="rc-filter-form" class="bg-white rounded b-shadow-4 p-3 mb-3">
        <div class="row align-items-end">
            <div class="col-md-2 mb-2">
                <label class="f-13 text-dark-grey mb-1">Financial year</label>
                <select name="fy" class="form-control select-picker">
                    @foreach ($years as $y)<option value="{{ $y }}" @selected($fy === $y)>20{{ $y }}</option>@endforeach
                    <option value="all" @selected($fy === 'all')>All years</option>
                </select>
            </div>
            <div class="col-md-2 mb-2">
                <label class="f-13 text-dark-grey mb-1">Status</label>
                <select name="status" class="form-control select-picker">
                    <option value="">All</option>
                    @foreach (['draft' => 'Draft', 'issued' => 'Issued', 'overdue' => 'Overdue', 'cancelled' => 'Cancelled'] as $k => $l)
                        <option value="{{ $k }}" @selected(request('status') === $k)>{{ $l }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3 mb-2">
                <label class="f-13 text-dark-grey mb-1">Client</label>
                <select name="client" class="form-control select-picker" data-live-search="true" data-size="8">
                    <option value="">All</option>
                    @foreach ($clients as $cl)<option value="{{ $cl->id }}" @selected(request('client') == $cl->id)>{{ $cl->company_name ?: $cl->name }}</option>@endforeach
                </select>
            </div>
            <div class="col-md-2 mb-2">
                <label class="f-13 text-dark-grey mb-1">Project</label>
                <select name="project" class="form-control select-picker" data-live-search="true" data-size="8">
                    <option value="">All</option>
                    @foreach ($projects as $p)<option value="{{ $p->id }}" @selected(request('project') == $p->id)>{{ $p->project_name }}</option>@endforeach
                </select>
            </div>
            <div class="col-md-3 mb-2">
                <label class="f-13 text-dark-grey mb-1">Search</label>
                <input type="text" name="search" value="{{ request('search') }}" class="form-control f-14" placeholder="Number or client">
            </div>
        </div>
    </form>

    <div class="d-inline-flex" data-rc-bar>
        <a href="{{ route('invoices.create') }}" class="btn btn-primary"><i class="fa fa-plus mr-1"></i>New Invoice</a>
        <a href="{{ route('invoices.settings') }}" class="btn btn-secondary openRightModal ml-2" title="GSTIN, numbering, bank details"><i class="fa fa-cog"></i></a>
    </div>

    {{-- The year --}}
    <div class="iv-tiles">
        <div class="iv-tile"><span>Invoiced · {{ $fy === 'all' ? 'all years' : 'FY 20' . $fy }}</span><b>{{ $money($summary['invoiced']) }}</b><small>{{ $summary['count'] }} {{ \Illuminate\Support\Str::plural('invoice', $summary['count']) }} issued</small></div>
        <div class="iv-tile"><span>GST on them</span><b>{{ $money($summary['tax']) }}</b><small>CGST + SGST + IGST</small></div>
        <div class="iv-tile {{ $summary['overdue'] > 0 ? 'is-bad' : '' }}"><span>Overdue</span><b>{{ $money($summary['overdue']) }}</b><small>{{ $summary['overdue_count'] }} past the due date</small></div>
        <div class="iv-tile"><span>Drafts</span><b>{{ $summary['drafts'] }}</b><small>not issued yet</small></div>
    </div>

    <div class="rc-list-card">
        <div class="table-responsive">
            <table class="table iv-table mb-0">
                <thead>
                    <tr><th>Invoice</th><th>Client</th><th>Date</th><th>Due</th><th class="text-right">Taxable</th><th class="text-right">GST</th><th class="text-right">Total</th><th>Status</th><th></th></tr>
                </thead>
                <tbody>
                    @forelse ($invoices as $inv)
                        @php
                            $overdue = in_array($inv->status, ['unpaid', 'partial'], true) && $inv->due_date < $today;
                            [$label, $tone] = match (true) {
                                $inv->status === 'draft' => ['Draft', 'grey'],
                                $inv->status === 'canceled' => ['Cancelled', 'red'],
                                $inv->status === 'paid' => ['Paid', 'green'],
                                $overdue => ['Overdue', 'amber'],
                                default => ['Issued', 'blue'],
                            };
                        @endphp
                        <tr class="{{ $inv->status === 'canceled' ? 'is-off' : '' }}">
                            <td>
                                <a href="{{ route('invoices.show', $inv->id) }}" class="iv-no">{{ $inv->status === 'draft' ? 'Draft' : $inv->invoice_number }}</a>
                                <small>{{ $inv->d['title'] ?? 'Tax Invoice' }}{{ $inv->project_name ? ' · ' . $inv->project_name : '' }}</small>
                            </td>
                            <td>
                                <b class="iv-client">{{ $inv->d['bill_to']['name'] ?? '—' }}</b>
                                <small>{{ ($inv->d['bill_to']['gstin'] ?? '') ?: 'Unregistered' }} · {{ $states[$inv->place_of_supply] ?? '' }}</small>
                            </td>
                            <td class="text-nowrap">{{ \Carbon\Carbon::parse($inv->issue_date)->format('d M Y') }}</td>
                            <td class="text-nowrap {{ $overdue ? 'text-danger' : '' }}">{{ \Carbon\Carbon::parse($inv->due_date)->format('d M Y') }}</td>
                            <td class="text-right">{{ $money($inv->sub_total) }}</td>
                            <td class="text-right">{{ $money($inv->cgst + $inv->sgst + $inv->igst) }}<small>{{ $inv->supply_type === 'inter' ? 'IGST' : 'CGST+SGST' }}</small></td>
                            <td class="text-right"><b>{{ $money($inv->total) }}</b></td>
                            <td><span class="iv-pill iv-pill--{{ $tone }}">{{ $label }}</span></td>
                            <td class="text-right text-nowrap">
                                <a href="{{ route('invoices.download', $inv->id) }}" class="iv-icon" title="Download PDF"><i class="fa fa-file-pdf"></i></a>
                                @if ($inv->status === 'draft')
                                    <a href="{{ route('invoices.edit', $inv->id) }}" class="iv-icon" title="Edit"><i class="fa fa-pen"></i></a>
                                @else
                                    <a href="{{ route('invoices.create', ['from' => $inv->id]) }}" class="iv-icon" title="Duplicate"><i class="fa fa-copy"></i></a>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="9" class="p-4"><x-cards.no-record icon="file-invoice" message="{{ request()->anyFilled(['status', 'client', 'project', 'search']) ? 'No invoices match.' : 'No invoices yet. Make the first one with New Invoice.' }}" /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @include('recruitment.partials.list-footer', ['paginator' => $invoices, 'sizes' => [25, 50, 100]])
    </div>
</div>

<style>
    .iv-tiles { display: grid; grid-template-columns: repeat(4, 1fr); gap: 12px; margin-bottom: 16px; }
    .iv-tile { background: #fff; border-radius: 12px; box-shadow: 0 1px 3px rgba(16, 24, 40, .06); padding: 16px 18px; display: flex; flex-direction: column; }
    .iv-tile span { font-size: 11.5px; font-weight: 600; text-transform: uppercase; letter-spacing: .05em; color: #667085; }
    .iv-tile b { font-size: 22px; color: #101828; margin: 4px 0 2px; }
    .iv-tile small { font-size: 12px; color: #98a2b3; }
    .iv-tile.is-bad b { color: #b42318; }
    .iv-table th { font-size: 11.5px; font-weight: 600; text-transform: uppercase; letter-spacing: .04em; color: #667085; background: #f9fafb; border-top: 0 !important; white-space: nowrap; padding: 12px 14px !important; }
    .iv-table td { vertical-align: middle !important; padding: 11px 14px !important; font-size: 13.5px; border-top: 1px solid #f2f4f7 !important; }
    .iv-table td small { display: block; font-size: 11.5px; color: #98a2b3; }
    .iv-table tr.is-off td { opacity: .55; }
    .iv-no { font-weight: 600; color: #101828; } .iv-no:hover { color: var(--epic-blue, #cb6b4f); text-decoration: none; }
    .iv-client { font-weight: 600; color: #344054; }
    .iv-pill { font-size: 12px; font-weight: 600; border-radius: 12px; padding: 3px 10px; white-space: nowrap; }
    .iv-pill--grey { background: #f2f4f7; color: #475467; } .iv-pill--blue { background: #eff8ff; color: #175cd3; }
    .iv-pill--amber { background: #fffaeb; color: #b54708; } .iv-pill--red { background: #fef3f2; color: #b42318; } .iv-pill--green { background: #ecfdf3; color: #067647; }
    .iv-icon { display: inline-grid; place-items: center; width: 30px; height: 30px; border-radius: 7px; color: #667085; }
    .iv-icon:hover { background: #f2f4f7; color: #101828; text-decoration: none; }
    @media (max-width: 991px) { .iv-tiles { grid-template-columns: 1fr 1fr; } }
</style>
@include('recruitment.partials.standard-bar', ['form' => '#rc-filter-form', 'searchLabel' => 'Search invoices', 'active' => request()->anyFilled(['status', 'client', 'project', 'search']) || $fy !== \AppModules\Finance\Services\GstInvoice::financialYear(now())])
@endsection
