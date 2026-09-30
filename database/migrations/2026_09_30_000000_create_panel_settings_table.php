<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CreatePanelSettingsTable extends Migration
{
    /**
     * Ajustes sencillos del panel (clave/valor). Por ahora: los minutos del aviso
     * "Por terminar" (amarillo y rojo).
     */
    public function up()
    {
        Schema::create('panel_settings', function (Blueprint $table) {
            $table->id();
            $table->string('clave', 100)->unique();
            $table->text('valor')->nullable();
            $table->timestamps();
        });

        DB::table('panel_settings')->insert([
            ['clave' => 'aviso_pedidos_amarillo_min', 'valor' => '30', 'created_at' => now(), 'updated_at' => now()],
            ['clave' => 'aviso_pedidos_rojo_min', 'valor' => '60', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function down()
    {
        Schema::dropIfExists('panel_settings');
    }
}
