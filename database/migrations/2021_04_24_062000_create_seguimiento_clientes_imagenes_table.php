<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSeguimientoClientesImagenesTable extends Migration
{
    /**
     * Nueva 2026-09-02 (ing. inversa): modelo App\Models\SeguimientoClientesImagenes
     * + CustomersController::storeImages/listImages. Guarda imágenes en Base64
     * (string largo) asociadas a un cliente, con peso y comentarios opcionales.
     */
    public function up()
    {
        Schema::create('seguimiento_clientes_imagenes', function (Blueprint $table) {
            $table->id();
            $table->longText('image');                 // Base64
            $table->unsignedBigInteger('customers_id');
            $table->decimal('peso', 8, 2)->nullable();
            $table->text('comentarios')->nullable();
            $table->timestamps();

            $table->foreign('customers_id')->references('id')->on('customers')->cascadeOnDelete();
            $table->index('customers_id');
        });
    }

    public function down()
    {
        Schema::dropIfExists('seguimiento_clientes_imagenes');
    }
}
