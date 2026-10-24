<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'authorize' => [
        'login' => env('AUTHORIZE_PAYMENT_API_LOGIN_ID'),
        'transaction' => env('AUTHORIZE_PAYMENT_TRANSACTION_KEY'),
        'sandbox' => env('AUTHORIZE_SANDBOX', true),
    ],

    'square' => [
        'application_id' => env('SQUARE_APPLICATION_ID'),
        'access_token' => env('SQUARE_ACCESS_TOKEN'),
        'location_id' => env('SQUARE_ACCESS_TOKEN'),
        'environment' => env('SQUARE_ENVIRONMENT', 'sandbox'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'telegram-bot-api' => [
        'token' => env('TELEGRAM_BOT_TOKEN', 'YOUR BOT TOKEN HERE')
    ],

    // CRM Notes bot: OpenAI speech-to-text and meeting notes
    'openai' => [
        'key' => env('OPENAI_API_KEY'),
        'organization' => env('OPENAI_ORG_ID'),
        'project' => env('OPENAI_PROJECT_ID'),
        'transcribe_model' => env('OPENAI_TRANSCRIBE_MODEL', 'whisper-1'),
        'notes_model' => env('OPENAI_NOTES_MODEL', 'gpt-4o-mini'),
        // Key of the CRM Notetaker bot (notetaker-bot/.env BOT_KEY)
        'bot_key' => env('CRM_NOTES_BOT_KEY'),
    ],

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT'),
        'redirect_uri' => (env('APP_URL') . '/account/settings/google-auth'),
        // Let the user know what we will be using from his Google account.
        'scopes' => [
            // Getting access to the user's email.
            \Google_Service_Oauth2::USERINFO_EMAIL,
            \Google_Service_Oauth2::USERINFO_PROFILE,
            // Managing the user's calendars and events.
            \Google_Service_Calendar::CALENDAR,
            // Google Meet: turn on automatic Smart Notes for CRM meetings and read them back
            'https://www.googleapis.com/auth/meetings.space.settings',
            'https://www.googleapis.com/auth/meetings.space.readonly',
            // Read the notes Docs that Meet creates in Drive (only files created by Meet)
            'https://www.googleapis.com/auth/drive.meet.readonly',
        ],
        // Enables automatic token refresh.
        'approval_prompt' => 'force',
        'access_type' => 'offline',
        // Enables incremental scopes (useful if in the future we need access to another type of data).
        'include_granted_scopes' => true,
    ],

    'sentry' => [
        'enabled' => env('SENTRY_ENABLED', false)
    ],
    'vonage' => [
        'key' => env('VONAGE_KEY') ?: 'ide-helper-key',
        'secret' => env('VONAGE_SECRET') ?: 'ide-helper-secret',
        'api_key' => env('VONAGE_KEY') ?: 'ide-helper-key',
        'api_secret' => env('VONAGE_SECRET') ?: 'ide-helper-secret',
    ],
    'onesignal' => [
        'app_id' => env('ONESIGNAL_APP_ID', 'YOUR-APP-ID-HERE'),
        'rest_api_key' => env('ONESIGNAL_REST_API_KEY', 'YOUR-REST-API-KEY-HERE'),
        'user_auth_key' => env('ONESIGNAL_USER_AUTH_KEY', 'YOUR-USER-AUTH-KEY'),
        'guzzle_client_timeout' => env('ONESIGNAL_GUZZLE_TIMEOUT', 0),
        'rest_api_url' => env('ONESIGNAL_REST_API_URL', null),
    ],

    /*
     * iApp (Spectra Vision) biometric / access-control platform.
     *
     * These are fallbacks only. The Biometric settings screen stores the same
     * values per company in `iapp_settings`, with credentials encrypted at
     * rest, and those take precedence when present.
     *
     * auth_method: 'user' (AuthenticateUser) or 'client_secret'
     * (authenticateClient). The live deployment uses 'user'.
     */
    'iapp' => [
        'base_url' => env('IAPP_API_BASE_URL'),
        'verify_ssl' => env('IAPP_VERIFY_SSL', true),
        'auth_method' => env('IAPP_AUTH_METHOD', 'user'),
        'username' => env('IAPP_USERNAME'),
        'password' => env('IAPP_PASSWORD'),
        'client_secret' => env('IAPP_CLIENT_SECRET'),
        'company_code' => env('IAPP_COMPANY_CODE'),
        'location_code' => env('IAPP_LOCATION_CODE'),
        // Server issues 20-minute tokens; stay below that.
        'token_cache_minutes' => env('IAPP_TOKEN_CACHE_MINUTES', 15),
    ],

    /*
     * Claude API, used by ai_lead_search.php (Target Account & Lead
     * Generation bot) to search the web for project leads.
     */
    'anthropic' => [
        'key' => env('ANTHROPIC_API_KEY'),
        'model' => env('ANTHROPIC_MODEL', 'claude-opus-5-5'),
        // Company name suggestions while typing: needs to be fast and cheap
        'suggest_model' => env('ANTHROPIC_SUGGEST_MODEL', 'claude-haiku-5-5'),
    ],
];
