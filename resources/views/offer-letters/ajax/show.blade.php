{{-- One offer letter with its actions: the page (offer-letters.show) and the slide-in panel
     (Dashboard -> Upcoming Joinings) both show this. --}}
<div class="{{ request()->ajax() ? 'p-3' : '' }}">
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap">
        <div class="mb-2">
            @unless (request()->ajax())
                <a href="{{ route('offer-letters.index') }}" class="f-13 text-dark-grey"><i class="fa fa-arrow-left mr-1"></i>Issued Letters</a>
            @endunless
            <span class="f-15 font-weight-bold {{ request()->ajax() ? '' : 'ml-3' }}">{{ $employee->name }}</span>
            <span class="f-13 text-dark-grey ml-2">Reference {{ $letter->reference_no }}</span>
            @if (!empty($letter->joining_date))
                <span class="f-13 text-dark-grey ml-2">· Joins {{ \Carbon\Carbon::parse($letter->joining_date)->format(company()->date_format) }}</span>
            @endif
            @if (($letter->source ?? '') === 'manual')<span class="badge badge-light ml-2">From the form</span>@endif
            @if (!empty($letter->cancelled_at))<span class="badge badge-danger ml-2">Offer withdrawn</span>@endif
        </div>
        <div class="mb-2">
            @if (empty($letter->cancelled_at))
                <a href="javascript:;" class="btn btn-cancel rounded f-14 p-2 mr-2 withdraw-offer" data-url="{{ route('offer-letters.cancel', $letter->id) }}"><i class="fa fa-ban mr-1"></i>Withdraw offer</a>
            @endif
            <a href="{{ route('offer-letters.generate', ['type' => 'offer', 'duplicate' => $letter->id]) }}" class="btn btn-secondary rounded f-14 p-2 mr-2">
                <i class="fa fa-copy mr-1"></i>Duplicate
            </a>
            <a href="{{ route('offer-letters.pdf', [$letter->id, 'inline' => 1]) }}" target="_blank" class="btn btn-secondary rounded f-14 p-2 mr-2">
                <i class="fa fa-print mr-1"></i>Print
            </a>
            <a href="{{ route('offer-letters.pdf', $letter->id) }}" class="btn btn-primary rounded f-14 p-2">
                <i class="fa fa-download mr-1"></i>Download PDF
            </a>
        </div>
    </div>

    @if ($problems)
        <div class="alert alert-warning f-13">
            The employee's record has changed since this letter was issued and now needs:
            <ul class="mb-0 mt-1">@foreach ($problems as $p)<li>{{ $p }}</li>@endforeach</ul>
            @if ($employee->id)<a href="{{ route('employees.edit', $employee->id) }}">Edit the employee</a> - @endif the letter shows their record as it is now.
        </div>
    @endif

    <div class="f-12 text-dark-grey mb-2">
        @if (!empty($letter->details))
            The letter prints the details it was issued with.
        @else
            The letter shows the employee's record as it is now, with this reference and date.
        @endif
        @if ($letter->user_id) Downloading or printing keeps a copy in the employee's Documents (Offer Letter) and ticks it on the joining checklist. @endif
    </div>

    <iframe src="{{ route('offer-letters.view', $letter->id) }}" class="w-100 border rounded"
            style="height: calc(100vh - {{ request()->ajax() ? '190' : '230' }}px); min-height: 600px; background: #e9ecef;" title="Offer letter"></iframe>
</div>

<script>
    // Withdrawn: off Upcoming Joinings and no joining-day reminder; the letter itself stays on record.
    $(document).off('click.offerWithdraw').on('click.offerWithdraw', '.withdraw-offer', function () {
        const url = $(this).data('url');
        Swal.fire({
            title: 'Withdraw this offer?', icon: 'warning',
            text: 'It comes off Upcoming Joinings and no joining-day reminder is sent. The letter stays on record.',
            showCancelButton: true, confirmButtonText: 'Withdraw',
            customClass: {confirmButton: 'btn btn-primary mr-3', cancelButton: 'btn btn-secondary'}, buttonsStyling: false
        }).then(function (r) {
            if (!r.isConfirmed) { return; }
            $.easyAjax({url: url, type: 'POST', blockUI: true, data: {_token: '{{ csrf_token() }}'},
                success: function (res) { if (res.status === 'success') { window.location.reload(); } }});
        });
    });
</script>
