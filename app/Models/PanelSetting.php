<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Ajustes clave/valor del panel de administracion.
 */
class PanelSetting extends Model
{
    protected $table = 'panel_settings';

    protected $fillable = ['clave', 'valor'];

    public static function get(string $clave, $default = null)
    {
        $valor = static::where('clave', $clave)->value('valor');

        return $valor === null ? $default : $valor;
    }

    public static function set(string $clave, $valor): void
    {
        static::updateOrCreate(['clave' => $clave], ['valor' => (string) $valor]);
    }
}
