<?php

namespace App\Support;

use Carbon\Carbon;
use Carbon\CarbonInterface;

/**
 * Hora de la tienda para mostrar en el panel.
 *
 * El sistema guarda las fechas en UTC (app.timezone); aqui solo se convierte al mostrarlas,
 * a la zona de la tienda (app.horario_timezone). No cambia ningun dato guardado.
 * Solo para fechas con hora: una fecha sin hora (por ejemplo fecha_entrega) no se convierte.
 *
 * Tambien da el dia de hoy en la zona de la tienda y convierte un rango de dias de la tienda a UTC
 * para consultar la base (folio, filtros por fecha, Dashboard).
 */
class HoraLocal
{
    public static function de(?CarbonInterface $fecha): ?Carbon
    {
        if (! $fecha) {
            return null;
        }

        return Carbon::instance($fecha)->timezone(config('app.horario_timezone'));
    }

    /** Fecha con hora ya formateada, o cadena vacia si no hay fecha. */
    public static function formato(?CarbonInterface $fecha, string $formato = 'd/m/Y H:i'): string
    {
        return $fecha ? self::de($fecha)->format($formato) : '';
    }

    /** Ahora mismo, en la zona de la tienda. */
    public static function ahora(): Carbon
    {
        return Carbon::now(config('app.horario_timezone'));
    }

    /** El dia de hoy en la zona de la tienda, como Y-m-d. */
    public static function hoy(): string
    {
        return self::ahora()->format('Y-m-d');
    }

    /**
     * Inicio y fin de un rango de dias (Y-m-d) de la tienda, convertidos a la zona del sistema (UTC)
     * para usarlos en consultas sobre fechas guardadas.
     */
    public static function rangoUtc(string $desde, string $hasta): array
    {
        $zona = config('app.horario_timezone');

        return [
            Carbon::parse($desde, $zona)->startOfDay()->timezone(config('app.timezone')),
            Carbon::parse($hasta, $zona)->endOfDay()->timezone(config('app.timezone')),
        ];
    }
}
