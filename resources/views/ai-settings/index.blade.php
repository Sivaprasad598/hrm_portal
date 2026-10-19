@extends('layouts.app')

@section('content')

    <!-- SETTINGS START -->
    <div class="w-100 d-flex">

        @include('sections.setting-sidebar')

        <x-setting-card>

            <x-slot name="header">
                <div class="s-b-n-header" id="tabs">
                    <h2 class="mb-0 p-20 f-21 font-weight-normal border-bottom-grey">AI Settings</h2>
                </div>
            </x-slot>

            <div class="col-lg-12 col-md-12 w-100 p-4">

                {{-- The one switch that turns every AI feature on or off. It saves at once. --}}
                <div class="d-flex align-items-center justify-content-between flex-wrap border rounded p-3 mb-3"
                     id="ai-switch-card" style="background: {{ $setting->enabled ? '#f0fdf4' : '#f8fafc' }}">
                    <div class="mr-3">
                        <h5 class="f-16 f-w-500 mb-1">
                            AI features are <span id="ai-state">{{ $setting->enabled ? 'ON' : 'OFF' }}</span>
                        </h5>
                        <p class="f-13 text-dark-grey mb-0" id="ai-state-note">
                            @if ($setting->enabled)
                                Resume auto-fill is available on Add / Edit Employee, using
                                {{ $providers[$setting->provider] }} ({{ $setting->modelFor($setting->provider) }}).
                            @else
                                Nothing is sent to an AI provider. Resume upload still works; auto-fill is disabled.
                            @endif
                        </p>
                        @if ($setting->last_tested_at)
                            <p class="f-12 text-lightest mb-0 mt-1">Last test {{ $setting->last_tested_at->diffForHumans() }}: {{ $setting->last_test_result }}</p>
                        @endif
                    </div>
                    <div class="custom-control custom-switch" style="transform: scale(1.4); transform-origin: right center;">
                        <input type="checkbox" class="custom-control-input" id="ai-enabled-switch" @checked($setting->enabled)>
                        <label class="custom-control-label" for="ai-enabled-switch"></label>
                    </div>
                </div>

                <p class="f-13 text-dark-grey mb-4">
                    Used by <strong>Add / Edit Employee → Upload resume → Extract with AI</strong>: the resume PDF is sent
                    to the provider chosen here, which reads the name, contact details, qualification and work experience
                    into the form for HR to check before saving. The resume leaves this server for that request, under the
                    provider's own data terms. Keys are stored encrypted and are never shown again after saving.
                </p>

                <x-form id="ai-settings-form">
                    <div class="row">
                        <div class="col-lg-4 col-md-6">
                            <x-forms.select fieldId="provider" fieldName="provider" :fieldLabel="__('Provider')" search="false">
                                @foreach ($providers as $value => $label)
                                    <option value="{{ $value }}" @selected(($setting->provider ?? 'openai') === $value)>{{ $label }}</option>
                                @endforeach
                            </x-forms.select>
                        </div>
                    </div>

                    @foreach ($providers as $value => $label)
                        <div class="ai-provider-block border rounded p-3 mb-3 {{ ($setting->provider ?? 'openai') === $value ? 'border-primary' : '' }}"
                             data-provider="{{ $value }}">
                            <h5 class="f-15 f-w-500 mb-1">{{ $label }}</h5>
                            <p class="f-12 text-lightest mb-2">
                                @switch($value)
                                    @case('openai') Key from platform.openai.com → API keys (starts with <code>sk-</code>). @break
                                    @case('anthropic') Key from console.anthropic.com → API keys (starts with <code>sk-ant-</code>). @break
                                    @case('gemini') Key from aistudio.google.com → Get API key. @break
                                @endswitch
                            </p>
                            <div class="row">
                                <div class="col-lg-6 col-md-12">
                                    <x-forms.label :fieldId="$value . '_key'" fieldLabel="API key"></x-forms.label>
                                    <input type="password" class="form-control height-35 f-14" autocomplete="new-password"
                                           id="{{ $value }}_key" name="{{ $value }}_key"
                                           placeholder="{{ $setting->maskedKey($value) ? 'Saved: ' . $setting->maskedKey($value) . ' — leave empty to keep' : 'Paste the API key' }}">
                                    @if ($setting->maskedKey($value))
                                        <div class="custom-control custom-checkbox mt-1">
                                            <input type="checkbox" class="custom-control-input" id="remove_{{ $value }}_key"
                                                   name="remove_{{ $value }}_key" value="1">
                                            <label class="custom-control-label f-12" for="remove_{{ $value }}_key">Remove the saved key</label>
                                        </div>
                                    @endif
                                </div>
                                <div class="col-lg-4 col-md-8">
                                    <x-forms.text :fieldId="$value . '_model'" :fieldName="$value . '_model'" fieldLabel="Model"
                                                  :fieldValue="$setting->{$value . '_model'}"
                                                  :fieldPlaceholder="$defaultModels[$value] . ' (default)'" />
                                </div>
                                <div class="col-lg-2 col-md-4 d-flex align-items-end">
                                    <button type="button" class="btn btn-outline-secondary btn-block mb-3 ai-test" data-provider="{{ $value }}">
                                        <i class="fa fa-plug mr-1"></i> Test
                                    </button>
                                </div>
                            </div>
                        </div>
                    @endforeach

                    <div class="w-100 border-top-grey mt-3">
                        <x-setting-form-actions>
                            <x-forms.button-primary id="save-ai-settings" class="mr-3" icon="check">
                                @lang('app.save')
                            </x-forms.button-primary>
                        </x-setting-form-actions>
                    </div>
                </x-form>

                <div id="ai-test-result" class="mt-3"></div>

            </div>

        </x-setting-card>
    </div>
    <!-- SETTINGS END -->

