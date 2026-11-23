@extends('layouts.app')

{{-- Finance -> Invoices -> one invoice (InvoiceController::show): the invoice as it prints, with what
     can be done to it - a draft is edited, issued or deleted; an issued one downloaded, duplicated
     or cancelled (its number stays used). --}}
@section('content')
@php
    $money = fn ($v) => '₹' . \AppModules\Finance\Services\GstInvoice::money($v);
    $overdue = in_array($invoice->status, ['unpaid', 'partial'], true) && $invoice->due_date < now()->toDateString();
    [$label, $tone] = match (true) {
        $invoice->status === 'draft' => ['Draft', 'grey'],
        $invoice->status === 'canceled' => ['Cancelled', 'red'],
        $invoice->status === 'paid' => ['Paid', 'green'],
        $overdue => ['Overdue', 'amber'],
        default => ['Issued', 'blue'],
    };
@endphp
<div class="content-wrapper" data-swap-links>
    <div class="iv-head">
        <div>
            <a href="{{ route('invoices.index') }}" class="f-13 text-dark-grey"><i class="fa fa-arrow-left mr-1"></i>Invoices</a>
            <h3>{{ $invoice->status === 'draft' ? 'Draft invoice' : $invoice->invoice_number }} <span class="iv-pill iv-pill--{{ $tone }}">{{ $label }}</span></h3>
            <p>{{ $d['bill_to']['name'] ?? '—' }} · <b>{{ $money($invoice->total) }}</b> · due {{ \Carbon\Carbon::parse($invoice->due_date)->format('d M Y') }}
                @if ($projectName) · {{ $projectName }}@endif</p>
            @if ($invoice->status === 'canceled' && $invoice->cancel_reason)<p class="text-danger mb-0"><i class="fa fa-ban mr-1"></i>Cancelled: {{ $invoice->cancel_reason }}</p>@endif
        </div>
        <div class="iv-head-actions">
            @if ($invoice->status === 'draft')
                <button type="button" class="btn btn-secondary" id="iv-delete"><i class="fa fa-trash mr-1"></i>Delete</button>
                <a href="{{ route('invoices.edit', $invoice->id) }}" class="btn btn-primary"><i class="fa fa-pen mr-1"></i>Edit and issue</a>
            @else
                @if (in_array($invoice->status, ['unpaid', 'partial'], true))
                    <button type="button" class="btn btn-secondary" id="iv-cancel"><i class="fa fa-ban mr-1"></i>Cancel</button>
                @endif
                <a href="{{ route('invoices.create', ['from' => $invoice->id]) }}" class="btn btn-secondary"><i class="fa fa-copy mr-1"></i>Duplicate</a>
            @endif
            <a href="{{ route('invoices.download', $invoice->id) }}" class="btn btn-primary"><i class="fa fa-file-pdf mr-1"></i>Download PDF</a>
        </div>
    </div>

    <div class="iv-paper">
        <iframe src="{{ route('invoices.sheet', $invoice->id) }}" title="Invoice"></iframe>
    </div>
</div>

<style>
    .iv-head { display: flex; flex-wrap: wrap; justify-content: space-between; align-items: flex-end; gap: 14px; margin-bottom: 16px; }
    .iv-head h3 { font-size: 22px; margin: 6px 0 4px; color: #101828; display: flex; align-items: center; gap: 10px; }
    .iv-head p { margin: 0; color: #475467; font-size: 13.5px; }
    .iv-head-actions { display: flex; flex-wrap: wrap; gap: 8px; }
    .iv-head-actions .btn { height: 38px; display: inline-flex; align-items: center; }
    .iv-pill { font-size: 12px; font-weight: 600; border-radius: 12px; padding: 3px 10px; }
    .iv-pill--grey { background: #f2f4f7; color: #475467; } .iv-pill--blue { background: #eff8ff; color: #175cd3; }
    .iv-pill--amber { background: #fffaeb; color: #b54708; } .iv-pill--red { background: #fef3f2; color: #b42318; } .iv-pill--green { background: #ecfdf3; color: #067647; }
    .iv-paper { max-width: 900px; margin: 0 auto; background: #fff; border-radius: 10px; box-shadow: 0 1px 4px rgba(16, 24, 40, .1); overflow: hidden; }
    .iv-paper iframe { width: 100%; height: calc(100vh - 210px); min-height: 640px; border: 0; display: block; }
</style>
<script>
    $(function () {
        $('#iv-cancel').on('click', function () {
            Swal.fire({
                title: 'Cancel {{ $invoice->invoice_number }}?', input: 'textarea', inputLabel: 'Why',
                inputPlaceholder: 'e.g. Raised on the wrong client; replaced by a new invoice',
                text: 'The number stays used and the invoice is kept, marked cancelled.', showCancelButton: true,
                confirmButtonText: 'Cancel invoice', cancelButtonText: 'Keep it', reverseButtons: true,
                customClass: {confirmButton: 'btn btn-danger mr-3', cancelButton: 'btn btn-secondary'}, buttonsStyling: false,
                inputValidator: function (v) { if (!$.trim(v)) { return 'Say why it is cancelled.'; } }
            }).then(function (res) {
                if (!res.isConfirmed) { return; }
                $.easyAjax({url: '{{ route('invoices.cancel', $invoice->id) }}', type: 'POST', blockUI: true,
                    data: {_token: '{{ csrf_token() }}', reason: res.value}, success: function (r) { if (r.status === 'success') { PageSwap.reload(); } }});
            });
        });
        $('#iv-delete').on('click', function () {
            Swal.fire({
                title: 'Delete this draft?', text: 'It was never issued, so nothing else changes.', icon: 'warning', showCancelButton: true,
                confirmButtonText: 'Delete', cancelButtonText: 'Keep it', reverseButtons: true,
                customClass: {confirmButton: 'btn btn-danger mr-3', cancelButton: 'btn btn-secondary'}, buttonsStyling: false
            }).then(function (res) {
                if (!res.isConfirmed) { return; }
                $.easyAjax({url: '{{ route('invoices.destroy', $invoice->id) }}', type: 'POST', blockUI: true,
                    data: {_token: '{{ csrf_token() }}', _method: 'DELETE'}, success: function (r) { if (r.status === 'success') { PageSwap.open(r.redirectUrl); } }});
            });
        });
    });
</script>
@endsection
