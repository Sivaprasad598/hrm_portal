<div class="modal-header">
    <h5 class="modal-title">@lang('app.paymentLogs')
        @if (isset($recurring))
            &nbsp;&mdash;&nbsp;<span class="f-14 text-dark-grey">{{ optional($recurring->client)->name ?? '--' }}</span>
        @endif
    </h5>
    <button type="button" class="close" data-dismiss="modal" aria-label="@lang('app.close')">
        <span aria-hidden="true">&times;</span>
    </button>
</div>
<div class="modal-body">
    @if (isset($logs) && $logs->isNotEmpty())
    <div class="table-responsive">
        <table class="table table-sm table-hover border-0">
            <thead class="bg-additional-grey">
                <tr>
                    <th class="f-13 text-dark-grey">#</th>
                    <th class="f-13 text-dark-grey">@lang('app.date')</th>
                    <th class="f-13 text-dark-grey">@lang('app.invoiceNumber')</th>
                    <th class="f-13 text-dark-grey">@lang('app.status')</th>
                    <th class="f-13 text-dark-grey">HTTP</th>
                    <th class="f-13 text-dark-grey">@lang('app.message')</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($logs as $log)
                <tr>
                    <td class="f-13">{{ $loop->iteration }}</td>
                    <td class="f-13 text-dark-grey">
                        {{ $log->created_at->timezone(company()->timezone)->format(company()->date_format . ' H:i') }}
                    </td>
                    <td class="f-13">
                        @if ($log->invoice)
                            <a href="{{ route('invoices.show', $log->invoice->id) }}" target="_blank" class="text-dark-grey">
                                {{ $log->invoice->invoice_number }}
                            </a>
                        @else
                            <span class="text-muted">--</span>
                        @endif
                    </td>
                    <td>
                        @if ($log->status === 'success')
                            <span class="badge badge-success f-12">@lang('app.success')</span>
                        @else
                            <span class="badge badge-danger f-12">@lang('app.failed')</span>
                        @endif
                    </td>
                    <td class="f-13 text-dark-grey">
                        @if ($log->response_code > 0)
                            <span class="{{ $log->response_code === 200 ? 'text-success' : 'text-danger' }}">
                                {{ $log->response_code }}
                            </span>
                        @else
                            <span class="text-muted">--</span>
                        @endif
                    </td>
                    <td class="f-12 text-dark-grey" style="max-width:300px; word-break:break-word;">
                        {{ \Illuminate\Support\Str::limit($log->message, 120) }}
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @else
        <div class="text-center py-4 text-muted f-14">@lang('messages.noRecordFound')</div>
    @endif
</div>
<div class="modal-footer">
    <button type="button" class="btn btn-secondary" data-dismiss="modal">@lang('app.close')</button>
</div>
