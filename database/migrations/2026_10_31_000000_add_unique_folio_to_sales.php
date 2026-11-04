<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * El folio (Ymd-0001) debe ser unico: dos ventas simultaneas podian calcular el mismo.
 * Si ya hay folios repetidos NO se crea el indice (no se toca ninguna venta): se avisa y se corrigen a mano.
 * Sale::performInsert reintenta con otro folio cuando el indice rechaza uno repetido.
 */
class AddUniqueFolioToSales extends Migration
{
    public function up()
    {
        $repetidos = DB::table('sales')
            ->select('folio')
            ->whereNotNull('folio')
            ->groupBy('folio')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('folio');

        if ($repetidos->isNotEmpty()) {
            echo "AVISO: hay folios repetidos, no se crea el indice unico: " . $repetidos->implode(', ') . PHP_EOL;

            return;
        }

        Schema::table('sales', function (Blueprint $table) {
            $table->unique('folio', 'sales_folio_unique');
        });
    }

    public function down()
    {
        if (Schema::hasTable('sales')) {
            try {
                Schema::table('sales', function (Blueprint $table) {
                    $table->dropUnique('sales_folio_unique');
                });
            } catch (\Throwable $e) {
                // El indice no se habia creado (habia folios repetidos).
            }
        }
    }
}
