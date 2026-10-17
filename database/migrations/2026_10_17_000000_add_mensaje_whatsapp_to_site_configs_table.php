<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mensaje que ya aparece escrito al abrir WhatsApp desde la tienda.
 * Vacio = la tienda usa su mensaje por omision.
 */
class AddMensajeWhatsappToSiteConfigsTable extends Migration
{
    public function up()
    {
        Schema::table('site_configs', function (Blueprint $table) {
            $table->string('mensaje_whatsapp', 300)->nullable()->after('whatsapp');
        });
    }

    public function down()
    {
        Schema::table('site_configs', function (Blueprint $table) {
            $table->dropColumn('mensaje_whatsapp');
        });
    }
}
