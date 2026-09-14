<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddFechaEntregaToSalesTable extends Migration
{
    /**
     * Fecha en que el cliente pidió que se entregara/despachara el pedido.
     * Null = entrega inmediata (el flujo de siempre). Con fecha futura, el
     * pedido se muestra en la pestaña "Pedidos Programados" del panel de
     * Despachos en vez de la lista normal, hasta que llega su día.
     */
    public function up()
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->date('fecha_entrega')->nullable()->after('fecha_venta');
            $table->index('fecha_entrega');
        });
    }

    public function down()
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropIndex(['fecha_entrega']);
            $table->dropColumn('fecha_entrega');
        });
    }
}
