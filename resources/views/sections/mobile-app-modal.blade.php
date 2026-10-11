<!-- Mobile App Modal -->
@php
    $mobileDownloadSetting = \App\Models\MobileAppDownloadSetting::instance();
@endphp
<div id="mobileAppModal" class="modal fade" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Mobile App</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">×</span>
                </button>
            </div>

            <div class="modal-body">
                <div class="container-fluid">
                    <!-- Header -->
                    <div class="text-center mb-4">
                        <h2 class="text-xl font-weight-bold text-dark mb-2">Download Mobile App</h2>
                        <p class="text-muted">Get mobile app on Android and iOS.</p>
                    </div>

                    <!-- Mobile App Download Card -->
                    <div class="row">
                        <!-- Downloads -->
                        <div class="col-md-8 col-lg-7 mx-auto mb-4">
                            <div class="card border">
                                <div class="card-body">
                                    <div class="d-flex align-items-center mb-3">

                                        <div>
                                            <h5 class="font-weight-bold text-dark mb-1"> <i class="fa fa-mobile text-primary" aria-hidden="true"
                                                style=""></i> Download Mobile App</h5>
                                            <p class="text-muted small mb-0">Android and iOS links</p>
                                        </div>
                                    </div>

                                    <div class="row mb-3">
                                        <div class="col-md-12">
                                            @if (filled($mobileDownloadSetting->app_android))
                                                <a href="javascript:void(0);"
                                                   class="btn btn-secondary btn-lg w-100 py-3">
                                                    <i class="fab fa-android mr-1" aria-hidden="true" style="line-height: 1; position: relative; top: -1px;"></i>
                                                    Google Play
                                                </a>
                                            @endif
                                        </div>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-12">
                                            @if (filled($mobileDownloadSetting->app_ios))
                                                <a href="javascript:void(0);"
                                                   class="btn btn-secondary btn-lg w-100 py-3">
                                                    <i class="fab fa-apple mr-1" aria-hidden="true" style="line-height: 1; position: relative; top: -1px;"></i>
                                                    App Store
                                                </a>
                                            @endif
                                        </div>
                                    </div>

                                    <p class="text-muted small mb-0 mt-3">
                                        Use these links to install the official mobile app.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">@lang('app.close')</button>
            </div>
        </div>
    </div>
</div>

