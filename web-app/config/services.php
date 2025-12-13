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
        'token' => env('POSTMARK_TOKEN'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
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

    'parser' => [
        'base_url' => env('PARSER_SERVICE_BASE_URL', 'http://localhost:8080'),
        'api_key' => env('PARSER_SERVICE_API_KEY'),
        'allow_duplicate_demos' => env('ALLOW_DUPLICATE_DEMOS', env('APP_ENV') === 'local'),
    ],

    'steam' => [
        'client_id' => env('STEAM_API_KEY'),
        'client_secret' => env('STEAM_API_KEY'),
        'redirect' => env('STEAM_REDIRECT_URI'),
        'api_key' => env('STEAM_API_KEY'),
        'max_sharecodes_per_run' => env('STEAM_MAX_SHARECODES_PER_RUN', 50),
    ],

    'valve_demo_url_service' => [
        'base_url' => env('VALVE_DEMO_URL_SERVICE_BASE_URL', 'http://localhost:3001'),
        'api_key' => env('VALVE_DEMO_URL_SERVICE_API_KEY'),
    ],

    'faceit' => [
        'api_key' => env('FACEIT_API_KEY'),
    ],

    'rate_limiting' => [
        'steam_api' => [
            'max_requests' => 100,
            'window_seconds' => 300,
            'redis_key' => 'rate_limit:steam_api',
        ],
        'valve_demo_url' => [
            'max_requests' => 20,
            'window_seconds' => 60,
            'redis_key' => 'rate_limit:valve_demo_url',
        ],
        'parser_service' => [
            'max_concurrent' => 3,
            'redis_key' => 'rate_limit:parser_service',
        ],
    ],

];
