<?php

namespace App\Http\Controllers;

use App\Models\Sale;
use App\Models\SaleDetail;
use App\Models\Product;
use App\Models\Customers;
use App\Services\OrderNotificationService;
use App\Services\InventoryService;
use App\Exceptions\InsufficientStockException;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class SalesController extends Controller
{
    private $inventory;

    public function __construct(InventoryService $inventory)
    {
        $this->inventory = $inventory;
    }

    /**
     * Respuesta 403 estándar cuando un cliente intenta acceder a algo ajeno.
     */
    private function noAutorizado()
    {
        return response()->json([
            'success' => false,
            'status' => 403,
            'message' => 'No autorizado',
            'data' => null
        ], 403);
    }

    /**
     * True si la petición viene de un cliente (token Sanctum) que NO es dueño
     * del customer_id dado. Los usuarios del panel (User) pasan sin restricción.
     */
    private function clienteNoEsDueno(Request $request, $customerId): bool
    {
        $user = $request->user();

        return $user instanceof Customers
            && (int) $customerId !== (int) $user->id;
    }

    /**
     * Crear una nueva venta (compra del cliente)
     */
    public function store(Request $request)
    {
        // La venta siempre se registra a nombre del cliente autenticado.
        // Se ignora cualquier customer_id que venga en el body.
        $request->merge(['customer_id' => $request->user()->id]);

        $validator = Validator::make($request->all(), [
            'customer_id' => 'required|exists:customers,id',
            'metodo_pago' => 'required|in:efectivo,tarjeta,transferencia,credito,mercado_pago',
            'productos' => 'required|array|min:1',
            'productos.*.product_id' => 'required',
            'productos.*.cantidad' => 'required|numeric|min:0.01',
            'productos.*.monto_pesos' => 'nullable|numeric|min:0', // Para venta por monto
            'descuento' => 'nullable|numeric|min:0',
            'notas' => 'nullable|string',
            'mercadopago_payment_id' => 'nullable|string', // ID del pago de MercadoPago
            'mercadopago_status' => 'nullable|string', // Estado del pago de MercadoPago
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'status' => 422,
                'message' => 'Error de validación',
                'data' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();

        try {
            $subtotal = 0;
            $detalles = [];

            // Validar stock y calcular totales
            foreach ($request->productos as $item) {
                $product = $this->resolveProductByIdentifier($item['product_id']);

                if (!$product) {
                    throw new Exception("Producto {$item['product_id']} no encontrado");
                }

                if (!$product->activo) {
                    throw new Exception("El producto {$product->nombre} no está disponible");
                }

                // Manejar venta por monto en pesos
                $cantidad = $item['cantidad'];
                if (isset($item['monto_pesos']) && $item['monto_pesos'] > 0) {
                    // Convertir monto en pesos a cantidad según la unidad de venta
                    if (in_array($product->unidad_venta, ['kilogramo', 'gramo'])) {
                        $precioFinal = $product->en_oferta && $product->precio_oferta ? $product->precio_oferta : $product->precio;
                        $cantidadKg = $item['monto_pesos'] / $precioFinal;

                        if ($product->unidad_venta == 'kilogramo') {
                            $cantidad = $cantidadKg; // Mantener en kilos
                        } else {
                            $cantidad = $cantidadKg * 1000; // Convertir a gramos
                        }
                    }
                }

                if ($product->stock < $cantidad) {
                    throw new InsufficientStockException($product->nombre, (float) $product->stock, (float) $cantidad);
                }

                // Calcular precio final (con oferta si aplica)
                $precioUnitario = $product->precio;
                $precioOferta = $product->en_oferta ? $product->precio_oferta : null;
                $precioFinal = $precioOferta ?? $precioUnitario;
                $itemSubtotal = $precioFinal * $cantidad;

                $subtotal += $itemSubtotal;

                $detalles[] = [
                    'product' => $product,
                    'cantidad' => $cantidad,
                    'monto_pesos' => $item['monto_pesos'] ?? null, // Guardar el monto original en pesos
                    'precio_unitario' => $precioUnitario,
                    'precio_oferta' => $precioOferta,
                    'subtotal' => $itemSubtotal,
                ];
            }

            // Calcular descuento e impuestos
            $descuento = $request->descuento ?? 0;
            $impuestos = ($subtotal - $descuento) * 0.16; // 16% IVA
            $total = $subtotal - $descuento + $impuestos;

            // Crear la venta
            $saleData = [
                'customer_id' => $request->customer_id,
                'fecha_venta' => now(),
                'subtotal' => $subtotal,
                'descuento' => $descuento,
                'impuestos' => $impuestos,
                'total' => $total,
                'metodo_pago' => $request->metodo_pago,
                'estatus' => $request->metodo_pago === 'transferencia' ? 'pendiente' : 'completada',
                'notas' => $request->notas,
                'estado_envio' => 'Pendiente', // Estado inicial para seguimiento
                'transferencia_estado' => $request->metodo_pago === 'transferencia' ? 'pendiente' : null,
            ];

            // Agregar información de Mercado Pago si existe
            if ($request->metodo_pago === 'mercado_pago') {
                $saleData['mercadopago_payment_id'] = $request->mercadopago_payment_id;
                $saleData['mercadopago_status'] = $request->mercadopago_status;
            }

            $sale = Sale::create($saleData);

            // Orden estable para evitar bloqueos cruzados entre ventas concurrentes.
            usort($detalles, function ($left, $right) {
                return $left['product']->id <=> $right['product']->id;
            });

            // Crear detalles y registrar las salidas de inventario.
            foreach ($detalles as $detalle) {
                $product = $detalle['product'];

                $saleDetail = SaleDetail::create([
                    'sale_id' => $sale->id,
                    'product_id' => $product->id,
                    'cantidad' => $detalle['cantidad'],
                    'monto_pesos' => $detalle['monto_pesos'], // Guardar monto en pesos si existe
                    'precio_unitario' => $detalle['precio_unitario'],
                    'precio_oferta' => $detalle['precio_oferta'],
                    'descuento' => 0,
                    'subtotal' => $detalle['subtotal'],
                    'total' => $detalle['subtotal'],
                    'producto_nombre' => $product->nombre,
                    'producto_codigo' => $product->codigo,
                    'unidad_venta' => $product->unidad_venta,
                ]);

                $this->inventory->addSaleExit($product, $saleDetail);
            }

            // Actualizar estadísticas del cliente
            $customer = Customers::find($request->customer_id);
            $customer->total_compras = ($customer->total_compras ?? 0) + $total;
            $customer->numero_compras = ($customer->numero_compras ?? 0) + 1;
            $customer->fecha_ultima_compra = now();
            $customer->save();

            DB::commit();

            // Cargar relaciones para respuesta
            $sale->load(['details', 'customer']);

            // Enviar notificación por correo al cliente
            OrderNotificationService::sendPurchaseCompletedNotification($sale);

            return response()->json([
                'success' => true,
                'status' => 201,
                'message' => 'Compra realizada exitosamente',
                'data' => $sale
            ], 201);

        } catch (InsufficientStockException $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'status' => 409,
                'message' => $e->getMessage(),
                'data' => [
                    'stock_disponible' => $e->getAvailable(),
                    'cantidad_solicitada' => $e->getRequested(),
                ]
            ], 409);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => 'Error al procesar la compra: ' . $e->getMessage(),
                'data' => null
            ], 500);
        }
    }

    /**
     * Obtener historial de compras del cliente
     */
    public function getCustomerPurchases(Request $request, $customerId)
    {
        if ($this->clienteNoEsDueno($request, $customerId)) {
            return $this->noAutorizado();
        }

        try {
            $customer = Customers::find($customerId);

            if (!$customer) {
                return response()->json([
                    'success' => false,
                    'status' => 404,
                    'message' => 'Cliente no encontrado',
                    'data' => null
                ], 404);
            }

            $purchases = Sale::with(['details.product'])
                ->where('customer_id', $customerId)
                ->orderBy('fecha_venta', 'desc')
                ->paginate(15);

            return response()->json([
                'success' => true,
                'status' => 200,
                'message' => 'Historial obtenido correctamente',
                'data' => [
                    'customer' => $customer,
                    'purchases' => $purchases
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => 'Error al obtener historial: ' . $e->getMessage(),
                'data' => null
            ], 500);
        }
    }

    /**
     * Obtener detalle de una compra específica
     */
    public function getPurchaseDetail(Request $request, $saleId)
    {
        try {
            $sale = Sale::with(['details.product', 'customer'])
                ->find($saleId);

            if (!$sale) {
                return response()->json([
                    'success' => false,
                    'status' => 404,
                    'message' => 'Compra no encontrada',
                    'data' => null
                ], 404);
            }

            if ($this->clienteNoEsDueno($request, $sale->customer_id)) {
                return $this->noAutorizado();
            }

            return response()->json([
                'success' => true,
                'status' => 200,
                'message' => 'Detalle de compra obtenido correctamente',
                'data' => $sale
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => 'Error al obtener detalle: ' . $e->getMessage(),
                'data' => null
            ], 500);
        }
    }

    /**
     * Obtener estadísticas de compras del cliente
     */
    public function getCustomerStats(Request $request, $customerId)
    {
        if ($this->clienteNoEsDueno($request, $customerId)) {
            return $this->noAutorizado();
        }

        try {
            $customer = Customers::find($customerId);

            if (!$customer) {
                return response()->json([
                    'success' => false,
                    'status' => 404,
                    'message' => 'Cliente no encontrado',
                    'data' => null
                ], 404);
            }

            $stats = [
                'total_compras' => $customer->total_compras ?? 0,
                'numero_compras' => $customer->numero_compras ?? 0,
                'fecha_ultima_compra' => $customer->fecha_ultima_compra,
                'promedio_compra' => $customer->numero_compras > 0
                    ? ($customer->total_compras / $customer->numero_compras)
                    : 0,
                'compras_este_mes' => Sale::where('customer_id', $customerId)
                    ->whereMonth('fecha_venta', now()->month)
                    ->whereYear('fecha_venta', now()->year)
                    ->count(),
                'total_este_mes' => Sale::where('customer_id', $customerId)
                    ->whereMonth('fecha_venta', now()->month)
                    ->whereYear('fecha_venta', now()->year)
                    ->sum('total'),
                'producto_mas_comprado' => $this->getTopProduct($customerId),
            ];

            return response()->json([
                'success' => true,
                'status' => 200,
                'message' => 'Estadísticas obtenidas correctamente',
                'data' => $stats
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => 'Error al obtener estadísticas: ' . $e->getMessage(),
                'data' => null
            ], 500);
        }
    }

    /**
     * Obtener últimas compras del cliente (más recientes)
     */
    public function getRecentPurchases(Request $request, $customerId, $limit = 5)
    {
        if ($this->clienteNoEsDueno($request, $customerId)) {
            return $this->noAutorizado();
        }

        try {
            $purchases = Sale::with(['details.product'])
                ->where('customer_id', $customerId)
                ->orderBy('fecha_venta', 'desc')
                ->limit($limit)
                ->get();

            return response()->json([
                'success' => true,
                'status' => 200,
                'message' => 'Últimas compras obtenidas correctamente',
                'data' => $purchases
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => 'Error al obtener compras recientes: ' . $e->getMessage(),
                'data' => null
            ], 500);
        }
    }

    /**
     * Cancelar una compra
     */
    public function cancelPurchase(Request $request, $saleId)
    {
        DB::beginTransaction();

        try {
            $sale = Sale::with('details')->lockForUpdate()->find($saleId);

            if (!$sale) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'status' => 404,
                    'message' => 'Compra no encontrada',
                    'data' => null
                ], 404);
            }

            if ($this->clienteNoEsDueno($request, $sale->customer_id)) {
                DB::rollBack();
                return $this->noAutorizado();
            }

            if ($sale->estatus == 'cancelada') {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'status' => 400,
                    'message' => 'La compra ya está cancelada',
                    'data' => null
                ], 400);
            }

            // Reintegrar existencias mediante movimientos de inventario.
            foreach ($sale->details as $detail) {
                $product = Product::find($detail->product_id);
                if ($product) {
                    $this->inventory->restoreCancelledSale($product, $detail);
                }
            }

            // Actualizar estadísticas del cliente
            $customer = Customers::find($sale->customer_id);
            $customer->total_compras -= $sale->total;
            $customer->numero_compras -= 1;
            $customer->save();

            // Cambiar estatus de la venta
            $sale->estatus = 'cancelada';
            $sale->save();

            DB::commit();

            return response()->json([
                'success' => true,
                'status' => 200,
                'message' => 'Compra cancelada exitosamente',
                'data' => $sale
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => 'Error al cancelar compra: ' . $e->getMessage(),
                'data' => null
            ], 500);
        }
    }

    /**
     * Método privado para obtener el producto más comprado
     */
    private function getTopProduct($customerId)
    {
        $topProduct = SaleDetail::select('product_id', 'producto_nombre', DB::raw('SUM(cantidad) as total_cantidad'))
            ->whereHas('sale', function($query) use ($customerId) {
                $query->where('customer_id', $customerId)
                      ->where('estatus', '!=', 'cancelada');
            })
            ->groupBy('product_id', 'producto_nombre')
            ->orderBy('total_cantidad', 'desc')
            ->first();

        return $topProduct ? [
            'producto' => $topProduct->producto_nombre,
            'cantidad_total' => $topProduct->total_cantidad
        ] : null;
    }

    /**
     * Obtener compras recomendadas para el cliente basadas en su historial
     */
    public function getRecommendedPurchases(Request $request, $customerId)
    {
        if ($this->clienteNoEsDueno($request, $customerId)) {
            return $this->noAutorizado();
        }

        try {
            $customer = Customers::find($customerId);

            if (!$customer) {
                return response()->json([
                    'success' => false,
                    'status' => 404,
                    'message' => 'Cliente no encontrado',
                    'data' => null
                ], 404);
            }

            // Obtener los productos más comprados por el cliente
            $topProducts = SaleDetail::select('product_id', 'producto_nombre', DB::raw('COUNT(*) as compras_count'), DB::raw('AVG(cantidad) as cantidad_promedio'))
                ->whereHas('sale', function($query) use ($customerId) {
                    $query->where('customer_id', $customerId)
                          ->where('estatus', '!=', 'cancelada');
                })
                ->groupBy('product_id', 'producto_nombre')
                ->orderBy('compras_count', 'desc')
                ->limit(10)
                ->get();

            if ($topProducts->isEmpty()) {
                return response()->json([
                    'success' => true,
                    'status' => 200,
                    'message' => 'No hay historial suficiente para generar recomendaciones',
                    'data' => []
                ], 200);
            }

            // Obtener productos relacionados (misma categoría)
            $relatedProducts = [];
            foreach ($topProducts->take(5) as $topProduct) {
                $product = Product::find($topProduct->product_id);
                if ($product) {
                    $similarProducts = Product::where('category_id', $product->category_id)
                        ->where('id', '!=', $product->id)
                        ->active()
                        ->conStock()
                        ->inRandomOrder()
                        ->limit(2)
                        ->get();

                    $relatedProducts = array_merge($relatedProducts, $similarProducts->toArray());
                }
            }

            // Generar 3 carritos recomendados
            $recommendations = [];

            // Carrito 1: Productos frecuentes (los que más compra)
            $carrito1 = [];
            foreach ($topProducts->take(4) as $topProduct) {
                $product = Product::find($topProduct->product_id);
                if ($product && $product->activo && $product->stock > 0) {
                    $carrito1[] = [
                        'product_id' => $product->id,
                        'nombre' => $product->nombre,
                        'precio' => $product->precio_final,
                        'cantidad_sugerida' => round($topProduct->cantidad_promedio, 2),
                        'unidad_venta' => $product->unidad_venta,
                        'imagen' => $product->imagen,
                        'categoria' => $product->category->nombre ?? 'Sin categoría',
                        'motivo' => 'Compras frecuentemente este producto'
                    ];
                }
            }

            if (!empty($carrito1)) {
                $total1 = array_sum(array_map(function($item) {
                    return $item['precio'] * $item['cantidad_sugerida'];
                }, $carrito1));

                $recommendations[] = [
                    'id' => 1,
                    'nombre' => 'Tus Favoritos',
                    'descripcion' => 'Productos que compras con más frecuencia',
                    'productos' => $carrito1,
                    'total_estimado' => round($total1, 2),
                    'ahorro_estimado' => 0
                ];
            }

            // Carrito 2: Mix de favoritos + productos nuevos/relacionados
            $carrito2 = [];
            // Agregar 2-3 productos favoritos
            foreach ($topProducts->take(3) as $topProduct) {
                $product = Product::find($topProduct->product_id);
                if ($product && $product->activo && $product->stock > 0) {
                    $carrito2[] = [
                        'product_id' => $product->id,
                        'nombre' => $product->nombre,
                        'precio' => $product->precio_final,
                        'cantidad_sugerida' => round($topProduct->cantidad_promedio * 0.8, 2), // Cantidad un poco menor
                        'unidad_venta' => $product->unidad_venta,
                        'imagen' => $product->imagen,
                        'categoria' => $product->category->nombre ?? 'Sin categoría',
                        'motivo' => 'Producto habitual'
                    ];
                }
            }

            // Agregar productos relacionados/nuevos
            $relatedProductsUnique = array_unique($relatedProducts, SORT_REGULAR);
            foreach (array_slice($relatedProductsUnique, 0, 2) as $relatedProduct) {
                if (isset($relatedProduct['id'])) {
                    $product = Product::find($relatedProduct['id']);
                    if ($product && $product->activo && $product->stock > 0) {
                        $carrito2[] = [
                            'product_id' => $product->id,
                            'nombre' => $product->nombre,
                            'precio' => $product->precio_final,
                            'cantidad_sugerida' => 0.5, // Cantidad pequeña para probar
                            'unidad_venta' => $product->unidad_venta,
                            'imagen' => $product->imagen,
                            'categoria' => $product->category->nombre ?? 'Sin categoría',
                            'motivo' => 'Te podría gustar (categoría similar)'
                        ];
                    }
                }
            }

            if (!empty($carrito2)) {
                $total2 = array_sum(array_map(function($item) {
                    return $item['precio'] * $item['cantidad_sugerida'];
                }, $carrito2));

                $recommendations[] = [
                    'id' => 2,
                    'nombre' => 'Mix Recomendado',
                    'descripcion' => 'Tus favoritos + productos que podrían gustarte',
                    'productos' => $carrito2,
                    'total_estimado' => round($total2, 2),
                    'ahorro_estimado' => 0
                ];
            }

            // Carrito 3: Productos en oferta + algunos favoritos
            $carrito3 = [];
            // Productos en oferta que estén en categorías que le gustan
            $categoriesIds = $topProducts->map(function($tp) {
                $product = Product::find($tp->product_id);
                return $product ? $product->category_id : null;
            })->filter()->unique()->toArray();

            $productosEnOferta = Product::whereIn('category_id', $categoriesIds)
                ->enOferta()
                ->active()
                ->conStock()
                ->limit(3)
                ->get();

            foreach ($productosEnOferta as $product) {
                $carrito3[] = [
                    'product_id' => $product->id,
                    'nombre' => $product->nombre,
                    'precio' => $product->precio_final,
                    'precio_original' => $product->precio,
                    'cantidad_sugerida' => 0.5,
                    'unidad_venta' => $product->unidad_venta,
                    'imagen' => $product->imagen,
                    'categoria' => $product->category->nombre ?? 'Sin categoría',
                    'motivo' => 'En oferta - categoría de tu interés'
                ];
            }

            // Agregar un producto favorito
            if ($topProducts->first()) {
                $favProduct = Product::find($topProducts->first()->product_id);
                if ($favProduct && $favProduct->activo && $favProduct->stock > 0) {
                    $carrito3[] = [
                        'product_id' => $favProduct->id,
                        'nombre' => $favProduct->nombre,
                        'precio' => $favProduct->precio_final,
                        'cantidad_sugerida' => round($topProducts->first()->cantidad_promedio, 2),
                        'unidad_venta' => $favProduct->unidad_venta,
                        'imagen' => $favProduct->imagen,
                        'categoria' => $favProduct->category->nombre ?? 'Sin categoría',
                        'motivo' => 'Tu producto más comprado'
                    ];
                }
            }

            if (!empty($carrito3)) {
                $total3 = array_sum(array_map(function($item) {
                    return $item['precio'] * $item['cantidad_sugerida'];
                }, $carrito3));

                $ahorroEstimado = array_sum(array_map(function($item) {
                    return isset($item['precio_original']) ?
                        ($item['precio_original'] - $item['precio']) * $item['cantidad_sugerida'] : 0;
                }, $carrito3));

                $recommendations[] = [
                    'id' => 3,
                    'nombre' => 'Ofertas Para Ti',
                    'descripcion' => 'Aprovecha las ofertas en tus categorías favoritas',
                    'productos' => $carrito3,
                    'total_estimado' => round($total3, 2),
                    'ahorro_estimado' => round($ahorroEstimado, 2)
                ];
            }

            return response()->json([
                'success' => true,
                'status' => 200,
                'message' => 'Recomendaciones generadas correctamente',
                'data' => [
                    'customer' => [
                        'id' => $customer->id,
                        'nombre' => $customer->nombre,
                        'email' => $customer->email
                    ],
                    'recomendaciones' => $recommendations,
                    'total_recomendaciones' => count($recommendations)
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => 'Error al generar recomendaciones: ' . $e->getMessage(),
                'data' => null
            ], 500);
        }
    }

    /**
     * Subir evidencia de transferencia (imagen o PDF) para una venta.
     */
    public function uploadTransferEvidence(Request $request, $saleId)
    {
        $validator = Validator::make($request->all(), [
            'evidencia' => 'required|file|mimes:jpeg,jpg,png,webp,pdf|max:10240',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'status' => 422,
                'message' => 'Error de validación',
                'data' => $validator->errors()
            ], 422);
        }

        try {
            $sale = Sale::find($saleId);

            if (!$sale) {
                return response()->json([
                    'success' => false,
                    'status' => 404,
                    'message' => 'Venta no encontrada',
                    'data' => null
                ], 404);
            }

            if ($this->clienteNoEsDueno($request, $sale->customer_id)) {
                return $this->noAutorizado();
            }

            if ($sale->metodo_pago !== 'transferencia') {
                return response()->json([
                    'success' => false,
                    'status' => 400,
                    'message' => 'La venta no es por transferencia',
                    'data' => null
                ], 400);
            }

            if ($sale->estatus === 'cancelada') {
                return response()->json([
                    'success' => false,
                    'status' => 400,
                    'message' => 'No se puede subir evidencia para una venta cancelada',
                    'data' => null
                ], 400);
            }

            if ($sale->transferencia_evidencia_path) {
                Storage::disk('public')->delete($sale->transferencia_evidencia_path);
            }

            $path = $request->file('evidencia')->store('transferencias', 'public');

            $sale->transferencia_evidencia_path = $path;
            $sale->transferencia_estado = 'pendiente';
            $sale->transferencia_subida_at = now();
            $sale->transferencia_validada_at = null;
            $sale->transferencia_validada_por = null;
            $sale->save();

            return response()->json([
                'success' => true,
                'status' => 200,
                'message' => 'Evidencia de transferencia subida correctamente',
                'data' => [
                    'sale_id' => $sale->id,
                    'transferencia_estado' => $sale->transferencia_estado,
                    'transferencia_evidencia_url' => $sale->transferencia_evidencia_url,
                ]
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => 'Error al subir evidencia: ' . $e->getMessage(),
                'data' => null
            ], 500);
        }
    }

    /**
     * Mostrar evidencia de transferencia almacenada en disco public.
     */
    public function showTransferEvidence(Request $request, $saleId)
    {
        try {
            $sale = Sale::find($saleId);

            if (!$sale) {
                return response()->json([
                    'success' => false,
                    'status' => 404,
                    'message' => 'Venta no encontrada',
                    'data' => null
                ], 404);
            }

            // El panel (User) puede ver cualquier evidencia; un cliente sólo la suya.
            if ($this->clienteNoEsDueno($request, $sale->customer_id)) {
                return $this->noAutorizado();
            }

            if ($sale->metodo_pago !== 'transferencia') {
                return response()->json([
                    'success' => false,
                    'status' => 400,
                    'message' => 'La venta no corresponde a transferencia',
                    'data' => null
                ], 400);
            }

            if (!$sale->transferencia_evidencia_path) {
                return response()->json([
                    'success' => false,
                    'status' => 404,
                    'message' => 'La venta no tiene evidencia de transferencia',
                    'data' => null
                ], 404);
            }

            $disk = Storage::disk('public');

            if (!$disk->exists($sale->transferencia_evidencia_path)) {
                return response()->json([
                    'success' => false,
                    'status' => 404,
                    'message' => 'Archivo de evidencia no encontrado',
                    'data' => null
                ], 404);
            }

            $mimeType = $disk->mimeType($sale->transferencia_evidencia_path) ?: 'application/octet-stream';

            return response()->file($disk->path($sale->transferencia_evidencia_path), [
                'Content-Type' => $mimeType,
                'Content-Disposition' => 'inline; filename="' . basename($sale->transferencia_evidencia_path) . '"',
                'X-Content-Type-Options' => 'nosniff',
                'X-Frame-Options' => 'SAMEORIGIN',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => 'Error al obtener evidencia: ' . $e->getMessage(),
                'data' => null
            ], 500);
        }
    }

    private function resolveProductByIdentifier($identifier)
    {
        return Product::query()
            ->where('id', $identifier)
            ->orWhere('codigo', (string) $identifier)
            ->first();
    }
}
