<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This is where credentials for third-party services like Mailgun,
    | Postmark, AWS, etc. are stored. This file is also the place for
    | Socialite providers (Google, Facebook, Apple).
    |
    */

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Socialite Provider Config
    |--------------------------------------------------------------------------
    |
    | This is where the OAuth providers used for social logins go.
    | The values are pulled from your .env each time.
    |
    */

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URI'),
    ],

    // 'facebook' => [
    //     'client_id'     => env('FACEBOOK_CLIENT_ID'),
    //     'client_secret' => env('FACEBOOK_CLIENT_SECRET'),
    //     'redirect'      => env('FACEBOOK_REDIRECT_URI'),
    // ],

    // 'apple' => [
    //     'client_id'        => env('APPLE_CLIENT_ID'),
    //     'team_id'          => env('APPLE_TEAM_ID'),
    //     'key_id'           => env('APPLE_KEY_ID'),
    //     // Best to store the private key base64-encoded in .env so line breaks don't cause issues:
    //     'private_key_base64' => env('APPLE_PRIVATE_KEY_BASE64'),
    //     'redirect'         => env('APPLE_REDIRECT_URI'),
    // ],

];
