<?php

namespace App\Support;

use App\Models\Customers;
use Illuminate\Http\Request;

/**
 * Aceptacion de los textos legales (aviso de privacidad, terminos y devoluciones).
 *
 * La version vigente sale de config/legal.php. La fecha y la version aceptadas las escribe el
 * servidor: el cliente solo manda `acepta_legales`. No se guarda IP.
 */
class Legales
{
    public static function version(): string
    {
        return (string) config('legal.version');
    }

    /** El cliente ya acepto la version vigente. */
    public static function alDia(?Customers $cliente): bool
    {
        return $cliente !== null
            && $cliente->legales_aceptados_at !== null
            && $cliente->legales_version === self::version();
    }

    /** Registro de cuenta: la casilla es obligatoria. */
    public static function reglasRegistro(): array
    {
        return ['acepta_legales' => 'accepted'];
    }

    /** Pedido: la casilla solo se exige a quien no ha aceptado la version vigente. */
    public static function reglasPedido(?Customers $cliente): array
    {
        return self::alDia($cliente) ? [] : self::reglasRegistro();
    }

    public static function mensajes(): array
    {
        return [
            'acepta_legales.accepted' => 'Debes aceptar el Aviso de privacidad y los Términos y condiciones para continuar.',
        ];
    }

    /** Guarda la aceptacion en el cliente (fecha y version del servidor) si hacia falta. */
    public static function registrarAceptacion(Request $request, Customers $cliente): void
    {
        if (self::alDia($cliente) || ! $request->boolean('acepta_legales')) {
            return;
        }

        // forceFill: estas columnas no estan en $fillable a proposito.
        $cliente->forceFill([
            'legales_aceptados_at' => now(),
            'legales_version' => self::version(),
        ])->save();
    }

    /** Columna de `sales` con la version vigente al comprar. */
    public static function paraVenta(): array
    {
        return ['legales_version' => self::version()];
    }
}
