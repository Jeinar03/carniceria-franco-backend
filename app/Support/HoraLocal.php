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
}
