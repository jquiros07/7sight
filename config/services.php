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

    'internal' => [
        'token' => env('INTERNAL_API_TOKEN'),
    ],

    'mediamtx' => [
        'api_url' => env('MEDIAMTX_API_URL', 'http://mediamtx:9997'),
        // Container-to-container only - the app proxies HLS playback itself
        // (routes/api.php's cameras.hls route) rather than exposing MediaMTX's
        // HLS server to browsers directly, so this never faces the internet.
        'internal_hls_url' => env('MEDIAMTX_INTERNAL_HLS_URL', 'http://mediamtx:8888'),
    ],

];
