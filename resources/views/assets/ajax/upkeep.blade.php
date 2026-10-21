{{-- Asset view: Service & Repairs and Renewals. Expects $asset with services and renewals.next loaded. --}}
@php
    $fmt = company()->date_format;
    $live = !in_array($asset->status, ['lost', 'disposed'], true);
    $current = $asset->renewals->filter(fn ($r) => !$r->next)->sortBy('expires_on');
    $past = $asset->renewals->filter(fn ($r) => (bool) $r->next)->sortByDesc('expires_on');
    $repairCost = $asset->services->where('kind', 'repair')->sum(fn ($s) => (float) $s->cost);
    $serviceCost = $asset->services->where('kind', '!=', 'repair')->sum(fn ($s) => (float) $s->cost);
@endphp

<div class="px-20 pb-3">
    {{-- ---------------- Service & Repairs ---------------- --}}
    <div class="d-flex justify-content-between align-items-center border-top-grey pt-3 mb-2">
        <h5 class="f-14 font-weight-600 text-dark-grey mb-0">Service &amp; Repairs</h5>
        @if ($live)
            <a href="{{ route('assets.service-form', $asset->id) }}" class="btn btn-sm btn-outline-primary openRightModal">
                <i class="fa fa-tools mr-1"></i>Log Service / Repair</a>
        @endif
    </div>

    <div class="d-flex flex-wrap f-13 mb-2">
        <div class="mr-4 mb-1">
            <span class="text-dark-grey">Next service:</span>
            @if ($asset->next_service_on && $live)
                {!! \App\Models\Asset::dueBadge($asset->next_service_on) !!}
            @else
                <span>--</span>
            @endif
        </div>
        <div class="mr-4 mb-1">
            <span class="text-dark-grey">Service every:</span>
            {{ $asset->service_interval_months ? $asset->service_interval_months . ' month(s)' : 'not set' }}
            <a href="{{ route('assets.edit', $asset->id) }}" class="openRightModal f-12 ml-1">change</a>
        </div>
        <div class="mr-4 mb-1"><span class="text-dark-grey">Spent on repairs:</span> {{ number_format($repairCost, 2) }}</div>
        <div class="mb-1"><span class="text-dark-grey">On servicing:</span> {{ number_format($serviceCost, 2) }}</div>
    </div>

    @if ($asset->services->isEmpty())
        <p class="f-13 text-dark-grey mb-3">No service or repair recorded.</p>
    @else
        <div class="table-responsive mb-3">
            <table class="table table-sm table-bordered f-13 mb-0">
                <thead class="bg-light"><tr>
                    <th>Type</th><th>Vendor</th><th>Sent</th><th>Completed</th><th class="text-right">Cost</th><th>Notes</th><th></th>
                </tr></thead>
                <tbody>
                @foreach ($asset->services as $s)
                    <tr>
                        <td>{{ $s->kindLabel() }}@if ($s->reference)<br><span class="text-dark-grey f-11">{{ $s->reference }}</span>@endif</td>
                        <td>{{ $s->vendor ?: '--' }}</td>
                        <td class="text-nowrap">{{ $s->sent_on->format($fmt) }}</td>
                        <td class="text-nowrap">
                            @if ($s->completed_on)
                                {{ $s->completed_on->format($fmt) }}
                            @else
                                <span class="badge badge-warning">Open, {{ (int) $s->sent_on->diffInDays(now()) }} day(s)</span>
                            @endif
                        </td>
                        <td class="text-right">{{ $s->cost !== null ? number_format((float) $s->cost, 2) : '--' }}</td>
                        <td>{{ \Illuminate\Support\Str::limit($s->notes, 80) ?: '--' }}</td>
                        <td class="text-nowrap text-right">
                            @if ($s->billUrl())
                                <a href="{{ $s->billUrl() }}" target="_blank" title="Bill"><i class="fa fa-file-invoice"></i></a>
                            @endif
                            @if (!$s->completed_on)
                                <a href="{{ route('assets.service-complete-form', $s->id) }}" class="openRightModal ml-2 f-12 font-weight-600">Complete</a>
                            @endif
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @endif

    {{-- ---------------- Renewals ---------------- --}}
    <div class="d-flex justify-content-between align-items-center border-top-grey pt-3 mb-2">
        <h5 class="f-14 font-weight-600 text-dark-grey mb-0">Renewals</h5>
        @if ($live)
            <a href="{{ route('assets.renewal-form', $asset->id) }}" class="btn btn-sm btn-outline-primary openRightModal">
                <i class="fa fa-plus mr-1"></i>Add Renewal</a>
        @endif
    </div>

    @if ($asset->warranty_until && $live)
        <p class="f-13 mb-2"><span class="text-dark-grey">Warranty:</span> {!! \App\Models\Asset::dueBadge($asset->warranty_until) !!}</p>
    @endif

    @if ($current->isEmpty())
        <p class="f-13 text-dark-grey mb-2">No AMC, insurance, RC / PUC or licence recorded.</p>
    @else
        <div class="table-responsive mb-2">
            <table class="table table-sm table-bordered f-13 mb-0">
                <thead class="bg-light"><tr>
                    <th>What</th><th>Provider</th><th>Reference</th><th>Period</th><th>Expires</th><th class="text-right">Cost</th><th></th>
                </tr></thead>
                <tbody>
                @foreach ($current as $r)
                    <tr>
                        <td class="font-weight-500">{{ $r->kindLabel() }}</td>
                        <td>{{ $r->provider ?: '--' }}</td>
                        <td>{{ $r->reference_no ?: '--' }}</td>
                        <td class="text-nowrap">{{ $r->starts_on?->format($fmt) ?? '--' }} to {{ $r->expires_on->format($fmt) }}</td>
                        <td class="text-nowrap">{!! $live ? $r->dueBadge() : e($r->expires_on->format($fmt)) !!}</td>
                        <td class="text-right">{{ $r->cost !== null ? number_format((float) $r->cost, 2) : '--' }}</td>
                        <td class="text-nowrap text-right">
                            @if ($r->documentUrl())
                                <a href="{{ $r->documentUrl() }}" target="_blank" title="Document"><i class="fa fa-file-alt"></i></a>
                            @endif
                            @if ($live)
                                <a href="{{ route('assets.renewal-form', ['asset' => $asset->id, 'renew' => $r->id]) }}"
                                   class="openRightModal ml-2 f-12 font-weight-600">Renew</a>
                            @endif
                            <a href="javascript:;" class="delete-renewal text-danger ml-2" data-renewal-id="{{ $r->id }}" title="Remove"><i class="fa fa-trash"></i></a>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @endif

    @if ($past->isNotEmpty())
        <details class="f-13 mb-2">
            <summary class="text-dark-grey">Earlier periods ({{ $past->count() }})</summary>
            <table class="table table-sm f-12 mt-2 mb-0">
                <tbody>
                @foreach ($past as $r)
                    <tr>
                        <td>{{ $r->kindLabel() }}</td>
                        <td>{{ $r->provider ?: '--' }}</td>
                        <td>{{ $r->reference_no ?: '--' }}</td>
                        <td class="text-nowrap">{{ $r->starts_on?->format($fmt) ?? '--' }} to {{ $r->expires_on->format($fmt) }}</td>
                        <td class="text-right">{{ $r->cost !== null ? number_format((float) $r->cost, 2) : '--' }}</td>
                        <td class="text-right">@if ($r->documentUrl())<a href="{{ $r->documentUrl() }}" target="_blank"><i class="fa fa-file-alt"></i></a>@endif</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </details>
    @endif
</div>

<script>
    $('.delete-renewal').off('click').on('click', function () {
        const id = $(this).data('renewal-id');
        Swal.fire({
            title: "@lang('messages.sweetAlertTitle')",
            text: 'Remove this renewal period? If it renewed an earlier one, that one becomes current again.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: "@lang('messages.confirmDelete')",
            cancelButtonText: "@lang('app.cancel')",
            customClass: {confirmButton: 'btn btn-primary mr-3', cancelButton: 'btn btn-secondary'},
            buttonsStyling: false
        }).then((result) => {
            if (result.isConfirmed) {
                $.easyAjax({
                    url: "{{ route('assets.renewal-destroy', ':id') }}".replace(':id', id),
                    type: 'POST',
                    blockUI: true,
                    data: {_token: '{{ csrf_token() }}', _method: 'DELETE'},
                    success: function (response) {
                        if (response.status === 'success') {
                            if (typeof closeTaskDetail === 'function' && $(RIGHT_MODAL).hasClass('in')) { closeTaskDetail(); }
                            var table = window.LaravelDataTables && window.LaravelDataTables['assets-table'];
                            table ? table.draw(false) : window.location.reload();
                        }
                    }
                });
            }
        });
    });
</script>
