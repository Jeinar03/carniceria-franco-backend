<?php

namespace App\Http\Controllers;

use App\Models\InventoryMovement;
use Barryvdh\DomPDF\Facade as PDF;
use Carbon\Carbon;

class InventoryReceiptController extends Controller
{
    public function entriesByDate(string $date)
    {
        try {
            $receiptDate = Carbon::createFromFormat('Y-m-d', $date)->startOfDay();
        } catch (\Throwable $e) {
            abort(404, 'Fecha de recepción inválida.');
        }

        if ($receiptDate->format('Y-m-d') !== $date) {
            abort(404, 'Fecha de recepción inválida.');
        }

        $movements = InventoryMovement::with(['product', 'user', 'cancelledBy', 'audits.user'])
            ->entries()
            ->where('origin', 'compra')
            ->whereDate('moved_at', $date)
            ->orderBy('moved_at')
            ->orderBy('id')
            ->get();

        abort_if($movements->isEmpty(), 404, 'No hay recepciones de productos para la fecha seleccionada.');

        $receptions = $movements->groupBy(function ($movement) {
            // Las entradas anteriores a reception_id se presentan juntas como recepción histórica.
            return $movement->reception_id ?: 'historica';
        });

        $users = $movements->pluck('user.name')->filter()->unique()->values();
        $notes = $movements->pluck('notes')->filter()->unique()->values();
        $allCancelled = $movements->every(function ($movement) {
            return $movement->status === InventoryMovement::STATUS_CANCELLED;
        });
        $hasCancelled = $movements->contains(function ($movement) {
            return $movement->status === InventoryMovement::STATUS_CANCELLED;
        });
        $cancelledWatermark = $allCancelled
            ? 'RECEPCIÓN CANCELADA'
            : ($hasCancelled ? 'CONTIENE ENTRADAS CANCELADAS' : null);
        $totalCost = $movements->sum(function ($movement) {
            if ($movement->total_cost !== null) {
                return (float) $movement->total_cost;
            }

            return (float) ($movement->product->precio ?? 0) * (float) $movement->quantity;
        });

        $logoPath = public_path('images/logo.jpeg');
        $logoData = file_exists($logoPath)
            ? 'data:image/jpeg;base64,' . base64_encode(file_get_contents($logoPath))
            : null;

        $pdf = PDF::setOptions(['isPhpEnabled' => true])->loadView('pdf.inventory-receipt', compact(
            'movements',
            'receptions',
            'receiptDate',
            'users',
            'notes',
            'allCancelled',
            'cancelledWatermark',
            'totalCost',
            'logoData'
        ))->setPaper('letter', 'portrait');

        return $pdf->stream('recepcion-productos-' . $date . '.pdf');
    }
}
