<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Category;
use App\Models\Product;
use App\Models\InventoryMovement;

class ProductSeeder extends Seeder
{
    /**
     * Datos de prueba — productos de carnicería + su entrada inicial de inventario.
     * Reescrito 2026-09-02 al esquema real (nombre/precio/unidad_venta/...).
     *
     * Cada producto recibe un movimiento 'entrada'/'ajuste' para que tenga stock,
     * porque la API filtra con el scope conStock() (saldo en inventory_movements).
     */
    public function run()
    {
        $porNombre = Category::pluck('id', 'nombre');

        $productos = [
            // [categoria, codigo, nombre, precio, precio_oferta, en_oferta, unidad, destacado, stock_inicial]
            ['Res',   'RES-001', 'Bistec de res',            189.00, null,   false, 'kilogramo', true,  40],
            ['Res',   'RES-002', 'Molida de res especial',   149.00, 129.00, true,  'kilogramo', true,  55],
            ['Res',   'RES-003', 'Arrachera marinada',       279.00, null,   false, 'kilogramo', true,  25],
            ['Res',   'RES-004', 'Costilla de res',          159.00, null,   false, 'kilogramo', false, 30],
            ['Res',   'RES-005', 'Cortadillo de res',        139.00, null,   false, 'kilogramo', false, 20],

            ['Cerdo', 'CER-001', 'Chuleta de cerdo',         119.00, null,   false, 'kilogramo', true,  45],
            ['Cerdo', 'CER-002', 'Costilla de cerdo',        129.00, 109.00, true,  'kilogramo', false, 35],
            ['Cerdo', 'CER-003', 'Pierna de cerdo sin hueso', 139.00, null,  false, 'kilogramo', false, 22],
            ['Cerdo', 'CER-004', 'Carnitas surtidas',        169.00, null,   false, 'kilogramo', true,  18],

            ['Pollo', 'POL-001', 'Pechuga de pollo',         129.00, null,   false, 'kilogramo', true,  60],
            ['Pollo', 'POL-002', 'Pierna y muslo',            89.00, 75.00,  true,  'kilogramo', false, 50],
            ['Pollo', 'POL-003', 'Pollo entero',              79.00, null,   false, 'kilogramo', false, 40],
            ['Pollo', 'POL-004', 'Alitas de pollo',          99.00, null,   false, 'kilogramo', true,  33],

            ['Embutidos', 'EMB-001', 'Chorizo argentino',    149.00, null,   false, 'kilogramo', true,  28],
            ['Embutidos', 'EMB-002', 'Salchicha para asar',  119.00, null,   false, 'kilogramo', false, 30],
            ['Embutidos', 'EMB-003', 'Tocino ahumado',       179.00, 159.00, true,  'kilogramo', true,  24],
            ['Embutidos', 'EMB-004', 'Jamón de pierna',      139.00, null,   false, 'kilogramo', false, 26],

            ['Marinados y Preparados', 'MAR-001', 'Fajitas de res marinadas',   239.00, null, false, 'kilogramo', true,  20],
            ['Marinados y Preparados', 'MAR-002', 'Pollo al pastor',            139.00, null, false, 'kilogramo', true,  22],
            ['Marinados y Preparados', 'MAR-003', 'Brochetas mixtas',           199.00, null, false, 'pieza',     false, 40],
        ];

        foreach ($productos as [$cat, $codigo, $nombre, $precio, $oferta, $enOferta, $unidad, $destacado, $stock]) {
            $product = Product::create([
                'category_id'   => $porNombre[$cat],
                'codigo'        => $codigo,
                'nombre'        => $nombre,
                'descripcion'   => $nombre . ' de la Carnicería Franco, calidad garantizada.',
                'precio'        => $precio,
                'precio_oferta' => $oferta,
                'en_oferta'     => $enOferta,
                'unidad_venta'  => $unidad,
                'stock'         => 0, // legacy
                'stock_minimo'  => 5,
                'activo'        => true,
                'destacado'     => $destacado,
                'refrigerado'   => true,
            ]);

            InventoryMovement::create([
                'product_id'    => $product->id,
                'type'          => 'entrada',
                'origin'        => 'ajuste',
                'quantity'      => $stock,
                'balance_after' => $stock,
                'unit'          => $unidad,
                'unit_cost'     => round($precio * 0.7, 2),
                'total_cost'    => round($precio * 0.7 * $stock, 2),
                'notes'         => 'Saldo inicial (seeder de datos de prueba)',
                'status'        => 'active',
                'moved_at'      => now(),
            ]);
        }
    }
}
