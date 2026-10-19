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

                {{-- What the module is using right now. These saved settings are
                     the only source; nothing is read from .env. --}}
                <div class="alert {{ $effective['configured'] ? 'alert-info' : 'alert-warning' }} f-13">
                    <strong>
                        {{ $effective['configured'] ? 'Connection configured' : 'Not configured yet' }}
                    </strong>
                    @unless ($setting->exists)
                        &mdash; nothing has been saved yet. Fill this form in and press Save;
                        the credentials are encrypted at rest.
                    @endunless
                    <div class="f-12 mt-1">
                        Endpoint <strong>{{ $effective['base_url'] ?: 'not set' }}</strong>
                        &middot; auth <strong>{{ $authMethods[$effective['auth_method']] ?? $effective['auth_method'] }}</strong>
                        &middot; company <strong>{{ $effective['company_code'] ?: '--' }}</strong>
                    </div>
                    @if (!$effective['verify_ssl'])
                        <div class="f-12 mt-1 text-red">
                            TLS certificate verification is off. Data and credentials travel without
                            certificate checks — acceptable for a self-signed server on a trusted
                            network, but worth fixing if this endpoint is reachable publicly.
                        </div>
                    @endif
                </div>

                @if ($setting->exists && $setting->last_sync_at)
                    <p class="f-12 text-lightest">
                        Last sync {{ $setting->last_sync_at->diffForHumans() }}
                        &mdash; {{ $setting->last_sync_message }}
                    </p>
                @endif

                <x-form id="biometric-settings-form">

                    <div class="row">
                        <div class="col-lg-6 col-md-6">
                            <x-forms.text fieldId="base_url" :fieldLabel="__('Server URL')"
                                          fieldName="base_url" :fieldValue="$setting->base_url ?? $effective['base_url']"
                                          fieldRequired="true" fieldPlaceholder="https://host/iapp" />
                        </div>

                        <div class="col-lg-6 col-md-6">
                            <x-forms.select fieldId="auth_method" :fieldLabel="__('Authentication')"
                                            fieldName="auth_method" search="false">
                                @foreach ($authMethods as $key => $label)
                                    <option value="{{ $key }}"
                                        @selected(($setting->auth_method ?? $effective['auth_method']) === $key)>
                                        {{ $label }}
                                    </option>
                                @endforeach
                            </x-forms.select>
                        </div>

                        <div class="col-lg-6 col-md-6">
                            <x-forms.text fieldId="username" :fieldLabel="__('Username')"
                                          fieldName="username" :fieldValue="$setting->username ?? $effective['username']" />
                        </div>

                        <div class="col-lg-6 col-md-6">
                            {{-- Left blank on purpose: an existing secret is never
                                 rendered back to the browser. --}}
                            <x-forms.password fieldId="password" :fieldLabel="__('Password')"
                                              fieldName="password"
                                              :fieldPlaceholder="$setting->exists && $setting->password ? 'Unchanged — type to replace' : ''" />
                        </div>

                        <div class="col-lg-6 col-md-6">
                            <x-forms.password fieldId="client_secret" :fieldLabel="__('Client Secret')"
                                              fieldName="client_secret"
                                              :fieldPlaceholder="$setting->exists && $setting->client_secret ? 'Unchanged — type to replace' : 'Only for client-secret auth'" />
                        </div>

                        <div class="col-lg-3 col-md-6">
                            <x-forms.text fieldId="company_code" :fieldLabel="__('Company Code')"
                                          fieldName="company_code" :fieldValue="$setting->company_code ?? $effective['company_code']" />
                        </div>

                        <div class="col-lg-3 col-md-6">
                            <x-forms.text fieldId="location_code" :fieldLabel="__('Location Code')"
                                          fieldName="location_code" :fieldValue="$setting->location_code ?? $effective['location_code']"
                                          fieldPlaceholder="Blank = all locations" />
                        </div>
                    </div>

                    <hr>
                    <h6 class="f-15 mb-3">Sync</h6>

                    <div class="row">
                        <div class="col-lg-3 col-md-6">
                            <x-forms.select fieldId="employee_match_field" :fieldLabel="__('Fallback match on')"
                                            fieldName="employee_match_field" search="false">
                                @foreach ($matchFields as $key => $label)
                                    <option value="{{ $key }}"
                                        @selected(($setting->employee_match_field ?? $effective['match_field']) === $key)>
                                        {{ $label }}
                                    </option>
                                @endforeach
                            </x-forms.select>
                            <p class="f-11 text-lightest mb-0">
                                Only used for codes nobody has mapped yet. A code linked on
                                <a href="{{ route('biometric.employees') }}">Employee Mapping</a> always wins —
                                the device code is the bridge between the two systems.
                            </p>
                        </div>

                        <div class="col-lg-3 col-md-6">
                            <x-forms.number fieldId="resync_days" :fieldLabel="__('Re-pull last N days')"
                                            fieldName="resync_days" :fieldValue="$setting->resync_days ?? 3"
                                            fieldRequired="true" />
                            <p class="f-11 text-lightest mt-n2">
                                Controllers upload buffered punches late, so recent days are re-read.
                            </p>
                        </div>

                        <div class="col-lg-3 col-md-6">
                            <x-forms.number fieldId="token_cache_minutes" :fieldLabel="__('Token cache (minutes)')"
                                            fieldName="token_cache_minutes" :fieldValue="$setting->token_cache_minutes ?? $effective['token_cache_minutes']"
                                            fieldRequired="true" />
                            <p class="f-11 text-lightest mt-n2">
                                Max 19 — the server issues 20-minute tokens.
                            </p>
                        </div>
                    </div>

                    {{-- Which devices the nightly pull reads. --}}
                    <hr>
                    <h6 class="f-15 mb-1">Devices to pull</h6>
                    <p class="f-12 text-lightest mb-2">
                        Punches on a device that is not pulled never reach attendance, so somebody who uses only that
                        device will look absent. To bring in a device's history later, tick it here, then pull its dates on
                        Setup → Sync Calendar.
                    </p>
                    @php $scope = $setting->device_scope ?? 'all'; $picked = array_map('strval', (array) ($setting->pull_devices ?? [])); @endphp
                    <div class="d-flex flex-wrap mb-2">
                        <label class="f-14 mr-4"><input type="radio" name="device_scope" value="all" class="mr-1" @checked($scope === 'all')> All devices</label>
                        <label class="f-14"><input type="radio" name="device_scope" value="selected" class="mr-1" @checked($scope === 'selected')> Selected devices</label>
                    </div>
                    <div id="pull-device-list" class="d-flex flex-wrap mb-2" @if ($scope !== 'selected') style="display:none !important" @endif>
                        @forelse ($deviceOptions as $code => $label)
                            <label class="f-13 mr-4 mb-1 d-inline-flex align-items-center">
                                <input type="checkbox" name="pull_devices[]" value="{{ $code }}" class="mr-1" @checked(in_array((string) $code, $picked, true))>
                                {{ $label }} <span class="text-lightest ml-1">{{ $code }}</span>
                            </label>
                        @empty
                            <span class="f-12 text-lightest">No devices known yet: run Test Connection, then Setup → Devices.</span>
                        @endforelse
                    </div>

                    <h6 class="f-15 mb-1 mt-3">Whose data comes in</h6>
                    <div class="row">
                        <div class="col-lg-6">
                            <x-forms.checkbox fieldId="only_known_employees" fieldName="only_known_employees"
                                              :fieldLabel="__('Only employees in this system')"
                                              :checked="$setting->only_known_employees ?? true" fieldValue="1" />
                            <p class="f-11 text-lightest">Punches and device-list entries are kept only for codes that belong to an employee here.
                                Temporary staff, leavers and the machine's own entries are left out.</p>
                        </div>
                        <div class="col-lg-6">
                            <x-forms.text fieldId="register_locations" fieldName="register_locations" :fieldLabel="__('Locations read from the machine\'s employee list')"
                                          :fieldValue="implode(', ', (array) ($setting->register_locations ?? []))" fieldPlaceholder="e.g. Visakhapatnam" />
                            <p class="f-11 text-lightest">As written in the biometric Employee report's Location column, comma separated. Empty = every location.</p>
                        </div>
                    </div>

                    {{-- The night, step by step. --}}
                    <h6 class="f-15 mb-1 mt-3">Nightly schedule</h6>
                    <p class="f-12 text-lightest mb-2">
                        Each step waits for the one before it; a pull that cannot reach the device is retried every 15 minutes.
                        If the server was off at these times, the steps run as soon as it is back.
                    </p>
                    <div class="row">
                        @foreach ([
                            ['pull_time', 'Pull', 'The previous day from the selected devices.'],
                            ['build_time', 'Build Device Attendance', 'Turns the punches into one day per person.'],
                            ['reconcile_time', 'Write ERP & cross-check', 'Writes ERP attendance, reviews every employee, and lists whatever device and ERP disagree on under Exceptions.'],
                            ['notify_time', 'Notify', 'HR summary and each employee\'s own exceptions, in working hours.'],
                        ] as [$field, $label, $help])
                            <div class="col-lg-3 col-md-6">
                                <label class="f-14 text-dark-grey mb-12 mt-2 d-block" for="{{ $field }}">{{ $label }}</label>
                                <input type="time" id="{{ $field }}" name="{{ $field }}" class="form-control height-35 f-14"
                                       value="{{ $setting->{$field} ?: \App\Models\IappSetting::DEFAULT_TIMES[$field] }}" required>
                                <p class="f-11 text-lightest mt-1">{{ $help }}</p>
                            </div>
                        @endforeach
                    </div>

                    <div class="row mt-2">
                        <div class="col-lg-3 col-md-6">
                            <x-forms.checkbox fieldId="status" fieldName="status"
                                              :fieldLabel="__('Integration enabled')"
                                              :checked="$setting->status ?? false" fieldValue="1" />
                        </div>
                        <div class="col-lg-3 col-md-6">
                            <x-forms.checkbox fieldId="sync_enabled" fieldName="sync_enabled"
                                              :fieldLabel="__('Nightly automatic run')"
                                              :checked="$setting->sync_enabled ?? false" fieldValue="1" />
                        </div>
                        <div class="col-lg-3 col-md-6">
                            <x-forms.checkbox fieldId="verify_ssl" fieldName="verify_ssl"
                                              :fieldLabel="__('Verify TLS certificate')"
                                              :checked="$setting->verify_ssl ?? $effective['verify_ssl']" fieldValue="1" />
                        </div>
                    </div>

                    {{-- The device → attendance → payslip chain. Overview shows where it stands. --}}
                    <div class="row mt-3">
                        <div class="col-12">
                            <h5 class="f-15 f-w-500 mb-1">Automation</h5>
                            <p class="f-12 text-lightest mb-2">
                                With all three on, attendance and draft payslips need no manual steps; approving and paying
                                stay with a person. See <a href="{{ route('biometric.overview') }}">Biometric → Overview</a>.
                            </p>
                        </div>
                        <div class="col-lg-4 col-md-6">
                            <x-forms.checkbox fieldId="auto_promote" fieldName="auto_promote"
                                              :fieldLabel="__('Write device days into ERP attendance automatically')"
                                              :checked="$setting->auto_promote ?? false" fieldValue="1" />
                            <p class="f-11 text-lightest">At the write &amp; cross-check time. A day that already has attendance from elsewhere is left alone, and so is a day already in an approved payslip.</p>
                        </div>
                        <div class="col-lg-4 col-md-6">
                            <x-forms.checkbox fieldId="auto_generate_payslips" fieldName="auto_generate_payslips"
                                              :fieldLabel="__('Generate draft payslips when a pay cycle ends')"
                                              :checked="$setting->auto_generate_payslips ?? false" fieldValue="1" />
                            <p class="f-11 text-lightest">After the nightly run, for the cycle just ended. Admins are notified to approve.</p>
                        </div>
                        <div class="col-lg-4 col-md-6">
                            <x-forms.checkbox fieldId="notify_employees" fieldName="notify_employees"
                                              :fieldLabel="__('Tell employees about their own exceptions')"
                                              :checked="$setting->notify_employees ?? true" fieldValue="1" />
                            <p class="f-11 text-lightest">Absent without leave, missing exit punch or short hours. HR and admins always get the daily summary.</p>
                        </div>
                    </div>

                    {{-- Kept inside the form itself. These used to sit in a slot
                         named "buttons", which the form component does not render,
                         so nothing was shown and the settings could not be saved. --}}
                    <div class="w-100 border-top-grey mt-3">
                        <x-setting-form-actions>
                            <x-forms.button-primary id="save-biometric-settings" class="mr-3" icon="check">
                                @lang('app.save')
                            </x-forms.button-primary>

                            <x-forms.button-secondary id="test-connection" icon="plug">
                                Test Connection
                            </x-forms.button-secondary>
                        </x-setting-form-actions>
                    </div>

                </x-form>

                <div id="test-result" class="mt-3"></div>

            </div>

        </x-setting-card>
    </div>
    <!-- SETTINGS END -->

