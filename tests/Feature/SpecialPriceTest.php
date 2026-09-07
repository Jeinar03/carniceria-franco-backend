<?php

namespace Tests\Feature;

use App\Http\Livewire\Despachos\DespachosController;
use App\Models\CustomerProductPrice;
use App\Models\Customers;
use App\Models\Product;
use App\Models\User;
use App\Services\PricingService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Precio especial por cliente y producto.
 * Ver: Carnicería Franco/Spec — Precio especial por cliente.md
 */
class SpecialPriceTest extends TestCase
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
    }

    private function cliente(string $correo): Customers
    {
        return Customers::create([
            'nombre' => 'C',
            'apellido' => 'X',
            'correo' => $correo,
            'password' => Hash::make('cliente123'),
            'estatus' => 'activo',
        ]);
    }

    private function productoNormal(): Product
    {
        return Product::where('en_oferta', false)->firstOrFail();
    }

    private function precioEspecial(Customers $c, Product $p, float $precio, bool $activo = true): CustomerProductPrice
    {
        return CustomerProductPrice::create([
            'customer_id' => $c->id,
            'product_id' => $p->id,
            'precio_especial' => $precio,
            'activo' => $activo,
        ]);
    }

    /** El PricingService es el punto único de resolución. */
    public function test_pricing_service_devuelve_el_precio_especial_activo(): void
    {
        $cliente = $this->cliente('a@test.com');
        $producto = $this->productoNormal();
        $this->precioEspecial($cliente, $producto, 12.34);

        $svc = app(PricingService::class);

        $this->assertSame(
            ['precio_unitario' => 12.34, 'precio_oferta' => null],
            $svc->precioParaCliente($producto, $cliente->id)
        );

        // Sin cliente => precio de lista
        $this->assertSame(
            (float) $producto->precio,
            $svc->precioParaCliente($producto, null)['precio_unitario']
        );
    }

    public function test_precio_especial_aplica_en_venta_online(): void
    {
        $cliente = $this->cliente('a@test.com');
        $producto = $this->productoNormal();
        $this->precioEspecial($cliente, $producto, 1.00);

        Sanctum::actingAs($cliente, ['cliente']);

        $this->postJson('/api/v1/ventas', [
            'metodo_pago' => 'efectivo',
            'productos' => [['product_id' => $producto->id, 'cantidad' => 2]],
        ])->assertStatus(201);

        $venta = DB::table('sales')->latest('id')->first();
        $detalle = DB::table('sale_details')->where('sale_id', $venta->id)->first();

        $this->assertEquals(1.00, (float) $detalle->precio_unitario);
        $this->assertEquals(2.00, (float) $venta->subtotal);
        $this->assertEquals(2.00, (float) $venta->total);
    }

    public function test_sin_precio_especial_usa_el_precio_normal(): void
    {
        $cliente = $this->cliente('a@test.com');
        $producto = $this->productoNormal();

        Sanctum::actingAs($cliente, ['cliente']);

        $this->postJson('/api/v1/ventas', [
            'metodo_pago' => 'efectivo',
            'productos' => [['product_id' => $producto->id, 'cantidad' => 2]],
        ])->assertStatus(201);

        $venta = DB::table('sales')->latest('id')->first();
        $this->assertEquals(round($producto->precio * 2, 2), (float) $venta->total);
    }

    public function test_precio_especial_gana_sobre_la_oferta(): void
    {
        $cliente = $this->cliente('a@test.com');
        $producto = $this->productoNormal();
        $producto->update(['en_oferta' => true, 'precio_oferta' => 0.50]); // oferta más barata
        $this->precioEspecial($cliente, $producto, 1.00);                  // especial más caro: gana igual

        Sanctum::actingAs($cliente, ['cliente']);

        $this->postJson('/api/v1/ventas', [
            'metodo_pago' => 'efectivo',
            'productos' => [['product_id' => $producto->id, 'cantidad' => 3]],
        ])->assertStatus(201);

        $venta = DB::table('sales')->latest('id')->first();
        $this->assertEquals(3.00, (float) $venta->total, 'Debe cobrar el especial (1.00), no la oferta (0.50)');
    }

    public function test_precio_especial_inactivo_se_ignora(): void
    {
        $cliente = $this->cliente('a@test.com');
        $producto = $this->productoNormal();
        $this->precioEspecial($cliente, $producto, 1.00, activo: false);

        Sanctum::actingAs($cliente, ['cliente']);

        $this->postJson('/api/v1/ventas', [
            'metodo_pago' => 'efectivo',
            'productos' => [['product_id' => $producto->id, 'cantidad' => 1]],
        ])->assertStatus(201);

        $venta = DB::table('sales')->latest('id')->first();
        $this->assertEquals(round((float) $producto->precio, 2), (float) $venta->total);
    }

    public function test_precio_especial_de_un_cliente_no_afecta_a_otro(): void
    {
        $juanita = $this->cliente('juanita@test.com');
        $otro = $this->cliente('otro@test.com');
        $producto = $this->productoNormal();
        $this->precioEspecial($juanita, $producto, 1.00);

        Sanctum::actingAs($otro, ['cliente']);

        $this->postJson('/api/v1/ventas', [
            'metodo_pago' => 'efectivo',
            'productos' => [['product_id' => $producto->id, 'cantidad' => 1]],
        ])->assertStatus(201);

        $venta = DB::table('sales')->latest('id')->first();
        $this->assertEquals(round((float) $producto->precio, 2), (float) $venta->total);
    }

    public function test_venta_por_monto_usa_el_precio_especial(): void
    {
        $cliente = $this->cliente('a@test.com');
        $producto = Product::where('unidad_venta', 'kilogramo')->where('en_oferta', false)->firstOrFail();
        $this->precioEspecial($cliente, $producto, 100.00); // $100/kg especial

        Sanctum::actingAs($cliente, ['cliente']);

        // $250 de un producto a $100/kg => 2.5 kg, subtotal 250
        $this->postJson('/api/v1/ventas', [
            'metodo_pago' => 'efectivo',
            'productos' => [['product_id' => $producto->id, 'cantidad' => 1, 'monto_pesos' => 250]],
        ])->assertStatus(201);

        $venta = DB::table('sales')->latest('id')->first();
        $detalle = DB::table('sale_details')->where('sale_id', $venta->id)->first();

        $this->assertEquals(2.5, (float) $detalle->cantidad);
        $this->assertEquals(250.00, (float) $venta->total);
        $this->assertEquals(100.00, (float) $detalle->precio_unitario);
    }

    public function test_no_se_puede_duplicar_el_par_cliente_producto(): void
    {
        $cliente = $this->cliente('a@test.com');
        $producto = $this->productoNormal();
        $this->precioEspecial($cliente, $producto, 10);

        $this->expectException(\Illuminate\Database\QueryException::class);
        $this->precioEspecial($cliente, $producto, 20);
    }

    public function test_borrar_el_cliente_borra_sus_precios_especiales(): void
    {
        $cliente = $this->cliente('a@test.com');
        $producto = $this->productoNormal();
        $this->precioEspecial($cliente, $producto, 10);

        $cliente->delete();

        $this->assertDatabaseCount('customer_product_prices', 0);
    }

    /** Mostrador: al crear la orden para un cliente con precio especial, la línea se cobra a ese precio. */
    public function test_mostrador_aplica_el_precio_especial(): void
    {
        $admin = User::create([
            'name' => 'Admin',
            'email' => 'admin@test.com',
            'password' => Hash::make('secret'),
        ]);
        $cliente = $this->cliente('juanita@test.com');
        $producto = $this->productoNormal();
        $this->precioEspecial($cliente, $producto, 1.00);

        Livewire::actingAs($admin)
            ->test(DespachosController::class)
            ->set('createCustomerId', (string) $cliente->id)
            ->call('addProductToCart', $producto->id)
            ->call('createOrder')
            ->assertEmitted('pedido-creado');

        $venta = DB::table('sales')->latest('id')->first();
        $this->assertEquals($cliente->id, $venta->customer_id);
        $this->assertEquals(1.00, (float) $venta->total);
    }
}
