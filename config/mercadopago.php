<?php

return [
    /*
    |--------------------------------------------------------------------------
    | MercadoPago Configuration
    |--------------------------------------------------------------------------
    |
    | Las credenciales se pueden capturar desde el panel admin
    | (Sistema → Mercado Pago); estos valores del .env son el respaldo.
    |
    */

    'access_token' => env('MERCADOPAGO_ACCESS_TOKEN'),
    'public_key' => env('MERCADOPAGO_PUBLIC_KEY'),
    'webhook_secret' => env('MERCADOPAGO_WEBHOOK_SECRET'),

    // false (por omisión): una firma de webhook que no coincide se anota en el log pero el aviso se
    // procesa igual (el pago se consulta siempre a Mercado Pago). true: se rechaza con 401.
    'firma_estricta' => filter_var(env('MERCADOPAGO_FIRMA_ESTRICTA', false), FILTER_VALIDATE_BOOLEAN),

    // Solo controla si se omite el correo del cliente en el checkout. Prueba vs
    // producción lo define el tipo de credencial (Access Token) que se use.
    'sandbox' => env('MERCADOPAGO_SANDBOX', env('APP_ENV', 'local') !== 'production'),

    // Tienda (Angular) a la que regresa el cliente después de pagar. Se toma la
    // primera URL de FRONTEND_URL (puede venir una lista separada por comas).
    'frontend_url' => rtrim(trim(explode(',', (string) env('FRONTEND_URL', ''))[0]), '/'),

    // Webhook (server-to-server). Debe ser HTTPS público; si no lo es, no se registra.
    // Por defecto APP_URL + /api/v1/mercadopago/webhook; MERCADOPAGO_WEBHOOK_URL lo sobrescribe.
    'notification_url' => env('MERCADOPAGO_WEBHOOK_URL')
        ?: rtrim((string) env('APP_URL'), '/') . '/api/v1/mercadopago/webhook',
];
