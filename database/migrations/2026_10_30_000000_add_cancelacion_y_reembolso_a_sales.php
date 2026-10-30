<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cancelacion de pedidos y reembolsos.
 *
 * - cancelacion_solicitada_at / cancelacion_motivo: el cliente pidio cancelar un pedido ya pagado;
 *   un Admin la aprueba o la rechaza.
 * - cancelado_at / cancelado_por_tipo: cuando y quien cancelo (cliente, admin, sistema).
 * - reembolso_*: devolucion del pago. reembolso_estado: pendiente (hay que devolver), hecho, manual.
 * - requiere_revision: marca visible en el panel para pagos aprobados que no cuadraron.
 *
 * Todas las columnas son opcionales: las ventas anteriores siguen funcionando.
 */
class AddCancelacionYReembolsoASales extends Migration
{
    public function up()
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->timestamp('cancelacion_solicitada_at')->nullable();
            $table->string('cancelacion_motivo', 255)->nullable();
            $table->timestamp('cancelado_at')->nullable();
            $table->string('cancelado_por_tipo', 20)->nullable();
            $table->string('reembolso_id', 100)->nullable();
            $table->string('reembolso_estado', 20)->nullable();
            $table->decimal('reembolso_monto', 10, 2)->nullable();
            $table->timestamp('reembolsado_at')->nullable();
            $table->boolean('requiere_revision')->default(false);
        });
    }

    public function down()
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropColumn([
                'cancelacion_solicitada_at', 'cancelacion_motivo', 'cancelado_at', 'cancelado_por_tipo',
                'reembolso_id', 'reembolso_estado', 'reembolso_monto', 'reembolsado_at', 'requiere_revision',
            ]);
        });
    }
}
