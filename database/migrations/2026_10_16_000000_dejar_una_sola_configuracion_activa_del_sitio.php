<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * En produccion quedaron dos configuraciones del sitio marcadas como activas.
 * Deja activa solo la que ya usa la tienda (la editada al ultimo) y apaga las demas.
 * Si no hay ninguna activa, activa la mas reciente para que la tienda tenga datos.
 */
class DejarUnaSolaConfiguracionActivaDelSitio extends Migration
{
    public function up()
    {
        $activa = DB::table('site_configs')
            ->where('activo', true)
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->first();

        if (! $activa) {
            $activa = DB::table('site_configs')
                ->orderByDesc('updated_at')
                ->orderByDesc('id')
                ->first();
        }

        if (! $activa) {
            return;
        }

        DB::table('site_configs')->where('id', '!=', $activa->id)->update(['activo' => false]);
        DB::table('site_configs')->where('id', $activa->id)->update(['activo' => true]);
    }

    public function down()
    {
        // No se puede saber cuales estaban activas antes; no hace nada.
    }
}
