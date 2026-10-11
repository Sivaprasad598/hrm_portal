@if($showAlert)
<div class="alert alert-info border-left border-primary mb-4 overflow-hidden" style="border-left-width: 4px !important;" role="alert">
    <div class="d-flex align-items-start justify-content-between flex-wrap">
        <div class="d-flex align-items-start flex-grow-1 min-w-0" style="min-width: 0;">
            <div class="flex-shrink-0 mr-3 mt-1">
                <i class="fa fa-info-circle fa-lg text-primary"></i>
            </div>
            <div class="flex-grow-1 min-w-0" style="min-width: 0;">
                <div class="d-flex align-items-center flex-wrap mb-1">
                    <h5 class="mb-0 mr-2 ">Universal Bundle Available</h5>
                    <span class="badge badge-primary">Best Value</span>
                </div>
                <p class="mb-2 text-muted" style="overflow-wrap: break-word;">
                    Get all current and future modules with a single purchase.
                    <a href="{{ $universalBundleLink }}" target="_blank" class="font-weight-bold">Learn more →</a>
                </p>
                <div class="d-flex flex-wrap align-items-center">
                    <a href="{{ $universalBundleLink }}" target="_blank" class="btn btn-primary btn-sm">
                        View Bundle <i class="fa fa-external-link-alt ml-1 small"></i>
                    </a>
                    @if(count($modules) > 0)
                    <button type="button" class="btn btn-outline-primary btn-sm ml-2" data-toggle="modal" data-target="#universalBundleModulesModal">
                        See What's Included ({{ count($modules) }})
                    </button>
                    @endif
                </div>
            </div>
        </div>
        <a href="{{ route('dashboard.universal_bundle_alert_dismiss') }}" id="universal-bundle-alert-dismiss" class="flex-shrink-0 ml-2 text-muted" data-toggle="tooltip" data-placement="left" data-original-title="Close permanently – will not show in future" title="Close permanently – will not show in future">
            <i class="fa fa-times fa-lg"></i>
        </a>
    </div>
</div>

@push('scripts')
<script>
    $(function() {
        $('#universal-bundle-alert-dismiss').tooltip();
    });
</script>
@endpush

@if(count($modules) > 0)
<div class="modal fade" id="universalBundleModulesModal" tabindex="-1" role="dialog" aria-labelledby="universalBundleModulesModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable" role="document">
        <div class="modal-content overflow-hidden">
            <div class="modal-header">
                <div>
                    <h5 class="modal-title font-weight-bold" id="universalBundleModulesModalLabel">Universal Bundle - Included Modules</h5>
                    <p class="mb-0 small text-muted">Get access to all these modules plus future releases.</p>
                </div>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="pr-1" style="max-height: 400px; overflow-y: auto; overflow-x: hidden;">
                    <div class="list-group list-group-flush">
                        @foreach($modules as $item)
                        <div class="list-group-item d-flex align-items-center p-3 {{ isset($item['product_name']) && stripos($item['product_name'], 'universal') !== false ? 'border-primary' : '' }}">
                            <a href="{{ $item['product_link'] ?? '#' }}" target="_blank" class="flex-shrink-0 mr-3">
                                <img src="{{ $item['product_thumbnail'] ?? '' }}" class="rounded border" width="48" height="48" alt="{{ $item['product_name'] ?? 'Module' }}" style="object-fit: cover;">
                            </a>
                            <div class="flex-grow-1 min-w-0 overflow-hidden" style="min-width: 0;">
                                <a href="{{ $item['product_link'] ?? '#' }}" target="_blank" class="font-weight-bold text-dark d-block text-truncate">{{ $item['product_name'] ?? 'Unknown Module' }}</a>
                                <p class="mb-0 small text-muted text-truncate">{{ $item['summary'] ?? '' }}</p>
                                <div class="d-flex align-items-center flex-wrap small mt-1">
                                    @if(isset($item['rating']))<span class="text-muted mr-2">{{ $item['rating'] ? number_format($item['rating'], 1) : '-' }} ★</span>@endif
                                    @if(isset($item['number_of_sales']))<span class="text-muted mr-2">{{ $item['number_of_sales'] }} sales</span>@endif
                                    @if(isset($item['price']))<span class="font-weight-bold text-success">${{ number_format($item['price'], 2) }}</span>@endif
                                </div>
                            </div>
                            <a href="{{ $item['product_link'] ?? '#' }}" target="_blank" class="btn btn-link btn-sm flex-shrink-0 p-0">View →</a>
                        </div>
                        @endforeach
                    </div>
                </div>
                <div class="d-flex align-items-center justify-content-between mt-3 pt-3 border-top">
                    <span class="small text-muted"><strong>{{ count($modules) }}</strong> modules</span>
                    <a href="{{ $universalBundleLink }}" target="_blank" class="btn btn-primary">
                        Buy Universal Bundle <i class="fa fa-arrow-right ml-1"></i>
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
@endif
@endif