@endsection

@push('scripts')
<script>
    $('#provider').on('change', function () {
        var chosen = $(this).val();
        $('.ai-provider-block').each(function () {
            $(this).toggleClass('border-primary', $(this).data('provider') === chosen);
        });
    });

    $('#ai-enabled-switch').on('change', function () {
        var input = $(this);
        var on = input.is(':checked');

        $.easyAjax({
            url: "{{ route('ai-settings.toggle') }}",
            type: 'POST',
            data: { _token: '{{ csrf_token() }}', enabled: on ? 1 : 0 },
            success: function (response) {
                if (response.status === 'success') {
                    window.location.reload();
                } else {
                    input.prop('checked', !on);   // refused: no key for the provider
                }
            },
            error: function () { input.prop('checked', !on); }
        });
    });

    $('#save-ai-settings').on('click', function () {
        $.easyAjax({
            url: "{{ route('ai-settings.update') }}",
            container: '#ai-settings-form',
            type: 'POST',
            data: $('#ai-settings-form').serialize(),
            success: function (response) {
                if (response.status === 'success') {
                    window.location.reload();
                }
            }
        });
    });

    $('.ai-test').on('click', function () {
        var button = $(this);
        var provider = button.data('provider');
        var data = { _token: '{{ csrf_token() }}', provider: provider };
        data[provider + '_key'] = $('#' + provider + '_key').val();
        data[provider + '_model'] = $('#' + provider + '_model').val();

        button.prop('disabled', true);
        $('#ai-test-result').html('<p class="f-13 text-lightest">Testing ' + provider + '…</p>');

        $.easyAjax({
            url: "{{ route('ai-settings.test') }}",
            type: 'POST',
            data: data,
            success: function (response) {
                button.prop('disabled', false);
                $('#ai-test-result').html('<p class="f-13 ' + (response.status === 'success' ? 'text-success' : 'text-red') + '">'
                    + $('<div>').text(response.message || '').html() + '</p>');
            },
            error: function () {
                button.prop('disabled', false);
                $('#ai-test-result').html('');
            }
        });
    });
</script>
@endpush
