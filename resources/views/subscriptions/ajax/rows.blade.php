@forelse ($subscriptions as $sub)
<tr>
    <td class="f-14">{{ $loop->iteration }}</td>
    <td>
        @if ($sub->client)
            <div class="d-flex align-items-center">
                @if ($sub->client->image)
                    <img src="{{ asset_url_local_s3('user-uploads/avatar/' . $sub->client->image) }}"
                         class="rounded-circle mr-2" width="32" height="32" alt="">
                @else
                    <div class="rounded-circle bg-additional-grey mr-2 d-flex align-items-center justify-content-center"
                         style="width:32px;height:32px;">
                        <i class="fa fa-user text-lightest f-12"></i>
                    </div>
                @endif
                <span class="f-14 text-dark-grey">{{ $sub->client->name }}</span>
            </div>
        @else
            <span class="text-muted">--</span>
        @endif
    </td>
    <td>
        @if ($sub->status === 'active')
            <span class="badge badge-success sub-status-badge">@lang('app.active')</span>
        @else
            <span class="badge badge-danger sub-status-badge">@lang('app.inactive')</span>
        @endif
    </td>
    <td class="f-14">
        {{ $sub->currency ? $sub->currency->currency_symbol : '$' }}{{ number_format($sub->total, 2) }}
    </td>
    <td class="f-14">{{ ucfirst($sub->rotation) }}</td>
    <td class="f-14">
        @php $fails = $sub->failed_payment_attempts ?? 0; @endphp
        @if ($fails > 0)
            <span class="badge badge-warning">{{ $fails }}</span>
        @else
            <span class="text-muted">0</span>
        @endif
    </td>
    <td class="f-14 text-dark-grey">
        {{ $sub->next_invoice_date ? $sub->next_invoice_date->format(company()->date_format) : '--' }}
    </td>
    <td>
        <div class="task_view d-flex align-items-center">
            <a href="javascript:;"
               class="btn-view-logs mr-2"
               data-url="{{ route('subscriptions.logs', $sub->id) }}"
               data-toggle="tooltip"
               data-original-title="@lang('app.paymentLogs')">
                <i class="fa fa-history f-17 text-lightest"></i>
            </a>

            @if ($sub->status === 'active')
            <a href="javascript:;"
               class="btn-cancel-subscription"
               data-id="{{ $sub->id }}"
               data-toggle="tooltip"
               data-original-title="@lang('app.cancel')">
                <i class="fa fa-ban f-17 text-danger"></i>
            </a>
            @endif
        </div>
    </td>
</tr>
@empty
<tr>
    <td colspan="8" class="text-center py-4 text-muted f-14">
        @lang('messages.noRecordFound')
    </td>
</tr>
@endforelse
