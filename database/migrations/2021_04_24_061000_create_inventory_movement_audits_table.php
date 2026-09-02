<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateInventoryMovementAuditsTable extends Migration
{
    /**
     * Nueva 2026-09-02 (ing. inversa): de database/sql/inventory_movements.sql.
     * Histórico de ediciones / cancelaciones de movimientos de inventario.
     * Modelo: App\Models\InventoryMovementAudit.
     */
    public function up()
    {
        Schema::create('inventory_movement_audits', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('inventory_movement_id');
            $table->char('reception_id', 36)->nullable();

            $table->enum('action', ['edited', 'cancelled']);

            $table->decimal('old_quantity', 14, 3);
            $table->decimal('new_quantity', 14, 3);
            $table->decimal('old_unit_cost', 14, 2)->nullable();
            $table->decimal('new_unit_cost', 14, 2)->nullable();
            $table->decimal('old_total_cost', 16, 2)->nullable();
            $table->decimal('new_total_cost', 16, 2)->nullable();
            $table->string('old_notes', 500)->nullable();
            $table->string('new_notes', 500)->nullable();

            $table->unsignedBigInteger('user_id')->nullable();

            $table->timestamps();

            $table->foreign('inventory_movement_id')->references('id')->on('inventory_movements')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();

            $table->index('inventory_movement_id');
            $table->index('reception_id');
        });
    }

    public function down()
    {
        Schema::dropIfExists('inventory_movement_audits');
    }
}
