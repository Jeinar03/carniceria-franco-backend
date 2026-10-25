<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;

/**
 * Link de Google Maps (el que se copia con Compartir). Se acepta solo ese origen porque
 * el link se le pasa tal cual al mandadito: no debe ser una pagina cualquiera.
 */
class UbicacionGoogleMaps implements Rule
{
    public function passes($attribute, $value): bool
    {
        if (! is_string($value) || strlen($value) > 500) {
            return false;
        }

        $partes = parse_url(trim($value));
        if (! $partes || ! in_array($partes['scheme'] ?? '', ['http', 'https'], true) || empty($partes['host'])) {
            return false;
        }

        $host = strtolower($partes['host']);
        $ruta = $partes['path'] ?? '';

        // Links cortos de la app de Maps.
        if ($host === 'maps.app.goo.gl') {
            return true;
        }
        if ($host === 'goo.gl') {
            return str_starts_with($ruta, '/maps');
        }

        // maps.google.com y google.com/maps (con o sin pais: .com.mx, www, etc.).
        if (! preg_match('/^(www\.|maps\.)?google\.com(\.mx)?$/', $host)) {
            return false;
        }

        return str_starts_with($host, 'maps.') || str_starts_with($ruta, '/maps');
    }

    public function message(): string
    {
        return 'Pega el link de Google Maps (en Maps toca tu ubicación, Compartir y Copiar link).';
    }
}
