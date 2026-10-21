@php
    $fmt = company()->date_format;
    $current = $asset->currentAssignment;
    $actionIcons = [
        'created' => ['fa-plus-circle', 'text-success'], 'updated' => ['fa-edit', 'text-dark-grey'],
        'assigned' => ['fa-user-plus', 'text-primary'], 'returned' => ['fa-undo', 'text-success'],
        'repair' => ['fa-tools', 'text-warning'], 'repaired' => ['fa-check-circle', 'text-success'],
        'lost' => ['fa-exclamation-triangle', 'text-danger'], 'disposed' => ['fa-trash', 'text-secondary'],
        'serviced' => ['fa-wrench', 'text-primary'], 'renewal' => ['fa-file-signature', 'text-primary'],
        'renewed' => ['fa-redo', 'text-success'],
    ];
@endphp

<div class="bg-white rounded">
    <div class="d-flex justify-content-between align-items-center p-20 border-bottom-grey">
        <div>
            <h4 class="mb-1 f-21 font-weight-normal">{{ $asset->name }}</h4>
            <span class="f-13 text-dark-grey">{{ $asset->asset_code }}</span> {!! $asset->statusBadge() !!}
        </div>
        <div class="text-right">
            @if ($asset->status === 'available')
                <a href="{{ route('assets.assign-form', ['asset_id' => $asset->id]) }}" class="btn btn-primary rounded f-14 openRightModal">
                    <i class="fa fa-user-plus mr-1"></i> Assign</a>
            @elseif ($current)
                <a href="{{ route('assets.return-form', $current->id) }}" class="btn btn-primary rounded f-14 openRightModal">
                    <i class="fa fa-undo mr-1"></i> Take Return</a>
            @endif
            <a href="{{ route('assets.edit', $asset->id) }}" class="btn btn-secondary rounded f-14 openRightModal ml-1">
                <i class="fa fa-edit mr-1"></i> Edit</a>
        </div>
    </div>

    <div class="row p-20">
        <div class="col-md-8">
            <div class="row">
                @foreach ([
                    'Type' => $asset->type?->name, 'Serial Number' => $asset->serial_number,
                    'Brand / Model' => trim($asset->brand . ' ' . $asset->model), 'Branch' => $asset->branch?->branch_name,
                    'Condition' => \App\Models\Asset::CONDITIONS[$asset->condition] ?? $asset->condition,
                    'Purchase Date' => $asset->purchase_date?->format($fmt),
                    'Purchase Cost' => $asset->purchase_cost !== null ? number_format((float) $asset->purchase_cost, 2) : null,
                    'Vendor' => $asset->vendor, 'Warranty Until' => $asset->warranty_until?->format($fmt),
                ] as $label => $value)
                    <div class="col-sm-6 col-lg-4 mb-3">
                        <p class="f-12 text-dark-grey mb-1">{{ $label }}</p>
                        <p class="f-14 mb-0">{{ $value ?: '--' }}</p>
                    </div>
                @endforeach
                @if ($asset->notes)
                    <div class="col-12 mb-3">
                        <p class="f-12 text-dark-grey mb-1">Notes</p>
                        <p class="f-14 mb-0">{!! nl2br(e($asset->notes)) !!}</p>
                    </div>
                @endif
            </div>
        </div>
        <div class="col-md-4">
            @if ($asset->image_url)
                <img src="{{ $asset->image_url }}" class="img-fluid rounded border" alt="">
            @endif

            @if ($current && $current->user)
                <div class="border rounded p-3 mt-3" style="background:#f8f9fa">
                    <p class="f-12 text-dark-grey mb-1">Held by</p>
                    <a href="{{ route('employees.show', $current->user_id) }}?tab=assets" class="f-15 font-weight-600 text-darkest-grey">{{ $current->user->name }}</a>
                    <p class="f-13 mb-0 mt-1">Since {{ $current->assigned_on->format($fmt) }}</p>
                    @if ($current->expected_return_on)
                        <p class="f-13 mb-0 {{ $current->isOverdue() ? 'text-danger font-weight-600' : '' }}">
                            Return by {{ $current->expected_return_on->format($fmt) }}{{ $current->isOverdue() ? ' (overdue)' : '' }}</p>
                    @endif
                    <a href="{{ route('assets.handover', ['assignment' => $current->id]) }}" target="_blank" class="f-13 d-inline-block mt-2">
                        <i class="fa fa-print mr-1"></i>Print handover acknowledgement</a>
                </div>
            @endif
        </div>
    </div>

    @include('assets.ajax.upkeep')

    <div class="px-20 pb-20">
        <h5 class="f-14 font-weight-600 text-dark-grey border-top-grey pt-3">Hand-over History</h5>
        @if ($asset->assignments->isEmpty())
            <p class="f-13 text-dark-grey">Never assigned.</p>
        @else
            <div class="table-responsive">
                <table class="table table-bordered f-13 mb-0">
                    <thead class="bg-light"><tr>
                        <th>Employee</th><th>Assigned</th><th>Condition Out</th><th>Returned</th><th>Condition In</th><th>Recovery</th><th>Notes</th>
                    </tr></thead>
                    <tbody>
                    @foreach ($asset->assignments as $a)
                        <tr>
                            <td>{{ $a->user?->name ?? '--' }}</td>
                            <td>{{ $a->assigned_on->format($fmt) }}</td>
                            <td>{{ \App\Models\Asset::CONDITIONS[$a->condition_out] ?? ($a->condition_out ?: '--') }}</td>
                            <td>
                                @if ($a->returned_on)
                                    {{ $a->returned_on->format($fmt) }}
                                @else
                                    <span class="badge badge-primary">With employee</span>
                                @endif
                            </td>
                            <td>{{ $a->condition_in === 'lost' ? 'Lost' : (\App\Models\Asset::CONDITIONS[$a->condition_in] ?? ($a->condition_in ?: '--')) }}</td>
                            <td>{{ $a->recovery_amount ? number_format((float) $a->recovery_amount, 2) : '--' }}</td>
                            <td>{{ trim(($a->assign_notes ?? '') . ($a->return_notes ? ' / ' . $a->return_notes : ''), ' /') ?: '--' }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        <h5 class="f-14 font-weight-600 text-dark-grey mt-4">Timeline</h5>
        <ul class="list-unstyled mb-0">
            @foreach ($asset->histories as $h)
                @php [$icon, $colour] = $actionIcons[$h->action] ?? ['fa-circle', 'text-dark-grey']; @endphp
                <li class="d-flex mb-2 f-13">
                    <i class="fa {{ $icon }} {{ $colour }} mr-2 mt-1"></i>
                    <div>
                        <span class="font-weight-600">{{ ucfirst($h->action) }}</span>
                        @if ($h->user) {{ in_array($h->action, ['assigned']) ? 'to' : 'from' }} {{ $h->user->name }} @endif
                        @if ($h->notes) <span class="text-dark-grey">— {{ $h->notes }}</span> @endif
                        <br><span class="f-11 text-lightest">{{ $h->created_at->timezone(company()->timezone)->format($fmt . ' h:i A') }}{{ $h->doneBy ? ' · by ' . $h->doneBy->name : '' }}</span>
                    </div>
                </li>
            @endforeach
        </ul>
    </div>
</div>
