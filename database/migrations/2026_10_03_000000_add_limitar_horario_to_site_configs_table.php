<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Interruptor para limitar las compras al horario de atencion.
 * Encendido por defecto; apagado permite comprar a cualquier hora (modo pruebas).
 */
class AddLimitarHorarioToSiteConfigsTable extends Migration
{
    public function up()
    {
        Schema::table('site_configs', function (Blueprint $table) {
            $table->boolean('limitar_horario')->default(true)->after('horarios');
        });
    }

    public function down()
    {
        Schema::table('site_configs', function (Blueprint $table) {
            $table->dropColumn('limitar_horario');
        });
    }
}
