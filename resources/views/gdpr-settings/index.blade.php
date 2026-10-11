@extends('layouts.app')

@push('datatable-styles')
    @include('sections.datatable_css')
@endpush

@push('styles')
<style>
    .gdpr-toggle-switch {
        position: relative;
        display: inline-block;
        width: 60px;
        height: 34px;
    }

    .gdpr-toggle-switch input {
        opacity: 0;
        width: 0;
        height: 0;
    }

    .gdpr-slider {
        position: absolute;
        cursor: pointer;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background-color: #ccc;
        -webkit-transition: .4s;
        transition: .4s;
        border-radius: 34px;
    }

    .gdpr-slider:before {
        position: absolute;
        content: "";
        height: 26px;
        width: 26px;
        left: 4px;
        bottom: 4px;
        background-color: white;
        -webkit-transition: .4s;
        transition: .4s;
        border-radius: 50%;
    }

    input:checked + .gdpr-slider {
        background-color: #2196F3;
    }

    input:focus + .gdpr-slider {
        box-shadow: 0 0 1px #2196F3;
    }

    input:checked + .gdpr-slider:before {
        -webkit-transform: translateX(26px);
        -ms-transform: translateX(26px);
        transform: translateX(26px);
    }

    .gdpr-main-toggle {
        margin-top: 10px;
        background: #fff;
        padding: 30px;
        border-radius: 10px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        margin-bottom: 20px;
    }

    .gdpr-settings-panel {
        width: 100%;
        background: #fff;
        border-radius: 10px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        overflow: hidden;
    }
</style>
@endpush

@section('content')

    <!-- SETTINGS START -->
    <div class="w-100 d-flex ">

        <x-setting-sidebar :activeMenu="$activeSettingMenu" />

        <x-setting-card>
            <x-slot name="header">
                <div class="s-b-n-header">
                    <h2 class="mb-0 p-20 f-21 font-weight-normal text-capitalize border-bottom-grey">
                        @lang('app.menu.gdprSettings')
                    </h2>
                </div>

                <!-- GDPR Master Toggle -->
            <div class="gdpr-main-toggle">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <h4 class="mb-2 f-18 font-weight-medium">@lang('modules.gdpr.enableGdpr')</h4>
                        <p class="text-muted mb-0 f-14">Enable or disable GDPR compliance features for your application</p>
                    </div>
                    <div class="d-flex align-items-center">
                        <label class="gdpr-toggle-switch mr-3">
                            <input type="checkbox" id="gdpr-master-toggle"
                                   @if($gdprSetting->enable_gdpr == 1) checked @endif>
                            <span class="gdpr-slider"></span>
                        </label>
                        <span class="f-14 font-weight-medium" id="gdpr-status-text">
                            @if($gdprSetting->enable_gdpr == 1)
                                @lang('app.enabled')
                            @else
                                @lang('app.disabled')
                            @endif
                        </span>
                    </div>
                </div>
            </div>

            @if($gdprSetting->enable_gdpr == 1)
            <!-- GDPR Settings Panel - Only visible when GDPR is enabled -->
            <div class="s-b-n-header" id="tabs">
                <nav class="gdpr-tabs tabs border-bottom-grey">
                    <ul class="nav -primary" id="nav-tab" role="tablist">
                        <li>
                            <a class="nav-item nav-link f-15 gdpr-ajax-tab active general"
                                href="{{ route('gdpr-settings.index') }}" role="tab" aria-controls="nav-general"
                                aria-selected="true">@lang('app.menu.general')
                            </a>
                        </li>

                        <li>
                            <a class="nav-item nav-link f-15 gdpr-ajax-tab right-to-data-portability"
                                href="{{ route('gdpr-settings.index') }}?tab=right-to-data-portability" role="tab"
                                aria-controls="nav-rightToDataPortability"
                                aria-selected="true">@lang('app.menu.rightToDataPortability')
                            </a>
                        </li>

                        <li>
                            <a class="nav-item nav-link f-15 gdpr-ajax-tab right-to-informed"
                                href="{{ route('gdpr-settings.index') }}?tab=right-to-informed" role="tab"
                                aria-controls="nav-rightToBeInformed"
                                aria-selected="true">@lang('app.menu.rightToBeInformed')
                            </a>
                        </li>

                        <li>
                            <a class="nav-item nav-link f-15 gdpr-ajax-tab right-to-erasure"
                                href="{{ route('gdpr-settings.index') }}?tab=right-to-erasure" role="tab"
                                aria-controls="nav-rightToErasure" aria-selected="true">@lang('app.menu.rightToErasure')
                            </a>
                        </li>

                        <li>
                            <a class="nav-item nav-link f-15 gdpr-ajax-tab right-to-access"
                                href="{{ route('gdpr-settings.index') }}?tab=right-to-access" role="tab"
                                aria-controls="nav-rightOfRectification"
                                aria-selected="true">@lang('app.menu.rightOfRectification')
                            </a>
                        </li>

                        <li>
                            <a class="nav-item nav-link f-15 removal-requests "
                                href="{{ route('gdpr-settings.index') }}?tab=removal-requests" role="tab"
                                aria-controls="nav-removalRequests"
                                aria-selected="true">@lang('app.menu.removalRequest')
                            </a>
                        </li>

                        <li>
                            <a class="nav-item nav-link f-15 removal-requests-lead"
                                href="{{ route('gdpr-settings.index') }}?tab=removal-requests-lead" role="tab"
                                aria-controls="nav-removalRequests"
                                aria-selected="true">@lang('app.menu.removalRequestLead')
                            </a>
                        </li>

                        <li>
                            <a class="nav-item nav-link f-15 consent-settings"
                                href="{{ route('gdpr-settings.index') }}?tab=consent-settings" role="tab"
                                aria-controls="nav-consent" aria-selected="true">@lang('app.menu.consentSettings')
                            </a>
                        </li>

                        <li>
                            <a class="nav-item nav-link f-15 consent-lists"
                                href="{{ route('gdpr-settings.index') }}?tab=consent-lists" role="tab"
                                aria-controls="nav-consent" aria-selected="true">@lang('app.menu.consentLists')
                            </a>
                        </li>

                    </ul>
                </nav>
                <div class="d-block d-lg-none d-md-none">
                    {{-- put select box here --}}
                </div>
            </div>

            @else
            <!-- Message when GDPR is disabled -->
            <div class="col-lg-12">
                <div class="alert alert-info mt-4">
                    <i class="fa fa-info-circle"></i>
                    @lang('modules.gdpr.gdprDisabledMessage')
                </div>
            </div>
            @endif
            </x-slot>


            @if($gdprSetting->enable_gdpr == 1)
             {{-- include tabs here --}}
                {{-- <div id="nav-tabContent"> --}}
                    @include($view)
                {{-- </div> --}}
            @endif
        </x-setting-card>

    </div>
    <!-- SETTINGS END -->

