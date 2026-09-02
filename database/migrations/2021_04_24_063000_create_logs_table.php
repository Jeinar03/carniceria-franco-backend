<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateLogsTable extends Migration
{
    /**
     * Nueva 2026-09-02 (ing. inversa): modelo App\Models\Logs + LogController::store
     * (ruta POST /api/v1/historial/guardar-accion). Bitácora de acciones del cliente.
     * 'usuario' guarda el id del customer (int), no una FK dura.
     */
    public function up()
    {
        Schema::create('logs', function (Blueprint $table) {
            $table->id();
            $table->string('accion');
            $table->text('contenido');
            $table->unsignedBigInteger('usuario')->nullable();  // id de customers
            $table->timestamps();

            $table->index('usuario');
        });
    }

    public function down()
    {
        Schema::dropIfExists('logs');
    }
}
