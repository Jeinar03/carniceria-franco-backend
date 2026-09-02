<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSaleDetailsTable extends Migration
{
    /**
     * Reconstruida 2026-09-02 (ing. inversa): el modelo App\Models\SaleDetail usa
     * cantidad/monto_pesos/precio_unitario/precio_oferta/subtotal/total + snapshot
     * del producto (producto_nombre/producto_codigo/unidad_venta) y estado_despacho.
     * El esquema original (price/quantity) era del POS.
     *
     * Tabla: sale_details (singular). La migración add_monto_pesos apuntaba por
     * error a "sales_details" (plural) y queda neutralizada — monto_pesos va aquí.
     */
    public function up()
    {
        Schema::create('sale_details', function (Blueprint $table) {
            $table->id();

            $table->foreignId('sale_id')->constrained('sales')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products');

            $table->decimal('cantidad', 10, 2)->default(0);
            $table->decimal('monto_pesos', 10, 2)->nullable();   // venta por monto (ej. "$100 de bistec")

            $table->decimal('precio_unitario', 10, 2)->default(0);
            $table->decimal('precio_oferta', 10, 2)->nullable();
            $table->decimal('descuento', 10, 2)->default(0);
            $table->decimal('subtotal', 10, 2)->default(0);      // calculado en el modelo (boot)
            $table->decimal('total', 10, 2)->default(0);         // calculado en el modelo (boot)

            // Snapshot del producto al momento de la venta
            $table->string('producto_nombre')->nullable();
            $table->string('producto_codigo', 50)->nullable();
            $table->string('unidad_venta', 30)->nullable();

            // Pendiente | Preparado | Despachado ...
            $table->string('estado_despacho', 30)->nullable();

            $table->timestamps();

            $table->index('sale_id');
            $table->index('product_id');
        });
    }

    public function down()
    {
        Schema::dropIfExists('sale_details');
    }
}
