@php $fmt = company()->date_format; @endphp

<div class="bg-white rounded b-shadow-4 p-20">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4 class="f-18 font-weight-normal mb-0">Assets held now <span class="badge badge-primary ml-1">{{ $heldAssets->count() }}</span></h4>
        <div>
            @if ($manageAssets && $heldAssets->isNotEmpty())
                <a href="{{ route('assets.handover', ['user' => $employee->id]) }}" target="_blank" class="btn btn-secondary rounded f-14 mr-1">
                    <i class="fa fa-print mr-1"></i>Print Acknowledgement</a>
            @endif
            @if ($manageAssets && $employee->status === 'active')
                <a href="{{ route('assets.assign-form', ['user_id' => $employee->id]) }}" class="btn btn-primary rounded f-14 openRightModal">
                    <i class="fa fa-user-plus mr-1"></i>Assign Asset</a>
            @endif
        </div>
    </div>

    <div class="table-responsive">
        <table class="table table-bordered f-14 mb-0">
            <thead class="bg-light">
                <tr>
                    <th>Asset</th><th>Type</th><th>Serial Number</th><th>Assigned On</th><th>Expected Return</th><th>Condition</th>
                    @if ($manageAssets)<th class="text-right">Action</th>@endif
                </tr>
            </thead>
            <tbody>
            @forelse ($heldAssets as $a)
                <tr>
                    <td>
                        @if ($manageAssets)
                            <a href="{{ route('assets.show', $a->asset_id) }}" class="openRightModal text-darkest-grey font-weight-500">{{ $a->asset?->name }}</a>
                        @else
                            <span class="font-weight-500">{{ $a->asset?->name }}</span>
                        @endif
                        <br><span class="f-11 text-dark-grey">{{ $a->asset?->asset_code }}</span>
                    </td>
                    <td>{{ $a->asset?->type?->name ?? '--' }}</td>
                    <td>{{ $a->asset?->serial_number ?: '--' }}</td>
                    <td>{{ $a->assigned_on->format($fmt) }}</td>
                    <td>
                        @if ($a->expected_return_on)
                            <span class="{{ $a->isOverdue() ? 'text-danger font-weight-600' : '' }}">{{ $a->expected_return_on->format($fmt) }}</span>
                            @if ($a->isOverdue()) <span class="badge badge-danger">Overdue</span> @endif
                        @else
                            --
                        @endif
                    </td>
                    <td>{{ \App\Models\Asset::CONDITIONS[$a->condition_out] ?? ($a->condition_out ?: '--') }}</td>
                    @if ($manageAssets)
                        <td class="text-right">
                            <a href="{{ route('assets.return-form', $a->id) }}" class="btn btn-sm btn-outline-primary openRightModal"><i class="fa fa-undo mr-1"></i>Take Return</a>
                        </td>
                    @endif
                </tr>
            @empty
                <tr><td colspan="7" class="text-center text-dark-grey py-4">No asset is with {{ $employee->id == user()->id ? 'you' : $employee->name }} now.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    @if ($pastAssets->isNotEmpty())
        <h5 class="f-15 font-weight-600 text-dark-grey mt-4 mb-2">Returned</h5>
        <div class="table-responsive">
            <table class="table table-sm table-bordered f-13 mb-0">
                <thead class="bg-light"><tr><th>Asset</th><th>Assigned</th><th>Returned</th><th>Condition In</th><th>Recovery</th></tr></thead>
                <tbody>
                @foreach ($pastAssets as $a)
                    <tr>
                        <td>{{ $a->asset?->name }} <span class="text-dark-grey">({{ $a->asset?->asset_code }})</span></td>
                        <td>{{ $a->assigned_on->format($fmt) }}</td>
                        <td>{{ $a->returned_on->format($fmt) }}</td>
                        <td>{{ $a->condition_in === 'lost' ? 'Lost' : (\App\Models\Asset::CONDITIONS[$a->condition_in] ?? ($a->condition_in ?: '--')) }}</td>
                        <td>{{ $a->recovery_amount ? number_format((float) $a->recovery_amount, 2) : '--' }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
