<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Aceptacion de los textos legales de la tienda.
 *
 * - customers: cuando y que version acepto el cliente. Los pone el servidor, no el cliente.
 * - sales: version vigente al momento de la compra.
 *
 * Columnas opcionales: clientes y ventas anteriores siguen funcionando (quedan sin aceptar).
 */
class AddLegalesACustomersYSales extends Migration
{
    public function up()
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->timestamp('legales_aceptados_at')->nullable();
            $table->string('legales_version', 40)->nullable();
        });

        Schema::table('sales', function (Blueprint $table) {
            $table->string('legales_version', 40)->nullable();
        });
    }

    public function down()
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropColumn('legales_version');
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn(['legales_aceptados_at', 'legales_version']);
        });
    }
}
