<?php

namespace Tests\Feature;

use App\Http\Controllers\MercadoPagoController;
use App\Models\Customers;
use App\Models\MercadoPagoSetting;
use App\Models\Product;
use App\Models\Sale;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use MercadoPago\Preference;
use MercadoPago\SDK;
use Tests\TestCase;

/**
 * Controlador con Mercado Pago simulado: no sale a la red.
 */
class MercadoPagoFirmaControllerFake extends MercadoPagoController
{
    /** @var array<string, object> */
    public static array $pagos = [];

    protected function inicializarSdk(string $accessToken): void
    {
        SDK::initialize();
    }

    protected function obtenerPago($paymentId)
    {
        return self::$pagos[(string) $paymentId] ?? null;
    }

    protected function guardarPreferencia(Preference $preference): bool
    {
        return true;
    }
}

/**
 * Firma del webhook (encabezado x-signature).
 * Ver: Carnicería Franco/Spec - Firma del webhook de Mercado Pago.md
 */
class MercadoPagoFirmaWebhookTest extends TestCase
{
    private const CLAVE = 'clave-secreta-de-prueba';

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
        config()->set('mercadopago.webhook_secret', null);

        MercadoPagoFirmaControllerFake::$pagos = [];
        $this->app->bind(MercadoPagoController::class, fn () => new MercadoPagoFirmaControllerFake());
    }

    /** Arma el encabezado x-signature igual que Mercado Pago: HMAC-SHA256 del manifiesto. */
    private function firma(string $secreto, string $dataId, ?string $requestId, int $ts = 1700000000123): string
    {
        $manifiesto = 'id:' . strtolower($dataId) . ';'
            . ($requestId !== null ? 'request-id:' . $requestId . ';' : '')
            . 'ts:' . $ts . ';';

        return 'ts=' . $ts . ',v1=' . hash_hmac('sha256', $manifiesto, $secreto);
    }

    /** Webhook como lo manda Mercado Pago: data.id en la URL y la firma en el encabezado. */
    private function webhook(int $paymentId, ?string $xSignature, ?string $requestId = 'req-abc-123')
    {
        $headers = [];
        if ($xSignature !== null) {
            $headers['x-signature'] = $xSignature;
        }
        if ($requestId !== null) {
            $headers['x-request-id'] = $requestId;
        }

        return $this->postJson(
            '/api/v1/mercadopago/webhook?data.id=' . $paymentId . '&type=payment',
            ['type' => 'payment', 'data' => ['id' => (string) $paymentId]],
            $headers
        );
    }

    /** Venta pendiente de Mercado Pago, creada como la tienda, con su pago aprobado listo. */
    private function ventaConPago(int $paymentId): Sale
    {
        $cliente = Customers::create([
            'nombre' => 'C',
            'apellido' => 'X',
            'correo' => "firma{$paymentId}@test.com",
            'password' => Hash::make('cliente123'),
            'estatus' => 'activo',
        ]);
        Sanctum::actingAs($cliente, ['cliente']);

        $producto = Product::where('activo', true)->where('en_oferta', false)->firstOrFail();
        $res = $this->postJson('/api/v1/mercadopago/create-preference', [
            'metodo_pago' => 'mercado_pago',
            'productos' => [['product_id' => $producto->id, 'cantidad' => 1]],
        ]);
        $res->assertStatus(201);

        $venta = Sale::findOrFail($res->json('data.venta_pendiente_id'));

        MercadoPagoFirmaControllerFake::$pagos[(string) $paymentId] = (object) [
            'id' => $paymentId,
            'status' => 'approved',
            'status_detail' => 'accredited',
            'external_reference' => (string) $venta->id,
            'transaction_amount' => (float) $venta->total,
            'currency_id' => 'MXN',
        ];

        return $venta;
    }

    public function test_sin_clave_de_firma_el_webhook_funciona_como_siempre(): void
    {
        $venta = $this->ventaConPago(901);

        $this->webhook(901, null)->assertOk();

        $this->assertSame('completada', $venta->fresh()->estatus);
    }

    public function test_con_clave_y_firma_valida_el_webhook_se_procesa(): void
    {
        config()->set('mercadopago.webhook_secret', self::CLAVE);
        $venta = $this->ventaConPago(902);

        $this->webhook(902, $this->firma(self::CLAVE, '902', 'req-abc-123'))->assertOk();

        $this->assertSame('completada', $venta->fresh()->estatus);
    }

    public function test_con_clave_y_firma_incorrecta_se_rechaza_y_no_se_toca_la_venta(): void
    {
        config()->set('mercadopago.webhook_secret', self::CLAVE);
        $venta = $this->ventaConPago(903);

        $this->webhook(903, $this->firma('otra-clave', '903', 'req-abc-123'))->assertStatus(401);

        $this->assertSame('pendiente', $venta->fresh()->estatus);
    }

    public function test_con_clave_y_sin_firma_se_rechaza(): void
    {
        config()->set('mercadopago.webhook_secret', self::CLAVE);
        $venta = $this->ventaConPago(904);

        $this->webhook(904, null)->assertStatus(401);

        $this->assertSame('pendiente', $venta->fresh()->estatus);
    }

    public function test_una_firma_valida_de_otro_pago_no_sirve(): void
    {
        config()->set('mercadopago.webhook_secret', self::CLAVE);
        $venta = $this->ventaConPago(905);

        // Firma calculada para el pago 999, usada en el aviso del pago 905.
        $this->webhook(905, $this->firma(self::CLAVE, '999', 'req-abc-123'))->assertStatus(401);

        $this->assertSame('pendiente', $venta->fresh()->estatus);
    }

    public function test_una_firma_valida_con_otro_request_id_no_sirve(): void
    {
        config()->set('mercadopago.webhook_secret', self::CLAVE);
        $venta = $this->ventaConPago(909);

        $this->webhook(909, $this->firma(self::CLAVE, '909', 'req-original'), 'req-distinto')->assertStatus(401);

        $this->assertSame('pendiente', $venta->fresh()->estatus);
    }

    public function test_la_firma_sin_request_id_se_valida_sin_esa_parte(): void
    {
        config()->set('mercadopago.webhook_secret', self::CLAVE);
        $venta = $this->ventaConPago(906);

        $this->webhook(906, $this->firma(self::CLAVE, '906', null), null)->assertOk();

        $this->assertSame('completada', $venta->fresh()->estatus);
    }

    public function test_un_encabezado_x_signature_mal_formado_se_rechaza(): void
    {
        config()->set('mercadopago.webhook_secret', self::CLAVE);
        $venta = $this->ventaConPago(910);

        foreach (['basura', 'ts=1700000000123', 'v1=abc', 'ts=,v1='] as $mal) {
            $this->webhook(910, $mal)->assertStatus(401);
        }

        $this->assertSame('pendiente', $venta->fresh()->estatus);
    }

    public function test_con_clave_el_formato_ipn_sin_firma_se_rechaza(): void
    {
        config()->set('mercadopago.webhook_secret', self::CLAVE);
        $venta = $this->ventaConPago(907);

        $this->postJson('/api/v1/mercadopago/webhook?topic=payment&id=907')->assertStatus(401);

        $this->assertSame('pendiente', $venta->fresh()->estatus);
    }

    public function test_la_clave_guardada_en_el_panel_tambien_se_usa(): void
    {
        MercadoPagoSetting::create([
            'name' => 'Principal',
            'access_token' => 'APP_USR-token-de-prueba-0000',
            'public_key' => 'APP_USR-llave-publica-0000',
            'webhook_secret' => 'clave-del-panel',
            'sandbox' => false,
            'active' => true,
        ]);
        $venta = $this->ventaConPago(908);

        $this->webhook(908, $this->firma('otra-clave', '908', 'req-abc-123'))->assertStatus(401);
        $this->assertSame('pendiente', $venta->fresh()->estatus);

        $this->webhook(908, $this->firma('clave-del-panel', '908', 'req-abc-123'))->assertOk();
        $this->assertSame('completada', $venta->fresh()->estatus);
    }
}
