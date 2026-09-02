<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateInventoryMovementsTable extends Migration
{
    /**
     * Nueva 2026-09-02 (ing. inversa): traducción de database/sql/inventory_movements.sql
     * (versión final, con receipts + audit ya integrados). El stock real del sistema
     * se calcula como SUM(entradas) - SUM(salidas) sobre esta tabla; products.stock
     * queda como columna legacy. Ver App\Services\InventoryService.
     */
    public function up()
    {
        Schema::create('inventory_movements', function (Blueprint $table) {
            $table->id();

            $table->foreignId('product_id')->constrained('products')->cascadeOnUpdate()->restrictOnDelete();
            $table->unsignedBigInteger('sale_id')->nullable();
            $table->unsignedBigInteger('sale_detail_id')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->char('reception_id', 36)->nullable();

            $table->enum('type', ['entrada', 'salida']);
            $table->enum('origin', ['compra', 'venta', 'cancelacion', 'ajuste']);

            $table->decimal('quantity', 14, 3);
            $table->decimal('balance_after', 14, 3);
            $table->string('unit', 30);

            $table->decimal('unit_cost', 14, 2)->nullable();
            $table->decimal('total_cost', 16, 2)->nullable();

            $table->string('notes', 500)->nullable();

            $table->enum('status', ['active', 'cancelled'])->default('active');
            $table->dateTime('cancelled_at')->nullable();
            $table->unsignedBigInteger('cancelled_by')->nullable();
            $table->string('cancellation_reason', 500)->nullable();

            $table->dateTime('moved_at')->useCurrent();
            $table->timestamps();

            $table->foreign('sale_id')->references('id')->on('sales')->nullOnDelete();
            $table->foreign('sale_detail_id')->references('id')->on('sale_details')->nullOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
            $table->foreign('cancelled_by')->references('id')->on('users')->nullOnDelete();

            $table->index(['product_id', 'moved_at']);
            $table->index(['type', 'moved_at']);
            $table->index('sale_id');
            $table->index('reception_id');
            $table->index('status');
            $table->unique(['sale_detail_id', 'type', 'origin'], 'inventory_movements_detail_type_origin_uq');
        });
    }

    public function down()
    {
        Schema::dropIfExists('inventory_movements');
    }
}
