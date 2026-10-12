{{-- CRM Notetaker bot settings (notetaker-bot/.env). Admins only. --}}
<div class="col-lg-8 col-md-10 w-100 p-4">
    <p class="text-muted mb-4">
        The CRM Notetaker bot joins every portal Google Meet meeting at its start time, records the call and saves
        the meeting notes in the CRM. These settings are saved to <code>notetaker-bot/.env</code>; the bot restarts
        with them within a minute, after any meeting in progress.
    </p>

    <div class="mb-4">
        <span class="f-14 text-dark-grey mr-2">Status:</span>
        @if ($botStatus['online'])
            <span class="badge badge-success">&#9679; Bot online</span>
        @else
            <span class="badge badge-danger">&#9679; Bot offline</span>
            <span class="ml-2 text-muted small">
                {{ $botStatus['last_seen'] ? 'Last seen ' . $botStatus['last_seen'] . '.' : 'Never seen.' }}
                Start it with <code>notetaker-bot\start-bot.vbs</code>.
            </span>
        @endif
    </div>

    @if (!$botStatus['writable'])
        <div class="alert alert-danger">
            The portal cannot write <code>notetaker-bot/.env</code> or the portal <code>.env</code> file, so these settings cannot be saved.
        </div>
    @elseif ($botSettings['bot_key'] !== '' && !$botStatus['keys_match'])
        <div class="alert alert-warning">
            The bot key does not match the portal's key, so the bot cannot check in. Tick <b>Generate a new bot key</b> and save to fix it.
        </div>
    @endif

    <form id="notetakerBotForm" autocomplete="off">
        @csrf
        @method('PUT')
        <div class="form-group">
            <label for="botPortalUrl">Portal URL <span class="text-danger">*</span></label>
            <div class="d-flex" style="gap: 8px;">
                <input type="url" class="form-control" name="portal_url" id="botPortalUrl"
                       value="{{ $botSettings['portal_url'] ?: url('/') }}" placeholder="{{ url('/') }}">
                <button type="button" class="btn btn-outline-secondary text-nowrap" id="useThisPortalUrl"
                        data-url="{{ url('/') }}">Use this portal</button>
            </div>
            <small class="form-text text-muted">The address the bot uses to reach this portal, e.g. <code>{{ url('/') }}</code></small>
        </div>

        <div class="form-group">
            <label for="botName">Bot name <span class="text-danger">*</span></label>
            <input type="text" class="form-control" name="bot_name" id="botName" maxlength="50"
                   value="{{ $botSettings['bot_name'] }}" placeholder="CRM Notetaker">
            <small class="form-text text-muted">The name shown to the other people in the Google Meet call.</small>
        </div>

        <div class="form-group">
            <label>Bot key</label>
            <input type="text" class="form-control" readonly
                   value="{{ $botSettings['bot_key'] !== '' ? str_repeat('•', 20) . substr($botSettings['bot_key'], -4) : 'Not set: a key is created when you save' }}">
            <div class="custom-control custom-checkbox mt-2">
                <input type="checkbox" class="custom-control-input" name="regenerate_key" value="1" id="regenerateBotKey">
                <label class="custom-control-label" for="regenerateBotKey">
                    Generate a new bot key <span class="text-muted">(the old key stops working; the bot gets the new one automatically)</span>
                </label>
            </div>
        </div>

        <button type="submit" class="btn btn-primary" id="saveNotetakerBot">@lang('app.save')</button>
    </form>
</div>
