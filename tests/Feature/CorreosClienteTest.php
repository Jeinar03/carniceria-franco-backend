<?php

namespace Tests\Feature;

use App\Http\Controllers\MercadoPagoController;
use App\Http\Livewire\Despachos\DespachosController;
use App\Mail\OrderStatusMail;
use App\Models\Customers;
use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use App\Services\OrderNotificationService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Livewire\Livewire;
use MercadoPago\Preference;
use MercadoPago\SDK;
use Tests\TestCase;

/**
 * Controlador con Mercado Pago simulado: no sale a la red.
 */
class MercadoPagoCorreosControllerFake extends MercadoPagoController
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
 * Correos que recibe el cliente segun como paga.
 * Ver: Carnicería Franco/Spec - Correos al cliente por compra y transferencia.md
 */
class CorreosClienteTest extends TestCase
{
    private Customers $cliente;
    private User $admin;

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

        MercadoPagoCorreosControllerFake::$pagos = [];
        $this->app->bind(MercadoPagoController::class, fn () => new MercadoPagoCorreosControllerFake());

        $this->cliente = Customers::create([
            'nombre' => 'Doña',
            'apellido' => 'Juanita',
            'correo' => 'juanita@test.com',
            'password' => Hash::make('cliente123'),
            'estatus' => 'activo',
        ]);

        $this->admin = User::create([
            'name' => 'Admin',
            'email' => 'admin@test.com',
            'password' => Hash::make('secret'),
        ]);
        $this->admin->assignRole('Admin');

