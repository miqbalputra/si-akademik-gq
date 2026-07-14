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

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URI'),
        // Optional Workspace hosted-domain restriction. When set, only Google
        // accounts whose verified hosted domain matches may log in via OAuth.
        'hosted_domain' => env('GOOGLE_HOSTED_DOMAIN'),
    ],

    // Integration token for the n8n automation that consumes the
    // "missing diniyyah journal reminders" endpoint. MUST be set in .env —
    // there is intentionally no fallback: a missing/empty token denies all
    // access (fail-closed) rather than authenticating against a guessable
    // default. Compare with hash_equals() to avoid timing leaks.
    'n8n' => [
        'token' => env('N8N_API_TOKEN'),
    ],

];
