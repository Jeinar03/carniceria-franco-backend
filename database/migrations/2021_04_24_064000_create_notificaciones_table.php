<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateNotificacionesTable extends Migration
{
    /**
     * Nueva 2026-09-02 (ing. inversa): modelo App\Models\Notificaciones +
     * panel admin Livewire (/sistema/notificaciones). Anuncios/notificaciones
     * push que se muestran a los clientes.
     */
    public function up()
    {
        Schema::create('notificaciones', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->text('descripcion')->nullable();
            $table->timestamp('fecha_envio')->nullable();
            $table->string('logo')->nullable();
            $table->string('titulo_notificacion')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('notificaciones');
    }
}
