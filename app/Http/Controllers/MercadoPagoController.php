<?php
namespace App\Http\Controllers;

use App\Models\Customers;
use App\Models\MercadoPagoSetting;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleDetail;
use App\Models\SiteConfig;
use App\Services\InventoryService;
use App\Services\OrderNotificationService;
use App\Services\PricingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use MercadoPago\Item;
use MercadoPago\Payer;
use MercadoPago\Preference;
use MercadoPago\SDK;

class MercadoPagoController extends Controller
{
    /** El SDK solo se inicializa cuando hay Access Token; sin él no se puede hablar con Mercado Pago. */
    private bool $configured = false;

    public function __construct()
    {
        $credentials = MercadoPagoSetting::credentials();

        if (! $credentials['access_token']) {
            Log::error('Mercado Pago sin Access Token: captúralo en Sistema → Mercado Pago (o MERCADOPAGO_ACCESS_TOKEN en el .env)');
            return;
        }

        try {
            $this->inicializarSdk($credentials['access_token']);
        } catch (\Throwable $e) {
            // El SDK consulta /users/me al recibir el token: si Mercado Pago lo rechaza (token
            // incorrecto o expirado) o no hay red, falla aquí. Sin este try/catch cualquier
            // petición a este controlador (webhook incluido) terminaba en un 500 sin explicación.
            Log::error('Mercado Pago rechazó el Access Token o no respondió: ' . $e->getMessage());
            return;
        }

        $this->configured = true;

        Log::info('Mercado Pago listo', [
            'sandbox'     => (bool) $credentials['sandbox'],
            'environment' => config('app.env'),
        ]);
    }

    private function noConfigurado()
    {
        return response()->json([
            'success' => false,
            'status'  => 503,
            'message' => 'El pago con Mercado Pago no está disponible por el momento. Elige otro método de pago.',
            'data'    => null,
        ], 503);
    }

    /**
     * Teléfono del comprador como lo pide Mercado Pago: solo dígitos, sin el 52 de México,
     * y separado en lada (3 dígitos) y número cuando son los 10 dígitos habituales.
     *
     * @return array{area_code: string, number: string}|null
     */
    private function telefonoParaMercadoPago(?string $telefono): ?array
    {
        $digitos = preg_replace('/\D+/', '', (string) $telefono);

        if (strlen($digitos) === 12 && str_starts_with($digitos, '52')) {
            $digitos = substr($digitos, 2);
        }

        if ($digitos === '') {
            return null;
        }

        if (strlen($digitos) === 10) {
            return ['area_code' => substr($digitos, 0, 3), 'number' => substr($digitos, 3)];
        }

        return ['area_code' => '', 'number' => $digitos];
    }

    protected function inicializarSdk(string $accessToken): void
    {
        SDK::setAccessToken($accessToken);
    }

    /** Consulta el pago a Mercado Pago (única fuente de verdad del estado y el monto). */
    protected function obtenerPago($paymentId)
    {
        return \MercadoPago\Payment::find_by_id($paymentId);
    }

    protected function guardarPreferencia(Preference $preference): bool
    {
        return (bool) $preference->save();
    }

