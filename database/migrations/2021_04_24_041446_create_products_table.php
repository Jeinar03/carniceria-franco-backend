<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateProductsTable extends Migration
{
    /**
     * Reconstruida 2026-09-02 (ing. inversa): el modelo App\Models\Product y
     * ProductsController usan el esquema "carnicería" (nombre/precio/unidad_venta/
     * imagenes JSON/etc). El esquema original (name/cost/price/barcode/alerts) era
     * del POS antiguo.
     *
     * NOTA: products.stock se conserva como columna legacy por compatibilidad,
     * pero el stock real se calcula desde inventory_movements (ver InventoryService
     * y database/sql/inventory_movements.sql). La API fuerza stock=0 al crear/editar.
     */
    public function up()
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();

            $table->foreignId('category_id')->constrained('categories');

            $table->string('codigo', 50)->nullable()->unique();
            $table->string('nombre', 255);
            $table->text('descripcion')->nullable();

            $table->decimal('precio', 10, 2)->default(0);
            $table->decimal('precio_oferta', 10, 2)->nullable();
            $table->boolean('en_oferta')->default(false);

            $table->string('unidad_venta', 30)->default('kilogramo');

            // Stock legacy (ya no se consulta; el saldo vive en inventory_movements)
            $table->decimal('stock', 10, 2)->default(0);
            $table->decimal('stock_minimo', 10, 2)->default(0);

            $table->string('imagen', 255)->nullable();
            $table->json('imagenes')->nullable();

            $table->decimal('peso_promedio', 10, 2)->nullable();

            $table->boolean('activo')->default(true);
            $table->boolean('destacado')->default(false);
            $table->boolean('refrigerado')->default(true);

            $table->date('fecha_vencimiento')->nullable();
            $table->string('etiquetas')->nullable();

            $table->timestamps();

            $table->index(['activo', 'destacado']);
            $table->index('en_oferta');
            $table->index('category_id');
        });
    }

    public function down()
    {
        Schema::dropIfExists('products');
    }
}
