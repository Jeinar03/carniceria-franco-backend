<?php

namespace Tests\Feature;

use App\Models\Customers;
use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Hallazgo S-14: la API del cliente no debe entregar notas del personal
 * ni ids de empleados. Ver vault: Carniceria Franco/Auditorias consolidadas.
 */
class ApiSinDatosInternosTest extends TestCase
{
    private const CAMPOS_INTERNOS_VENTA = ['usuario_id', 'entregado_por', 'transferencia_validada_por', 'notas_internas'];

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

    private function cliente(): Customers
    {
        return Customers::create([
            'nombre' => 'C',
            'apellido' => 'X',
            'correo' => 'cliente@test.com',
            'password' => Hash::make('cliente123'),
            'estatus' => 'activo',
            'notas' => 'Observacion del personal: paga tarde',
        ]);
    }

    private function ventaConDatosInternos(Customers $cliente): Sale
    {
        $empleado = User::firstOrFail();
        $sale = Sale::create([
            'customer_id' => $cliente->id,
            'fecha_venta' => now(),
            'subtotal' => 100,
            'total' => 100,
            'metodo_pago' => 'transferencia',
            'transferencia_estado' => 'aprobada',
            'transferencia_validada_por' => $empleado->id,
            'estatus' => Sale::ESTATUS_COMPLETADA,
            'notas' => 'Sin cebolla por favor',
            'notas_internas' => 'Cliente dificil, cobrar antes',
            'usuario_id' => $empleado->id,
            'entregado_por' => $empleado->id,
        ]);

        $producto = Product::firstOrFail();
        DB::table('sale_details')->insert([
            'sale_id' => $sale->id,
            'product_id' => $producto->id,
            'cantidad' => 1,
            'precio_unitario' => 100,
            'subtotal' => 100,
            'total' => 100,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $sale;
    }

    private function assertSinCamposInternos(string $json): void
    {
        foreach (self::CAMPOS_INTERNOS_VENTA as $campo) {
            $this->assertStringNotContainsString('"' . $campo . '"', $json, "La API no debe entregar {$campo}");
        }
        $this->assertStringNotContainsString('Cliente dificil', $json);
        $this->assertStringNotContainsString('paga tarde', $json);
    }

    public function test_detalle_de_venta_no_entrega_datos_internos(): void
    {
        $cliente = $this->cliente();
        $sale = $this->ventaConDatosInternos($cliente);
        Sanctum::actingAs($cliente, ['cliente']);

        $res = $this->getJson("/api/v1/ventas/{$sale->id}")->assertOk();

        $this->assertSinCamposInternos($res->getContent());
        $this->assertStringContainsString('Sin cebolla por favor', $res->getContent(), 'La nota del cliente si se le muestra');
    }

    public function test_historial_de_compras_no_entrega_datos_internos(): void
    {
        $cliente = $this->cliente();
        $this->ventaConDatosInternos($cliente);
        Sanctum::actingAs($cliente, ['cliente']);

        $res = $this->getJson("/api/v1/ventas/cliente/{$cliente->id}")->assertOk();

        $this->assertSinCamposInternos($res->getContent());
    }

    public function test_datos_del_cliente_no_entregan_observaciones_del_personal(): void
    {
        $cliente = $this->cliente();
        Sanctum::actingAs($cliente, ['cliente']);

        $res = $this->getJson('/api/v1/clientes/data')->assertOk();

        $this->assertStringNotContainsString('"notas"', $res->getContent());
        $this->assertStringNotContainsString('paga tarde', $res->getContent());
    }

    public function test_cliente_no_puede_sobrescribir_las_observaciones_del_personal(): void
    {
        $cliente = $this->cliente();
        Sanctum::actingAs($cliente, ['cliente']);

        $this->putJson("/api/v1/clientes/{$cliente->id}", ['nombre' => 'Nuevo', 'notas' => 'borrado'])->assertOk();

        $cliente->refresh();
        $this->assertSame('Nuevo', $cliente->nombre);
        $this->assertSame('Observacion del personal: paga tarde', $cliente->notas);
    }

    public function test_registro_ignora_las_notas(): void
    {
        $this->postJson('/api/v1/clientes/registro', [
            'nombre' => 'Ana',
            'apellido' => 'Lopez',
            'correo' => 'ana@test.com',
            'password' => 'secreto123',
            'password_confirmation' => 'secreto123',
            'telefono' => '5512345678',
            'notas' => 'me pongo yo la nota',
        ])->assertSuccessful();

        $this->assertNull(Customers::where('correo', 'ana@test.com')->value('notas'));
    }
}