    public function createPreference(Request $request)
    {
        if (! $this->configured) {
            return $this->noConfigurado();
        }

        $atencion = SiteConfig::estadoDeAtencion();
        if (! $atencion['abierto']) {
            return response()->json([
                'success' => false,
                'status'  => 403,
                'message' => 'La tienda está cerrada por horario de atención (hoy: ' . $atencion['horario'] . ').',
                'data'    => null,
            ], 403);
        }

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

        // La preferencia y la venta pendiente siempre son del cliente autenticado.
        $request->merge(['customer_id' => $request->user()->id]);

        Log::info('📦 Datos recibidos (normalizados):', $request->all());

        $validator = Validator::make($request->all(), [
            'customer_id'             => 'required|exists:customers,id',
            'productos'               => 'required|array|min:1',
            'productos.*.product_id'  => 'required|exists:products,id',
            'productos.*.cantidad'    => 'nullable|numeric|min:0.01',
            'productos.*.monto_pesos' => 'nullable|numeric|min:1',
            'metodo_pago'             => 'required|string',
            // El descuento no se recibe: lo calcula el servidor según el tipo de cliente.
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
            $montos   = []; // total de cada renglón, en el mismo orden que $items
            $subtotal = 0;
            $detalles = [];

            foreach ($request->productos as $index => $productoData) {
                Log::info("📦 Procesando producto #{$index}:", $productoData);

                $product = Product::findOrFail($productoData['product_id']);
                Log::info("✅ Producto encontrado: {$product->nombre}, Precio: \${$product->precio}");

                if (! $product->activo) {
                    throw new \Exception("El producto {$product->nombre} no está disponible");
                }

                // Precio para el cliente autenticado: especial si lo tiene, si no lista/oferta.
                ['precio_unitario' => $precioBaseUnitario, 'precio_oferta' => $precioOfertaDetalle]
                    = app(PricingService::class)->precioParaCliente($product, (int) $request->customer_id);
                $precioUnitario = $precioOfertaDetalle ?? $precioBaseUnitario;

                Log::info("💰 Precio unitario: \${$precioUnitario}");

                if (isset($productoData['monto_pesos']) && $productoData['monto_pesos'] > 0) {
                    $montoPesos = round(floatval($productoData['monto_pesos']), 2);
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
                    $montos[] = $montoPesos;
                    $subtotal += $montoPesos;

                    $detalles[] = [
                        'product_id' => $product->id,
                        'cantidad' => $cantidadEquivalente,
                        'monto_pesos' => $montoPesos,
                        'precio_unitario' => $precioBaseUnitario,
                        'precio_oferta' => $precioOfertaDetalle,
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

                    $itemSubtotal = round($precioUnitario * $cantidad, 2);

                    $item              = new Item();
                    $item->id          = strval($product->id);
                    $item->title       = $product->nombre;
                    $item->description = $product->descripcion ?? "Producto de carnicería";
                    $item->category_id = "food";
                    // MercadoPago requiere quantity entero > 0.
                    // Para cantidades decimales (kg, gramos, etc.), consolidamos el total del renglon en una sola unidad.
                    $item->quantity    = 1;
                    $item->unit_price  = $itemSubtotal;
                    $item->currency_id = "MXN";

                    if ($product->imagen) {
                        $item->picture_url = url($product->imagen);
                    }

                    $items[]  = $item;
                    $montos[] = $itemSubtotal;
                    $subtotal += $itemSubtotal;

                    $detalles[] = [
                        'product_id' => $product->id,
                        'cantidad' => $cantidad,
                        'monto_pesos' => null,
                        'precio_unitario' => $precioBaseUnitario,
                        'precio_oferta' => $precioOfertaDetalle,
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

            // Carne fresca sin procesar: IVA tasa 0% (art. 2-A LIVA). No se cobra impuesto.
            // El descuento (mayoristas) lo decide el servidor, no el cliente: así la venta,
            // el total del carrito y lo que cobra Mercado Pago son siempre el mismo número.
            $pricing    = app(PricingService::class);
            $porcentaje = $pricing->porcentajeDescuentoParaCliente($customer);
            $subtotal   = round($subtotal, 2);
            $descuento  = round($subtotal * $porcentaje / 100, 2);
            $impuestos  = 0;
            $total      = round($subtotal - $descuento, 2);

            if ($total < 0.01) {
                throw new \Exception('El total de la compra no es válido');
            }

            // Mercado Pago cobra la suma de sus items: repartimos el descuento entre ellos.
            foreach ($pricing->repartirTotal($montos, $total) as $i => $monto) {
                if ($monto < 0.01) {
                    throw new \Exception('El monto de un producto es demasiado pequeño para cobrarlo');
                }
                $items[$i]->unit_price = $monto;
            }

            Log::info("💳 Creando venta pendiente - Subtotal: \${$subtotal}, Descuento: \${$descuento}, Total: \${$total}");

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

            // ✅ Información del pagador
            $payer          = new Payer();
            $payer->name    = $customer->nombre;
            $payer->surname = $customer->apellido ?? '';

            // Con Sandbox activo se omite el correo (evita la verificación 2FA de una cuenta real
            // durante las pruebas). Sin Sandbox se usa el correo real del cliente.
            // Ojo: este toggle SOLO afecta al correo; el webhook y el regreso a la tienda no dependen de él.
            $sandbox = (bool) MercadoPagoSetting::credentials()['sandbox'];

            if ($sandbox) {
                Log::info("⚠️ Payer email OMITIDO (Sandbox activo)");
            } else {
                $payer->email = $customer->correo;
                Log::info("✉️ Payer email CONFIGURADO: {$customer->correo}");
            }

            Log::info("👤 Payer configurado:", [
                'name'        => $payer->name,
                'surname'     => $payer->surname,
                'email'       => $sandbox ? '🚫 OMITIDO (sandbox)' : $customer->correo,
                'environment' => config('app.env'),
            ]);

            $telefono = $this->telefonoParaMercadoPago($customer->telefono);
            if ($telefono) {
                $payer->phone = $telefono;
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

            // Webhook: Mercado Pago solo puede llamar a una URL pública con HTTPS.
            // Sirve igual con credenciales de prueba que productivas (no depende de Sandbox).
            $notificationUrl = (string) config('mercadopago.notification_url');
            if (Str::startsWith($notificationUrl, 'https://')) {
                $preference->notification_url = $notificationUrl;
                Log::info("🔔 Webhook habilitado: {$notificationUrl}");
            } else {
                Log::warning("⚠️ Webhook NO registrado: la URL no es HTTPS público ({$notificationUrl}). "
                    . "La venta solo se confirmará cuando el cliente regrese a la tienda.");
            }

            // Regreso a la tienda: sin back_urls el cliente se queda en Mercado Pago después de pagar.
            $frontendUrl = (string) config('mercadopago.frontend_url');
            if ($frontendUrl !== '') {
                $volver = $frontendUrl . '/pages/payment-callback';
                $preference->back_urls = [
                    'success' => $volver,
                    'failure' => $volver,
                    'pending' => $volver,
                ];

                // auto_return solo lo acepta Mercado Pago con URLs HTTPS.
                if (Str::startsWith($frontendUrl, 'https://')) {
                    $preference->auto_return = 'approved';
                }
            } else {
                Log::warning('⚠️ FRONTEND_URL no configurado: el cliente no regresará a la tienda al terminar de pagar.');
            }

            // Configuraciones adicionales
            $preference->statement_descriptor = "CARNICERIA";
            $preference->expires              = true;
            $preference->expiration_date_from = now()->toIso8601String();
            $preference->expiration_date_to   = now()->addHours(24)->toIso8601String();

            Log::info("💾 Guardando preferencia en MercadoPago...");

            // Guardar preferencia
            $saved = $this->guardarPreferencia($preference);

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

        if (! $this->configured) {
            return response()->json(['error' => 'Mercado Pago no configurado'], 503);
        }

        // Si hay clave de firma guardada, el aviso debe venir firmado por Mercado Pago.
        if ($this->verificarFirma($request) === 'invalida') {
            return response()->json(['error' => 'Firma no valida'], 401);
        }

        // Webhooks: {"type":"payment","data":{"id":"123"}}. IPN: ?topic=payment&id=123.
        $type      = $request->input('type', $request->input('topic'));
        $paymentId = $request->input('data.id', $request->input('id'));

        // Solo pagos con id numérico: el id se usa para armar la URL de la API de Mercado Pago.
        if ($type !== 'payment' || ! is_scalar($paymentId) || ! ctype_digit((string) $paymentId)) {
            return response()->json(['success' => true], 200);
        }

        try {
            Log::info("💳 Procesando pago ID: {$paymentId}");

            $payment = $this->obtenerPago($paymentId);

            if ($payment) {
                $this->sincronizarPago($payment);
            } else {
                Log::warning("⚠️ Pago no encontrado en MP: {$paymentId}");
            }

            return response()->json(['success' => true], 200);

        } catch (\Exception $e) {
            Log::error('❌ Error en webhook: ' . $e->getMessage());
            // 500 para que Mercado Pago reintente; el detalle queda solo en el log.
            return response()->json(['error' => 'Error al procesar la notificación'], 500);
        }
    }

    /**
     * Revisa la firma (x-signature) del aviso. Devuelve 'sin_clave' (no hay clave guardada:
     * no se valida nada, como antes), 'valida' o 'invalida'. Siempre deja en el log si el
     * aviso traia firma, sin escribir nunca la clave ni la firma.
     */
    private function verificarFirma(Request $request): string
    {
        $secreto = MercadoPagoSetting::credentials()['webhook_secret'] ?? null;
        $xSignature = (string) $request->header('x-signature', '');
        $requestId = $request->header('x-request-id');

        $resultado = 'sin_clave';
        if ($secreto) {
            $resultado = $this->firmaEsValida($request, (string) $secreto, $xSignature, $requestId) ? 'valida' : 'invalida';
        }

        $datos = [
            'clave_configurada' => (bool) $secreto,
            'trae_x_signature' => $xSignature !== '',
            'trae_x_request_id' => $requestId !== null && $requestId !== '',
            // Si el id del aviso viene en la URL (lo normal en avisos reales) o solo en el cuerpo (p. ej. el simulador).
            'data_id_en_url' => $this->dataIdDeLaUrl($request) !== null,
            'resultado' => $resultado,
        ];

        if ($resultado === 'invalida') {
            // Para poder depurar sin la clave: qué aviso era y qué texto se firmó (ninguno es secreto).
            $firma = $this->datosDeFirma($request, $xSignature, $requestId);
            $datos += [
                'tipo'         => $request->input('type', $request->input('topic')),
                'accion'       => $request->input('action'),
                'query'        => (string) $request->server('QUERY_STRING'),
                'manifiesto'   => $firma['manifiesto'] ?? null,
                'v1_recibido'  => $firma['v1'] ?? null,
                'v1_calculado' => $firma ? substr(hash_hmac('sha256', $firma['manifiesto'], (string) $secreto), 0, 12) : null,
            ];
            Log::warning('Webhook rechazado por firma', $datos);
        } else {
            Log::info('Webhook firma', $datos);
        }

        return $resultado;
    }

    /**
     * Mercado Pago firma con HMAC-SHA256 (hex) el texto: id:{data.id};request-id:{x-request-id};ts:{ts};
     * usando la clave secreta. Si el aviso no trae request-id o data.id, esa parte se omite.
     */
    private function firmaEsValida(Request $request, string $secreto, string $xSignature, ?string $requestId): bool
    {
        $datos = $this->datosDeFirma($request, $xSignature, $requestId);

        if ($datos === null) {
            return false;
        }

        return hash_equals(hash_hmac('sha256', $datos['manifiesto'], $secreto), strtolower($datos['v1']));
    }

    /**
     * Texto que se firma (manifiesto) y firma recibida (v1), o null si el encabezado no es utilizable.
     * Ninguno de los dos contiene la clave secreta.
     *
     * @return array{manifiesto: string, v1: string}|null
     */
    private function datosDeFirma(Request $request, string $xSignature, ?string $requestId): ?array
    {
        if ($xSignature === '') {
            return null;
        }

        $ts = null;
        $v1 = null;
        foreach (explode(',', $xSignature) as $parte) {
            $par = explode('=', trim($parte), 2);
            if (count($par) !== 2) {
                continue;
            }
            if ($par[0] === 'ts') {
                $ts = trim($par[1]);
            } elseif ($par[0] === 'v1') {
                $v1 = trim($par[1]);
            }
        }

        if ($ts === null || $ts === '' || $v1 === null || $v1 === '') {
            return null;
        }

        $dataId = $this->dataIdDeLaUrl($request) ?? (string) $request->input('data.id', '');

        $manifiesto = ($dataId !== '' ? 'id:' . strtolower($dataId) . ';' : '')
            . ($requestId !== null && $requestId !== '' ? 'request-id:' . $requestId . ';' : '')
            . 'ts:' . $ts . ';';

        return ['manifiesto' => $manifiesto, 'v1' => $v1];
    }

    /** data.id viene en la URL (?data.id=123). PHP cambia el punto por guion bajo, por eso se lee a mano. */
    private function dataIdDeLaUrl(Request $request): ?string
    {
        if (preg_match('/(?:^|&)data\.id=([^&]*)/', (string) $request->server('QUERY_STRING'), $m)) {
            return urldecode($m[1]);
        }

        return null;
    }

    /**
     * Aplica a la venta el estado real de un pago. Lo usan el webhook (servidor a servidor)
     * y el retorno del cliente (confirmPayment); pueden llegar al mismo tiempo, por eso
     * todo es idempotente.
     */
    private function sincronizarPago($payment): ?Sale
    {
        $venta = Sale::find((int) $payment->external_reference);

        if (! $venta || $venta->metodo_pago !== 'mercado_pago') {
            Log::warning('⚠️ Pago sin venta de Mercado Pago asociada', [
                'payment_id'         => $payment->id,
                'external_reference' => $payment->external_reference,
            ]);
            return null;
        }

        Log::info("📦 Venta {$venta->id} - pago {$payment->id}: {$payment->status}");

        if ($payment->status === 'approved') {
            $this->procesarPagoAprobado($venta, $payment);
        } elseif ($venta->estatus !== 'completada') {
            // Un intento fallido puede notificarse después del aprobado: nunca se degrada una venta ya cobrada.
            if (in_array($payment->status, ['rejected', 'cancelled'], true)) {
                $venta->estatus = 'cancelada';
            }
            $this->registrarPago($venta, $payment);
        }

        return $venta->fresh();
    }

    private function registrarPago(Sale $venta, $payment): void
    {
        $venta->mercadopago_payment_id    = $payment->id;
        $venta->mercadopago_status        = $payment->status;
        // El motivo (p. ej. cc_rejected_high_risk) es lo que explica un rechazo; el estado solo dice "rejected".
        $venta->mercadopago_status_detail = $payment->status_detail ?? null;
        $venta->save();

        if (in_array($payment->status, ['rejected', 'cancelled'], true)) {
            Log::warning('Pago de Mercado Pago no aprobado', [
                'venta_id'      => $venta->id,
                'payment_id'    => $payment->id,
                'status'        => $payment->status,
                'status_detail' => $payment->status_detail ?? null,
            ]);
        }
    }

    /**
     * Cobro aprobado: verifica el monto, descuenta el stock y completa la venta.
     * Devuelve true si la venta quedó completada. Bloquea la venta para que el webhook
     * y el retorno del cliente no descuenten el stock dos veces.
     */
    private function procesarPagoAprobado(Sale $venta, $payment): bool
    {
        $recienCompletada = false;

        $resultado = DB::transaction(function () use ($venta, $payment, &$recienCompletada) {
            $venta = Sale::whereKey($venta->id)->lockForUpdate()->firstOrFail();

            if ($venta->estatus === 'completada') {
                Log::info("ℹ️ Venta {$venta->id} ya estaba completada, se omite reproceso");
                return true;
            }

            // Se registra siempre: aunque la venta no se pueda completar, queda constancia del cobro.
            $this->registrarPago($venta, $payment);

            $pagado = (float) $payment->transaction_amount;
            if (abs($pagado - (float) $venta->total) > 0.01 || ($payment->currency_id ?? 'MXN') !== 'MXN') {
                Log::error('❌ El monto cobrado no coincide con la venta; requiere revisión manual', [
                    'venta_id'    => $venta->id,
                    'payment_id'  => $payment->id,
                    'total_venta' => $venta->total,
                    'pagado'      => $pagado,
                    'moneda'      => $payment->currency_id ?? null,
                ]);
                return false;
            }

            try {
                // Transacción anidada (savepoint): si falla, se revierten las salidas de stock pero no el registro del pago.
                DB::transaction(function () use ($venta) {
                    $venta->loadMissing('details');

                    foreach ($venta->details as $detail) {
                        $product = Product::find($detail->product_id);

                        if (! $product) {
                            throw new \RuntimeException("Producto no encontrado para el detalle {$detail->id}");
                        }

                        // Lanza InsufficientStockException (RuntimeException) si ya no hay existencia.
                        app(InventoryService::class)->addSaleExit($product, $detail);
                    }

                    $venta->estatus = 'completada';
                    $venta->save();
                });
            } catch (\RuntimeException $e) {
                Log::error('❌ Pago aprobado pero no se pudo completar la venta; requiere revisión manual (reembolso o surtir)', [
                    'venta_id'   => $venta->id,
                    'payment_id' => $payment->id,
                    'detalle'    => $e->getMessage(),
                ]);
                return false;
            }

            $customer = Customers::find($venta->customer_id);
            if ($customer) {
                $customer->total_compras       = ($customer->total_compras ?? 0) + $venta->total;
                $customer->numero_compras      = ($customer->numero_compras ?? 0) + 1;
                $customer->fecha_ultima_compra = now();
                $customer->save();

                Log::info("👤 Cliente actualizado: {$customer->nombre}");
            }

            Log::info('✅ Pago procesado correctamente', [
                'venta_id'   => $venta->id,
                'payment_id' => $payment->id,
                'total'      => $venta->total,
            ]);

            $recienCompletada = true;

            return true;
        });

        // Correo de confirmacion fuera de la transaccion y solo cuando la venta acaba de completarse:
        // webhook y confirm-payment pueden llegar juntos y el segundo encuentra la venta ya completada.
        if ($recienCompletada) {
            OrderNotificationService::sendPurchaseCompletedNotification(
                Sale::with(['customer', 'details'])->find($venta->id)
            );
        }

        return $resultado;
    }

    /**
     * Retorno del cliente desde Mercado Pago. Confirma el pago contra la API de Mercado Pago
     * (no contra lo que diga la URL) y devuelve el estado real de la venta. Es el respaldo
     * del webhook: si éste ya procesó el pago, no repite nada.
     */
    public function confirmPayment(Request $request)
    {
        if (! $this->configured) {
            return $this->noConfigurado();
        }

        $validator = Validator::make($request->all(), [
            'payment_id' => 'required|digits_between:1,20',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'status'  => 422,
                'message' => 'Error de validación',
                'data'    => $validator->errors(),
            ], 422);
        }

        try {
            $payment = $this->obtenerPago($request->input('payment_id'));

            if (! $payment) {
                return response()->json([
                    'success' => false,
                    'status'  => 404,
                    'message' => 'Pago no encontrado',
                    'data'    => null,
                ], 404);
            }

            $venta = Sale::find((int) $payment->external_reference);

            // Solo el dueño de la venta puede confirmar su pago.
            if (! $venta || (int) $venta->customer_id !== (int) $request->user()->id) {
                return response()->json([
                    'success' => false,
                    'status'  => 403,
                    'message' => 'No autorizado',
                    'data'    => null,
                ], 403);
            }

            $venta = $this->sincronizarPago($payment) ?? $venta;

            return response()->json([
                'success' => true,
                'status'  => 200,
                'message' => 'Pago confirmado',
                'data'    => [
                    'venta_id'           => $venta->id,
                    'folio'              => $venta->folio,
                    'estatus'            => $venta->estatus,
                    'total'              => $venta->total,
                    'pago_status'        => $payment->status,
                    'pago_status_detail' => $payment->status_detail,
                ],
            ], 200);

        } catch (\Exception $e) {
            Log::error('❌ Error al confirmar pago: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'status'  => 500,
                'message' => 'No se pudo confirmar el pago. Si te cobraron, tu compra se confirmará automáticamente.',
                'data'    => null,
            ], 500);
        }
    }

    public function checkPaymentStatus(Request $request, $paymentId)
    {
        if (! $this->configured) {
            return $this->noConfigurado();
        }

        if (! ctype_digit((string) $paymentId)) {
            return response()->json([
                'success' => false,
                'message' => 'Pago no encontrado',
            ], 404);
        }

        try {
            $payment = $this->obtenerPago($paymentId);

            if (! $payment) {
                return response()->json([
                    'success' => false,
                    'message' => 'Pago no encontrado',
                ], 404);
            }

            // Solo el dueño de la venta puede consultar el pago.
            $venta = Sale::find((int) $payment->external_reference);
            if (! $venta || (int) $venta->customer_id !== (int) $request->user()->id) {
                return response()->json([
                    'success' => false,
                    'status'  => 403,
                    'message' => 'No autorizado',
                    'data'    => null,
                ], 403);
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

    public function getVentaByPreference(Request $request, $preferenceId)
    {
        try {
            // Devuelve la última venta MP pendiente del cliente autenticado.
            $venta = Sale::where('estatus', 'pendiente')
                ->where('metodo_pago', 'mercado_pago')
                ->where('customer_id', $request->user()->id)
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
