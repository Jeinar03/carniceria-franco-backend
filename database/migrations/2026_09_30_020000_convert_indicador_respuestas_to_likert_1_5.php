<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class ConvertIndicadorRespuestasToLikert15 extends Migration
{
    /**
     * La encuesta de satisfaccion pasa de escala 1 a 10 a escala Likert 1 a 5 (la de la
     * Tabla de operacionalizacion de variables del Taller 2). Las respuestas ya guardadas
     * se convierten una sola vez: 1-2 a 1, 3-4 a 2, 5-6 a 3, 7-8 a 4 y 9-10 a 5.
     * Eran respuestas de prueba; no se guarda el valor original.
     */
    public function up()
    {
        $conversion = DB::getDriverName() === 'sqlite'
            ? '(respuesta + 1) / 2'
            : 'FLOOR((respuesta + 1) / 2)';

        DB::table('indicador_respuestas')->update(['respuesta' => DB::raw($conversion)]);
    }

    /** Vuelta aproximada: cada valor de 1 a 5 regresa a la escala 1 a 10 (1 a 2, 2 a 4 ... 5 a 10). */
    public function down()
    {
        DB::table('indicador_respuestas')->update(['respuesta' => DB::raw('respuesta * 2')]);
    }
}
