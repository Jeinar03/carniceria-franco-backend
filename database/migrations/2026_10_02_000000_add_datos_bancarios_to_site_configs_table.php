<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Datos bancarios para que el cliente sepa a donde transferir.
 * Todos son opcionales. Ver: Carnicería Franco/Spec - Datos bancarios para transferencias.md
 */
class AddDatosBancariosToSiteConfigsTable extends Migration
{
    public function up()
    {
        Schema::table('site_configs', function (Blueprint $table) {
            $table->string('banco', 60)->nullable()->after('whatsapp');
            $table->string('titular_cuenta', 100)->nullable()->after('banco');
            $table->string('numero_cuenta', 20)->nullable()->after('titular_cuenta');
            $table->string('clabe', 18)->nullable()->after('numero_cuenta');
        });
    }

    public function down()
    {
        Schema::table('site_configs', function (Blueprint $table) {
            $table->dropColumn(['banco', 'titular_cuenta', 'numero_cuenta', 'clabe']);
        });
    }
}
