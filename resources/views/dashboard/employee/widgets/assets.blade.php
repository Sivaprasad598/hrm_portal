{{-- Assets: the register at a glance for HR and admins - each count opens the
     Asset List filtered to it; for everybody else, what they hold, opening
     their own assigned assets. --}}
@if (\Illuminate\Support\Facades\Schema::hasTable('assets'))
    @php
        $manageAssets = user()->permission('manage_assets') === 'all' && in_array('assets', dashboard_modules());

        if ($manageAssets) {
            $assetCounts = \App\Models\Asset::selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status');
            $assetOverdue = \App\Models\AssetAssignment::whereNull('returned_on')->whereDate('expected_return_on', '<', now()->toDateString())->count();
            $assetWarranty = \App\Models\Asset::whereNotIn('status', ['lost', 'disposed'])
                ->whereBetween('warranty_until', [now()->toDateString(), now()->addDays(30)->toDateString()])->count();
            $hasDue = \Illuminate\Support\Facades\Schema::hasColumn('assets', 'next_due_on');
            $assetDueOverdue = $hasDue ? \App\Models\Asset::whereDate('next_due_on', '<', now()->toDateString())->count() : 0;
            $assetDueSoon = $hasDue ? \App\Models\Asset::whereBetween('next_due_on', [now()->toDateString(), now()->addDays(30)->toDateString()])->count() : 0;
        } else {
            $myAssets = \App\Services\Assets\AssetFlow::heldBy((int) user()->id);
            $myAssetsUrl = route('employees.show', user()->id) . '?tab=assets';
        }
    @endphp

    <style>
        .asset-stats { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 8px; text-align: center; }
        .asset-stats a { display: block; padding: 8px 4px; border-radius: 8px; color: inherit; }
        .asset-stats a:hover { background: #f2f4f7; text-decoration: none; }
        .asset-stats h3 { margin: 0; font-weight: 700; line-height: 1.2; }
        .asset-stats span { display: block; font-size: 12px; color: #667085; line-height: 1.3; }
        .my-asset-row { display: flex; justify-content: space-between; gap: 8px; padding: 6px 0; font-size: 13px; border-bottom: 1px solid #eaecf0; color: inherit; }
        .my-asset-row:hover { text-decoration: none; color: #1d82f5; }
    </style>

    @if ($manageAssets)
        <div class="col-md-6 mb-3">
            <div class="bg-white p-20 rounded b-shadow-4 h-100">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="f-15 f-w-500 mb-0 text-darkest-grey"><i class="bi bi-laptop text-primary mr-2"></i>Assets</h5>
                    <a href="{{ route('assets.index') }}" class="f-12">Asset List</a>
                </div>
                <div class="asset-stats">
                    @foreach ([['Total', $assetCounts->sum(), 'text-dark', null], ['Available', $assetCounts['available'] ?? 0, 'text-success', 'available'],
                               ['Assigned', $assetCounts['assigned'] ?? 0, 'text-primary', 'assigned'], ['In Repair', $assetCounts['repair'] ?? 0, 'text-warning', 'repair']] as [$label, $n, $class, $status])
                        <a href="{{ route('assets.index', $status ? ['status' => $status] : []) }}" title="{{ $label }}: open the list">
                            <h3 class="{{ $class }}">{{ $n }}</h3>
                            <span>{{ $label }}</span>
                        </a>
                    @endforeach
                </div>
                @if ($assetOverdue || $assetWarranty || $assetDueOverdue || $assetDueSoon)
                    <div class="mt-3 pt-2 border-top-grey f-13">
                        @if ($assetOverdue)
                            <a href="{{ route('assets.assigned', ['overdue' => 1]) }}" class="d-block text-danger">
                                <i class="fa fa-clock mr-1"></i>{{ $assetOverdue }} asset(s) past their return date</a>
                        @endif
                        @if ($assetDueOverdue)
                            <a href="{{ route('assets.index') }}" class="d-block text-danger mt-1">
                                <i class="fa fa-calendar-times mr-1"></i>{{ $assetDueOverdue }} asset(s) with a service, renewal or warranty overdue</a>
                        @endif
                        @if ($assetDueSoon)
                            <a href="{{ route('assets.index') }}" class="d-block text-warning mt-1">
                                <i class="fa fa-calendar-alt mr-1"></i>{{ $assetDueSoon }} service / renewal date(s) in the next 30 days</a>
                        @endif
                        @if ($assetWarranty)
                            <a href="{{ route('assets.index') }}" class="d-block text-warning mt-1">
                                <i class="fa fa-shield-alt mr-1"></i>Warranty ends within 30 days on {{ $assetWarranty }} asset(s)</a>
                        @endif
                    </div>
                @endif
            </div>
        </div>
    @elseif (in_array('assets', dashboard_modules()) || $myAssets->isNotEmpty())
        <div class="col-md-6 mb-3">
            <div class="bg-white p-20 rounded b-shadow-4 h-100">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="f-15 f-w-500 mb-0 text-darkest-grey"><i class="bi bi-laptop text-primary mr-2"></i>My Assets</h5>
                    <a href="{{ $myAssetsUrl }}" class="f-12">View all</a>
                </div>
                @forelse ($myAssets->take(5) as $held)
                    <a href="{{ $myAssetsUrl }}" class="my-asset-row">
                        <span>{{ $held->asset?->name }} <span class="text-dark-grey">({{ $held->asset?->asset_code }})</span></span>
                        <span class="text-dark-grey text-nowrap">since {{ $held->assigned_on->format(company()->date_format) }}</span>
                    </a>
                @empty
                    <p class="f-13 text-dark-grey mb-0">No assets are assigned to you.</p>
                @endforelse
                @if ($myAssets->count() > 5)
                    <a href="{{ $myAssetsUrl }}" class="d-block f-12 mt-2">and {{ $myAssets->count() - 5 }} more</a>
                @endif
            </div>
        </div>
    @endif
@endif
