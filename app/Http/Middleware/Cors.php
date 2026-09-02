<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class Cors
{
    /**
     * Cabeceras CORS para las rutas del grupo 'cors' (ver routes/api.php).
     *
     * El origen permitido sale de config/cors.php (que lee FRONTEND_URL del .env).
     * Si no hay lista (entorno local), se permite cualquier origen ('*').
     * En producción: FRONTEND_URL=https://tienda.carniceriafrancoadmin.shop
     */
    public function handle($request, Closure $next)
    {
        $allowed = (array) config('cors.allowed_origins', ['*']);
        $origin = $request->headers->get('Origin');

        if (in_array('*', $allowed, true)) {
            $allowOrigin = '*';
        } elseif ($origin && in_array($origin, $allowed, true)) {
            $allowOrigin = $origin;
        } else {
            $allowOrigin = $allowed[0] ?? '*';
        }

        return $next($request)
            ->header('Access-Control-Allow-Origin', $allowOrigin)
            ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, PATCH, DELETE, OPTIONS')
            ->header('Access-Control-Allow-Headers', 'X-Requested-With, Content-Type, X-Token-Auth, Authorization');
    }
}
