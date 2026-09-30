<?php

namespace Tests\Feature;

use App\Models\Customers;
use App\Models\Product;
use App\Models\Sale;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * El cliente no decide su propio descuento, tipo, estatus ni forma de pago.
 * Ver: Carnicería Franco/Spec - Seguridad de cliente y ventas en la API.md
 */
class ApiClienteSeguridadTest extends TestCase
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

    private function cliente(string $correo, array $extra = []): Customers
    {
        $cliente = new Customers(array_merge([
            'nombre' => 'C',
            'apellido' => 'X',
            'correo' => $correo,
            'password' => Hash::make('cliente123'),
            'estatus' => 'activo',
        ], $extra));
        $cliente->save();

        return $cliente;
    }

    private function producto(): Product
    {
        return Product::where('en_oferta', false)->firstOrFail();
    }

    private function pedido(array $extra = []): array
    {
        return array_merge([
            'metodo_pago' => 'transferencia',
            'productos' => [['product_id' => $this->producto()->id, 'cantidad' => 2]],
        ], $extra);
    }

    /** Criterio 1 */
    public function test_el_registro_publico_ignora_tipo_descuento_limite_y_estatus(): void
    {
        $this->postJson('/api/v1/clientes/registro', [
            'nombre' => 'Listo',
            'apellido' => 'Pillo',
            'correo' => 'listo@test.com',
            'password' => 'secreto123',
            'tipo_cliente' => 'mayorista',
            'descuento_preferencial' => 100,
            'limite_credito' => 99999,
            'estatus' => 'inactivo',
        ])->assertStatus(201);

        $cliente = Customers::where('correo', 'listo@test.com')->firstOrFail();
        $this->assertSame('minorista', $cliente->tipo_cliente);
        $this->assertEquals(0, (float) $cliente->descuento_preferencial);
        $this->assertEquals(0, (float) $cliente->limite_credito);
        $this->assertSame('activo', $cliente->estatus);
    }

    /** Criterio 2 */
    public function test_el_cliente_no_puede_subirse_a_mayorista_desde_su_perfil(): void
    {
        $cliente = $this->cliente('a@test.com');
        Sanctum::actingAs($cliente, ['cliente']);

        $this->putJson("/api/v1/clientes/{$cliente->id}", [
            'telefono' => '7531234567',
            'tipo_cliente' => 'mayorista',
            'descuento_preferencial' => 50,
            'limite_credito' => 50000,
        ])->assertStatus(200);

        $cliente->refresh();
        $this->assertSame('7531234567', $cliente->telefono);
        $this->assertSame('minorista', $cliente->tipo_cliente);
        $this->assertEquals(0, (float) $cliente->descuento_preferencial);
        $this->assertEquals(0, (float) $cliente->limite_credito);
    }

    /** Criterio 3 */
    public function test_un_minorista_no_puede_darse_descuento_en_la_venta(): void
    {
        $cliente = $this->cliente('a@test.com');
        Sanctum::actingAs($cliente, ['cliente']);

        $this->postJson('/api/v1/ventas', $this->pedido(['descuento' => 99999]))->assertStatus(201);

        $venta = Sale::latest('id')->firstOrFail();
        $this->assertEquals(0, (float) $venta->descuento);
        $this->assertEquals((float) $venta->subtotal, (float) $venta->total);
    }

    /** Criterio 4 */
    public function test_el_descuento_del_mayorista_lo_calcula_el_servidor(): void
    {
        $cliente = $this->cliente('m@test.com', [
            'tipo_cliente' => 'mayorista',
            'descuento_preferencial' => 10,
        ]);
        Sanctum::actingAs($cliente, ['cliente']);

        // El body manda 10 (como hace el front, un porcentaje) y otro valor no debe importar.
        $this->postJson('/api/v1/ventas', $this->pedido(['descuento' => 10]))->assertStatus(201);

        $venta = Sale::latest('id')->firstOrFail();
        $this->assertGreaterThan(0, (float) $venta->subtotal);
        $this->assertEqualsWithDelta(round((float) $venta->subtotal * 0.10, 2), (float) $venta->descuento, 0.001);
        $this->assertEqualsWithDelta((float) $venta->subtotal - (float) $venta->descuento, (float) $venta->total, 0.001);
    }

    /** Criterio 5 */
    public function test_la_api_publica_solo_acepta_transferencia(): void
    {
        $cliente = $this->cliente('a@test.com');
        Sanctum::actingAs($cliente, ['cliente']);
        $stockAntes = (float) $this->producto()->stock;

        foreach (['efectivo', 'tarjeta', 'credito', 'mercado_pago'] as $metodo) {
            $this->postJson('/api/v1/ventas', $this->pedido(['metodo_pago' => $metodo]))
                ->assertStatus(422);
        }

        $this->assertSame(0, Sale::count());
        $this->assertEquals($stockAntes, (float) $this->producto()->fresh()->stock);
    }

    /** Criterio 6 */
    public function test_los_datos_de_pago_de_mercado_pago_del_cliente_se_ignoran(): void
    {
        $cliente = $this->cliente('a@test.com');
        Sanctum::actingAs($cliente, ['cliente']);

        $this->postJson('/api/v1/ventas', $this->pedido([
            'mercadopago_status' => 'approved',
            'mercadopago_payment_id' => '123456',
        ]))->assertStatus(201);

        $venta = Sale::latest('id')->firstOrFail();
        $this->assertSame('pendiente', $venta->estatus);
        $this->assertNull($venta->mercadopago_status);
        $this->assertNull($venta->mercadopago_payment_id);
    }
}
