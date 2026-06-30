<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | Hier werden die Zugangsdaten für Drittanbieter-Services wie Mailgun,
    | Postmark, AWS usw. gespeichert. Dieses File ist auch der Ort für
    | Socialite-Provider (Google, Facebook, Apple).
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
    | Hier kommen die OAuth-Provider rein, die für Social Logins genutzt
    | werden. Die Werte ziehst du dir jeweils aus deiner .env.
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
    //     // Private Key am besten Base64-codiert in .env speichern, damit keine Probleme mit Zeilenumbrüchen entstehen:
    //     'private_key_base64' => env('APPLE_PRIVATE_KEY_BASE64'),
    //     'redirect'         => env('APPLE_REDIRECT_URI'),
    // ],

];
