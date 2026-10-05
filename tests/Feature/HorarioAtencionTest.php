<?php

namespace Tests\Feature;

use App\Http\Controllers\MercadoPagoController;
use App\Models\Customers;
use App\Models\SiteConfig;
use Carbon\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** Mercado Pago simulado: no sale a la red al inicializar el SDK. */
class MercadoPagoSinRed extends MercadoPagoController
{
    protected function inicializarSdk(string $accessToken): void
    {
        \MercadoPago\SDK::initialize();
    }
}

/**
 * Horario de atencion: se evalua en hora de Mexico (el servidor corre en UTC) y se puede apagar desde el admin.
 */
class HorarioAtencionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite.database', ':memory:');
        DB::purge('sqlite');
        DB::reconnect('sqlite');

        Artisan::call('migrate', ['--force' => true, '--no-interaction' => true]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function sitio(array $extra = []): SiteConfig
    {
        $dia = ['abierto' => true, 'apertura' => '08:00', 'cierre' => '20:00'];

        return SiteConfig::create(array_merge([
            'nombre' => 'Tienda',
            'horarios' => [
                'lunes' => $dia, 'martes' => $dia, 'miercoles' => $dia, 'jueves' => $dia, 'viernes' => $dia,
                'sabado' => ['abierto' => false, 'apertura' => '', 'cierre' => ''],
                'domingo' => '08:00 - 15:00',
            ],
            'activo' => true,
        ], $extra));
    }

    public function test_usa_hora_de_mexico_y_no_la_utc_del_servidor(): void
    {
        $this->sitio();

        // Viernes 6:30 pm en Mexico = sabado 00:30 UTC. En UTC pareceria sabado (cerrado).
        $this->assertTrue(SiteConfig::estadoDeAtencion(Carbon::parse('2026-10-03 00:30:00', 'UTC'))['abierto']);

        // Viernes 8:30 pm en Mexico = sabado 02:30 UTC: ya cerro.
        $this->assertFalse(SiteConfig::estadoDeAtencion(Carbon::parse('2026-10-03 02:30:00', 'UTC'))['abierto']);
    }

    public function test_dia_cerrado_y_horario_en_texto(): void
    {
        $this->sitio();

        // Sabado 12:00 en Mexico (18:00 UTC): dia marcado como cerrado.
        $this->assertFalse(SiteConfig::estadoDeAtencion(Carbon::parse('2026-10-03 18:00:00', 'UTC'))['abierto']);

        // Domingo 12:00 en Mexico: horario guardado como texto "08:00 - 15:00".
        $domingo = SiteConfig::estadoDeAtencion(Carbon::parse('2026-10-04 18:00:00', 'UTC'));
        $this->assertTrue($domingo['abierto']);
        $this->assertSame('08:00 - 15:00', $domingo['horario']);
    }

    public function test_con_el_interruptor_apagado_siempre_esta_abierto(): void
    {
        $this->sitio(['limitar_horario' => false]);

        $this->assertTrue(SiteConfig::estadoDeAtencion(Carbon::parse('2026-10-03 18:00:00', 'UTC'))['abierto']);
    }

    public function test_sin_configuracion_activa_no_bloquea(): void
    {
        $this->assertTrue(SiteConfig::estadoDeAtencion()['abierto']);
    }

    public function test_el_interruptor_viene_encendido_por_defecto(): void
    {
        $this->assertTrue($this->sitio()->fresh()->limitar_horario);
    }

    public function test_si_hay_dos_activas_gana_la_editada_mas_recientemente(): void
    {
        $vieja = $this->sitio(['nombre' => 'Vieja']);
        $nueva = $this->sitio(['nombre' => 'Nueva']);

        SiteConfig::whereKey($vieja->id)->update(['updated_at' => '2026-09-02 00:00:00']);
        SiteConfig::whereKey($nueva->id)->update(['updated_at' => '2026-10-02 00:00:00']);

        $this->assertSame('Nueva', SiteConfig::activa()->nombre);
        $this->getJson('/api/v1/sitio/config')
            ->assertOk()
            ->assertJsonPath('data.nombre', 'Nueva')
            ->assertJsonPath('data.zona_horaria', 'America/Mexico_City');
    }

    public function test_el_servidor_rechaza_compras_fuera_de_horario(): void
    {
        $this->sitio();
        Carbon::setTestNow(Carbon::parse('2026-10-03 18:00:00', 'UTC')); // sabado, cerrado

        config()->set('mercadopago.access_token', 'APP_USR-token-de-prueba-0000');
        $this->app->bind(MercadoPagoController::class, fn () => new MercadoPagoSinRed());

        $cliente = Customers::create([
            'nombre' => 'C', 'apellido' => 'X', 'correo' => 'h@test.com',
            'password' => Hash::make('cliente123'), 'estatus' => 'activo',
        ]);
        Sanctum::actingAs($cliente, ['cliente']);

        $cuerpo = ['metodo_pago' => 'transferencia', 'productos' => [['product_id' => 1, 'cantidad' => 1]]];

        $this->postJson('/api/v1/ventas', $cuerpo)
            ->assertStatus(403)
            ->assertJsonPath('success', false);

        $this->postJson('/api/v1/mercadopago/create-preference', array_merge($cuerpo, ['metodo_pago' => 'mercado_pago']))
            ->assertStatus(403);
    }
}
