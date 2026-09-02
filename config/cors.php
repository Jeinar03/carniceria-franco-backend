<?php

/*
 * Orígenes permitidos para peticiones cross-origin a la API.
 *
 * - En local: si FRONTEND_URL no está en el .env, se permite '*' (cualquier origen).
 * - En producción: pon FRONTEND_URL=https://tienda.carniceriafrancoadmin.shop en el .env
 *   para que solo la tienda pueda consumir la API.
 *
 * Acepta varias URLs separadas por coma:
 *   FRONTEND_URL=https://tienda.carniceriafrancoadmin.shop,https://otra.com
 */

$frontendUrls = array_filter(array_map('trim', explode(',', (string) env('FRONTEND_URL', ''))));

return [
    'paths' => ['api/*', 'sanctum/csrf-cookie'],
    'allowed_methods' => ['*'],
    'allowed_origins' => $frontendUrls ?: ['*'],
    'allowed_origins_patterns' => [],
    'allowed_headers' => ['*'],
    'exposed_headers' => [],
    'max_age' => 0,
    'supports_credentials' => false,
];
