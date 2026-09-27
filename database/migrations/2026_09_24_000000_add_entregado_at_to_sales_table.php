<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddEntregadoAtToSalesTable extends Migration
{
    /**
     * Registro de cuándo (y por quién) una venta quedó marcada como
     * entregada. Se usa principalmente para la venta de mostrador, que
     * queda entregada de forma automática al crearse en vez de pasar por
     * la cola de Despachos.
     */
    public function up()
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->timestamp('entregado_at')->nullable()->after('estado_envio');
            $table->unsignedBigInteger('entregado_por')->nullable()->after('entregado_at');

            $table->foreign('entregado_por')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down()
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropForeign(['entregado_por']);
            $table->dropColumn(['entregado_at', 'entregado_por']);
        });
    }
}
