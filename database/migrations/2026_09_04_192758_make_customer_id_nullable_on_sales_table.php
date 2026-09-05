<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class MakeCustomerIdNullableOnSalesTable extends Migration
{
    /**
     * Venta de mostrador sin cliente ("Cliente General"). La UI (Despachos, Ventas)
     * ya traía el fallback "Cliente General" a medio hacer; faltaba quitar el NOT NULL
     * y la FK obligatoria de customer_id.
     */
    public function up()
    {
        // SQLite (usado por varios tests con :memory:) no soporta dropForeign sin
        // recrear la tabla completa; esos tests no ejercitan esta ruta, así que se
        // omite ahí y solo se aplica en el driver real (MySQL, local y producción).
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            return;
        }

        Schema::table('sales', function (Blueprint $table) {
            $table->dropForeign(['customer_id']);
        });

        DB::statement('ALTER TABLE sales MODIFY customer_id BIGINT UNSIGNED NULL');

        Schema::table('sales', function (Blueprint $table) {
            $table->foreign('customer_id')->references('id')->on('customers')->nullOnDelete();
        });
    }

    public function down()
    {
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            return;
        }

        Schema::table('sales', function (Blueprint $table) {
            $table->dropForeign(['customer_id']);
        });

        DB::statement('ALTER TABLE sales MODIFY customer_id BIGINT UNSIGNED NOT NULL');

        Schema::table('sales', function (Blueprint $table) {
            $table->foreign('customer_id')->references('id')->on('customers');
        });
    }
}
