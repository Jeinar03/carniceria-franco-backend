<?php

namespace Tests\Feature;

use App\Http\Livewire\Ventas\VentasController;
use App\Models\Customers;
use App\Models\Sale;
use App\Models\User;
use App\Support\HoraLocal;
use Carbon\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * El sistema guarda en UTC; el folio, los filtros por fecha y las fechas de entrega
 * cuentan el dia de la tienda (America/Mexico_City). Despues de las 6 pm en Mexico
 * ya es el dia siguiente en UTC, y eso no debe cambiar el dia que ve la tienda.
 */
class DiaMexicoTest extends TestCase
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

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function cliente(): Customers
    {
        return Customers::create([
            'nombre' => 'Dona', 'apellido' => 'Juanita', 'correo' => 'juanita@test.com',
            'password' => Hash::make('cliente123'), 'estatus' => 'activo',
        ]);
    }

    private function venta(Customers $cliente, array $extra = []): Sale
    {
        return Sale::create(array_merge([
            'customer_id' => $cliente->id,
            'fecha_venta' => now(),
            'subtotal' => 20,
            'total' => 20,
        ], $extra));
    }

    public function test_hoy_y_rango_usan_el_dia_de_mexico(): void
    {
        // 02:25 UTC del 25 son las 20:25 del 24 en Mexico.
        Carbon::setTestNow(Carbon::parse('2026-10-25 02:25:00', 'UTC'));

        $this->assertSame('2026-10-24', HoraLocal::hoy());

        [$desde, $hasta] = HoraLocal::rangoUtc('2026-10-24', '2026-10-24');
        $this->assertSame('2026-10-24 06:00:00', $desde->format('Y-m-d H:i:s'));
        $this->assertSame('2026-10-25 05:59:59', $hasta->format('Y-m-d H:i:s'));
        $this->assertSame('UTC', $desde->timezoneName);
    }

    public function test_el_folio_usa_el_dia_de_mexico_y_no_se_repite(): void
    {
        $cliente = $this->cliente();

        Carbon::setTestNow(Carbon::parse('2026-10-24 15:00:00', 'UTC')); // 09:00 en Mexico
        $this->assertSame('20261024-0001', $this->venta($cliente)->folio);

        // 8:25 pm en Mexico del 24: antes salia 20261025-0001.
        Carbon::setTestNow(Carbon::parse('2026-10-25 02:25:33', 'UTC'));
        $this->assertSame('20261024-0002', $this->venta($cliente)->folio);

        // Ya es 25 en Mexico; el consecutivo arranca de nuevo.
        Carbon::setTestNow(Carbon::parse('2026-10-25 15:00:00', 'UTC'));
        $this->assertSame('20261025-0001', $this->venta($cliente)->folio);
    }

    public function test_el_folio_no_choca_con_uno_viejo_guardado_con_el_dia_utc(): void
    {
        $cliente = $this->cliente();
        // Folio del sistema anterior: 8:25 pm del 24 en Mexico, guardado como 20261025-0001.
        $this->venta($cliente, ['folio' => '20261025-0001', 'fecha_venta' => Carbon::parse('2026-10-25 02:25:33', 'UTC')]);

        Carbon::setTestNow(Carbon::parse('2026-10-25 15:00:00', 'UTC')); // 09:00 del 25 en Mexico
        $this->assertSame('20261025-0002', $this->venta($cliente)->folio);
    }

    public function test_el_filtro_de_ventas_por_dia_usa_el_dia_de_mexico(): void
    {
        $admin = User::create(['name' => 'Admin', 'email' => 'admin@test.com', 'password' => Hash::make('secret')]);
        $admin->assignRole('Admin');
        $cliente = $this->cliente();
        $this->venta($cliente, ['folio' => 'TARDE-24', 'fecha_venta' => Carbon::parse('2026-10-25 02:25:33', 'UTC')]); // 24, 8:25 pm
        $this->venta($cliente, ['folio' => 'MANANA-25', 'fecha_venta' => Carbon::parse('2026-10-25 15:00:00', 'UTC')]); // 25, 9:00 am

        Livewire::actingAs($admin)
            ->test(VentasController::class)
            ->set('fechaInicio', '2026-10-24')
            ->set('fechaFin', '2026-10-24')
            ->assertSee('TARDE-24')
            ->assertDontSee('MANANA-25')
            ->set('fechaInicio', '2026-10-25')
            ->set('fechaFin', '2026-10-25')
            ->assertSee('MANANA-25')
            ->assertDontSee('TARDE-24');
    }

    public function test_pedidos_programados_y_de_hoy_se_separan_por_el_dia_de_mexico(): void
    {
        $cliente = $this->cliente();
        Carbon::setTestNow(Carbon::parse('2026-10-25 02:25:00', 'UTC')); // sigue siendo 24 en Mexico

        $hoy = $this->venta($cliente, ['fecha_entrega' => '2026-10-24']);
        $manana = $this->venta($cliente, ['fecha_entrega' => '2026-10-25']);

        $this->assertTrue(Sale::entregaInmediata()->pluck('id')->contains($hoy->id));
        $this->assertFalse(Sale::entregaInmediata()->pluck('id')->contains($manana->id));
        $this->assertTrue(Sale::programadas()->pluck('id')->contains($manana->id));
        $this->assertFalse(Sale::programadas()->pluck('id')->contains($hoy->id));
    }
}
