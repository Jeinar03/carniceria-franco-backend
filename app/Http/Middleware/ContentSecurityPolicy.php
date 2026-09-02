<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class ContentSecurityPolicy
{
    /**
     * Rutas donde se aplica CSP ESTRICTO completo con nonce
     * + Cross-Origin headers + cache no-store.
     *
     * El resto de la app: solo headers básicos, sin CSP.
     */
     private array $cspRoutes = [
        '/',
        'login',
        'logout',
        'register',
        'password/*',
        '2fa/*',
    ];

    public function handle(Request $request, Closure $next)
    {
        $nonce = base64_encode(random_bytes(16));
        app()->instance('nonce', $nonce);

        $response = $next($request);

        if (!$response || !method_exists($response, 'header')) {
            return $response;
        }

        $statusCode = $response->getStatusCode();

        // =====================================================
        // HEADERS QUE APLICAN A TODAS LAS RUTAS
        // =====================================================
        // La evidencia de transferencia se muestra dentro del modal de Despachos.
        // Únicamente esa ruta autenticada puede ser embebida por páginas del mismo origen.
        $allowsSameOriginFrame = $request->routeIs('admin.transfer-evidence.show');
        $response->headers->set('X-Frame-Options', $allowsSameOriginFrame ? 'SAMEORIGIN' : 'DENY');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-XSS-Protection', '1; mode=block');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'geolocation=(), microphone=(), camera=()');
        $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains; preload');

        // =====================================================
        // CONDICIONES PARA CSP ESTRICTO:
        // - Rutas de auth definidas en $cspRoutes
        // - Cualquier redirect (301, 302, 303, 307, 308)
        // - Respuestas de error (4xx, 5xx)
        // =====================================================
        $isAuthRoute = $request->is(...$this->cspRoutes);
        $isRedirect  = $statusCode >= 300 && $statusCode < 400;
        $isError     = $statusCode >= 400;

        if ($isAuthRoute || $isRedirect || $isError) {

            $response->headers->set(
                'Content-Security-Policy',
                "default-src 'self'; " .
                "script-src 'self' 'nonce-{$nonce}'; " .
                "style-src 'self' 'nonce-{$nonce}'; " .
                "img-src 'self' data: blob:; " .
                "font-src 'self' data:; " .
                "connect-src 'self'; " .
                "media-src 'self'; " .
                "object-src 'none'; " .
                "manifest-src 'self'; " .
                "worker-src 'self'; " .
                "child-src 'self'; " .
                "frame-src 'none'; " .
                "frame-ancestors 'none'; " .
                "form-action 'self'; " .
                "base-uri 'self'; " .
                "upgrade-insecure-requests; " .
                "block-all-mixed-content;"
            );

            // Cross-Origin headers estrictos
            // (seguros aquí: no hay Livewire en estas rutas)
            $response->headers->set('Cross-Origin-Embedder-Policy', 'require-corp');
            $response->headers->set('Cross-Origin-Opener-Policy', 'same-origin');
            $response->headers->set('Cross-Origin-Resource-Policy', 'same-origin');

            // Cache estricto - no cachear credenciales ni redirects
            $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
            $response->headers->set('Pragma', 'no-cache');
            $response->headers->set('Expires', '0');
        // =====================================================
        // RESTO DE LA APP: CSP RELAJADO
        // Livewire, SweetAlert2, etc. funcionan sin problemas
        // =====================================================
        }  else {
            // =====================================================
            // RESTO DE LA APP: Sin CSP, sin Cross-Origin headers
            // Livewire, SweetAlert2, etc. funcionan sin restricciones
            // =====================================================
            $response->headers->set('Cache-Control', 'private, no-cache, must-revalidate');
        }

        return $response;
    }
}
