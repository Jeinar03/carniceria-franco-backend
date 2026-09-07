<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCustomerProductPricesTable extends Migration
{
    /**
     * Precio especial en pesos por par (cliente, producto). El administrador lo
     * define desde el panel; cuando una venta es para ese cliente, la línea se
     * cobra a ese precio en vez del precio de lista / oferta.
     * Ver: Carnicería Franco/Spec — Precio especial por cliente.md
     */
    public function up()
    {
        Schema::create('customer_product_prices', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('customer_id');
            $table->unsignedBigInteger('product_id');
            $table->decimal('precio_especial', 10, 2);
            $table->boolean('activo')->default(true);
            $table->string('notas', 255)->nullable();
            $table->unsignedBigInteger('created_by')->nullable();

            $table->timestamps();

            $table->unique(['customer_id', 'product_id']);

            $table->foreign('customer_id')->references('id')->on('customers')->onDelete('cascade');
            $table->foreign('product_id')->references('id')->on('products')->onDelete('cascade');
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down()
    {
        Schema::dropIfExists('customer_product_prices');
    }
}
