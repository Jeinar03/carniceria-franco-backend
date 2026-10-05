<?php

namespace Tests\Feature;

use App\Http\Livewire\Despachos\DespachosController;
use App\Mail\OrderStatusMail;
use App\Models\Customers;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SiteConfig;
use App\Models\User;
use App\Services\OrderNotificationService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Forma de entrega de un pedido: mandadito (servicio externo) o recoger en la carniceria.
 * Spec: "Spec - Estado Entregado de una venta" en el vault.
 */
class FormaEntregaTest extends TestCase
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
    }

    private function producto(): Product
    {
        return Product::where('activo', true)->where('en_oferta', false)->firstOrFail();
    }

    private function comprarEnTienda(array $extra = [])
    {
        Sanctum::actingAs($this->cliente, ['cliente']);

        return $this->postJson('/api/v1/ventas', array_merge([
            'metodo_pago' => 'transferencia',
            'productos' => [['product_id' => $this->producto()->id, 'cantidad' => 1]],
        ], $extra));
    }

    /** Deja una venta lista para "Enviar pedido" desde Despachos. */
    private function ventaListaParaEnviar(string $tipo): Sale
    {
        Mail::fake();
        $venta = Sale::findOrFail($this->comprarEnTienda(['tipo_entrega' => $tipo])->json('data.id'));
        $venta->update(['transferencia_estado' => 'aprobada', 'estatus' => Sale::ESTATUS_COMPLETADA]);

        Livewire::actingAs($this->admin)
            ->test(DespachosController::class)
            ->set('selectedSaleId', $venta->id)
            ->call('toggleProductDespacho', $venta->details()->firstOrFail()->id);

        $this->assertSame(Sale::ENVIO_LISTO, $venta->fresh()->estado_envio);

        return $venta;
    }

    /** HTML del correo; con Mail::fake() el mailer es falso, asi que se renderiza la vista directo. */
    private function htmlDe(OrderStatusMail $mail): string
    {
        return view('emails.order-status', [
            'sale' => $mail->sale,
            'config' => $mail->statusConfig,
            'order' => $mail->orderSummary,
            'customer' => $mail->sale->customer,
            'datosBancarios' => null,
        ])->render();
    }

    private function enviar(Sale $venta)
    {
        return Livewire::actingAs($this->admin)
            ->test(DespachosController::class)
            ->set('selectedSaleId', $venta->id)
            ->call('enviarPedido');
    }

    // ---------- Tienda (API) ----------

    public function test_la_tienda_sin_tipo_de_entrega_queda_como_mandadito(): void
    {
        $res = $this->comprarEnTienda()->assertStatus(201);

        $this->assertSame('mandadito', Sale::findOrFail($res->json('data.id'))->tipo_entrega);
    }

    public function test_la_tienda_guarda_recoger(): void
    {
        $res = $this->comprarEnTienda(['tipo_entrega' => 'recoger'])->assertStatus(201);

        $this->assertSame('recoger', Sale::findOrFail($res->json('data.id'))->tipo_entrega);
    }

    public function test_la_tienda_rechaza_un_tipo_de_entrega_invalido(): void
    {
        $this->comprarEnTienda(['tipo_entrega' => 'dron'])->assertStatus(422);

        $this->assertSame(0, Sale::count());
    }

    // ---------- Panel: crear y editar ----------

    public function test_el_panel_crea_un_pedido_para_recoger(): void
    {
        Mail::fake();

        Livewire::actingAs($this->admin)
            ->test(DespachosController::class)
            ->set('createCustomerId', (string) $this->cliente->id)
            ->set('createTipoEntrega', 'recoger')
            ->call('addProductToCart', $this->producto()->id)
            ->call('createOrder')
            ->assertEmitted('pedido-creado');

        $this->assertSame('recoger', Sale::latest('id')->firstOrFail()->tipo_entrega);
    }

    public function test_el_panel_por_omision_crea_mandadito(): void
    {
        Mail::fake();

        Livewire::actingAs($this->admin)
            ->test(DespachosController::class)
            ->set('createCustomerId', (string) $this->cliente->id)
            ->call('addProductToCart', $this->producto()->id)
            ->call('createOrder')
            ->assertEmitted('pedido-creado');

        $this->assertSame('mandadito', Sale::latest('id')->firstOrFail()->tipo_entrega);
    }

    public function test_el_panel_rechaza_un_tipo_de_entrega_invalido(): void
    {
        Mail::fake();

        Livewire::actingAs($this->admin)
            ->test(DespachosController::class)
            ->set('createCustomerId', (string) $this->cliente->id)
            ->set('createTipoEntrega', 'dron')
            ->call('addProductToCart', $this->producto()->id)
            ->call('createOrder')
            ->assertEmitted('despacho-error');

        $this->assertSame(0, Sale::count());
    }

    public function test_editar_un_pedido_cambia_la_forma_de_entrega(): void
    {
        Mail::fake();
        $venta = Sale::findOrFail($this->comprarEnTienda()->json('data.id'));
        $this->assertSame('mandadito', $venta->tipo_entrega);

        Livewire::actingAs($this->admin)
            ->test(DespachosController::class)
            ->call('openEditOrderModal', $venta->id)
            ->assertSet('editTipoEntrega', 'mandadito')
            ->set('editTipoEntrega', 'recoger')
            ->call('updateOrder');

        $this->assertSame('recoger', $venta->fresh()->tipo_entrega);
    }

    // ---------- Despachos: etiqueta y boton ----------

    public function test_despachos_muestra_la_etiqueta_de_cada_tipo(): void
    {
        Mail::fake();
        $recoger = Sale::findOrFail($this->comprarEnTienda(['tipo_entrega' => 'recoger'])->json('data.id'));
        $recoger->update(['transferencia_estado' => 'aprobada', 'estatus' => Sale::ESTATUS_COMPLETADA]);

        Livewire::actingAs($this->admin)
            ->test(DespachosController::class)
            ->assertSee($recoger->folio)
            ->assertSee('Recoger en la carnicería');
    }

    // ---------- Correo al terminar el trabajo de la carniceria ----------

    public function test_mandadito_avisa_que_el_pedido_ya_salio_sin_hablar_de_repartidor_propio(): void
    {
        $venta = $this->ventaListaParaEnviar('mandadito');

        $this->enviar($venta)->assertEmitted('pedido-enviado');

        $this->assertSame(Sale::ENVIO_ENVIADO, $venta->fresh()->estado_envio);
        Mail::assertSent(OrderStatusMail::class, function ($mail) {
            $html = $this->htmlDe($mail);

            return $mail->hasTo('juanita@test.com')
                && $mail->statusConfig['subject'] === 'Tu pedido ya salió - Carnicería Franko'
                && stripos($html, 'repartidor') === false
                && stripos($html, 'mandadito') !== false;
        });
    }

    public function test_recoger_avisa_que_esta_listo_con_direccion_y_horarios(): void
    {
        SiteConfig::activa()->update([
            'direccion' => 'Calle Hidalgo 45, Centro',
            'horarios' => [
                'lunes' => '08:00 - 20:00',
                'martes' => ['abierto' => true, 'apertura' => '09:00', 'cierre' => '18:00'],
                'domingo' => ['abierto' => false],
            ],
        ]);
        $venta = $this->ventaListaParaEnviar('recoger');

        $this->enviar($venta)->assertEmitted('pedido-enviado');

        Mail::assertSent(OrderStatusMail::class, function ($mail) {
            $html = $this->htmlDe($mail);

            return $mail->hasTo('juanita@test.com')
                && $mail->statusConfig['subject'] === 'Tu pedido está listo para recoger - Carnicería Franko'
                && str_contains($html, 'Calle Hidalgo 45, Centro')
                && str_contains($html, '08:00 - 20:00')
                && str_contains($html, '09:00 - 18:00')
                && stripos($html, 'repartidor') === false;
        });
    }

    public function test_recoger_sin_direccion_capturada_igual_manda_el_correo(): void
    {
        SiteConfig::activa()->update(['direccion' => null, 'horarios' => null]);
        $venta = $this->ventaListaParaEnviar('recoger');

        $this->enviar($venta)->assertEmitted('pedido-enviado');

        Mail::assertSent(OrderStatusMail::class, function ($mail) {
            return $mail->statusConfig['subject'] === 'Tu pedido está listo para recoger - Carnicería Franko'
                && ! empty($this->htmlDe($mail));
        });
    }

    public function test_un_pedido_anterior_a_esta_funcion_se_avisa_como_mandadito(): void
    {
        Mail::fake();
        $venta = Sale::findOrFail($this->comprarEnTienda()->json('data.id'));
        // Simula una venta vieja: la columna se llena con el valor por omision de la migracion.
        $this->assertSame('mandadito', $venta->fresh()->tipo_entrega);

        $venta->update(['estado_envio' => Sale::ENVIO_ENVIADO]);
        OrderNotificationService::sendStatusNotification($venta->fresh(['customer', 'details']));

        Mail::assertSent(OrderStatusMail::class, fn ($mail) => $mail->statusConfig['subject'] === 'Tu pedido ya salió - Carnicería Franko');
    }

    public function test_ninguna_plantilla_dice_nuestro_repartidor(): void
    {
        $venta = Sale::findOrFail($this->comprarEnTienda()->json('data.id'));
        $venta->load(['customer', 'details']);
        $metodo = new \ReflectionMethod(OrderNotificationService::class, 'getStatusConfig');
        $metodo->setAccessible(true);

        foreach (['Procesando', 'Listo_para_enviar', 'Enviado'] as $estado) {
            $venta->estado_envio = $estado;
            $config = $metodo->invoke(null, $estado, $venta);

            $this->assertStringNotContainsStringIgnoringCase('repartidor', json_encode($config, JSON_UNESCAPED_UNICODE), "La plantilla {$estado} habla de repartidor");
            $this->assertNotEmpty((new OrderStatusMail($venta, $config))->render());
        }
    }
}
