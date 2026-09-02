<?php
// app/Http/Middleware/DetectInjectionAttempts.php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

class DetectInjectionAttempts
{
    /**
     * Patrones de alto riesgo: si se detectan, se bloquea.
     */
    protected $highRiskPatterns = [
        'union\s+all\s+select',
        'drop\s+table',
        'exec\s+xp_',
        ';\s*drop\s+table',
        ';\s*(wget|curl|nc|bash|sh)\b',
        '`[^`]+`',
        '\$\([^\)]+\)',
    ];

    /**
     * Patrones de riesgo medio: solo se registran para revisión.
     * Se eliminan reglas muy amplias para evitar falsos positivos.
     */
    protected $mediumRiskPatterns = [
        'union\s+select',
        'or\s+1\s*=\s*1',
        'and\s+1\s*=\s*1',
        '\{\s*\$ne\s*:',
        '\{\s*\$gt\s*:',
        '\{\s*\$regex\s*:',
    ];

    protected $excludedKeys = ['password', 'password_confirmation', 'token', '_token'];

    protected $excludedPaths = [
        'livewire',
        'livewire/message',
        'livewire/upload-file',
    ];

    public function handle(Request $request, Closure $next)
    {
        if ($this->shouldSkip($request)) {
            return $next($request);
        }

        $payload = $this->inspectablePayload($request->all());
        $input = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if (! is_string($input) || $input === '') {
            return $next($request);
        }

        $normalizedInput = strtolower(rawurldecode($input));

        foreach ($this->highRiskPatterns as $pattern) {
            if ($this->matches($pattern, $normalizedInput)) {
                $this->logDetection($request, $payload, $pattern, 'high');

                abort(403, 'Actividad sospechosa detectada');
            }
        }

        foreach ($this->mediumRiskPatterns as $pattern) {
            if ($this->matches($pattern, $normalizedInput)) {
                $this->logDetection($request, $payload, $pattern, 'medium');
                break;
            }
        }

        return $next($request);
    }

    private function matches(string $pattern, string $input): bool
    {
        return preg_match('/' . $pattern . '/i', $input) === 1;
    }

    private function shouldSkip(Request $request): bool
    {
        if ($request->is('livewire/*')) {
            return true;
        }

        foreach ($this->excludedPaths as $path) {
            if ($request->is($path . '*')) {
                return true;
            }
        }

        return false;
    }

    private function inspectablePayload(array $data): array
    {
        $sanitized = $data;

        foreach ($this->excludedKeys as $key) {
            if (array_key_exists($key, $sanitized)) {
                $sanitized[$key] = '[hidden]';
            }
        }

        return $sanitized;
    }

    private function logDetection(Request $request, array $payload, string $pattern, string $risk): void
    {
        $context = [
            'risk' => $risk,
            'ip' => $request->ip(),
            'user_id' => auth()->id(),
            'url' => $request->fullUrl(),
            'method' => $request->method(),
            'input' => $payload,
            'pattern_matched' => $pattern,
            'user_agent' => $request->userAgent(),
            'timestamp' => now(),
        ];

        try {
            Log::channel('security')->warning('Intento de inyección detectado', $context);
        } catch (Throwable $e) {
            // Fallback si el canal "security" no esta configurado.
            Log::warning('Intento de inyeccion detectado', $context + ['log_fallback' => true]);
        }
    }
}
