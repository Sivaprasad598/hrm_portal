@extends('layouts.app')

@section('content')

    <!-- SETTINGS START -->
    <div class="w-100 d-flex ">

        @include('sections.setting-sidebar')

        <x-setting-card>
            <x-slot name="header">
                <div class="s-b-n-header" id="tabs">
                    <h2 class="mb-0 p-20 f-21 font-weight-normal border-bottom-grey">Microsoft Teams</h2>
                </div>
            </x-slot>

            <div class="col-lg-8 col-md-10 w-100 p-4">
                @if (session('teams_success'))
                    <div class="alert alert-success">{{ session('teams_success') }}</div>
                @endif
                @if (session('teams_error'))
                    <div class="alert alert-danger">Could not connect: {{ session('teams_error') }}</div>
                @endif

                <p class="text-muted mb-4">
                    Creates real Microsoft Teams meetings (with a join link) for customer meetings, from the date, start time and end time.
                    Needs an app registration in the Azure portal made with a <b>Microsoft 365 work account</b>
                    (personal Outlook.com accounts cannot create Teams meetings through Microsoft Graph).
                </p>

                <div class="mb-4">
                    <span class="f-14 text-dark-grey mr-2">Status:</span>
                    @if ($teams->connected())
                        <span class="badge badge-success">Connected</span>
                        <span class="ml-2">{{ $teams->account_name }} ({{ $teams->account_email }})</span>
                    @elseif ($teams->configured())
                        <span class="badge badge-warning">Not connected: click Connect</span>
                    @else
                        <span class="badge badge-secondary">Not set up</span>
                    @endif
                </div>

                <form id="teamsSettingsForm" autocomplete="off">
                    @csrf
                    <div class="form-group">
                        <label for="tenantId">Directory (tenant) ID <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="tenant_id" id="tenantId" value="{{ $teams->tenant_id }}"
                               placeholder="xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx">
                    </div>
                    <div class="form-group">
                        <label for="clientId">Application (client) ID <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="client_id" id="clientId" value="{{ $teams->client_id }}"
                               placeholder="xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx">
                    </div>
                    <div class="form-group">
                        <label for="clientSecret">Client secret (Value) <span class="text-danger">*</span></label>
                        <input type="password" class="form-control" name="client_secret" id="clientSecret"
                               placeholder="{{ $teams->client_secret ? 'Saved. Leave empty to keep it' : 'Client secret value' }}">
                    </div>

                    <div class="form-group">
                        <label>Redirect URI <small class="text-muted">(add this in the Azure app registration → Authentication → Web)</small></label>
                        <div class="d-flex" style="gap: 8px;">
                            <input type="text" class="form-control" id="teamsRedirectUri" value="{{ $redirectUri }}" readonly>
                            <button type="button" class="btn btn-outline-secondary text-nowrap" id="copyRedirectUri">Copy</button>
                        </div>
                    </div>

                    <p class="text-muted small mb-4">
                        Azure app permissions (Microsoft Graph, Delegated): <code>OnlineMeetings.ReadWrite</code>, <code>User.Read</code>,
                        <code>offline_access</code>, then <b>Grant admin consent</b>.
                    </p>

                    <div class="d-flex flex-wrap" style="gap: 8px;">
                        <button type="submit" class="btn btn-primary" id="saveTeamsBtn">Save</button>
                        @if ($teams->configured())
                            <a href="{{ route('microsoftAuth') }}" class="btn btn-outline-primary">{{ $teams->connected() ? 'Reconnect' : 'Connect' }}</a>
                        @endif
                        @if ($teams->connected())
                            <button type="button" class="btn btn-outline-danger" id="disconnectTeamsBtn">Disconnect</button>
                        @endif
                    </div>
                </form>
            </div>
        </x-setting-card>
    </div>
    <!-- SETTINGS END -->

@endsection

@push('scripts')
<script>
$(document).ready(function () {
    $('#copyRedirectUri').on('click', function () {
        $('#teamsRedirectUri').select();
        document.execCommand('copy');
        $(this).text('Copied');
    });

    $('#teamsSettingsForm').on('submit', function (e) {
        e.preventDefault();
        var btn = $('#saveTeamsBtn').prop('disabled', true).text('Saving...');

        $.ajax({
            url: "{{ route('microsoft-teams-settings.store') }}",
            type: 'POST',
            data: $(this).serialize(),
            success: function () { location.reload(); },
            error: function (xhr) {
                var body = xhr.responseJSON || {};
                var errors = body.errors ? Object.values(body.errors).map(function (e) { return e[0]; }).join('\n') : (body.message || 'Could not save.');
                alert(errors);
            },
            complete: function () { btn.prop('disabled', false).text('Save'); }
        });
    });

    $('#disconnectTeamsBtn').on('click', function () {
        if (!confirm('Disconnect Microsoft Teams? New Teams links cannot be generated until you connect again.')) return;

        $.ajax({
            url: "{{ route('microsoftAuth.destroy') }}",
            type: 'POST',
            data: { _token: '{{ csrf_token() }}', _method: 'DELETE' },
            success: function () { location.reload(); }
        });
    });
});
</script>
@endpush
