<?php

namespace Tests\Feature;

use App\Http\Controllers\MercadoPagoController;
use App\Models\Customers;
use App\Models\Product;
use App\Models\Sale;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use MercadoPago\Preference;
use MercadoPago\SDK;
use Tests\TestCase;

/** Mercado Pago simulado: no sale a la red. */
class MercadoPagoParaLegales extends MercadoPagoController
{
    protected function inicializarSdk(string $accessToken): void
    {
        SDK::initialize();
    }

    protected function guardarPreferencia(Preference $preference): bool
    {
        return true;
    }
}

/**
 * Aceptacion de los textos legales: registro, pedido por transferencia y por Mercado Pago.
 * Spec: "Spec - Paginas legales y consentimiento de la tienda" en el vault, seccion 8.
 */
class LegalesTest extends TestCase
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

        config()->set('legal.version', 'v-vigente');
        config()->set('mercadopago.access_token', 'APP_USR-token-de-prueba-0000');
        config()->set('mercadopago.frontend_url', 'https://tienda.test');
        config()->set('mercadopago.notification_url', 'https://api.test/api/v1/mercadopago/webhook');
        $this->app->bind(MercadoPagoController::class, fn () => new MercadoPagoParaLegales());
    }

    private function registro(array $extra = [])
    {
        return $this->postJson('/api/v1/clientes/registro', array_merge([
            'nombre' => 'Ana',
            'apellido' => 'Lopez',
            'correo' => 'ana@test.com',
            'password' => 'secreto123',
            'telefono' => '7531112233',
        ], $extra));
    }

    /** Cliente anterior a este cambio: nunca ha aceptado nada. */
    private function clienteViejo(string $correo = 'viejo@test.com'): Customers
    {
        return Customers::create([
            'nombre' => 'Doña',
            'apellido' => 'Juanita',
            'correo' => $correo,
            'password' => Hash::make('cliente123'),
            'telefono' => '7531234567',
            'estatus' => 'activo',
        ]);
    }

    private function clienteAlDia(): Customers
    {
        return $this->clienteConLegales([
            'nombre' => 'Al',
            'apellido' => 'Dia',
            'correo' => 'aldia@test.com',
            'password' => Hash::make('cliente123'),
            'telefono' => '7531234567',
            'estatus' => 'activo',
        ]);
    }

    private function pedido(string $metodo, array $extra = []): array
    {
        return array_merge([
            'metodo_pago' => $metodo,
            'tipo_entrega' => 'recoger',
            'productos' => [[
                'product_id' => Product::where('activo', true)->where('en_oferta', false)->firstOrFail()->id,
                'cantidad' => 1,
            ]],
        ], $extra);
    }

    private function comprar(string $metodo, Customers $cliente, array $extra = [])
    {
        Sanctum::actingAs($cliente, ['cliente']);
        $ruta = $metodo === 'transferencia' ? '/api/v1/ventas' : '/api/v1/mercadopago/create-preference';

        return $this->postJson($ruta, $this->pedido($metodo, $extra));
    }

    // ---------- Registro ----------

    public function test_registro_sin_la_casilla_da_422_y_no_crea_la_cuenta(): void
    {
        $this->registro()->assertStatus(422)->assertJsonValidationErrors('acepta_legales', 'data');
        $this->registro(['acepta_legales' => false])->assertStatus(422);

        $this->assertDatabaseMissing('customers', ['correo' => 'ana@test.com']);
    }

    public function test_registro_con_la_casilla_guarda_fecha_y_version_del_servidor(): void
    {
        $this->registro(['acepta_legales' => true])->assertStatus(201);

        $cliente = Customers::where('correo', 'ana@test.com')->firstOrFail();
        $this->assertSame('v-vigente', $cliente->legales_version);
        $this->assertNotNull($cliente->legales_aceptados_at);
    }

    public function test_el_cliente_no_puede_fijar_la_fecha_ni_la_version_al_registrarse(): void
    {
        $this->registro([
            'acepta_legales' => true,
            'legales_version' => 'inventada',
            'legales_aceptados_at' => '2000-01-01 00:00:00',
        ])->assertStatus(201);

        $cliente = Customers::where('correo', 'ana@test.com')->firstOrFail();
        $this->assertSame('v-vigente', $cliente->legales_version);
        $this->assertTrue($cliente->legales_aceptados_at->isToday());
    }

    public function test_el_cliente_no_puede_fijar_la_fecha_ni_la_version_desde_su_perfil(): void
    {
        $cliente = $this->clienteViejo();
        Sanctum::actingAs($cliente, ['cliente']);

        $this->putJson("/api/v1/clientes/{$cliente->id}", [
            'telefono' => '7531234567',
            'legales_version' => 'v-vigente',
            'legales_aceptados_at' => '2026-11-04 00:00:00',
            'acepta_legales' => true,
        ])->assertStatus(200);

        $cliente->refresh();
        $this->assertNull($cliente->legales_version);
        $this->assertNull($cliente->legales_aceptados_at);
        $this->getJson('/api/v1/clientes/data')->assertJsonPath('data.legales_al_dia', false);
    }

    // ---------- Pedido por transferencia ----------

    public function test_transferencia_sin_aceptacion_vigente_da_422_y_no_crea_la_venta(): void
    {
        $this->comprar('transferencia', $this->clienteViejo())
            ->assertStatus(422)
            ->assertJsonValidationErrors('acepta_legales', 'data');

        $this->assertSame(0, Sale::count());
    }

    public function test_transferencia_con_la_casilla_guarda_la_version_en_la_venta_y_en_el_cliente(): void
    {
        $cliente = $this->clienteViejo();

        $res = $this->comprar('transferencia', $cliente, ['acepta_legales' => true])->assertStatus(201);

        $this->assertSame('v-vigente', Sale::findOrFail($res->json('data.id'))->legales_version);
        $cliente->refresh();
        $this->assertSame('v-vigente', $cliente->legales_version);
        $this->assertNotNull($cliente->legales_aceptados_at);
        $this->getJson('/api/v1/clientes/data')->assertJsonPath('data.legales_al_dia', true);
    }

    public function test_cliente_al_dia_compra_sin_casilla_y_la_venta_guarda_la_version(): void
    {
        $res = $this->comprar('transferencia', $this->clienteAlDia())->assertStatus(201);

        $this->assertSame('v-vigente', Sale::findOrFail($res->json('data.id'))->legales_version);
    }

    public function test_al_cambiar_la_version_el_cliente_deja_de_estar_al_dia(): void
    {
        $cliente = $this->clienteAlDia();
        Sanctum::actingAs($cliente, ['cliente']);

        $this->getJson('/api/v1/clientes/data')->assertJsonPath('data.legales_al_dia', true);

        config()->set('legal.version', 'v-nueva');

        $this->getJson('/api/v1/clientes/data')->assertJsonPath('data.legales_al_dia', false);
        $this->comprar('transferencia', $cliente)->assertStatus(422)->assertJsonValidationErrors('acepta_legales', 'data');

        // Acepta la version nueva al comprar y vuelve a estar al dia.
        $this->comprar('transferencia', $cliente, ['acepta_legales' => true])->assertStatus(201);
        $this->getJson('/api/v1/clientes/data')->assertJsonPath('data.legales_al_dia', true);
    }

    // ---------- Pedido por Mercado Pago ----------

    public function test_mercado_pago_sin_aceptacion_vigente_da_422_y_no_crea_la_venta(): void
    {
        $this->comprar('mercado_pago', $this->clienteViejo())
            ->assertStatus(422)
            ->assertJsonValidationErrors('acepta_legales', 'data');

        $this->assertSame(0, Sale::count());
    }

    public function test_mercado_pago_con_la_casilla_guarda_la_version_en_la_venta(): void
    {
        $cliente = $this->clienteViejo();

        $res = $this->comprar('mercado_pago', $cliente, ['acepta_legales' => true])->assertStatus(201);

        $this->assertSame('v-vigente', Sale::findOrFail($res->json('data.venta_pendiente_id'))->legales_version);
        $this->assertSame('v-vigente', $cliente->refresh()->legales_version);
    }

    public function test_recoger_tambien_exige_la_aceptacion(): void
    {
        // Para recoger no se pide direccion ni aviso del mandadito: solo falta la casilla.
        $this->comprar('transferencia', $this->clienteViejo(), ['tipo_entrega' => 'recoger'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('acepta_legales', 'data');
    }
}
