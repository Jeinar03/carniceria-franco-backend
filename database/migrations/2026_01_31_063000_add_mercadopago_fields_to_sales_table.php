<?php

use Illuminate\Database\Migrations\Migration;

/**
 * NEUTRALIZADA 2026-09-02.
 *
 * Las columnas mercadopago_payment_id / mercadopago_status ahora se crean
 * directamente en create_sales_table (2021_04_24_051547). Esta migración original
 * hacía ->after('metodo_pago') sobre una tabla POS que no tenía esa columna, y
 * rompía `php artisan migrate`.
 *
 * Se deja el archivo (no se borra) para conservar el registro en la tabla
 * `migrations` de entornos donde ya se haya ejecutado.
 */
return new class extends Migration
{
    public function up(): void
    {
        // no-op: columnas ya presentes en create_sales_table
    }

    public function down(): void
    {
        // no-op
    }
};