        Mail::fake();
    }

    private function producto(): Product
    {
        return Product::where('activo', true)->where('en_oferta', false)->firstOrFail();
    }

    private function comprarPorTransferencia(): Sale
    {
        Sanctum::actingAs($this->cliente, ['cliente']);

        $res = $this->postJson('/api/v1/ventas', [
            'metodo_pago' => 'transferencia',
            'productos' => [['product_id' => $this->producto()->id, 'cantidad' => 1]],
        ]);
        $res->assertStatus(201);

        return Sale::findOrFail($res->json('data.id'));
    }

    private function ventaMercadoPago(int $paymentId, string $status = 'approved'): Sale
    {
        Sanctum::actingAs($this->cliente, ['cliente']);

        $res = $this->postJson('/api/v1/mercadopago/create-preference', [
            'metodo_pago' => 'mercado_pago',
            'productos' => [['product_id' => $this->producto()->id, 'cantidad' => 1]],
        ]);
        $res->assertStatus(201);
        $venta = Sale::findOrFail($res->json('data.venta_pendiente_id'));

        MercadoPagoCorreosControllerFake::$pagos[(string) $paymentId] = (object) [
            'id' => $paymentId,
            'status' => $status,
            'status_detail' => $status === 'approved' ? 'accredited' : 'cc_rejected_other_reason',
            'external_reference' => (string) $venta->id,
            'transaction_amount' => (float) $venta->total,
            'currency_id' => 'MXN',
        ];

        return $venta;
    }

    private function avisoMp(int $paymentId)
    {
        return $this->postJson('/api/v1/mercadopago/webhook', [
            'type' => 'payment',
            'data' => ['id' => (string) $paymentId],
        ]);
    }

    /** Texto del correo, generado con la plantilla (render() del correo no funciona con Mail::fake). */
    private function htmlDe(OrderStatusMail $correo): string
    {
        return view('emails.order-status', [
            'sale' => $correo->sale,
            'config' => $correo->statusConfig,
            'order' => $correo->orderSummary,
            'customer' => $correo->sale->customer,
        ])->render();
    }

    private function correos(string $asunto): int
    {
        return Mail::sent(OrderStatusMail::class, fn (OrderStatusMail $m) => $m->statusConfig['subject'] === $asunto)->count();
    }

    public function test_transferencia_desde_la_tienda_manda_recibimos_tu_pedido_y_no_dice_completada(): void
    {
        $venta = $this->comprarPorTransferencia();

        Mail::assertSent(OrderStatusMail::class, 1);
        $this->assertSame(1, $this->correos('Recibimos tu pedido - Carnicería Franko'));

        $correo = Mail::sent(OrderStatusMail::class)->first();
        $this->assertTrue($correo->hasTo('juanita@test.com'));

        $html = $this->htmlDe($correo);
        $this->assertStringContainsString($venta->folio, $html);
        $this->assertStringContainsString('Pendiente de validar la transferencia', $html);
        $this->assertStringNotContainsString('Compra completada', $html);
        $this->assertStringNotContainsString('procesada exitosamente', $html);
    }

    public function test_aprobar_la_transferencia_manda_confirmamos_tu_pago_una_sola_vez(): void
    {
        $venta = $this->comprarPorTransferencia();
        $venta->update(['transferencia_evidencia_path' => 'evidencia/prueba.png']);

        $panel = Livewire::actingAs($this->admin)
            ->test(DespachosController::class)
            ->call('openTransferValidationModal', $venta->id)
            ->call('approveTransfer');

        $this->assertSame(1, $this->correos('Confirmamos tu pago - Carnicería Franko'));

        // Volver a aprobar una transferencia ya aprobada no repite el correo.
        $panel->call('openTransferValidationModal', $venta->id)->call('approveTransfer');

        $this->assertSame(1, $this->correos('Confirmamos tu pago - Carnicería Franko'));
        Mail::assertSent(OrderStatusMail::class, 2); // recibimos + confirmamos
    }

    public function test_rechazar_la_transferencia_manda_un_correo_generico_sin_el_motivo(): void
    {
        $venta = $this->comprarPorTransferencia();
        $venta->update(['transferencia_evidencia_path' => 'evidencia/prueba.png']);

        Livewire::actingAs($this->admin)
            ->test(DespachosController::class)
            ->call('openTransferValidationModal', $venta->id)
            ->set('transferValidationNote', 'Nota interna: parece comprobante falso de Juan')
            ->call('rejectTransfer');

        $this->assertSame(1, $this->correos('No pudimos validar tu transferencia - Carnicería Franko'));

        $correo = Mail::sent(OrderStatusMail::class, fn (OrderStatusMail $m) => str_starts_with($m->statusConfig['subject'], 'No pudimos'))->first();
        $this->assertTrue($correo->hasTo('juanita@test.com'));

        $html = $this->htmlDe($correo);
        $this->assertStringNotContainsString('Nota interna', $html);
        $this->assertStringNotContainsString('comprobante falso', $html);
        $this->assertStringContainsString($venta->folio, $html);
    }

    public function test_pago_aprobado_de_mercado_pago_manda_un_solo_correo_de_confirmacion(): void
    {
        $venta = $this->ventaMercadoPago(701);

        $this->avisoMp(701)->assertOk();
        $this->avisoMp(701)->assertOk(); // Mercado Pago reintenta
        $this->postJson('/api/v1/mercadopago/confirm-payment', ['payment_id' => '701'])->assertOk();

        $this->assertSame('completada', $venta->fresh()->estatus);
        Mail::assertSent(OrderStatusMail::class, 1);
        $this->assertSame(1, $this->correos('Confirmación de compra - Carnicería Franko'));
        $this->assertTrue(Mail::sent(OrderStatusMail::class)->first()->hasTo('juanita@test.com'));
    }

    public function test_un_pago_rechazado_de_mercado_pago_no_manda_correo(): void
    {
        $this->ventaMercadoPago(702, 'rejected');

        $this->avisoMp(702)->assertOk();

        Mail::assertNothingSent();
    }

    public function test_efectivo_creado_en_el_panel_con_cliente_manda_la_confirmacion_de_compra(): void
    {
        Livewire::actingAs($this->admin)
            ->test(DespachosController::class)
            ->set('createCustomerId', (string) $this->cliente->id)
            ->set('createMetodoPago', 'efectivo')
            ->call('addProductToCart', $this->producto()->id)
            ->call('createOrder');

        Mail::assertSent(OrderStatusMail::class, 1);
        $this->assertSame(1, $this->correos('Confirmación de compra - Carnicería Franko'));
    }

    public function test_transferencia_creada_en_el_panel_con_cliente_manda_recibimos_tu_pedido(): void
    {
        Livewire::actingAs($this->admin)
            ->test(DespachosController::class)
            ->set('createCustomerId', (string) $this->cliente->id)
            ->set('createMetodoPago', 'transferencia')
            ->call('addProductToCart', $this->producto()->id)
            ->call('createOrder');

        Mail::assertSent(OrderStatusMail::class, 1);
        $this->assertSame(1, $this->correos('Recibimos tu pedido - Carnicería Franko'));
    }

    public function test_las_plantillas_nuevas_se_generan_con_folio_productos_y_total(): void
    {
        $venta = $this->comprarPorTransferencia();
        $venta->load(['customer', 'details']);

        $metodo = new \ReflectionMethod(OrderNotificationService::class, 'getStatusConfig');
        $metodo->setAccessible(true);

        foreach (['recibido_transferencia', 'transferencia_aprobada', 'transferencia_rechazada'] as $clave) {
            $config = $metodo->invoke(null, $clave);
            $this->assertNotNull($config, "Falta la configuracion {$clave}");

            $html = $this->htmlDe(new OrderStatusMail($venta, $config));

            $this->assertStringContainsString($venta->folio, $html, "El correo {$clave} debe traer el folio");
            $this->assertStringContainsString('Carnicería Franko', $html);
            $this->assertStringContainsString(number_format($venta->total, 2), $html);
        }
    }
}
