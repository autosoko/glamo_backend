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

    'provider_onboarding' => [
        'admin_email' => env('PROVIDER_ONBOARDING_ADMIN_EMAIL', 'info@getglamo.com'),
        'admin_phone' => env('PROVIDER_ONBOARDING_ADMIN_PHONE', '255743693885'),
    ],

    'glamo' => [
        'website_url' => env('GLAMO_WEBSITE_URL', 'https://getglamo.com'),
        'app_store_url' => env('GLAMO_APP_STORE_URL', 'https://apps.apple.com/'),
        'play_store_url' => env('GLAMO_PLAY_STORE_URL', 'https://play.google.com/store'),
        'support_email' => env('GLAMO_SUPPORT_EMAIL', 'info@getglamo.com'),
    ],

];
