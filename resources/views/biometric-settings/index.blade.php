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
                            <x-forms.number fieldId="sync_interval_minutes" :fieldLabel="__('Sync every (minutes)')"
                                            fieldName="sync_interval_minutes" :fieldValue="$setting->sync_interval_minutes ?? 30"
                                            fieldRequired="true" />
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

                    <div class="row mt-2">
                        <div class="col-lg-3 col-md-6">
                            <x-forms.checkbox fieldId="status" fieldName="status"
                                              :fieldLabel="__('Integration enabled')"
                                              :checked="$setting->status ?? false" fieldValue="1" />
                        </div>
                        <div class="col-lg-3 col-md-6">
                            <x-forms.checkbox fieldId="sync_enabled" fieldName="sync_enabled"
                                              :fieldLabel="__('Scheduled sync')"
                                              :checked="$setting->sync_enabled ?? false" fieldValue="1" />
                        </div>
                        <div class="col-lg-3 col-md-6">
                            <x-forms.checkbox fieldId="verify_ssl" fieldName="verify_ssl"
                                              :fieldLabel="__('Verify TLS certificate')"
                                              :checked="$setting->verify_ssl ?? $effective['verify_ssl']" fieldValue="1" />
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
