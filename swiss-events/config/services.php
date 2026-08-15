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

    'cron' => [
        // Shared secret for the /cron/run endpoint that Infomaniak's task
        // scheduler hits (no shell crontab on shared hosting). Generate a
        // long random value per environment — never reuse the local one in production.
        'secret' => env('CRON_SECRET'),
    ],

    'sources' => [
        // API keys for import sources, referenced from a source's `config` JSON
        // as "secret:<name>" (e.g. {"headers": {"x-api-key": "secret:myswitzerland"}}).
        //
        // They live here rather than in the sources table because that config is
        // editable — and visible — in the admin UI, and they're read through
        // config() rather than env() because `config:cache` (which the deploy
        // runs) stops env() resolving at runtime entirely.
        //
        // Add one line per source that needs a key, then set the variable in the
        // server's .env.
        'secrets' => [
            'myswitzerland' => env('MYSWITZERLAND_API_KEY'),
            'ticketmaster' => env('TICKETMASTER_API_KEY'),
        ],
    ],

];
