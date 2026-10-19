<?php

namespace App\Http\Controllers;

use App\Helper\Reply;
use App\Models\AiSetting;
use App\Services\Ai\AiClient;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Settings → AI Settings: whether AI features are on, which provider answers
 * (OpenAI, Claude or Gemini), and each provider's key and model.
 *
 * Keys are write-only: the page shows whether one is saved, never the key.
 * Leaving a key field empty keeps the saved key; ticking "remove" clears it.
 */
class AiSettingController extends AccountBaseController
{
    public function __construct()
    {
        parent::__construct();
        $this->pageTitle = 'AI Settings';
        $this->activeSettingMenu = 'ai_settings';

        $this->middleware(function ($request, $next) {
            abort_403(user()->permission('manage_company_setting') !== 'all' && user()->permission('edit_employees') !== 'all');

            return $next($request);
        });
    }

    public function index()
    {
        $this->setting = AiSetting::forCompany(company()->id);
        $this->providers = AiSetting::PROVIDERS;
        $this->defaultModels = AiSetting::DEFAULT_MODELS;

        return view('ai-settings.index', $this->data);
    }

    public function update(Request $request)
    {
        $request->validate([
            'provider' => ['required', Rule::in(array_keys(AiSetting::PROVIDERS))],
            'openai_key' => 'nullable|string|max:500',
            'anthropic_key' => 'nullable|string|max:500',
            'gemini_key' => 'nullable|string|max:500',
            'openai_model' => 'nullable|string|max:100',
            'anthropic_model' => 'nullable|string|max:100',
            'gemini_model' => 'nullable|string|max:100',
        ]);

        $setting = AiSetting::forCompany(company()->id);
        // On/off is the switch at the top of the page (toggle()); Save keeps it as it is.
        $setting->enabled = (bool) $setting->enabled;
        $setting->provider = $request->provider;

        foreach (array_keys(AiSetting::PROVIDERS) as $provider) {
            if ($request->boolean('remove_' . $provider . '_key')) {
                $setting->setKey($provider, null);
            } elseif ($request->filled($provider . '_key')) {
                $setting->setKey($provider, trim($request->input($provider . '_key')));
            }

            $setting->{$provider . '_model'} = $request->filled($provider . '_model')
                ? trim($request->input($provider . '_model')) : null;
        }

        if ($setting->enabled && !$setting->keyFor($setting->provider)) {
            return Reply::error('Add an API key for ' . AiSetting::PROVIDERS[$setting->provider]
                . ' before turning AI features on.');
        }

        $setting->updated_by = user()->id;
        $setting->save();

        return Reply::success('AI settings saved.');
    }

    /** The on/off switch at the top of the page: takes effect at once. */
    public function toggle(Request $request)
    {
        $setting = AiSetting::forCompany(company()->id);
        $on = $request->boolean('enabled');

        if ($on && !$setting->keyFor($setting->provider ?: 'openai')) {
            return Reply::error('Save an API key for ' . (AiSetting::PROVIDERS[$setting->provider] ?? 'the provider')
                . ' before turning AI features on.');
        }

        $setting->enabled = $on;
        $setting->updated_by = user()->id;
        $setting->save();

        return Reply::success($on
            ? 'AI features are on: resume auto-fill is available on Add / Edit Employee.'
            : 'AI features are off: nothing is sent to an AI provider.');
    }

    /** Send a one-word request with the key on the form (or the saved one). */
    public function test(Request $request)
    {
        $request->validate(['provider' => ['required', Rule::in(array_keys(AiSetting::PROVIDERS))]]);

        $provider = $request->provider;
        $setting = AiSetting::forCompany(company()->id);
        $key = $request->filled($provider . '_key') ? trim($request->input($provider . '_key')) : $setting->keyFor($provider);
        $model = $request->filled($provider . '_model') ? trim($request->input($provider . '_model')) : $setting->modelFor($provider);

        if (!$key) {
            return Reply::error('Enter an API key for ' . AiSetting::PROVIDERS[$provider] . ' first.');
        }

        try {
            $answer = (new AiClient($setting))->test($provider, $key, $model);
        } catch (\Throwable $e) {
            $this->record($setting, 'Failed: ' . mb_strimwidth($e->getMessage(), 0, 200, '…'));

            return Reply::error($e->getMessage());
        }

        $this->record($setting, 'OK - ' . AiSetting::PROVIDERS[$provider] . ', ' . $model);

        return Reply::success('Connected to ' . AiSetting::PROVIDERS[$provider] . ' (' . $model . '). Reply: "'
            . mb_strimwidth($answer, 0, 60, '…') . '"');
    }

    protected function record(AiSetting $setting, string $result): void
    {
        if ($setting->exists) {
            $setting->last_tested_at = now();
            $setting->last_test_result = $result;
            $setting->save();
        }
    }
}
