@php
    use Illuminate\Support\Str;
    $androidLower = $d->app_android ? Str::lower($d->app_android) : '';
    $androidPlayHint = $androidLower !== '' && Str::contains($androidLower, 'play.google.com');
    $iosLower = $d->app_ios ? Str::lower($d->app_ios) : '';
    $iosStoreHint = $iosLower !== '' && Str::contains($iosLower, ['apps.apple.com', 'testflight.apple.com']);
@endphp

@if (!$hasMobile)
    <div class="row">
        <div class="col-sm-12">
            <div class="text-lightest f-14 py-4 px-3 text-center border rounded bg-additional-grey">
                <i class="fa fa-mobile-alt f-20 text-lightest d-block mb-2" aria-hidden="true"></i>
                <div class="f-16 text-dark font-weight-bold mb-2">@lang('modules.accountSettings.downloadsEmptyTitle')</div>
                <p class="text-lightest f-14 mb-0 mx-auto" style="max-width: 420px;">@lang('modules.accountSettings.downloadsMobileEmpty')</p>
            </div>
        </div>
    </div>
@else
    <div class="row">
        <div class="col-12 mb-4">
            <div class="d-flex align-items-start">
                <span class="rounded bg-additional-grey d-flex align-items-center justify-content-center mr-2 flex-shrink-0 mt-1"
                      style="width: 36px; height: 36px;">
                    <i class="fab fa-apple f-16 text-dark" aria-hidden="true"></i>
                </span>
                <div class="flex-grow-1 min-w-0">
                    <x-forms.text
                        class="mr-0 mr-lg-2 mr-md-2"
                        :fieldLabel="__('modules.accountSettings.downloadsCardIosTitle')"
                        :fieldPlaceholder="__('modules.accountSettings.downloadsUrlNotConfigured')"
                        fieldName="downloads_display_ios"
                        fieldId="downloads_display_ios"
                        fieldReadOnly="true"
                        :fieldValue="$d->app_ios"
                        :popover="__('modules.accountSettings.iosAppUrl')"
                    />

                    @if ($iosStoreHint)
                        <p class="f-12 text-lightest mb-2 mt-2">
                            <i class="fa fa-info-circle mr-1"></i>@lang('modules.accountSettings.downloadsAppStoreHint')
                        </p>
                    @endif

                    @if (filled($d->app_ios))
                        <div class="mt-3 p-3 border border-additional-grey rounded bg-white">
                            <p class="f-12 text-lightest mb-2 mb-0">
                                <span class="text-dark font-weight-bold">@lang('modules.accountSettings.downloadCta')</span>
                                — @lang('modules.accountSettings.downloadsCardIosTitle')
                            </p>
                            <div class="mt-2">
                                <a href="{{ $d->app_ios }}"
                                   class="btn btn-primary rounded f-14 height-35 d-inline-flex align-items-center px-3"
                                   target="_blank" rel="noopener noreferrer">
                                    <i class="fab fa-apple mr-2" aria-hidden="true"></i>
                                    @lang('modules.accountSettings.downloadsCardIosTitle')
                                </a>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <div class="col-12">
            <div class="d-flex align-items-start">
                <span class="rounded bg-additional-grey d-flex align-items-center justify-content-center mr-2 flex-shrink-0 mt-1"
                      style="width: 36px; height: 36px;">
                    <i class="fab fa-android f-16 text-success" aria-hidden="true"></i>
                </span>
                <div class="flex-grow-1 min-w-0">
                    <x-forms.text
                        class="mr-0 mr-lg-2 mr-md-2"
                        :fieldLabel="__('modules.accountSettings.downloadsCardAndroidTitle')"
                        :fieldPlaceholder="__('modules.accountSettings.downloadsUrlNotConfigured')"
                        fieldName="downloads_display_android"
                        fieldId="downloads_display_android"
                        fieldReadOnly="true"
                        :fieldValue="$d->app_android"
                        :popover="__('modules.accountSettings.androidAppUrl')"
                    />

                    @if ($androidPlayHint)
                        <p class="f-12 text-lightest mb-2 mt-2">
                            <i class="fa fa-info-circle mr-1"></i>@lang('modules.accountSettings.downloadsPlayStoreHint')
                        </p>
                    @endif

                    @if (filled($d->app_android))
                        <div class="mt-3 p-3 border border-additional-grey rounded bg-white">
                            <p class="f-12 text-lightest mb-2 mb-0">
                                <span class="text-dark font-weight-bold">@lang('modules.accountSettings.downloadCta')</span>
                                — @lang('modules.accountSettings.downloadsCardAndroidTitle')
                            </p>
                            <div class="mt-2">
                                <a href="{{ $d->app_android }}"
                                   class="btn btn-primary rounded f-14 height-35 d-inline-flex align-items-center px-3"
                                   target="_blank" rel="noopener noreferrer">
                                    <i class="fab fa-android mr-2" aria-hidden="true"></i>
                                    @lang('modules.accountSettings.downloadsCardAndroidTitle')
                                </a>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endif
