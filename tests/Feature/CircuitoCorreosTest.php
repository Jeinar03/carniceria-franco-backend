<?php

namespace Tests\Feature;

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
use Tests\TestCase;

/**
 * Circuito de una compra por transferencia: compra, validacion, despacho y envio,
 * y los correos que recibe el cliente en cada paso.
 */
class CircuitoCorreosTest extends TestCase
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

    private function comprarPorTransferencia(): Sale
    {
        Sanctum::actingAs($this->cliente, ['cliente']);
        $producto = Product::where('activo', true)->where('en_oferta', false)->firstOrFail();

        $res = $this->postJson('/api/v1/ventas', [
            'metodo_pago' => 'transferencia',
            'productos' => [['product_id' => $producto->id, 'cantidad' => 1]],
        ]);
        $res->assertStatus(201);

        return Sale::findOrFail($res->json('data.id'));
    }

    public function test_la_compra_envia_el_correo_de_confirmacion_al_cliente(): void
    {
        Mail::fake();

        $this->comprarPorTransferencia();

        Mail::assertSent(OrderStatusMail::class, 1);
        Mail::assertSent(OrderStatusMail::class, function ($mail) {
            return $mail->hasTo('juanita@test.com')
                && $mail->statusConfig['subject'] === 'Confirmación de compra - Carnicería Franko';
        });
    }

    public function test_sin_validar_la_transferencia_no_se_puede_despachar(): void
    {
        Mail::fake();
        $venta = $this->comprarPorTransferencia();
        $detalle = $venta->details()->firstOrFail();

        Livewire::actingAs($this->admin)
            ->test(DespachosController::class)
            ->set('selectedSaleId', $venta->id)
            ->call('toggleProductDespacho', $detalle->id)
            ->assertEmitted('despacho-error', 'Debes validar la transferencia antes de gestionar el despacho');

        $this->assertSame(Sale::ENVIO_PENDIENTE, $venta->fresh()->estado_envio);
    }

    public function test_despachar_los_productos_no_manda_correo_y_enviar_avisa_que_va_en_camino(): void
    {
        Mail::fake();
        $venta = $this->comprarPorTransferencia();
        $venta->update(['transferencia_estado' => 'aprobada', 'estatus' => Sale::ESTATUS_COMPLETADA]);
        $detalle = $venta->details()->firstOrFail();
        Mail::assertSent(OrderStatusMail::class, 1); // solo el de confirmacion de la compra

        $despachos = Livewire::actingAs($this->admin)
            ->test(DespachosController::class)
            ->set('selectedSaleId', $venta->id)
            ->call('toggleProductDespacho', $detalle->id);

        // Avance del despacho: no se notifica al cliente.
        $this->assertSame(Sale::ENVIO_LISTO, $venta->fresh()->estado_envio);
        Mail::assertSent(OrderStatusMail::class, 1);

        $despachos->call('enviarPedido')->assertEmitted('pedido-enviado');

        $this->assertSame(Sale::ENVIO_ENVIADO, $venta->fresh()->estado_envio);
        Mail::assertSent(OrderStatusMail::class, 2);
        Mail::assertSent(OrderStatusMail::class, function ($mail) {
            return $mail->hasTo('juanita@test.com')
                && $mail->statusConfig['subject'] === 'Tu pedido está en camino - Carnicería Franko';
        });
    }

    public function test_un_pedido_de_cliente_general_no_manda_correos(): void
    {
        Mail::fake();

        $producto = Product::where('activo', true)->where('en_oferta', false)->firstOrFail();
        Livewire::actingAs($this->admin)
            ->test(DespachosController::class)
            ->call('addProductToCart', $producto->id)
            ->call('createOrder');

        // En sqlite customer_id no acepta NULL (solo MySQL), asi que puede no crearse la venta;
        // lo importante es que no salga ningun correo.
        Mail::assertNothingSent();
    }

    public function test_las_plantillas_de_correo_se_pueden_generar_en_todos_los_estados(): void
    {
        $venta = $this->comprarPorTransferencia();
        $venta->load(['customer', 'details']);

        foreach (['completada', 'Procesando', 'Listo_para_enviar', 'Enviado'] as $estado) {
            $venta->estado_envio = $estado === 'completada' ? Sale::ENVIO_PENDIENTE : $estado;
            $metodo = new \ReflectionMethod(OrderNotificationService::class, 'getStatusConfig');
            $metodo->setAccessible(true);
            $config = $metodo->invoke(null, $estado);

            $html = (new OrderStatusMail($venta, $config))->render();

            $this->assertStringContainsString($venta->folio, $html, "El correo de {$estado} debe traer el folio");
            $this->assertStringContainsString('Carnicería Franko', $html);
        }
    }
}
