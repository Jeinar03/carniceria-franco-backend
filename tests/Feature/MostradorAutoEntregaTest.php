<?php

namespace Tests\Feature;

use App\Http\Livewire\Despachos\DespachosController;
use App\Models\Customers;
use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Venta de mostrador (Cliente General) se entrega sola; el resto sigue el flujo de Despachos.
 */
class MostradorAutoEntregaTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // La migración que vuelve nullable sales.customer_id (Cliente General) solo corre en
        // MySQL, así que estas pruebas usan una base MySQL desechable en vez de sqlite.
        config()->set('database.default', 'mysql');
        config()->set('database.connections.mysql.database', 'carniceria_test');

        try {
            $admin = new \PDO(
                sprintf('mysql:host=%s;port=%s', config('database.connections.mysql.host'), config('database.connections.mysql.port')),
                config('database.connections.mysql.username'),
                (string) config('database.connections.mysql.password')
            );
            $admin->exec('CREATE DATABASE IF NOT EXISTS carniceria_test CHARACTER SET utf8mb4');
        } catch (\Throwable $e) {
            $this->markTestSkipped('MySQL no disponible: ' . $e->getMessage());
        }

        DB::purge('mysql');
        DB::reconnect('mysql');

        Artisan::call('migrate:fresh', ['--force' => true, '--no-interaction' => true]);
        Artisan::call('db:seed', ['--force' => true, '--no-interaction' => true]);
    }

    private function admin(): User
    {
        $admin = User::create([
            'name' => 'Admin',
            'email' => 'admin@test.com',
            'password' => Hash::make('secret'),
        ]);
        $admin->assignRole('Admin');

        return $admin;
    }

    private function cliente(): Customers
    {
        return Customers::create([
            'nombre' => 'Mayorista',
            'apellido' => 'X',
            'correo' => 'mayorista@test.com',
            'password' => Hash::make('cliente123'),
            'estatus' => 'activo',
        ]);
    }

    private function producto(): Product
    {
        return Product::where('en_oferta', false)->firstOrFail();
    }

    private function crearOrden(User $admin, array $props = [])
    {
        $c = Livewire::actingAs($admin)->test(DespachosController::class);
        foreach ($props as $k => $v) {
            $c->set($k, $v);
        }

        return $c->call('addProductToCart', $this->producto()->id)->call('createOrder');
    }

    private function ultimaVenta(): Sale
    {
        return Sale::latest('id')->firstOrFail();
    }

    public function test_cliente_general_en_efectivo_sale_entregado(): void
    {
        $this->crearOrden($this->admin())->assertEmitted('pedido-creado');

        $venta = $this->ultimaVenta();
        $this->assertNull($venta->customer_id);
        $this->assertSame(Sale::ENVIO_ENTREGADO, $venta->estado_envio);
        $this->assertNotNull($venta->entregado_at);
        $this->assertSame(0, $venta->details()->where('estado_despacho', '!=', 1)->count());
    }

    public function test_entregada_no_aparece_en_la_cola_de_despachos(): void
    {
        $admin = $this->admin();
        $this->crearOrden($admin);
        $folio = $this->ultimaVenta()->folio;

        Livewire::actingAs($admin)->test(DespachosController::class)->assertDontSee($folio);
    }

    public function test_cliente_registrado_sigue_pendiente(): void
    {
        $cliente = $this->cliente();

        $this->crearOrden($this->admin(), ['createCustomerId' => (string) $cliente->id])
            ->assertEmitted('pedido-creado');

        $venta = $this->ultimaVenta();
        $this->assertSame($cliente->id, $venta->customer_id);
        $this->assertSame(Sale::ENVIO_PENDIENTE, $venta->estado_envio);
        $this->assertNull($venta->entregado_at);
        $this->assertSame(0, $venta->details()->where('estado_despacho', 1)->count());
    }

    public function test_cliente_general_sin_marcar_checkbox_queda_pendiente(): void
    {
        $this->crearOrden($this->admin(), ['createEntregadoMostrador' => false]);

        $this->assertSame(Sale::ENVIO_PENDIENTE, $this->ultimaVenta()->estado_envio);
    }

    public function test_pedido_programado_a_futuro_no_se_auto_entrega(): void
    {
        $this->crearOrden($this->admin(), ['createFechaEntrega' => now()->addDays(2)->toDateString()]);

        $this->assertSame(Sale::ENVIO_PENDIENTE, $this->ultimaVenta()->estado_envio);
    }

    public function test_transferencia_de_mostrador_queda_pendiente_y_se_entrega_al_confirmar(): void
    {
        $admin = $this->admin();
        $this->crearOrden($admin, ['createMetodoPago' => 'transferencia']);

        $venta = $this->ultimaVenta();
        $this->assertSame(Sale::ENVIO_PENDIENTE, $venta->estado_envio);
        $this->assertSame(Sale::ESTATUS_PENDIENTE, $venta->estatus);

        Livewire::actingAs($admin)
            ->test(DespachosController::class)
            ->call('openTransferValidationModal', $venta->id)
            ->call('confirmarTransferenciaMostrador');

        $venta->refresh();
        $this->assertSame(Sale::ESTATUS_COMPLETADA, $venta->estatus);
        $this->assertSame(Sale::ENVIO_ENTREGADO, $venta->estado_envio);
        $this->assertSame(0, $venta->details()->where('estado_despacho', '!=', 1)->count());
    }
}
