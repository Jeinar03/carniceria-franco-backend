<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSalesTable extends Migration
{
    /**
     * Reconstruida 2026-09-02 (ing. inversa): el modelo App\Models\Sale y
     * SalesController::store usan el esquema "ecommerce" completo (customer_id,
     * folio, fecha_venta, metodo_pago, transferencia_*, estatus, estado_envio...).
     * El esquema original (total/items/cash/change/status/user_id) era del POS.
     *
     * Incluye directamente las columnas que antes agregaban las migraciones
     * add_mercadopago_fields / add_transferencia_fields (ahora neutralizadas).
     */
    public function up()
    {
        Schema::create('sales', function (Blueprint $table) {
            $table->id();

            $table->foreignId('customer_id')->constrained('customers');
            $table->string('folio')->nullable()->index();       // auto: Ymd-0001 (Sale::generarFolio)
            $table->timestamp('fecha_venta')->nullable();

            $table->decimal('subtotal', 10, 2)->default(0);
            $table->decimal('descuento', 10, 2)->default(0);
            $table->decimal('impuestos', 10, 2)->default(0);
            $table->decimal('total', 10, 2)->default(0);

            // efectivo | tarjeta | transferencia | credito | mercado_pago
            $table->string('metodo_pago', 30)->default('efectivo');

            // MercadoPago
            $table->string('mercadopago_payment_id')->nullable();
            $table->string('mercadopago_status')->nullable();

            // Transferencia bancaria con validación manual
            $table->string('transferencia_estado')->nullable();
            $table->string('transferencia_evidencia_path')->nullable();
            $table->timestamp('transferencia_subida_at')->nullable();
            $table->timestamp('transferencia_validada_at')->nullable();
            $table->unsignedBigInteger('transferencia_validada_por')->nullable();

            // completada | pendiente | cancelada | entregada
            $table->string('estatus', 30)->default('completada');
            $table->text('notas')->nullable();

            // Pendiente | Procesando | Enviado | Entregado
            $table->string('estado_envio', 30)->default('Pendiente');

            // Usuario (admin/empleado) que registró la venta, si aplica
            $table->unsignedBigInteger('usuario_id')->nullable();

            $table->timestamps();

            $table->foreign('usuario_id')->references('id')->on('users')->nullOnDelete();
            $table->foreign('transferencia_validada_por')->references('id')->on('users')->nullOnDelete();

            $table->index(['customer_id', 'estatus']);
            $table->index('metodo_pago');
            $table->index('estado_envio');
            $table->index('fecha_venta');
        });
    }

    public function down()
    {
        Schema::dropIfExists('sales');
    }
}
