<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Forma de entrega del pedido: mandadito (servicio externo) o recoger en la carniceria.
 * Las ventas que ya existen quedan como mandadito.
 */
class AddTipoEntregaToSalesTable extends Migration
{
    public function up()
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->string('tipo_entrega', 20)->default('mandadito')->after('fecha_entrega');
        });
    }

    public function down()
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropColumn('tipo_entrega');
        });
    }
}
