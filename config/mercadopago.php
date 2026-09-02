<?php

return [
    /*
    |--------------------------------------------------------------------------
    | MercadoPago Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for MercadoPago integration
    |
    */

    'access_token' => env('MERCADOPAGO_ACCESS_TOKEN'),
    'public_key' => env('MERCADOPAGO_PUBLIC_KEY'),
    'webhook_secret' => env('MERCADOPAGO_WEBHOOK_SECRET'),

    // Environment settings
    'sandbox' => env('MERCADOPAGO_SANDBOX', env('APP_ENV', 'local') !== 'production'),

    // URLs
    'success_url' => env('APP_URL') . '/payment/success',
    'failure_url' => env('APP_URL') . '/payment/failure',
    'pending_url' => env('APP_URL') . '/payment/pending',
    'notification_url' => env('APP_URL') . '/api/v1/mercadopago/webhook',
];
