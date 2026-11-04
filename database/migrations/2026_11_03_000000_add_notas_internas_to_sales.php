<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Notas del personal separadas de la nota del cliente (hallazgo S-14).
 * sales.notas = lo que escribe el cliente al pedir y el motivo de rechazo de la transferencia (los ve el cliente).
 * sales.notas_internas = notas del personal en Despachos; no salen por la API (Sale::$hidden).
 * Las notas viejas no se mueven: no hay forma segura de separar lo que ya esta mezclado.
 */
class AddNotasInternasToSales extends Migration
{
    public function up()
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->text('notas_internas')->nullable()->after('notas');
        });
    }

    public function down()
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropColumn('notas_internas');
        });
    }
}