@endsection

@push('scripts')
    <script>
                // Handle GDPR Master Toggle
        $(document).ready(function() {
            $('#gdpr-master-toggle').on('change', function() {
                const isEnabled = $(this).is(':checked');
                const statusText = isEnabled ? '@lang("app.enabled")' : '@lang("app.disabled")';

                console.log('Toggle changed to:', isEnabled);

                // Update status text immediately
                $('#gdpr-status-text').text(statusText);

                // Show loading state
                $(this).prop('disabled', true);

                $.easyAjax({
                    url: "{{ route('gdpr_settings.update_general') }}",
                    container: '.content-wrapper',
                    type: "PUT",
                    data: {
                        '_token': '{{ csrf_token() }}',
                        '_method': 'PUT',
                        'enable_gdpr': isEnabled ? 1 : 0
                    },
                    success: function(response) {
                        console.log('Success response:', response);
                        if (response.status == "success") {
                            // Reload the page to show/hide GDPR settings panel
                            setTimeout(function() {
                                window.location.reload();
                            }, 500);
                        }
                    },
                    error: function(xhr, status, error) {
                        console.log('Error:', error);
                        console.log('Response:', xhr.responseText);
                        // Revert toggle state on error
                        $('#gdpr-master-toggle').prop('checked', !isEnabled);
                        $('#gdpr-status-text').text(isEnabled ? '@lang("app.disabled")' : '@lang("app.enabled")');
                    },
                    complete: function() {
                        $('#gdpr-master-toggle').prop('disabled', false);
                    }
                });
            });
        });

        @if($gdprSetting->enable_gdpr == 1)
        /* manage menu active class */
        $('.nav-item').removeClass('active');
        const activeTab = "{{ $activeTab }}";
        $('.' + activeTab).addClass('active');

        showBtn(activeTab);

        function showBtn(activeTab) {
            $('.actionBtn').addClass('d-none');
            $('.' + activeTab + '-btn').removeClass('d-none');
        }

        $("body").on("click", ".gdpr-ajax-tab", function(event) {
            event.preventDefault();

            $('.nav-item').removeClass('active');
            $(this).addClass('active');

            const requestUrl = this.href;

            $.easyAjax({
                url: requestUrl,
                blockUI: true,
                container: ".content-wrapper",
                historyPush: true,
                success: function(response) {
                    if (response.status == "success") {
                        $('#nav-tabContent').html(response.html);
                        init('#nav-tabContent');
                    }
                }
            });
        });

        /*******************************************************
                         More btn in projects menu Start
        *******************************************************/

        const container = document.querySelector('.tabs');
        if (container) {
            const primary = container.querySelector('.-primary');
            const primaryItems = container.querySelectorAll('.-primary > li:not(.-more)');
            container.classList.add('--jsfied');

            primary.insertAdjacentHTML('beforeend', `
            <li class="-more bg-grey">
                <button type="button" class="px-4 h-100 w-100 d-lg-flex d-md-flex align-items-center justify-content-center" aria-haspopup="true" aria-expanded="false">
                More <span>&darr;</span>
                </button>
                <ul class="-secondary" id="hide-project-menues">
                ${primary.innerHTML}
                </ul>
            </li>
            `);

            const secondary = container.querySelector('.-secondary');
            const secondaryItems = secondary.querySelectorAll('li');
            const allItems = container.querySelectorAll('li');
            const moreLi = primary.querySelector('.-more');
            const moreBtn = moreLi.querySelector('button');

            moreBtn.addEventListener('click', e => {
                e.preventDefault();
                container.classList.toggle('--show-secondary');
                moreBtn.setAttribute('aria-expanded', container.classList.contains('--show-secondary'));
            });

            const doAdapt = () => {
                allItems.forEach(item => {
                    item.classList.remove('--hidden');
                });

                let stopWidth = moreBtn.offsetWidth;
                let hiddenItems = [];
                const primaryWidth = primary.offsetWidth;

                primaryItems.forEach((item, i) => {
                    if (primaryWidth >= stopWidth + item.offsetWidth) {
                        stopWidth += item.offsetWidth;
                    } else {
                        item.classList.add('--hidden');
                        hiddenItems.push(i);
                    }
                });

                if (!hiddenItems.length) {
                    moreLi.classList.add('--hidden');
                    container.classList.remove('--show-secondary');
                    moreBtn.setAttribute('aria-expanded', false);
                } else {
                    secondaryItems.forEach((item, i) => {
                        if (!hiddenItems.includes(i)) {
                            item.classList.add('--hidden');
                        }
                    });
                }
            };

            doAdapt();
            window.addEventListener('resize', doAdapt);

            document.addEventListener('click', e => {
                let el = e.target;
                while (el) {
                    if (el === secondary || el === moreBtn) {
                        return;
                    }
                    el = el.parentNode;
                }
                container.classList.remove('--show-secondary');
                moreBtn.setAttribute('aria-expanded', false);
            });
        }

        /*******************************************************
                 More btn in projects menu End
        *******************************************************/
        @endif
    </script>

    <script>
        $('body').on('click', '#save-general-data', function() {
            $.easyAjax({
                url: "{{ route('gdpr_settings.update_general') }}",
                container: '#editSettings',
                type: "POST",
                disableButton: true,
                buttonSelector: "#save-general-data",
                data: $('#editSettings').serialize(),
            })
        })
    </script>

    <script>
        $('body').on('click', '#save-right-to-data-portability', function() {
            $.easyAjax({
                url: "{{ route('gdpr_settings.update_general') }}",
                container: '#editSettings',
                type: "POST",
                disableButton: true,
                buttonSelector: "#save-right-to-data-portability",
                data: $('#editSettings').serialize(),
            })
        })
    </script>

    <script>
        $('body').on('click', '#save-right-to-informed-data', function() {
            $.easyAjax({
                url: "{{ route('gdpr_settings.update_general') }}",
                container: '#editSettings',
                type: "POST",
                disableButton: true,
                buttonSelector: "#save-right-to-informed-data",
                data: $('#editSettings').serialize(),
            })
        })
    </script>

    <script>
        $('body').on('click', '#save-right-to-erasure-data', function() {
            $.easyAjax({
                url: "{{ route('gdpr_settings.update_general') }}",
                container: '#editSettings',
                type: "POST",
                disableButton: true,
                buttonSelector: "#save-right-to-erasure-data",
                data: $('#editSettings').serialize(),
            })
        })
    </script>

    <script>
        $('body').on('click', '#save-right-to-access-data', function() {
            $.easyAjax({
                url: "{{ route('gdpr_settings.update_general') }}",
                container: '#editSettings',
                type: "POST",
                disableButton: true,
                buttonSelector: "#save-right-to-access-data",
                data: $('#editSettings').serialize(),
            })
        })
    </script>

    <script>
        $('body').on('click', '#save-consent-data', function() {
            $.easyAjax({
                url: "{{route('gdpr_settings.update_general')}}",
                container: '#editSettings',
                type: "POST",
                disableButton: true,
                buttonSelector: "#save-consent-data",
                data: $('#editSettings').serialize(),
            })
        })
    </script>

@endpush
