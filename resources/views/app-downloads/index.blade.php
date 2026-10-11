@extends('layouts.app')

@section('content')

    @php
        $d = $downloadSetting;
        $hasMobile = filled($d->app_ios) || filled($d->app_android);
        $canManageUrls = user()->permission('manage_app_setting') === 'all';
    @endphp

    <!-- SETTINGS START -->
    <div class="w-100 d-flex ">

        @include('sections.setting-sidebar')

        <x-setting-card method="POST">
            <x-slot name="header">
                <div class="s-b-n-header" id="tabs">
                    <h2 class="mb-0 p-20 f-21 font-weight-normal  border-bottom-grey">
                        @lang($pageTitle)</h2>
                </div>
            </x-slot>

            <div class="col-lg-12 col-md-12 ntfcn-tab-content-left w-100 p-4 ">
                @if ($canManageUrls)
                    <div class="border rounded p-3 p-lg-4 mb-4">
                        <div class="row">
                            <div class="col-sm-12 mb-2">
                                <h4 class="mb-0 f-16 text-dark font-weight-normal">@lang('modules.accountSettings.downloadsStoreLinksSection')</h4>
                            </div>
                            <div class="col-sm-12 mb-4">
                                <p class="text-lightest f-14 mb-0">@lang('modules.accountSettings.partnerAppDownloadsHelp')</p>
                            </div>
                        </div>
                        @include('app-downloads.partials.mobile-urls-form')
                        <div class="row">
                            <div class="col-sm-12 mt-2">
                                <p class="f-12 text-lightest mb-0">@lang('modules.accountSettings.downloadsSaveHint')</p>
                            </div>
                        </div>
                    </div>
                @else
                    <div class="border rounded p-3 p-lg-4 mb-4">
                        <div class="row">
                            <div class="col-sm-12 mb-4">
                                <p class="text-lightest f-14 mb-0">@lang('modules.accountSettings.downloadsReadOnlyHelp')</p>
                            </div>
                        </div>
                        @include('app-downloads.partials.mobile-urls-readonly', ['d' => $d, 'hasMobile' => $hasMobile])
                    </div>
                @endif

                <div class="row border-top-grey pt-4 mt-4">
                    <div class="col-sm-12">
                        <div class="border rounded p-3 p-lg-4 bg-additional-grey">
                            <h4 class="f-16 text-dark font-weight-normal mb-2">@lang('modules.accountSettings.downloadsWhiteLabelTitle')</h4>
                            <p class="text-lightest f-14 mb-3">@lang('modules.accountSettings.downloadsWhiteLabelBody')</p>
                            <div class="d-flex flex-wrap align-items-center">
                                <x-forms.link-primary
                                    link="https://envato.froid.works/my-account"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    class="mr-2 mb-2 height-35 d-inline-flex align-items-center px-3">
                                    Order Custom Logo apps
                                </x-forms.link-primary>

                            </div>
                        </div>
                    </div>
                </div>
            </div>

            @if ($canManageUrls)
                <x-slot name="action">
                    <div class="w-100 border-top-grey">
                        <x-setting-form-actions>
                            <x-forms.button-primary id="save-form" class="mr-3" icon="check">@lang('app.save')
                            </x-forms.button-primary>
                        </x-setting-form-actions>
                    </div>
                </x-slot>
            @endif
        </x-setting-card>

    </div>
    <!-- SETTINGS END -->

@endsection

@push('scripts')
    <script>
        function updatePreviewDownloadButtons() {
            const iosUrl = ($('#app_ios').val() || '').trim();
            const androidUrl = ($('#app_android').val() || '').trim();
            const $iosBtn = $('#preview-ios-download-btn');
            const $androidBtn = $('#preview-android-download-btn');
            const $iosWrap = $('#preview-ios-download-wrap');
            const $androidWrap = $('#preview-android-download-wrap');

            if ($iosBtn.length) {
                $iosBtn.attr('href', iosUrl || 'javascript:;');
                $iosWrap.toggleClass('d-none', iosUrl === '');
            }

            if ($androidBtn.length) {
                $androidBtn.attr('href', androidUrl || 'javascript:;');
                $androidWrap.toggleClass('d-none', androidUrl === '');
            }
        }

        $('body').off('click', '.partner-clear-url').on('click', '.partner-clear-url', function () {
            const sel = $(this).data('target');
            $(sel).val('');
            updatePreviewDownloadButtons();
        });
        $('body').off('click', '.partner-reset-url').on('click', '.partner-reset-url', function () {
            const sel = $(this).data('target');
            const def = $(this).data('default');
            $(sel).val(def);
            updatePreviewDownloadButtons();
        });
        $('body').off('input', '#app_ios, #app_android').on('input', '#app_ios, #app_android', function () {
            updatePreviewDownloadButtons();
        });
        updatePreviewDownloadButtons();

        $('#save-form').click(function () {
            $.easyAjax({
                url: "{{ route('downloads.update') }}",
                container: '#editSettings',
                type: "POST",
                disableButton: true,
                blockUI: true,
                buttonSelector: "#save-form",
                data: {
                    _token: "{{ csrf_token() }}",
                    app_ios: $('#app_ios').val(),
                    app_android: $('#app_android').val(),
                },
            });
        });
    </script>
@endpush
