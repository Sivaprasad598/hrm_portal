<?php

namespace App\Http\Controllers;

use App\Helper\Reply;
use App\Services\NotetakerBot;
use Illuminate\Http\Request;

/**
 * Settings → Lead Settings → Notetaker Bot: Portal URL, bot name and key of the CRM Notetaker bot.
 * Admins only, since the key gives access to every customer meeting.
 */
class NotetakerBotSettingController extends AccountBaseController
{

    public function __construct()
    {
        parent::__construct();
        $this->middleware(function ($request, $next) {
            abort_403(!in_array('admin', user_roles()));
            return $next($request);
        });
    }

    public function update(Request $request, NotetakerBot $bot)
    {
        $request->validate([
            'portal_url' => 'required|url|max:191',
            'bot_name' => ['required', 'string', 'max:50', 'regex:/^[^\r\n=]+$/'],
            'regenerate_key' => 'nullable|boolean',
        ], [
            'bot_name.regex' => 'The bot name cannot contain "=" or line breaks.',
        ]);

        if (!$bot->status()['writable']) {
            return Reply::error('The portal cannot write notetaker-bot/.env or the portal .env file. Check the file permissions.');
        }

        $newKey = $request->boolean('regenerate_key') || $bot->settings()['bot_key'] === ''
            ? NotetakerBot::generateKey()
            : null;

        $bot->save($request->portal_url, trim($request->bot_name), $newKey);

        return Reply::success('Notetaker bot settings saved. The bot restarts with them within a minute (after any meeting in progress).');
    }

}
