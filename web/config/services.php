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
    | fake_charge_outcome okrece ishod lokalne MIT naplate pri obnovi pretplate,
    | da se i odbijena obnova moze provjeriti bez prave kartice.
    |
    */

    'haus' => [
        'payment_gateway' => env('HAUS_PAYMENT_GATEWAY', 'fake'),
        'fake_gateway_secret' => env('HAUS_FAKE_GATEWAY_SECRET', 'haus-lokalna-tajna'),
        'fake_charge_outcome' => env('HAUS_FAKE_CHARGE_OUTCOME', 'approved'),
        'dispatcher_email' => env('HAUS_DISPATCHER_EMAIL', 'dispecer@haus.ba'),
        'bank_account' => env('HAUS_BANK_ACCOUNT', '1610000000000000'),
        'company_name' => env('HAUS_COMPANY_NAME', 'HAUS d.o.o.'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Anthropic (AI podrska)
    |--------------------------------------------------------------------------
    |
    | Model za chat podrske na sajtu. Jeftin model, kratki odgovori.
    | Bez kljuca endpoint vraca 503 i widget kaze da podrska nije dostupna.
    |
    */

    'anthropic' => [
        'api_key' => env('ANTHROPIC_API_KEY'),
        'model' => env('ANTHROPIC_SUPPORT_MODEL', 'claude-haiku-4-5'),
        'max_tokens' => 1024,
    ],

    /*
    |--------------------------------------------------------------------------
    | Monri Payments
    |--------------------------------------------------------------------------
    |
    | Sve pretpostavke o Monri integraciji zive ovdje, da se pri onboardingu
    | mijenja konfiguracija a ne kod. Kod je u App\Services\Payments\MonriGateway
    | i nosi TODO MONRI komentare uz svaku stavku koja ceka potvrdu.
    |
    | key            tajna trgovca, ulazi u svaki digest. Nikad ne ide klijentu.
    | api_base       server za API pozive (refund, naplata po tokenu).
    | webpay_base    server hosted stranice na koju se salje forma sa 3DS2.
    |
    */

    'monri' => [
        'key' => env('MONRI_KEY', env('MONRI_MERCHANT_KEY')),
        'authenticity_token' => env('MONRI_AUTHENTICITY_TOKEN'),
        'api_base' => env('MONRI_API_BASE', 'https://ipgtest.monri.com'),
        'webpay_base' => env('MONRI_WEBPAY_BASE', 'https://ipgtest.monri.com'),

        // TODO MONRI: potvrditi putanje iz dokumentacije.
        'form_path' => env('MONRI_FORM_PATH', '/v2/form'),
        'transaction_path' => env('MONRI_TRANSACTION_PATH', '/v2/transaction'),
        'refund_path' => env('MONRI_REFUND_PATH', '/v2/transaction/{order_number}/refund'),

        'currency' => env('MONRI_CURRENCY', 'BAM'),
        'language' => env('MONRI_LANGUAGE', 'bs'),
        'transaction_type' => env('MONRI_TRANSACTION_TYPE', 'purchase'),
        'refund_transaction_type' => env('MONRI_REFUND_TRANSACTION_TYPE', 'refund'),
        'approved_statuses' => ['approved'],

        // Povratne adrese. Prazno znaci da vaze one podesene u Monri panelu.
        'success_url' => env('MONRI_SUCCESS_URL'),
        'cancel_url' => env('MONRI_CANCEL_URL'),
        'callback_url' => env('MONRI_CALLBACK_URL'),

        // TODO MONRI: potvrditi kako Monri potpisuje callback.
        'callback_signature_header' => env('MONRI_CALLBACK_SIGNATURE_HEADER', 'authorization'),
        'callback_signature_scheme' => env('MONRI_CALLBACK_SIGNATURE_SCHEME', 'WP3-callback'),

        'timeout' => (int) env('MONRI_TIMEOUT', 20),
        'connect_timeout' => (int) env('MONRI_CONNECT_TIMEOUT', 5),
    ],

];
