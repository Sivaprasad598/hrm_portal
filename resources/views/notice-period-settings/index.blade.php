@extends('layouts.app')

@section('content')

    <!-- SETTINGS START -->
    <div class="w-100 d-flex">

        @include('sections.setting-sidebar')

        <x-setting-card>

            {{-- standard settings header: the tabs sit in the card header (no separate title - the top bar names the page) --}}
            <x-slot name="header">
                <div class="s-b-n-header" id="tabs">
                    <nav class="tabs px-4 border-bottom-grey">
                        <div class="nav" id="periodSettingsTabs" role="tablist">
                            <a class="nav-item nav-link f-15 active" id="notice-period-tab" data-toggle="tab" href="#noticePeriodTabPane"
                               role="tab" aria-controls="noticePeriodTabPane" aria-selected="true">@lang('app.menu.noticePeriodSettings')</a>
                            <a class="nav-item nav-link f-15" id="probation-period-tab" data-toggle="tab" href="#probationPeriodTabPane"
                               role="tab" aria-controls="probationPeriodTabPane" aria-selected="false">@lang('app.menu.probationPeriodSettings')</a>
                            <a class="nav-item nav-link f-15" id="probation-period-requests-tab" data-toggle="tab" href="#probationPeriodRequestsTabPane"
                               role="tab" aria-controls="probationPeriodRequestsTabPane" aria-selected="false">@lang('app.menu.probationPeriodRequests')</a>
                        </div>
                    </nav>
                </div>
            </x-slot>

            <div class="col-lg-12 col-md-12 w-100 p-0">

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
                            title="@lang('app.menu.probationPeriodRequests')"
                            {{-- no "Filters" button on this page: its filter card stays open --}}
                            onload="try { this.contentDocument.body.classList.add('filters-open'); } catch (e) {}">
                        </iframe>
                    </div>

                </div>

            </div>

        </x-setting-card>

    </div>
    <!-- SETTINGS END -->

@endsection
