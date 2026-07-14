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

    // ✅ GOOGLE DEBE IR AQUÍ
    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URI'),
    ],

    'webfleet' => [
        'base_url' => env('WEBFLEET_BASE_URL', 'https://csv.webfleet.com/extern'),
        'account' => env('WEBFLEET_ACCOUNT'),
        'username' => env('WEBFLEET_USERNAME'),
        'password' => env('WEBFLEET_PASSWORD'),
        'api_key' => env('WEBFLEET_API_KEY'),
    ],

    'odoo' => [
        'url'        => env('ODOO_URL', 'https://agricolaehe-prueba-31455293.dev.odoo.com'),
        'db'         => env('ODOO_DB', 'agricolaehe-prueba-31455293'),
        'user'       => env('ODOO_USER', 's.lopez.epple@gmail.com'),
        'password'   => env('ODOO_PASSWORD', '1234'),
        'journal_id' => env('ODOO_JOURNAL_ID', 22),
    ],

    'banco_chile' => [
        'client_id'     => env('BANCO_CHILE_CLIENT_ID', '0b881efcaf610304f3c0e8f7ce18cd5d'),
        'client_secret' => env('BANCO_CHILE_CLIENT_SECRET', 'de5eea84369b05dd2fc5f9bb484a56ee'),
        'api_url'       => env('BANCO_CHILE_API_URL', 'https://gw.apistore.bancochile.cl/banco-chile/sandbox/v1/movimientos-cuenta/obtener'),
    ],

];
