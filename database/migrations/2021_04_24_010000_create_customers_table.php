<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCustomersTable extends Migration
{
    /**
     * Nueva 2026-09-02 (ing. inversa): no existía migración pese a haber modelo
     * App\Models\Customers, CustomersController y rutas /api/v1/clientes/*.
     * Columnas derivadas de $fillable + $casts + $attributes del modelo y de
     * las reglas de validación de CustomersController::store/update.
     */
    public function up()
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();

            // Identidad
            $table->string('nombre');
            $table->string('apellido');
            $table->string('apellido2')->nullable();
            $table->string('correo')->unique();
            $table->string('password');
            $table->string('telefono', 20)->nullable();

            // Dirección
            $table->string('direccion')->nullable();
            $table->string('ciudad')->nullable();
            $table->string('estado')->nullable();
            $table->string('codigo_postal', 10)->nullable();
            $table->string('pais')->default('México');
            $table->string('rfc', 20)->nullable();

            // Métricas / relación comercial
            $table->timestamp('fecha_registro')->nullable();
            $table->timestamp('fecha_ultima_compra')->nullable();
            $table->decimal('total_compras', 12, 2)->default(0);
            $table->unsignedInteger('numero_compras')->default(0);
            $table->decimal('saldo_cuenta', 12, 2)->default(0);
            $table->decimal('limite_credito', 12, 2)->default(0);
            $table->decimal('descuento_preferencial', 5, 2)->default(0);

            $table->enum('tipo_cliente', ['minorista', 'mayorista', 'distribuidor'])->default('minorista');
            $table->string('estatus')->default('activo');
            $table->text('notas')->nullable();

            $table->rememberToken();
            $table->timestamps();

            $table->index('estatus');
            $table->index('tipo_cliente');
        });
    }

    public function down()
    {
        Schema::dropIfExists('customers');
    }
}
