<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
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

    'africastalking' => [
        'username' => env('AT_USERNAME', 'sandbox'),
        'api_key' => env('AT_API_KEY', ''),
        'sender_id' => env('AT_SENDER_ID'),
        'webhook_token' => env('AT_WEBHOOK_TOKEN', 'local-delivery-token'),
    ],

    'mpesa' => [
        'env' => env('MPESA_ENV', 'sandbox'),
        'consumer_key' => env('MPESA_CONSUMER_KEY', ''),
        'consumer_secret' => env('MPESA_CONSUMER_SECRET', ''),
        'shortcode' => env('MPESA_SHORTCODE', '174379'),
        'passkey' => env('MPESA_PASSKEY', 'bfb279f9aa9bdbcf158e97dd71a467cd2e0c893059b10f78e6b72ada1ed2c919'),
        'callback_token' => env('MPESA_CALLBACK_TOKEN', 'bmas-mpesa-secret-callback-token'),
    ],

    'card' => [
        'driver' => env('CARD_DRIVER', 'stripe'),
    ],

    'stripe' => [
        'key' => env('STRIPE_KEY', 'pk_test_mock'),
        'secret' => env('STRIPE_SECRET', 'sk_test_mock'),
        'webhook_secret' => env('STRIPE_WEBHOOK_SECRET', 'whsec_mock'),
    ],

    'paypal' => [
        'mode' => env('PAYPAL_MODE', 'sandbox'),
        'client_id' => env('PAYPAL_CLIENT_ID', 'mock_paypal_client_id'),
        'client_secret' => env('PAYPAL_CLIENT_SECRET', 'mock_paypal_client_secret'),
        'webhook_id' => env('PAYPAL_WEBHOOK_ID', 'mock_paypal_webhook_id'),
    ],
];
