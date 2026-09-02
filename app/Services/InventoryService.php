<?php

namespace App\Services;

use App\Exceptions\InsufficientStockException;
use App\Models\InventoryMovement;
use App\Models\InventoryMovementAudit;
use App\Models\Product;
use App\Models\SaleDetail;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class InventoryService
{
    public function currentStock($productId): float
    {
        $balance = InventoryMovement::where('product_id', $productId)
            ->selectRaw("COALESCE(SUM(CASE WHEN status = 'cancelled' THEN 0 WHEN type = 'entrada' THEN quantity ELSE -quantity END), 0) AS stock")
            ->first();

        return (float) $balance->stock;
    }

    public function addEntry(
        Product $product,
        float $quantity,
        ?string $notes = null,
        ?int $userId = null,
        ?string $receptionId = null
    ): InventoryMovement
    {
        return $this->record(
            $product,
            InventoryMovement::TYPE_ENTRY,
            $quantity,
            'compra',
            $notes,
            $userId,
            null,
            $receptionId,
            (float) $product->precio
        );
    }

    public function addSaleExit(Product $product, SaleDetail $detail, ?int $userId = null): InventoryMovement
    {
        return $this->record(
            $product,
            InventoryMovement::TYPE_EXIT,
            (float) $detail->cantidad,
            'venta',
            'Salida por venta ' . ($detail->sale->folio ?? ('#' . $detail->sale_id)),
            $userId,
            $detail
        );
    }

    public function restoreCancelledSale(Product $product, SaleDetail $detail, ?int $userId = null): ?InventoryMovement
    {
        $hasSaleExit = InventoryMovement::where('sale_detail_id', $detail->id)
            ->where('type', InventoryMovement::TYPE_EXIT)
            ->where('origin', 'venta')
            ->exists();

        if (!$hasSaleExit) {
            return null;
        }

        return $this->record(
            $product,
            InventoryMovement::TYPE_ENTRY,
            (float) $detail->cantidad,
            'cancelacion',
            'Reintegro por cancelación de venta ' . ($detail->sale->folio ?? ('#' . $detail->sale_id)),
            $userId,
            $detail
        );
    }

    public function editEntry(int $movementId, float $newQuantity, ?string $notes, ?int $userId): InventoryMovement
    {
        if ($newQuantity <= 0) {
            throw new InvalidArgumentException('La cantidad debe ser mayor a cero. Para retirar toda la entrada utiliza Cancelar.');
        }

        $movementReference = InventoryMovement::findOrFail($movementId);

        return DB::transaction(function () use ($movementReference, $newQuantity, $notes, $userId) {
            $product = Product::whereKey($movementReference->product_id)->lockForUpdate()->firstOrFail();
            $movement = InventoryMovement::whereKey($movementReference->id)->lockForUpdate()->firstOrFail();
            $this->assertEditableEntry($movement);

            $oldQuantity = (float) $movement->quantity;
            $removedQuantity = max(0, $oldQuantity - $newQuantity);
            $available = $this->availableToRemoveFromEntry($movement);

            if ($removedQuantity > $available) {
                throw new \RuntimeException(sprintf(
                    'No se puede reducir la entrada. Sólo hay %s %s disponibles y la edición retiraría %s.',
                    number_format($available, 3, '.', ''),
                    $movement->unit,
                    number_format($removedQuantity, 3, '.', '')
                ));
            }

            $unitCost = $movement->unit_cost !== null
                ? (float) $movement->unit_cost
                : (float) $product->precio;
            $newTotalCost = round($unitCost * $newQuantity, 2);
            InventoryMovementAudit::create([
                'inventory_movement_id' => $movement->id,
                'reception_id' => $movement->reception_id,
                'action' => 'edited',
                'old_quantity' => $oldQuantity,
                'new_quantity' => $newQuantity,
                'old_unit_cost' => $movement->unit_cost,
                'new_unit_cost' => $unitCost,
                'old_total_cost' => $movement->total_cost,
                'new_total_cost' => $newTotalCost,
                'old_notes' => $movement->notes,
                'new_notes' => $notes,
                'user_id' => $userId,
            ]);

            $movement->update([
                'quantity' => $newQuantity,
                'unit_cost' => $unitCost,
                'total_cost' => $newTotalCost,
                'notes' => $notes,
            ]);

            $this->recalculateProductBalances($movement->product_id);

            return $movement->fresh(['product', 'user', 'audits.user']);
        });
    }

    public function cancelEntry(int $movementId, ?string $reason, ?int $userId): InventoryMovement
    {
        $movementReference = InventoryMovement::findOrFail($movementId);

        return DB::transaction(function () use ($movementReference, $reason, $userId) {
            Product::whereKey($movementReference->product_id)->lockForUpdate()->firstOrFail();
            $movement = InventoryMovement::whereKey($movementReference->id)->lockForUpdate()->firstOrFail();
            $this->assertEditableEntry($movement);

            $available = $this->availableToRemoveFromEntry($movement);
            $quantity = (float) $movement->quantity;
            if ($quantity > $available) {
                throw new \RuntimeException(sprintf(
                    'No se puede cancelar la entrada porque sólo quedan %s %s y se deben retirar %s. Existen ventas posteriores que dependen de esta recepción.',
                    number_format($available, 3, '.', ''),
                    $movement->unit,
                    number_format($quantity, 3, '.', '')
                ));
            }

            InventoryMovementAudit::create([
                'inventory_movement_id' => $movement->id,
                'reception_id' => $movement->reception_id,
                'action' => 'cancelled',
                'old_quantity' => $quantity,
                'new_quantity' => 0,
                'old_unit_cost' => $movement->unit_cost,
                'new_unit_cost' => $movement->unit_cost,
                'old_total_cost' => $movement->total_cost,
                'new_total_cost' => 0,
                'old_notes' => $movement->notes,
                'new_notes' => $movement->notes,
                'user_id' => $userId,
            ]);

            $movement->update([
                'status' => InventoryMovement::STATUS_CANCELLED,
                'cancelled_at' => now(),
                'cancelled_by' => $userId,
                'cancellation_reason' => $reason,
            ]);

            $this->recalculateProductBalances($movement->product_id);

            return $movement->fresh(['product', 'user', 'cancelledBy', 'audits.user']);
        });
    }

    private function assertEditableEntry(InventoryMovement $movement): void
    {
        if ($movement->type !== InventoryMovement::TYPE_ENTRY || $movement->origin !== 'compra') {
            throw new \RuntimeException('Sólo se pueden editar o cancelar entradas creadas desde una recepción de inventario.');
        }

        if ($movement->status === InventoryMovement::STATUS_CANCELLED) {
            throw new \RuntimeException('Esta entrada ya fue cancelada.');
        }
    }

    private function recalculateProductBalances(int $productId): void
    {
        $balance = 0.0;
        $movements = InventoryMovement::where('product_id', $productId)
            ->orderBy('moved_at')
            ->orderBy('id')
            ->get();

        foreach ($movements as $movement) {
            if ($movement->status !== InventoryMovement::STATUS_CANCELLED) {
                $balance += $movement->type === InventoryMovement::TYPE_ENTRY
                    ? (float) $movement->quantity
                    : -(float) $movement->quantity;
            }

            DB::table('inventory_movements')
                ->where('id', $movement->id)
                ->update(['balance_after' => round($balance, 3)]);
        }
    }

    private function availableToRemoveFromEntry(InventoryMovement $target): float
    {
        $balance = 0.0;
        $minimumAfterEntry = null;
        $movements = InventoryMovement::where('product_id', $target->product_id)
            ->orderBy('moved_at')
            ->orderBy('id')
            ->get();

        foreach ($movements as $movement) {
            if ($movement->status !== InventoryMovement::STATUS_CANCELLED) {
                $balance += $movement->type === InventoryMovement::TYPE_ENTRY
                    ? (float) $movement->quantity
                    : -(float) $movement->quantity;
            }

            if ($movement->id === $target->id) {
                $minimumAfterEntry = $balance;
            } elseif ($minimumAfterEntry !== null) {
                $minimumAfterEntry = min($minimumAfterEntry, $balance);
            }
        }

        return max(0, (float) ($minimumAfterEntry ?? 0));
    }

    private function record(
        Product $product,
        string $type,
        float $quantity,
        string $origin,
        ?string $notes,
        ?int $userId,
        ?SaleDetail $detail = null,
        ?string $receptionId = null,
        ?float $unitCost = null
    ): InventoryMovement {
        if ($quantity <= 0) {
            throw new InvalidArgumentException('La cantidad del movimiento debe ser mayor a cero.');
        }

        // El bloqueo del producto serializa movimientos concurrentes del mismo artículo.
        Product::whereKey($product->id)->lockForUpdate()->firstOrFail();
        $current = $this->currentStock($product->id);

        if ($type === InventoryMovement::TYPE_EXIT && $current < $quantity) {
            throw new InsufficientStockException($product->nombre, $current, $quantity);
        }

        $balanceAfter = $type === InventoryMovement::TYPE_ENTRY
            ? $current + $quantity
            : $current - $quantity;

        return InventoryMovement::create([
            'product_id' => $product->id,
            'sale_id' => $detail ? $detail->sale_id : null,
            'sale_detail_id' => $detail ? $detail->id : null,
            'user_id' => $userId,
            'reception_id' => $receptionId,
            'type' => $type,
            'origin' => $origin,
            'quantity' => $quantity,
            'balance_after' => $balanceAfter,
            'unit' => $product->unidad_venta,
            'unit_cost' => $unitCost,
            'total_cost' => $unitCost === null ? null : round($unitCost * $quantity, 2),
            'notes' => $notes,
            'moved_at' => now(),
        ]);
    }
}
