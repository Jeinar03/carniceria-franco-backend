<?php

use Illuminate\Database\Migrations\Migration;

/**
 * NEUTRALIZADA 2026-09-02.
 *
 * La columna monto_pesos ahora se crea directamente en create_sale_details_table
 * (2021_04_24_054445). Esta migración original apuntaba por error a la tabla
 * "sales_details" (plural) — que nunca existió; la tabla real es "sale_details".
 *
 * Se deja el archivo (no se borra) para conservar el registro en la tabla
 * `migrations` de entornos donde ya se haya ejecutado.
 */
return new class extends Migration
{
    public function up(): void
    {
        // no-op: columna ya presente en create_sale_details_table
    }

    public function down(): void
    {
        // no-op
    }
};
