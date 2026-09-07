<?php

namespace App\Services;

use App\Models\CustomerProductPrice;
use App\Models\Product;

/**
 * Punto único donde se decide el precio unitario de un producto para una venta.
 *
 * Regla (Spec — Precio especial por cliente, D1): si el cliente tiene un precio
 * especial ACTIVO en ese producto, ese precio gana — incluso sobre una oferta
 * general más barata. Sin cliente o sin precio especial, se usa el precio de
 * lista (con oferta si el producto está en oferta), igual que siempre.
 */
class PricingService
{
    /**
     * @return array{precio_unitario: float, precio_oferta: float|null}
     */
    public function precioParaCliente(Product $product, ?int $customerId): array
    {
        if ($customerId) {
            $especial = CustomerProductPrice::query()
                ->where('customer_id', $customerId)
                ->where('product_id', $product->id)
                ->where('activo', true)
                ->value('precio_especial');

            if ($especial !== null && (float) $especial > 0) {
                return [
                    'precio_unitario' => (float) $especial,
                    'precio_oferta' => null,
                ];
            }
        }

        return [
            'precio_unitario' => (float) $product->precio,
            'precio_oferta' => $product->en_oferta ? (float) $product->precio_oferta : null,
        ];
    }

    /**
     * Precio efectivo por unidad (lo que realmente se cobra): la oferta si aplica,
     * si no el unitario. Útil para conversiones monto($) -> cantidad.
     */
    public function precioFinalParaCliente(Product $product, ?int $customerId): float
    {
        ['precio_unitario' => $unitario, 'precio_oferta' => $oferta]
            = $this->precioParaCliente($product, $customerId);

        return $oferta ?? $unitario;
    }
}
