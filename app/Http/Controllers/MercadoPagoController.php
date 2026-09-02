<?php
namespace App\Http\Controllers;

use App\Models\Customers;
use App\Models\MercadoPagoSetting;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleDetail;
use App\Services\InventoryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use MercadoPago\Item;
use MercadoPago\Payer;
use MercadoPago\Preference;
use MercadoPago\SDK;

class MercadoPagoController extends Controller
{
    public function __construct()
    {
        $credentials = MercadoPagoSetting::credentials();
        $accessToken = $credentials['access_token'];

        // ⚠️ DEBUG: Verifica qué credencial está usando
        Log::info('🔑 Access Token cargado:', [
            'token_prefix' => $accessToken ? substr($accessToken, 0, 10) : null,
            'is_test'      => strpos($accessToken, 'TEST-') === 0 ? 'SÍ ✅' : 'NO ❌ PRODUCCIÓN',
            'environment'  => config('app.env'),
        ]);

        if (! $accessToken) {
            Log::error('MERCADOPAGO_ACCESS_TOKEN no configurado en .env');
            return;
        }

        SDK::setAccessToken($accessToken);
    }

    public function createPreference(Request $request)
    {
        Log::info('═══════════════════════════════════════');
        Log::info('🚀 INICIANDO CREACIÓN DE PREFERENCIA MP');
        Log::info('═══════════════════════════════════════');

        // Normalizar payload para soportar distintos nombres de llaves desde frontend.
        $payload = $request->all();
        if (isset($payload['productos']) && is_array($payload['productos'])) {
            $payload['productos'] = array_map(function ($item) {
                $item = is_array($item) ? $item : [];

                if (!isset($item['product_id'])) {
                    $item['product_id'] = $item['id']
                        ?? $item['productId']
                        ?? $item['producto_id']
                        ?? null;
                }

                if (!isset($item['cantidad'])) {
                    $item['cantidad'] = $item['qty']
                        ?? $item['quantity']
                        ?? 1;
                }

                return $item;
            }, $payload['productos']);
        }

        $request->replace($payload);

        Log::info('📦 Datos recibidos (normalizados):', $request->all());

        $validator = Validator::make($request->all(), [
            'customer_id'             => 'required|exists:customers,id',
            'productos'               => 'required|array|min:1',
            'productos.*.product_id'  => 'required|exists:products,id',
            'productos.*.cantidad'    => 'nullable|numeric|min:0.01',
            'productos.*.monto_pesos' => 'nullable|numeric|min:1',
            'metodo_pago'             => 'required|string',
            'descuento'               => 'nullable|numeric|min:0',
            'notas'                   => 'nullable|string',
        ]);

        if ($validator->fails()) {
            Log::error('❌ Validación fallida:', $validator->errors()->toArray());
            return response()->json([
                'success' => false,
                'status'  => 422,
                'message' => 'Error de validación',
                'data'    => $validator->errors(),
            ], 422);
        }

        DB::beginTransaction();

        try {
            $customer = Customers::findOrFail($request->customer_id);
            Log::info('👤 Cliente encontrado:', [
                'id'     => $customer->id,
                'nombre' => $customer->nombre,
                'correo' => $customer->correo,
            ]);

            $items    = [];
            $subtotal = 0;
            $detalles = [];

            foreach ($request->productos as $index => $productoData) {
                Log::info("📦 Procesando producto #{$index}:", $productoData);

                $product = Product::findOrFail($productoData['product_id']);
                Log::info("✅ Producto encontrado: {$product->nombre}, Precio: \${$product->precio}");

                if (! $product->activo) {
                    throw new \Exception("El producto {$product->nombre} no está disponible");
                }

                $precioUnitario = $product->en_oferta && $product->precio_oferta
                    ? floatval($product->precio_oferta)
                    : floatval($product->precio);

                Log::info("💰 Precio unitario: \${$precioUnitario}");

                if (isset($productoData['monto_pesos']) && $productoData['monto_pesos'] > 0) {
                    $montoPesos = floatval($productoData['monto_pesos']);
                    Log::info("💵 VENTA POR PESOS - Monto: \${$montoPesos}");

                    $cantidadEquivalente = $montoPesos / $precioUnitario;
                    Log::info("⚖️ Cantidad equivalente: {$cantidadEquivalente}");

                    if ($product->stock < $cantidadEquivalente) {
                        throw new \Exception("Stock insuficiente para {$product->nombre}");
                    }

                    $item              = new Item();
                    $item->id          = strval($product->id);
                    $item->title       = $product->nombre;
                    $item->description = $product->descripcion ?? "Producto de carnicería";
                    $item->category_id = "food";
                    $item->quantity    = 1;
                    $item->unit_price  = floatval($montoPesos);
                    $item->currency_id = "MXN";

                    if ($product->imagen) {
                        $item->picture_url = url($product->imagen);
                    }

                    $items[]  = $item;
                    $subtotal += $montoPesos;

                    $detalles[] = [
                        'product_id' => $product->id,
                        'cantidad' => $cantidadEquivalente,
                        'monto_pesos' => $montoPesos,
                        'precio_unitario' => $product->precio,
                        'precio_oferta' => $product->en_oferta ? $product->precio_oferta : null,
                        'subtotal' => $montoPesos,
                        'producto_nombre' => $product->nombre,
                        'producto_codigo' => $product->codigo,
                        'unidad_venta' => $product->unidad_venta,
                    ];

                    Log::info("✅ Item PESOS creado: qty=1, price=\${$montoPesos}");

                } else {
                    $cantidad = floatval($productoData['cantidad'] ?? 1);
                    Log::info("🔢 VENTA POR CANTIDAD - Cantidad: {$cantidad}");

                    if ($product->stock < $cantidad) {
                        throw new \Exception("Stock insuficiente para {$product->nombre}");
                    }

                    $itemSubtotal = $precioUnitario * $cantidad;

                    $item              = new Item();
                    $item->id          = strval($product->id);
                    $item->title       = $product->nombre;
                    $item->description = $product->descripcion ?? "Producto de carnicería";
                    $item->category_id = "food";
                    // MercadoPago requiere quantity entero > 0.
                    // Para cantidades decimales (kg, gramos, etc.), consolidamos el total del renglon en una sola unidad.
                    $item->quantity    = 1;
                    $item->unit_price  = round((float) $itemSubtotal, 2);
                    $item->currency_id = "MXN";

                    if ($product->imagen) {
                        $item->picture_url = url($product->imagen);
                    }

                    $items[]  = $item;
                    $subtotal += $itemSubtotal;

                    $detalles[] = [
                        'product_id' => $product->id,
                        'cantidad' => $cantidad,
                        'monto_pesos' => null,
                        'precio_unitario' => $product->precio,
                        'precio_oferta' => $product->en_oferta ? $product->precio_oferta : null,
                        'subtotal' => $itemSubtotal,
                        'producto_nombre' => $product->nombre,
                        'producto_codigo' => $product->codigo,
                        'unidad_venta' => $product->unidad_venta,
                    ];

                    Log::info("✅ Item CANTIDAD creado para MP: qty=1, row_total=\${$itemSubtotal}");
                }
            }

            if (empty($items)) {
                throw new \Exception("No se pudieron procesar los productos");
            }

            Log::info("📊 Total items: " . count($items) . ", Subtotal: \${$subtotal}");

            $descuento = floatval($request->descuento ?? 0);
            $impuestos = ($subtotal - $descuento) * 0.16;
            $total     = $subtotal - $descuento + $impuestos;

            Log::info("💳 Creando venta pendiente - Total: \${$total}");

            $ventaPendiente = Sale::create([
                'customer_id'  => $request->customer_id,
                'fecha_venta'  => now(),
                'subtotal'     => $subtotal,
                'descuento'    => $descuento,
                'impuestos'    => $impuestos,
                'total'        => $total,
                'metodo_pago'  => 'mercado_pago',
                'estatus'      => 'pendiente',
                'notas'        => $request->notas,
                'estado_envio' => 'Pendiente',
            ]);

            // Guardar items de la compra aunque el pago aun este pendiente.
            foreach ($detalles as $detalle) {
                SaleDetail::create([
                    'sale_id' => $ventaPendiente->id,
                    'product_id' => $detalle['product_id'],
                    'cantidad' => $detalle['cantidad'],
                    'monto_pesos' => $detalle['monto_pesos'],
                    'precio_unitario' => $detalle['precio_unitario'],
                    'precio_oferta' => $detalle['precio_oferta'],
                    'descuento' => 0,
                    'subtotal' => $detalle['subtotal'],
                    'total' => $detalle['subtotal'],
                    'producto_nombre' => $detalle['producto_nombre'],
                    'producto_codigo' => $detalle['producto_codigo'],
                    'unidad_venta' => $detalle['unidad_venta'],
                    'estado_despacho' => 0,
                ]);
            }

            Log::info("✅ Venta pendiente creada - ID: {$ventaPendiente->id}");

            // Crear preferencia de MercadoPago
            $preference        = new Preference();
            $preference->items = $items;

            // ✅ Información del pagador - CONDICIONAL POR AMBIENTE
            $payer          = new Payer();
            $payer->name    = $customer->nombre;
            $payer->surname = $customer->apellido ?? '';

            // SOLO en desarrollo local: omitir email para evitar verificación 2FA
            // En producción: usar email real del cliente
            $isLocal = (bool) MercadoPagoSetting::credentials()['sandbox'];

            if ($isLocal) {
                // NO establecer email en desarrollo - evita verificación 2FA
                Log::info("⚠️ Payer email OMITIDO (modo: " . config('app.env') . ")");
            } else {
                // En producción usar email real
                $payer->email = $customer->correo;
                Log::info("✉️ Payer email CONFIGURADO (producción): {$customer->correo}");
            }

            Log::info("👤 Payer configurado:", [
                'name'        => $payer->name,
                'surname'     => $payer->surname,
                'email'       => $isLocal ? '🚫 OMITIDO (desarrollo)' : $customer->correo,
                'environment' => config('app.env'),
            ]);

            if ($customer->telefono) {
                $payer->phone = [
                    'area_code' => '',
                    'number'    => $customer->telefono,
                ];
            }

            if ($customer->direccion) {
                $payer->address = [
                    'street_name' => $customer->direccion,
                    'zip_code'    => $customer->codigo_postal ?? '',
                ];
            }

            $preference->payer = $payer;

            // Metadata
            $preference->external_reference = strval($ventaPendiente->id);
            $preference->metadata           = [
                'venta_id'    => $ventaPendiente->id,
                'customer_id' => $customer->id,
            ];

            // Webhook - Solo en producción con HTTPS
            if (!$isLocal && !empty(env('MERCADOPAGO_WEBHOOK_URL'))) {
                $preference->notification_url = env('MERCADOPAGO_WEBHOOK_URL');
                Log::info("🔔 Webhook habilitado: " . env('MERCADOPAGO_WEBHOOK_URL'));
            } else {
                Log::info("⚠️ Webhook deshabilitado (desarrollo local o sin URL configurada)");
            }

            // Configuraciones adicionales
            $preference->statement_descriptor = "CARNICERIA";
            $preference->expires              = true;
            $preference->expiration_date_from = now()->toIso8601String();
            $preference->expiration_date_to   = now()->addHours(24)->toIso8601String();

            Log::info("💾 Guardando preferencia en MercadoPago...");

            // Guardar preferencia
            $saved = $preference->save();

            if (! $saved) {
                Log::error('❌ Error al guardar preferencia');
                Log::error('Detalles:', [
                    'error'  => $preference->error ?? 'Sin información de error',
                    'status' => $preference->status ?? 'Sin status',
                ]);
                throw new \Exception('No se pudo crear la preferencia en MercadoPago');
            }

            DB::commit();

            Log::info("═══════════════════════════════════════");
            Log::info("✅ ¡PREFERENCIA CREADA EXITOSAMENTE!");
            Log::info("═══════════════════════════════════════");
            Log::info("🆔 Preference ID: {$preference->id}");
            Log::info("🔗 Init Point: {$preference->init_point}");
            Log::info("🧪 Sandbox Init Point: {$preference->sandbox_init_point}");
            Log::info("═══════════════════════════════════════");

            return response()->json([
                'success' => true,
                'status'  => 201,
                'message' => 'Preferencia creada exitosamente',
                'data'    => [
                    'preference_id'      => $preference->id,
                    'init_point'         => $preference->init_point,
                    'sandbox_init_point' => $preference->sandbox_init_point,
                    'venta_pendiente_id' => $ventaPendiente->id,
                ],
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error("═══════════════════════════════════════");
            Log::error("❌ ERROR AL CREAR PREFERENCIA");
            Log::error("═══════════════════════════════════════");
            Log::error("Mensaje: " . $e->getMessage());
            Log::error("Archivo: " . $e->getFile() . " (Línea: " . $e->getLine() . ")");
            Log::error("═══════════════════════════════════════");

            return response()->json([
                'success' => false,
                'status'  => 500,
                'message' => 'Error al crear preferencia: ' . $e->getMessage(),
                'data'    => null,
            ], 500);
        }
    }

    public function webhook(Request $request)
    {
        Log::info('═══ WEBHOOK MERCADOPAGO ═══', $request->all());

        try {
            $type = $request->input('type');
            $data = $request->input('data');

            if ($type === 'payment') {
                $paymentId = $data['id'];
                Log::info("💳 Procesando pago ID: {$paymentId}");

                $payment = \MercadoPago\Payment::find_by_id($paymentId);

                if ($payment) {
                    $ventaId = intval($payment->external_reference);
                    $venta   = Sale::find($ventaId);

                    if ($venta) {
                        Log::info("📦 Venta encontrada ID: {$ventaId}");

                        if ($payment->status === 'approved') {
                            Log::info("✅ Pago APROBADO");
                            $this->procesarPagoAprobado($venta, $payment);
                        } elseif ($payment->status === 'rejected') {
                            Log::info("❌ Pago RECHAZADO");
                            $venta->estatus                = 'cancelada';
                            $venta->mercadopago_payment_id = $paymentId;
                            $venta->mercadopago_status     = $payment->status;
                            $venta->save();
                        } elseif ($payment->status === 'pending') {
                            Log::info("⏳ Pago PENDIENTE");
                            $venta->mercadopago_payment_id = $paymentId;
                            $venta->mercadopago_status     = $payment->status;
                            $venta->save();
                        }
                    } else {
                        Log::warning("⚠️ Venta no encontrada para ID: {$ventaId}");
                    }
                } else {
                    Log::warning("⚠️ Pago no encontrado en MP: {$paymentId}");
                }
            }

            return response()->json(['success' => true], 200);

        } catch (\Exception $e) {
            Log::error('❌ Error en webhook: ' . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    private function procesarPagoAprobado($venta, $payment)
    {
        DB::beginTransaction();

        try {
            // Evita procesar dos veces la misma venta por reintentos de webhook.
            if ($venta->estatus === 'completada') {
                Log::info("ℹ️ Venta {$venta->id} ya estaba completada, se omite reproceso");
                DB::commit();
                return;
            }

            $venta->loadMissing('details');

            foreach ($venta->details as $detail) {
                $product = Product::find($detail->product_id);

                if (!$product) {
                    throw new \Exception("Producto no encontrado para detalle {$detail->id}");
                }

                if ((float) $product->stock < (float) $detail->cantidad) {
                    throw new \Exception("Stock insuficiente para {$product->nombre} al confirmar el pago");
                }

                app(InventoryService::class)->addSaleExit($product, $detail);
            }

            $venta->estatus                = 'completada';
            $venta->mercadopago_payment_id = $payment->id;
            $venta->mercadopago_status     = $payment->status;
            $venta->save();

            $customer = Customers::find($venta->customer_id);
            if ($customer) {
                $customer->total_compras       = ($customer->total_compras ?? 0) + $venta->total;
                $customer->numero_compras      = ($customer->numero_compras ?? 0) + 1;
                $customer->fecha_ultima_compra = now();
                $customer->save();

                Log::info("👤 Cliente actualizado: {$customer->nombre}");
            }

            DB::commit();

            Log::info('✅ Pago procesado correctamente', [
                'venta_id'   => $venta->id,
                'payment_id' => $payment->id,
                'total'      => $venta->total,
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('❌ Error procesando pago aprobado: ' . $e->getMessage());
            throw $e;
        }
    }

    public function checkPaymentStatus($paymentId)
    {
        try {
            $payment = \MercadoPago\Payment::find_by_id($paymentId);

            if (! $payment) {
                return response()->json([
                    'success' => false,
                    'message' => 'Pago no encontrado',
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data'    => [
                    'status'             => $payment->status,
                    'status_detail'      => $payment->status_detail,
                    'payment_id'         => $payment->id,
                    'external_reference' => $payment->external_reference,
                ],
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al verificar pago: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function getVentaByPreference($preferenceId)
    {
        try {
            $venta = Sale::where('estatus', 'pendiente')
                ->where('metodo_pago', 'mercado_pago')
                ->latest()
                ->first();

            if (! $venta) {
                return response()->json([
                    'success' => false,
                    'message' => 'Venta no encontrada',
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data'    => $venta,
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage(),
            ], 500);
        }
    }
}
