@extends('layouts.app')

@section('content')
    {{-- Standard page gutter, matching the rest of the app --}}
    <div class="content-wrapper arw-dash">

        @include('biometric.nav', ['active' => $activeTab])

        {{-- Counts are per controller, matching iApp's own device list. The table
             below lists one row per gate, so its row count is higher. --}}
        <div class="row mb-3">
            <div class="col-md-3 col-6 mb-2">
                <div class="card bg-white border-0 b-shadow-4">
                    <div class="card-body p-3">
                        <p class="mb-1 f-12 text-lightest text-uppercase">Devices</p>
                        <h4 class="mb-0 f-21 text-dark-grey">{{ $deviceCount }}</h4>
                        <p class="mb-0 f-11 text-lightest">{{ $gateCount }} gates &middot; {{ $locationCount }} locations</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-6 mb-2">
                <div class="card bg-white border-0 b-shadow-4">
                    <div class="card-body p-3">
                        <p class="mb-1 f-12 text-lightest text-uppercase">Active</p>
                        <h4 class="mb-0 f-21 text-primary">{{ $activeCount }}</h4>
                        <p class="mb-0 f-11 text-lightest">of {{ $deviceCount }} devices</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-6 mb-2">
                <div class="card bg-white border-0 b-shadow-4">
                    <div class="card-body p-3">
                        <p class="mb-1 f-12 text-lightest text-uppercase">Inactive</p>
                        <h4 class="mb-0 f-21 {{ $inactiveCount > 0 ? 'text-red' : 'text-dark-grey' }}">{{ $inactiveCount }}</h4>
                        <p class="mb-0 f-11 text-lightest">&nbsp;</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-6 mb-2">
                <div class="card bg-white border-0 b-shadow-4">
                    <div class="card-body p-3">
                        <p class="mb-1 f-12 text-lightest text-uppercase">Directional</p>
                        <h4 class="mb-0 f-21 text-dark-grey">{{ $directionalCount }}</h4>
                        <p class="mb-0 f-11 text-lightest">In / Out readers</p>
                    </div>
                </div>
            </div>
        </div>

        <x-cards.data padding="false">
            <div class="table-responsive">
                <table class="table table-hover border-0 mb-0">
                    <thead>
                        <tr>
                            <th class="f-12">Device</th>
                            <th class="f-12">Code</th>
                            <th class="f-12">Location</th>
                            <th class="f-12">Model</th>
                            <th class="f-12">IP / Port</th>
                            <th class="f-12">Gate</th>
                            <th class="f-12">Mode</th>
                            <th class="f-12">Entry Auth</th>
                            <th class="f-12">Exit Auth</th>
                            <th class="f-12">Last Activity</th>
                            <th class="f-12">@lang('app.status')</th>
                        </tr>
                    </thead>
                    <tbody>
                        @php $previousController = null; @endphp

                        @forelse ($devices as $device)
                            @php
                                // One row per gate: repeat the device identity only
                                // on its first gate so the grouping reads clearly.
                                $groupKey = $device->location_code . '|' . $device->controller_code;
                                $isFirstOfDevice = $groupKey !== $previousController;
                                $previousController = $groupKey;
                            @endphp

                            <tr @class(['border-top-grey' => $isFirstOfDevice])>
                                <td class="f-13">
                                    @if ($isFirstOfDevice)
                                        <span class="f-w-500">{{ $device->controller_name ?: '--' }}</span>
                                    @endif
                                </td>
                                <td class="f-13">{{ $isFirstOfDevice ? $device->controller_code : '' }}</td>
                                <td class="f-13">{{ $isFirstOfDevice ? ($device->location_code ?: '--') : '' }}</td>
                                <td class="f-13">{{ $isFirstOfDevice ? ($device->controller_model ?: '--') : '' }}</td>
                                <td class="f-13">
                                    @if ($isFirstOfDevice)
                                        {{ $device->ip_address ?: '--' }}@if ($device->port)<span class="text-lightest">:{{ $device->port }}</span>@endif
                                    @endif
                                </td>
                                <td class="f-13">
                                    {{ $device->gate_name ?: '--' }}
                                    @if ($device->gate_no)
                                        <span class="text-lightest f-11">({{ $device->gate_no }})</span>
                                    @endif
                                </td>
                                <td class="f-13">
                                    @if ($isFirstOfDevice)
                                        <span class="badge badge-secondary f-11">{{ $device->mode_label }}</span>
                                    @endif
                                </td>
                                <td class="f-13">{{ $device->entry_auth_mode ?: '--' }}</td>
                                <td class="f-13">{{ $device->exit_auth_mode ?: '--' }}</td>
                                <td class="f-13">
                                    @if ($isFirstOfDevice)
                                        {{ $device->last_activity_on?->format(company()->date_format . ' ' . company()->time_format) ?: '--' }}
                                    @endif
                                </td>
                                <td>
                                    @if ($isFirstOfDevice)
                                        <span class="badge badge-{{ $device->status_color }} f-11">
                                            {{ $device->isActive() ? 'Active' : 'Inactive' }}
                                        </span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="11" class="text-center py-5">
                                    <p class="mb-1 f-15 text-dark-grey">@lang('messages.noRecordFound')</p>
                                    <p class="mb-0 f-13 text-lightest">
                                        Devices appear here once a device sync has run.
                                    </p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($devices->count() > 0)
                <p class="f-12 text-lightest px-3 py-2 mb-0 border-top-grey">
                    {{ $deviceCount }} devices across {{ $gateCount }} gates. iApp reports each gate as its
                    own record, so a controller with four doors appears on four rows.
                </p>
            @endif
        </x-cards.data>
    </div>

@endsection
