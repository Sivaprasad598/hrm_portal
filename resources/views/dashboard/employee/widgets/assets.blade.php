{{-- Assets: the register at a glance for HR and admins; for everybody else, what they hold. --}}
@if (\Illuminate\Support\Facades\Schema::hasTable('assets'))
    @php
        $manageAssets = user()->permission('manage_assets') === 'all';

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
        }
    @endphp

    @if ($manageAssets)
        <div class="col-md-6 mb-3">
            <div class="bg-white p-20 rounded b-shadow-4 h-100">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="f-15 f-w-500 mb-0 text-darkest-grey"><i class="bi bi-laptop text-primary mr-2"></i>Assets</h5>
                    <a href="{{ route('assets.index') }}" class="f-12">Asset List</a>
                </div>
                <div class="row text-center">
                    @foreach ([['Total', $assetCounts->sum(), 'text-dark', 'all'], ['Available', $assetCounts['available'] ?? 0, 'text-success', 'available'],
                               ['Assigned', $assetCounts['assigned'] ?? 0, 'text-primary', 'assigned'], ['Under Repair', $assetCounts['repair'] ?? 0, 'text-warning', 'repair']] as [$label, $n, $class, $status])
                        <div class="col-3">
                            <a href="{{ route('assets.index') }}" class="d-block">
                                <h3 class="mb-0 font-weight-700 {{ $class }}">{{ $n }}</h3>
                                <span class="f-12 text-dark-grey">{{ $label }}</span>
                            </a>
                        </div>
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
    @elseif ($myAssets->isNotEmpty())
        <div class="col-md-6 mb-3">
            <div class="bg-white p-20 rounded b-shadow-4 h-100">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="f-15 f-w-500 mb-0 text-darkest-grey"><i class="bi bi-laptop text-primary mr-2"></i>My Assets</h5>
                    <a href="{{ route('employees.show', user()->id) }}?tab=assets" class="f-12">View all</a>
                </div>
                @foreach ($myAssets->take(5) as $held)
                    <div class="d-flex justify-content-between f-13 py-1 border-bottom-grey">
                        <span>{{ $held->asset?->name }} <span class="text-dark-grey">({{ $held->asset?->asset_code }})</span></span>
                        <span class="text-dark-grey">since {{ $held->assigned_on->format(company()->date_format) }}</span>
                    </div>
                @endforeach
            </div>
        </div>
    @endif
@endif
