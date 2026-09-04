<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use App\Models\Category;
use App\Models\Product;
use App\Models\InventoryMovement;

/**
 * Catálogo REAL de la Carnicería Franco (Res y Cerdo) — reemplaza los datos de prueba.
 *
 * Ejecutar explícitamente (NO va en DatabaseSeeder):
 *   php artisan db:seed --class=CatalogoRealSeeder --force
 *
 * Qué hace:
 *  - Borra ventas de prueba (sale_details, sales), movimientos de inventario,
 *    productos y categorías. Deja usuarios, clientes y config del sitio.
 *  - Pone en 0 las estadísticas de compra de los clientes (sus ventas eran de prueba).
 *  - Crea 2 categorías (Res, Cerdo) y 24 productos con descripción, precio y
 *    una entrada de inventario inicial.
 *
 * Es idempotente: se puede volver a correr y deja el catálogo igual.
 *
 * Precios y textos: dados por Einar (2026-09-03). Unidad por defecto: kilogramo.
 * Excepciones: cabeza de cerdo (pieza), manteca en litro y en medio litro.
 */
class CatalogoRealSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            Schema::disableForeignKeyConstraints();

            // 1. Limpiar datos de prueba ligados al catálogo
            DB::table('inventory_movements')->delete();
            DB::table('sale_details')->delete();
            DB::table('sales')->delete();
            DB::table('products')->delete();
            DB::table('categories')->delete();

            // 2. Resetear métricas de clientes (sus compras eran de prueba)
            DB::table('customers')->update([
                'total_compras'       => 0,
                'numero_compras'      => 0,
                'fecha_ultima_compra' => null,
            ]);

            Schema::enableForeignKeyConstraints();

            // 3. Categorías reales
            $res = Category::create([
                'nombre'      => 'Res',
                'descripcion' => 'Cortes de res frescos: para asar, para caldo y para guisar.',
                'orden'       => 1,
                'activo'      => true,
            ]);
            $cerdo = Category::create([
                'nombre'      => 'Cerdo',
                'descripcion' => 'Cortes y productos de cerdo: chuleta, costilla, carnitas, chorizo y más.',
                'orden'       => 2,
                'activo'      => true,
            ]);

            // 4. Productos
            // [cat, codigo, nombre, precio, unidad, destacado, stock_inicial, stock_min, descripcion]
            $productos = [
                // ---------------- RES ----------------
                [$res->id, 'RES-01', 'Bistec de res', 270.00, 'kilogramo', true, 45, 5,
                    'Corte delgado de pierna de res, suave y de cocción rápida. Ideal para asar en el comal, encebollado o en bistec ranchero.'],
                [$res->id, 'RES-02', 'Molida especial', 270.00, 'kilogramo', true, 50, 5,
                    'Carne de res molida selecta, con el punto justo de grasa para que quede jugosa. Perfecta para albóndigas, picadillo y hamburguesas caseras.'],
                [$res->id, 'RES-03', 'Molida 80/20', 240.00, 'kilogramo', false, 40, 5,
                    'Molida de res con 80% carne y 20% grasa. Rinde muy bien y da sabor a chiles rellenos, tacos y salsas con carne.'],
                [$res->id, 'RES-04', 'Carne para deshebrar', 270.00, 'kilogramo', false, 25, 3,
                    'Corte de falda y pecho de res que se cuece y se deshebra fácil. Para tinga, salpicón, tacos dorados y sincronizadas.'],
                [$res->id, 'RES-05', 'Chuleta de res', 270.00, 'kilogramo', false, 25, 3,
                    'Chuleta de res con hueso y buen marmoleo. Queda excelente a la parrilla o frita con un toque de sal y limón.'],
                [$res->id, 'RES-06', 'Surtido para cocido', 180.00, 'kilogramo', false, 20, 3,
                    'Mezcla de cortes con hueso, tuétano y carne para un caldo de res sustancioso. Trae lo necesario para el cocido o el puchero.'],
                [$res->id, 'RES-07', 'Costilla de res', 180.00, 'kilogramo', true, 30, 4,
                    'Costilla de res carnosa. Ideal para caldo, para hornear lento o para asar hasta que se desprenda del hueso.'],
                [$res->id, 'RES-08', 'Chambarete', 200.00, 'kilogramo', false, 30, 4,
                    'Chambarete de res con hueso y tuétano. El corte clásico para el caldo de res: suelta mucho sabor y la carne queda muy suave.'],
                [$res->id, 'RES-09', 'Pescuezo de res', 200.00, 'kilogramo', false, 15, 2,
                    'Pescuezo de res, económico y con mucho sabor. Para caldos largos y para deshebrar.'],
                [$res->id, 'RES-10', 'Menudo', 140.00, 'kilogramo', false, 20, 3,
                    'Pancita de res limpia y en trozos, lista para preparar un menudo rojo tradicional de fin de semana.'],
                [$res->id, 'RES-11', 'Carne seca', 700.00, 'kilogramo', false, 8, 2,
                    'Carne de res salada y secada al natural, estilo norteño. Para machaca con huevo, para botana o para dar sabor a los frijoles.'],

                // ---------------- CERDO ----------------
                [$cerdo->id, 'CER-01', 'Maciza de cerdo', 180.00, 'kilogramo', false, 35, 4,
                    'Pulpa de cerdo sin hueso, magra y versátil. Para guisar en salsa verde, hacer carnitas o freír en trozos.'],
                [$cerdo->id, 'CER-02', 'Costilla de cerdo', 180.00, 'kilogramo', true, 35, 4,
                    'Costilla de cerdo con buena capa de carne. Para asar, para costillas en salsa BBQ o para guisar con nopales.'],
                [$cerdo->id, 'CER-03', 'Chuleta natural de cerdo', 180.00, 'kilogramo', true, 40, 4,
                    'Chuleta de cerdo fresca con hueso, sin ahumar. Jugosa a la plancha, empanizada o en chuletas adobadas.'],
                [$cerdo->id, 'CER-04', 'Surtido de cerdo', 160.00, 'kilogramo', false, 25, 3,
                    'Surtido de cerdo con costilla, espinazo y trozos con hueso. Para pozole, para frijoles puercos o para un guiso caldoso.'],
                [$cerdo->id, 'CER-05', 'Manitas de cerdo', 120.00, 'kilogramo', false, 15, 2,
                    'Manitas de cerdo limpias, listas para cocer. Para prepararlas en escabeche, capeadas o en salsa.'],
                [$cerdo->id, 'CER-06', 'Cuero de cerdo', 90.00, 'kilogramo', false, 12, 2,
                    'Cuero de cerdo limpio para cocer y agregar a los frijoles, o para preparar cueritos en vinagre.'],
                [$cerdo->id, 'CER-07', 'Cabeza de cerdo', 300.00, 'pieza', false, 4, 1,
                    'Cabeza de cerdo completa, ideal para queso de puerco o para tacos de cabeza al vapor. Precio desde $300; varía según el tamaño de la pieza.'],
                [$cerdo->id, 'CER-08', 'Chorizo', 200.00, 'kilogramo', true, 30, 4,
                    'Chorizo de cerdo preparado en la casa, con su punto de chile y especias. Para desayunar con huevo, para queso fundido o para tacos.'],
                [$cerdo->id, 'CER-09', 'Manteca de cerdo (1 L)', 80.00, 'litro', false, 20, 3,
                    'Manteca de cerdo natural, líquida y limpia. Da el sabor tradicional a tamales, frijoles refritos y masa para antojitos. Presentación de 1 litro.'],
                [$cerdo->id, 'CER-10', 'Manteca de cerdo (1/2 L)', 45.00, 'pieza', false, 25, 4,
                    'La misma manteca de cerdo natural en presentación de medio litro, práctica para el uso diario en la cocina.'],
                [$cerdo->id, 'CER-11', 'Tocino', 270.00, 'kilogramo', true, 25, 3,
                    'Tocino de cerdo en rebanadas, curado y ahumado. Para el desayuno, para envolver rollos de carne o para dar sabor a las alubias.'],
                [$cerdo->id, 'CER-12', 'Chuleta ahumada', 200.00, 'kilogramo', false, 18, 2,
                    'Chuleta de cerdo ahumada, ya con sabor y lista para calentar. Solo se dora en el sartén y se acompaña con puré o ensalada.'],
                [$cerdo->id, 'CER-13', 'Chicharrón', 350.00, 'kilogramo', true, 20, 3,
                    'Chicharrón de cerdo recién hecho, dorado y crujiente. Para botana, para chicharrón en salsa verde o para acompañar los tacos.'],
            ];

            foreach ($productos as [$catId, $codigo, $nombre, $precio, $unidad, $destacado, $stock, $stockMin, $descripcion]) {
                $product = Product::create([
                    'category_id'   => $catId,
                    'codigo'        => $codigo,
                    'nombre'        => $nombre,
                    'descripcion'   => $descripcion,
                    'precio'        => $precio,
                    'precio_oferta' => null,
                    'en_oferta'     => false,
                    'unidad_venta'  => $unidad,
                    'stock'         => 0, // legacy; el saldo real vive en inventory_movements
                    'stock_minimo'  => $stockMin,
                    'activo'        => true,
                    'destacado'     => $destacado,
                    'refrigerado'   => true,
                ]);

                InventoryMovement::create([
                    'product_id'    => $product->id,
                    'type'          => InventoryMovement::TYPE_ENTRY,
                    'origin'        => 'ajuste',
                    'quantity'      => $stock,
                    'balance_after' => $stock,
                    'unit'          => $unidad,
                    'unit_cost'     => round($precio * 0.7, 2),
                    'total_cost'    => round($precio * 0.7 * $stock, 2),
                    'notes'         => 'Saldo inicial del catálogo real',
                    'status'        => InventoryMovement::STATUS_ACTIVE,
                    'moved_at'      => now(),
                ]);
            }
        });
    }
}
