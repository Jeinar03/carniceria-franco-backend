<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Motivo del estado del pago (status_detail de Mercado Pago), p. ej. cc_rejected_high_risk.
 * Explica por que se rechazo un pago sin tener que entrar al panel de Mercado Pago.
 */
class AddMercadopagoStatusDetailToSalesTable extends Migration
{
    public function up()
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->string('mercadopago_status_detail', 100)->nullable()->after('mercadopago_status');
        });
    }

    public function down()
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropColumn('mercadopago_status_detail');
        });
    }
}