@endsection

@push('scripts')
<script>
    $('input[name="device_scope"]').on('change', function () {
        $('#pull-device-list').attr('style', this.value === 'selected' ? '' : 'display:none !important');
    });

    $('#save-biometric-settings').on('click', function () {
        $.easyAjax({
            url: "{{ route('biometric-settings.update') }}",
            container: '#biometric-settings-form',
            type: 'POST',
            // `redirect: true` was here, but the controller answers with a
            // plain success and never asks for a redirect, so easyAjax showed
            // a toast and left the page exactly as it was. The values had
            // saved; nothing on screen said so, and the summary at the top —
            // endpoint, auth mode, last sync — still described the old
            // settings. Reloading is what makes the save visible.
            file: false,
            data: $('#biometric-settings-form').serialize(),
            success: function (response) {
                if (response.status === 'success') {
                    window.location.reload();
                }
            }
        });
    });

    $('#test-connection').on('click', function () {
        var button = $(this);
        button.prop('disabled', true);
        $('#test-result').html('<p class="f-13 text-lightest">Testing…</p>');

        $.easyAjax({
            url: "{{ route('biometric-settings.test') }}",
            type: 'POST',
            data: { _token: '{{ csrf_token() }}' },
            success: function (response) {
                button.prop('disabled', false);

                // The test reports each step separately, so a partial failure
                // says which stage broke rather than just "failed".
                // The endpoint returns the steps at the top level; older builds
                // nested them under "data", so both shapes are accepted.
                var steps = (response.data && response.data.steps) || response.steps || [];
                var html = '<div class="card border-0 b-shadow-4"><div class="card-body p-3">';
                html += '<p class="f-13 mb-2"><strong>Connection test</strong></p>';

                if (!steps.length) {
                    html += '<p class="f-13 text-red mb-0">' + (response.message || 'No response.') + '</p>';
                }

                steps.forEach(function (step) {
                    html += '<div class="f-13 mb-1">'
                        + '<span class="badge badge-' + (step.ok ? 'success' : 'danger') + ' f-11 mr-2">'
                        + (step.ok ? 'OK' : 'FAIL') + '</span>'
                        + '<strong>' + step.step + '</strong> — ' + step.detail
                        + '</div>';
                });

                html += '</div></div>';
                $('#test-result').html(html);
            },
            error: function () {
                button.prop('disabled', false);
                $('#test-result').html('<p class="f-13 text-red">Test could not run.</p>');
            }
        });
    });
</script>
@endpush
