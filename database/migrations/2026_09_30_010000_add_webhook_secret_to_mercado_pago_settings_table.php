<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddWebhookSecretToMercadoPagoSettingsTable extends Migration
{
    /**
     * Clave secreta con la que Mercado Pago firma los avisos del webhook (x-signature).
     * Se guarda cifrada. Vacia = no se valida la firma.
     */
    public function up()
    {
        Schema::table('mercado_pago_settings', function (Blueprint $table) {
            $table->text('webhook_secret')->nullable()->after('public_key');
        });
    }

    public function down()
    {
        Schema::table('mercado_pago_settings', function (Blueprint $table) {
            $table->dropColumn('webhook_secret');
        });
    }
}
