<?php

namespace Tests\Feature;

use App\Models\Customers;
use App\Models\Sale;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * R3, R4 — un cliente autenticado nunca ve ni toca recursos de otro cliente,
 * ni siquiera pasando el id ajeno en la URL o en el body.
 */
class ApiOwnershipTest extends TestCase
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

    public function test_un_cliente_no_puede_leer_el_historial_de_otro(): void
    {
        $a = $this->cliente('a@test.com');
        $b = $this->cliente('b@test.com');
        Sanctum::actingAs($a, ['cliente']);

        $this->getJson("/api/v1/ventas/cliente/{$b->id}")->assertStatus(403);
        $this->getJson("/api/v1/ventas/cliente/{$b->id}/estadisticas")->assertStatus(403);
        $this->getJson("/api/v1/ventas/cliente/{$b->id}/recientes")->assertStatus(403);
        $this->getJson("/api/v1/ventas/cliente/{$b->id}/recomendaciones")->assertStatus(403);

        // Los suyos sí
        $this->getJson("/api/v1/ventas/cliente/{$a->id}")->assertOk();
    }

    public function test_un_cliente_no_puede_editar_a_otro(): void
    {
        $a = $this->cliente('a@test.com');
        $b = $this->cliente('b@test.com');
        Sanctum::actingAs($a, ['cliente']);

        $this->putJson("/api/v1/clientes/{$b->id}", ['nombre' => 'Hackeado'])->assertStatus(403);
        $this->assertDatabaseHas('customers', ['id' => $b->id, 'nombre' => 'C']);

        $this->putJson("/api/v1/clientes/{$a->id}", ['nombre' => 'Nuevo'])->assertOk();
    }

    public function test_un_cliente_no_puede_ver_el_detalle_de_una_venta_ajena(): void
    {
        $a = $this->cliente('a@test.com');
        $b = $this->cliente('b@test.com');
        $ventaDeB = Sale::create([
            'customer_id' => $b->id,
            'metodo_pago' => 'efectivo',
            'total' => 100,
            'estatus' => 'completada',
            'fecha_venta' => now(),
        ]);

        Sanctum::actingAs($a, ['cliente']);

        $this->getJson("/api/v1/ventas/{$ventaDeB->id}")->assertStatus(403);
        $this->putJson("/api/v1/ventas/{$ventaDeB->id}/cancelar")->assertStatus(403);
        $this->getJson("/api/v1/ventas/{$ventaDeB->id}/evidencia-transferencia")->assertStatus(403);

        $this->assertDatabaseHas('sales', ['id' => $ventaDeB->id, 'estatus' => 'completada']);
    }

    public function test_getData_ignora_el_clienteId_de_la_query(): void
    {
        $a = $this->cliente('a@test.com');
        $b = $this->cliente('b@test.com');
        Sanctum::actingAs($a, ['cliente']);

        $this->getJson("/api/v1/usuarios/customer-data?clienteId={$b->id}")
            ->assertOk()
            ->assertJsonPath('data.correo', 'a@test.com');
    }

    public function test_crear_venta_ignora_el_customer_id_del_body(): void
    {
        $a = $this->cliente('a@test.com');
        $b = $this->cliente('b@test.com');

        // Producto con stock real (el seeder le da entrada de inventario)
        $productoId = DB::table('products')->value('id');

        Sanctum::actingAs($a, ['cliente']);

        $res = $this->postJson('/api/v1/ventas', [
            'customer_id' => $b->id, // <- intento de spoofing
            'metodo_pago' => 'efectivo',
            'productos' => [
                ['product_id' => $productoId, 'cantidad' => 1],
            ],
        ]);

        $res->assertStatus(201);
        $this->assertDatabaseHas('sales', ['customer_id' => $a->id]);
        $this->assertDatabaseMissing('sales', ['customer_id' => $b->id]);
    }

    public function test_la_venta_no_cobra_iva(): void
    {
        $a = $this->cliente('a@test.com');
        $producto = DB::table('products')->first();

        Sanctum::actingAs($a, ['cliente']);

        $res = $this->postJson('/api/v1/ventas', [
            'metodo_pago' => 'efectivo',
            'productos' => [
                ['product_id' => $producto->id, 'cantidad' => 2],
            ],
        ]);

        $res->assertStatus(201);

        $venta = DB::table('sales')->latest('id')->first();
        $esperado = round($producto->precio * 2, 2);

        $this->assertEquals(0, (float) $venta->impuestos, 'La venta no debe llevar IVA');
        $this->assertEquals($esperado, (float) $venta->subtotal);
        $this->assertEquals($esperado, (float) $venta->total, 'El total = subtotal (sin impuestos)');
    }
}
