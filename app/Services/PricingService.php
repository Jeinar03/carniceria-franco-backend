<?php

namespace App\Services;

use App\Models\CustomerProductPrice;
use App\Models\Customers;
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
     * Porcentaje de descuento preferencial (0-100) que se aplica al total de la compra.
     * Mismo criterio que muestra la tienda en el carrito: solo clientes mayoristas.
     */
    public function porcentajeDescuentoParaCliente(?Customers $customer): float
    {
        if (! $customer || strtolower((string) $customer->tipo_cliente) !== 'mayorista') {
            return 0.0;
        }

        return max(0.0, min(100.0, (float) $customer->descuento_preferencial));
    }

    /**
     * Reparte $total entre las líneas en proporción a su monto, trabajando en
     * centavos: la suma del resultado es exactamente $total (el resto del
     * redondeo cae en la última línea). Sirve para que Mercado Pago, que cobra
     * la suma de sus items, cobre lo mismo que registra la venta.
     *
     * @param  float[]  $montos
     * @return float[]
     */
    public function repartirTotal(array $montos, float $total): array
    {
        $montos = array_values($montos);
        $centavos = array_map(fn ($monto) => (int) round($monto * 100), $montos);
        $suma = array_sum($centavos);
        $totalCentavos = (int) round($total * 100);

        if ($suma <= 0 || $suma === $totalCentavos) {
            return array_map(fn ($c) => $c / 100.0, $centavos);
        }

        $ultimo = count($centavos) - 1;
        $acumulado = 0;
        $resultado = [];

        foreach ($centavos as $i => $c) {
            $parte = $i === $ultimo
                ? $totalCentavos - $acumulado
                : (int) round($c * $totalCentavos / $suma);

            $acumulado += $parte;
            $resultado[] = $parte / 100.0;
        }

        return $resultado;
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
