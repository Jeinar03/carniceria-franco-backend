<?php

namespace Tests\Feature;

use App\Http\Middleware\TrustProxies;
use App\Models\Customers;
use App\Models\Product;
use App\Models\Sale;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Auditorias consolidadas, fase A: TrustProxies sin host reenviado, comprobante aprobado protegido,
 * contrasena actual para cambiar correo o contrasena y errores internos fuera de la respuesta.
 */
class AuditoriaFaseATest extends TestCase
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

    private function cliente(): Customers
    {
        return Customers::create([
            'nombre' => 'C', 'apellido' => 'X', 'correo' => 'fasea@test.com',
            'password' => Hash::make('clave-actual-1'), 'estatus' => 'activo',
        ]);
    }

    public function test_el_host_reenviado_no_cambia_el_host_de_la_peticion(): void
    {
        $request = Request::create('http://admin.test/x', 'GET', [], [], [], [
            'HTTP_X_FORWARDED_HOST' => 'sitio-ajeno.test',
            'REMOTE_ADDR' => '10.0.0.1',
        ]);

        (new TrustProxies($this->app['config']))->handle($request, fn ($r) => response('ok'));

        $this->assertSame('admin.test', $request->getHost());
    }

    public function test_no_se_puede_resubir_el_comprobante_de_una_transferencia_ya_aprobada(): void
    {
        Storage::fake('public');
        $cliente = $this->cliente();
        $sale = Sale::create([
            'customer_id' => $cliente->id, 'folio' => 'FA-1', 'fecha_venta' => now(),
            'metodo_pago' => 'transferencia', 'estatus' => 'completada', 'estado_envio' => 'Pendiente',
            'transferencia_estado' => 'aprobada', 'transferencia_evidencia_path' => 'transferencias/original.jpg',
            'subtotal' => 100, 'total' => 100,
        ]);
        Storage::disk('public')->put('transferencias/original.jpg', 'x');

        Sanctum::actingAs($cliente, ['cliente']);
        $this->postJson("/api/v1/ventas/{$sale->id}/evidencia-transferencia", [
            'evidencia' => UploadedFile::fake()->image('otro.jpg'),
        ])->assertStatus(400);

        $sale->refresh();
        $this->assertSame('aprobada', $sale->transferencia_estado);
        $this->assertSame('transferencias/original.jpg', $sale->transferencia_evidencia_path);
        Storage::disk('public')->assertExists('transferencias/original.jpg');
    }

    public function test_cambiar_el_correo_o_la_contrasena_exige_la_contrasena_actual(): void
    {
        $cliente = $this->cliente();
        Sanctum::actingAs($cliente, ['cliente']);

        $this->putJson("/api/v1/clientes/{$cliente->id}", ['correo' => 'nuevo@test.com'])->assertStatus(422);
        $this->putJson("/api/v1/clientes/{$cliente->id}", ['password' => 'otra-clave-9'])->assertStatus(422);
        $this->putJson("/api/v1/clientes/{$cliente->id}", ['password' => 'otra-clave-9', 'password_actual' => 'mal'])->assertStatus(422);
        $this->assertSame('fasea@test.com', $cliente->fresh()->correo);

        $this->putJson("/api/v1/clientes/{$cliente->id}", ['correo' => 'nuevo@test.com', 'password_actual' => 'clave-actual-1'])->assertOk();
        $this->assertSame('nuevo@test.com', $cliente->fresh()->correo);
    }

    public function test_cambiar_datos_normales_no_pide_contrasena_actual(): void
    {
        $cliente = $this->cliente();
        Sanctum::actingAs($cliente, ['cliente']);

        $this->putJson("/api/v1/clientes/{$cliente->id}", ['nombre' => 'Nuevo Nombre'])->assertOk();
        $this->assertSame('Nuevo Nombre', $cliente->fresh()->nombre);
    }

    public function test_un_error_interno_no_se_muestra_al_cliente(): void
    {
        $cliente = $this->cliente();
        Sanctum::actingAs($cliente, ['cliente']);
        // Se fuerza un fallo de base de datos: el historial consulta la tabla de ventas.
        DB::statement('DROP TABLE sales');

        $res = $this->getJson("/api/v1/ventas/cliente/{$cliente->id}")->assertStatus(500);

        $this->assertStringNotContainsString('sales', (string) $res->json('message'));
        $this->assertStringNotContainsString('SQLSTATE', (string) $res->json('message'));
    }
}
