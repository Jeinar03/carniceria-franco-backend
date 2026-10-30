<?php

namespace App\Console\Commands;

use App\Models\Customers;
use App\Models\Sale;
use Illuminate\Console\Command;

/**
 * Total gastado, Compras realizadas y ultima compra solo cuentan ventas pagadas (completada o entregada).
 * Las ventas anteriores a este cambio sumaron transferencias sin pagar; este comando corrige a los clientes.
 */
class RecalcularEstadisticasClientes extends Command
{
    protected $signature = 'clientes:recalcular-estadisticas {--dry-run : Solo muestra a quien le cambiaria}';

    protected $description = 'Recalcula Total gastado y Compras realizadas de cada cliente con sus ventas pagadas';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $cambiados = 0;

        Customers::query()->orderBy('id')->each(function (Customers $cliente) use ($dryRun, &$cambiados) {
            $pagadas = Sale::where('customer_id', $cliente->id)->whereIn('estatus', ['completada', 'entregada']);
            $total = (float) (clone $pagadas)->sum('total');
            $numero = (clone $pagadas)->count();

            if (abs($total - (float) $cliente->total_compras) < 0.005 && $numero === (int) $cliente->numero_compras) {
                return;
            }

            $cambiados++;
            $this->line("Cliente {$cliente->id}: total {$cliente->total_compras} -> {$total}, compras {$cliente->numero_compras} -> {$numero}");

            if (!$dryRun) {
                $cliente->recalcularEstadisticas();
            }
        });

        $this->info(($dryRun ? 'Cambiarian: ' : 'Corregidos: ') . $cambiados);

        return self::SUCCESS;
    }
}
