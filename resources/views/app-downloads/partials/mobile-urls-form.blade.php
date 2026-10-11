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
                    :fieldPlaceholder="__('placeholders.url')"
                    fieldName="app_ios"
                    fieldId="app_ios"
                    :fieldValue="$downloadSetting->app_ios"
                    :popover="__('modules.accountSettings.iosAppUrl')"
                />

                <div class="d-flex flex-wrap align-items-center mt-2 pt-3 border-top-grey">
                    <x-forms.button-secondary class="mr-2 mb-2 partner-clear-url" data-target="#app_ios">
                        @lang('modules.accountSettings.clearUrl')
                    </x-forms.button-secondary>
                    <x-forms.button-secondary class="mr-2 mb-2 partner-reset-url" data-target="#app_ios"
                        :data-default="\App\Models\MobileAppDownloadSetting::DEFAULT_APP_IOS">
                        @lang('modules.accountSettings.resetUrlToDefault')
                    </x-forms.button-secondary>
                    @if ($downloadSetting->app_ios)
                        <a href="{{ $downloadSetting->app_ios }}" class="f-14 text-primary font-weight-bold mb-2 ml-lg-2"
                           target="_blank" rel="noopener noreferrer">
                            <i class="fa fa-external-link-alt mr-1"></i>@lang('modules.accountSettings.openDownloadLink')
                        </a>
                    @endif
                </div>

                <div id="preview-ios-download-wrap"
                     class="mt-3 p-3 border border-additional-grey rounded bg-white {{ $downloadSetting->app_ios ? '' : 'd-none' }}">
                    <p class="f-12 text-lightest mb-2 mb-0">
                        <span class="text-dark font-weight-bold">@lang('modules.accountSettings.downloadCta')</span>
                        — @lang('modules.accountSettings.downloadsCardIosTitle')
                    </p>
                    <div class="mt-2">
                        <a href="{{ $downloadSetting->app_ios ?: 'javascript:;' }}"
                           id="preview-ios-download-btn"
                           class="btn btn-primary rounded f-14 height-35 d-inline-flex align-items-center px-3"
                           target="_blank" rel="noopener noreferrer">
                            <i class="fab fa-apple mr-2" aria-hidden="true"></i>
                            @lang('modules.accountSettings.downloadsCardIosTitle')
                        </a>
                    </div>
                </div>
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
                    :fieldPlaceholder="__('placeholders.url')"
                    fieldName="app_android"
                    fieldId="app_android"
                    :fieldValue="$downloadSetting->app_android"
                    :popover="__('modules.accountSettings.androidAppUrl')"
                />

                <div class="d-flex flex-wrap align-items-center mt-2 pt-3 border-top-grey">
                    <x-forms.button-secondary class="mr-2 mb-2 partner-clear-url" data-target="#app_android">
                        @lang('modules.accountSettings.clearUrl')
                    </x-forms.button-secondary>
                    <x-forms.button-secondary class="mr-2 mb-2 partner-reset-url" data-target="#app_android"
                        :data-default="\App\Models\MobileAppDownloadSetting::DEFAULT_APP_ANDROID">
                        @lang('modules.accountSettings.resetUrlToDefault')
                    </x-forms.button-secondary>
                    @if ($downloadSetting->app_android)
                        <a href="{{ $downloadSetting->app_android }}" class="f-14 text-primary font-weight-bold mb-2 ml-lg-2"
                           target="_blank" rel="noopener noreferrer">
                            <i class="fa fa-external-link-alt mr-1"></i>@lang('modules.accountSettings.openDownloadLink')
                        </a>
                    @endif
                </div>

                <div id="preview-android-download-wrap"
                     class="mt-3 p-3 border border-additional-grey rounded bg-white {{ $downloadSetting->app_android ? '' : 'd-none' }}">
                    <p class="f-12 text-lightest mb-2 mb-0">
                        <span class="text-dark font-weight-bold">@lang('modules.accountSettings.downloadCta')</span>
                        — @lang('modules.accountSettings.downloadsCardAndroidTitle')
                    </p>
                    <div class="mt-2">
                        <a href="{{ $downloadSetting->app_android ?: 'javascript:;' }}"
                           id="preview-android-download-btn"
                           class="btn btn-primary rounded f-14 height-35 d-inline-flex align-items-center px-3"
                           target="_blank" rel="noopener noreferrer">
                            <i class="fab fa-android mr-2" aria-hidden="true"></i>
                            @lang('modules.accountSettings.downloadsCardAndroidTitle')
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
