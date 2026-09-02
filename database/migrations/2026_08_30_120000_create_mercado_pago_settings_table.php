<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateMercadoPagoSettingsTable extends Migration
{
    public function up()
    {
        Schema::create('mercado_pago_settings', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->default('Configuracion principal');
            $table->text('access_token')->nullable();
            $table->text('public_key')->nullable();
            $table->boolean('sandbox')->default(true);
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->index('active');
        });
    }

    public function down()
    {
        Schema::dropIfExists('mercado_pago_settings');
    }
}
