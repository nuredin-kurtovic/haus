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

    /*
    |--------------------------------------------------------------------------
    | HAUS
    |--------------------------------------------------------------------------
    |
    | payment_gateway bira driver placanja: "fake" lokalno, "monri" u produkciji.
    | Monri driver stize u fazi 6, do tada je jedini implementiran driver fake.
    |
    */

    'haus' => [
        'payment_gateway' => env('HAUS_PAYMENT_GATEWAY', 'fake'),
        'fake_gateway_secret' => env('HAUS_FAKE_GATEWAY_SECRET', 'haus-lokalna-tajna'),
        'dispatcher_email' => env('HAUS_DISPATCHER_EMAIL', 'dispecer@haus.ba'),
        'bank_account' => env('HAUS_BANK_ACCOUNT', '1610000000000000'),
        'company_name' => env('HAUS_COMPANY_NAME', 'HAUS d.o.o.'),

        'monri' => [
            'merchant_key' => env('MONRI_MERCHANT_KEY'),
            'authenticity_token' => env('MONRI_AUTHENTICITY_TOKEN'),
            'endpoint' => env('MONRI_ENDPOINT', 'https://ipgtest.monri.com'),
        ],
    ],

];
