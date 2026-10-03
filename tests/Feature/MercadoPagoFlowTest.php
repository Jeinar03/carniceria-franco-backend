<?php

namespace Tests\Feature;

use App\Http\Controllers\MercadoPagoController;
use App\Models\Customers;
use App\Models\Product;
use App\Models\Sale;
use App\Services\InventoryService;
use App\Services\PricingService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use MercadoPago\Preference;
use MercadoPago\SDK;
use Tests\TestCase;

/**
 * Controlador con Mercado Pago simulado: no sale a la red. Los "pagos" que devuelve
 * la API de Mercado Pago se cargan en $pagos y la preferencia creada se guarda en $preferencia.
 */
class MercadoPagoControllerFake extends MercadoPagoController
{
    /** @var array<string, object> */
    public static array $pagos = [];
    public static ?Preference $preferencia = null;

    protected function inicializarSdk(string $accessToken): void
    {
        // El SDK real consulta /users/me al recibir el token; aquí solo se arma el manager, sin red.
        SDK::initialize();
    }

    protected function obtenerPago($paymentId)
    {
        return self::$pagos[(string) $paymentId] ?? null;
    }

    protected function guardarPreferencia(Preference $preference): bool
    {
        self::$preferencia = $preference;

        return true;
    }
}

/**
 * Flujo de pago con Mercado Pago: preferencia, webhook y retorno del cliente.
 * Ver: docs/flujo-pago-mercadopago.html
 */
class MercadoPagoFlowTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite.database', ':memory:');
        DB::purge('sqlite');
        DB::reconnect('sqlite');

        Artisan::call('migrate', ['--force' => true, '--no-interaction' => true]);
        Artisan::call('db:seed', ['--force' => true, '--no-interaction' => true]);

        config()->set('mercadopago.access_token', 'APP_USR-token-de-prueba-0000');
        config()->set('mercadopago.frontend_url', 'https://tienda.test');
        config()->set('mercadopago.notification_url', 'https://api.test/api/v1/mercadopago/webhook');

        MercadoPagoControllerFake::$pagos = [];
        MercadoPagoControllerFake::$preferencia = null;
        $this->app->bind(MercadoPagoController::class, fn () => new MercadoPagoControllerFake());
    }

    private function cliente(string $correo, array $extra = []): Customers
    {
        return Customers::create(array_merge([
            'nombre' => 'C',
            'apellido' => 'X',
            'correo' => $correo,
            'password' => Hash::make('cliente123'),
            'estatus' => 'activo',
        ], $extra));
    }

    /** @return Product[] */
    private function productos(int $cuantos = 1): array
    {
        return Product::where('activo', true)->where('en_oferta', false)->take($cuantos)->get()->all();
    }

    /** Crea la venta pendiente igual que la tienda: por el endpoint create-preference. */
    private function crearVentaPendiente(Customers $cliente, int $productos = 1, array $extraBody = []): Sale
    {
        Sanctum::actingAs($cliente, ['cliente']);

        $res = $this->postJson('/api/v1/mercadopago/create-preference', array_merge([
            'metodo_pago' => 'mercado_pago',
            'productos' => array_map(
                fn (Product $p) => ['product_id' => $p->id, 'cantidad' => 1],
                $this->productos($productos)
            ),
        ], $extraBody));

        $res->assertStatus(201);

        return Sale::findOrFail($res->json('data.venta_pendiente_id'));
    }

    private function pago(int $id, Sale $venta, string $status = 'approved', ?float $monto = null): object
    {
        return MercadoPagoControllerFake::$pagos[(string) $id] = (object) [
            'id' => $id,
            'status' => $status,
            'status_detail' => $status === 'approved' ? 'accredited' : 'cc_rejected_other_reason',
            'external_reference' => (string) $venta->id,
            'transaction_amount' => $monto ?? (float) $venta->total,
            'currency_id' => 'MXN',
        ];
    }

    private function webhook(int $paymentId)
    {
        return $this->postJson('/api/v1/mercadopago/webhook', [
            'type' => 'payment',
            'data' => ['id' => (string) $paymentId],
        ]);
    }

    private function stock(Product $producto): float
    {
        return app(InventoryService::class)->currentStock($producto->id);
    }

    public function test_sin_credenciales_responde_503_claro_y_no_crea_ventas(): void
    {
        config()->set('mercadopago.access_token', null);
        Sanctum::actingAs($this->cliente('a@test.com'), ['cliente']);
        [$producto] = $this->productos();

        $this->postJson('/api/v1/mercadopago/create-preference', [
            'metodo_pago' => 'mercado_pago',
            'productos' => [['product_id' => $producto->id, 'cantidad' => 1]],
        ])
            ->assertStatus(503)
            ->assertJsonPath('success', false);

        $this->assertSame(0, Sale::count());
    }

    public function test_la_preferencia_regresa_a_la_tienda_y_registra_el_webhook_aun_con_sandbox(): void
    {
        config()->set('mercadopago.sandbox', true);

        $venta = $this->crearVentaPendiente($this->cliente('a@test.com'));
        $preferencia = MercadoPagoControllerFake::$preferencia;

        $this->assertSame('pendiente', $venta->estatus);
        $this->assertSame((string) $venta->id, $preferencia->external_reference);
        $this->assertSame('https://api.test/api/v1/mercadopago/webhook', $preferencia->notification_url);
        $this->assertSame('approved', $preferencia->auto_return);

        $back = (array) $preferencia->back_urls;
        foreach (['success', 'failure', 'pending'] as $resultado) {
            $this->assertSame('https://tienda.test/pages/payment-callback', $back[$resultado]);
        }
    }

    public function test_sin_https_no_se_registra_el_webhook_ni_auto_return(): void
    {
        config()->set('mercadopago.notification_url', 'http://localhost/api/v1/mercadopago/webhook');
        config()->set('mercadopago.frontend_url', 'http://localhost:4200');

        $this->crearVentaPendiente($this->cliente('a@test.com'));
        $preferencia = MercadoPagoControllerFake::$preferencia;

        $this->assertNull($preferencia->notification_url);
        $this->assertNull($preferencia->auto_return);
        $this->assertSame('http://localhost:4200/pages/payment-callback', ((array) $preferencia->back_urls)['success']);
    }

    public function test_el_descuento_del_mayorista_lo_calcula_el_servidor_y_mp_cobra_lo_mismo(): void
    {
        $mayorista = $this->cliente('m@test.com', ['tipo_cliente' => 'mayorista', 'descuento_preferencial' => 10]);

        // 'descuento' => 999 es un intento de manipular el cobro: el servidor lo ignora.
        $venta = $this->crearVentaPendiente($mayorista, 2, ['descuento' => 999]);

        $subtotal = (float) $venta->subtotal;
        $this->assertEqualsWithDelta(round($subtotal * 0.10, 2), (float) $venta->descuento, 0.001);
        $this->assertEqualsWithDelta(round($subtotal * 0.90, 2), (float) $venta->total, 0.001);

        $cobradoPorMp = 0;
        foreach (MercadoPagoControllerFake::$preferencia->items as $item) {
            $cobradoPorMp += $item->unit_price * $item->quantity;
        }
        $this->assertEqualsWithDelta((float) $venta->total, $cobradoPorMp, 0.001);
    }

    public function test_un_cliente_minorista_no_recibe_descuento(): void
    {
        $venta = $this->crearVentaPendiente($this->cliente('a@test.com', ['descuento_preferencial' => 15]), 1, ['descuento' => 50]);

        $this->assertEqualsWithDelta(0, (float) $venta->descuento, 0.001);
        $this->assertEqualsWithDelta((float) $venta->subtotal, (float) $venta->total, 0.001);
    }

    public function test_el_webhook_completa_la_venta_y_descuenta_el_stock_una_sola_vez(): void
    {
        $cliente = $this->cliente('a@test.com');
        [$producto] = $this->productos();
        $venta = $this->crearVentaPendiente($cliente);
        $this->pago(111, $venta);
        $antes = $this->stock($producto);

        $this->webhook(111)->assertOk();
        $this->webhook(111)->assertOk(); // Mercado Pago reintenta: no debe repetir nada

        $venta->refresh();
        $this->assertSame('completada', $venta->estatus);
        $this->assertSame('111', (string) $venta->mercadopago_payment_id);
        $this->assertSame('approved', $venta->mercadopago_status);
        $this->assertEqualsWithDelta($antes - 1, $this->stock($producto), 0.001);
        $this->assertSame(1, (int) $cliente->fresh()->numero_compras);
    }

    public function test_el_webhook_acepta_el_formato_ipn(): void
    {
        $venta = $this->crearVentaPendiente($this->cliente('a@test.com'));
        $this->pago(222, $venta);

        $this->postJson('/api/v1/mercadopago/webhook?topic=payment&id=222')->assertOk();

        $this->assertSame('completada', $venta->fresh()->estatus);
    }

    public function test_el_webhook_ignora_ids_que_no_son_numericos(): void
    {
        $this->webhook(0)->assertOk();
        $this->postJson('/api/v1/mercadopago/webhook', ['type' => 'payment', 'data' => ['id' => '../v1/users']])->assertOk();
    }

    public function test_confirm_payment_completa_la_venta_si_el_webhook_no_llego(): void
    {
        $cliente = $this->cliente('a@test.com');
        [$producto] = $this->productos();
        $venta = $this->crearVentaPendiente($cliente);
        $this->pago(333, $venta);
        $antes = $this->stock($producto);

        $this->postJson('/api/v1/mercadopago/confirm-payment', ['payment_id' => '333'])
            ->assertOk()
            ->assertJsonPath('data.estatus', 'completada')
            ->assertJsonPath('data.venta_id', $venta->id)
            ->assertJsonPath('data.pago_status', 'approved');

        $this->assertEqualsWithDelta($antes - 1, $this->stock($producto), 0.001);
    }

    public function test_webhook_y_confirm_payment_juntos_descuentan_el_stock_una_sola_vez(): void
    {
        $cliente = $this->cliente('a@test.com');
        [$producto] = $this->productos();
        $venta = $this->crearVentaPendiente($cliente);
        $this->pago(444, $venta);
        $antes = $this->stock($producto);

        $this->webhook(444)->assertOk();
        $this->postJson('/api/v1/mercadopago/confirm-payment', ['payment_id' => '444'])
            ->assertOk()
            ->assertJsonPath('data.estatus', 'completada');

        $this->assertEqualsWithDelta($antes - 1, $this->stock($producto), 0.001);
        $this->assertSame(1, (int) $cliente->fresh()->numero_compras);
    }

    public function test_confirm_payment_de_un_pago_ajeno_da_403(): void
    {
        $duenio = $this->cliente('a@test.com');
        $otro = $this->cliente('b@test.com');
        $venta = $this->crearVentaPendiente($duenio);
        $this->pago(555, $venta);

        Sanctum::actingAs($otro, ['cliente']);

        $this->postJson('/api/v1/mercadopago/confirm-payment', ['payment_id' => '555'])->assertStatus(403);
        $this->getJson('/api/v1/mercadopago/payment-status/555')->assertStatus(403);
        $this->assertSame('pendiente', $venta->fresh()->estatus);
    }

    public function test_confirm_payment_valida_el_payment_id(): void
    {
        Sanctum::actingAs($this->cliente('a@test.com'), ['cliente']);

        $this->postJson('/api/v1/mercadopago/confirm-payment', [])->assertStatus(422);
        $this->postJson('/api/v1/mercadopago/confirm-payment', ['payment_id' => 'null'])->assertStatus(422);
        $this->postJson('/api/v1/mercadopago/confirm-payment', ['payment_id' => '999999'])->assertStatus(404);
    }

    public function test_un_monto_distinto_no_completa_la_venta_pero_deja_constancia_del_cobro(): void
    {
        [$producto] = $this->productos();
        $venta = $this->crearVentaPendiente($this->cliente('a@test.com'));
        $this->pago(666, $venta, 'approved', 1.00);
        $antes = $this->stock($producto);

        $this->webhook(666)->assertOk();

        $venta->refresh();
        $this->assertSame('pendiente', $venta->estatus);
        $this->assertSame('666', (string) $venta->mercadopago_payment_id);
        $this->assertSame('approved', $venta->mercadopago_status);
        $this->assertEqualsWithDelta($antes, $this->stock($producto), 0.001);
    }

    public function test_cobro_aprobado_sin_stock_deja_constancia_del_pago_y_no_completa_la_venta(): void
    {
        [$producto] = $this->productos();
        $venta = $this->crearVentaPendiente($this->cliente('a@test.com'));
        $this->pago(690, $venta);

        // Entre crear la preferencia y cobrar, el producto se quedó sin existencia.
        DB::table('inventory_movements')->where('product_id', $producto->id)->delete();
        $movimientos = DB::table('inventory_movements')->count();

        $this->webhook(690)->assertOk();

        $venta->refresh();
        $this->assertSame('pendiente', $venta->estatus);
        $this->assertSame('690', (string) $venta->mercadopago_payment_id);
        $this->assertSame('approved', $venta->mercadopago_status);
        $this->assertSame($movimientos, DB::table('inventory_movements')->count());
    }

    public function test_un_pago_rechazado_cancela_la_venta_pendiente(): void
    {
        $venta = $this->crearVentaPendiente($this->cliente('a@test.com'));
        $this->pago(777, $venta, 'rejected');

        $this->webhook(777)->assertOk();

        $this->assertSame('cancelada', $venta->fresh()->estatus);
        $this->assertSame('rejected', $venta->fresh()->mercadopago_status);
        // El motivo del rechazo queda guardado para no depender del panel de Mercado Pago.
        $this->assertSame('cc_rejected_other_reason', $venta->fresh()->mercadopago_status_detail);
    }

    public function test_el_comprador_viaja_completo_a_mercado_pago_en_produccion(): void
    {
        config()->set('mercadopago.sandbox', false);

        $this->crearVentaPendiente($this->cliente('prod@test.com', ['telefono' => '+52 (753) 100-2000']));
        $payer = MercadoPagoControllerFake::$preferencia->payer;

        $this->assertSame('prod@test.com', $payer->email);
        $this->assertSame('X', $payer->surname);
        $this->assertEquals(['area_code' => '753', 'number' => '1002000'], (array) $payer->phone);
    }

    public function test_en_prueba_no_se_manda_el_correo_del_comprador(): void
    {
        config()->set('mercadopago.sandbox', true);

        $this->crearVentaPendiente($this->cliente('prueba@test.com'));

        $this->assertNull(MercadoPagoControllerFake::$preferencia->payer->email);
    }

    public function test_un_rechazo_tardio_no_degrada_una_venta_ya_cobrada(): void
    {
        $venta = $this->crearVentaPendiente($this->cliente('a@test.com'));
        $this->pago(801, $venta, 'approved');
        $this->pago(800, $venta, 'rejected'); // intento anterior que se notifica después

        $this->webhook(801)->assertOk();
        $this->webhook(800)->assertOk();

        $venta->refresh();
        $this->assertSame('completada', $venta->estatus);
        $this->assertSame('801', (string) $venta->mercadopago_payment_id);
        $this->assertSame('approved', $venta->mercadopago_status);
    }

    public function test_repartir_total_suma_exactamente_el_total(): void
    {
        $pricing = new PricingService();

        $reparto = $pricing->repartirTotal([33.33, 33.33, 33.34], 90.00);
        $this->assertSame(9000, (int) round(array_sum($reparto) * 100));

        $this->assertSame([90.0, 45.0], $pricing->repartirTotal([100.0, 50.0], 135.0));

        // Sin descuento el reparto no cambia los montos
        $this->assertSame([10.5, 20.25], $pricing->repartirTotal([10.5, 20.25], 30.75));
    }

    public function test_porcentaje_de_descuento_solo_para_mayoristas(): void
    {
        $pricing = new PricingService();

        $this->assertSame(10.0, $pricing->porcentajeDescuentoParaCliente(new Customers(['tipo_cliente' => 'mayorista', 'descuento_preferencial' => 10])));
        $this->assertSame(0.0, $pricing->porcentajeDescuentoParaCliente(new Customers(['tipo_cliente' => 'minorista', 'descuento_preferencial' => 10])));
        $this->assertSame(100.0, $pricing->porcentajeDescuentoParaCliente(new Customers(['tipo_cliente' => 'mayorista', 'descuento_preferencial' => 250])));
        $this->assertSame(0.0, $pricing->porcentajeDescuentoParaCliente(null));
    }
}
