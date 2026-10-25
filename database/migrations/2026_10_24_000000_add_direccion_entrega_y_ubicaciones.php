<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A donde se entrega cada pedido por mandadito y la ubicacion de la carniceria.
 *
 * - sales: direccion del pedido (puede ser distinta a la del perfil), referencias, link de
 *   Google Maps y la hora en que el cliente acepto el aviso de que el mandadito se paga aparte.
 * - customers: referencias y link de Maps de su direccion guardada (la direccion ya existia).
 * - site_configs: link de Google Maps de la carniceria, para quien elige recoger.
 *
 * Todas las columnas son opcionales: los pedidos y clientes anteriores siguen funcionando.
 */
class AddDireccionEntregaYUbicaciones extends Migration
{
    public function up()
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->string('direccion_entrega', 255)->nullable()->after('tipo_entrega');
            $table->string('referencias_entrega', 255)->nullable()->after('direccion_entrega');
            $table->string('ubicacion_url', 500)->nullable()->after('referencias_entrega');
            $table->timestamp('aviso_mandadito_aceptado_at')->nullable()->after('ubicacion_url');
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->string('referencias', 255)->nullable()->after('direccion');
            $table->string('ubicacion_url', 500)->nullable()->after('referencias');
        });

        Schema::table('site_configs', function (Blueprint $table) {
            $table->string('ubicacion_url', 500)->nullable()->after('direccion');
        });
    }

    public function down()
    {
        Schema::table('site_configs', function (Blueprint $table) {
            $table->dropColumn('ubicacion_url');
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn(['referencias', 'ubicacion_url']);
        });

        Schema::table('sales', function (Blueprint $table) {
            $table->dropColumn(['direccion_entrega', 'referencias_entrega', 'ubicacion_url', 'aviso_mandadito_aceptado_at']);
        });
    }
}
