@extends('layouts.app')

@section('content')

    <!-- SETTINGS START -->
    <div class="w-100 d-flex">

        @include('sections.setting-sidebar')

        <x-setting-card>

            <x-slot name="header">
                <div class="s-b-n-header" id="tabs">
                    <h2 class="mb-0 p-20 f-21 font-weight-normal border-bottom-grey">
                        @lang($pageTitle)</h2>
                </div>
            </x-slot>

            <div class="col-lg-12 col-md-12 w-100 p-4">

                <ul class="nav nav-tabs mb-3" id="periodSettingsTabs" role="tablist">
                    <li class="nav-item">
                        <a class="nav-link active" id="notice-period-tab" data-toggle="tab" href="#noticePeriodTabPane"
                           role="tab" aria-controls="noticePeriodTabPane" aria-selected="true">
                            @lang('app.menu.noticePeriodSettings')
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" id="probation-period-tab" data-toggle="tab" href="#probationPeriodTabPane"
                           role="tab" aria-controls="probationPeriodTabPane" aria-selected="false">
                            @lang('app.menu.probationPeriodSettings')
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" id="probation-period-requests-tab" data-toggle="tab" href="#probationPeriodRequestsTabPane"
                           role="tab" aria-controls="probationPeriodRequestsTabPane" aria-selected="false">
                            @lang('app.menu.probationPeriodRequests')
                        </a>
                    </li>
                </ul>

                <div class="tab-content" id="periodSettingsTabContent">

                    <div class="tab-pane fade show active" id="noticePeriodTabPane" role="tabpanel"
                         aria-labelledby="notice-period-tab">
                        <iframe
                            id="notice-period-settings-iframe"
                            src="{{ route('legacy.page', 'notice_period_settings') }}"
                            class="w-100"
                            style="min-height: calc(100vh - 280px); border: 0;"
                            title="@lang('app.menu.noticePeriodSettings')">
                        </iframe>
                    </div>

                    <div class="tab-pane fade" id="probationPeriodTabPane" role="tabpanel"
                         aria-labelledby="probation-period-tab">
                        <iframe
                            id="probation-period-settings-iframe"
                            src="{{ route('legacy.page', 'probation_period_settings') }}"
                            class="w-100"
                            style="min-height: calc(100vh - 280px); border: 0;"
                            title="@lang('app.menu.probationPeriodSettings')">
                        </iframe>
                    </div>

                    <div class="tab-pane fade" id="probationPeriodRequestsTabPane" role="tabpanel"
                         aria-labelledby="probation-period-requests-tab">
                        <iframe
                            id="probation-period-requests-iframe"
                            src="{{ route('legacy.page', 'probation_period_request') }}"
                            class="w-100"
                            style="min-height: calc(100vh - 280px); border: 0;"
                            title="@lang('app.menu.probationPeriodRequests')">
                        </iframe>
                    </div>

                </div>

            </div>

        </x-setting-card>

    </div>
    <!-- SETTINGS END -->

@endsection
