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
 * El sistema guarda en UTC; el panel muestra la hora de la tienda (America/Mexico_City).
 */
class HoraLocalTest extends TestCase
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

    public function test_convierte_utc_a_hora_de_mexico_sin_cambiar_la_fecha_original(): void
    {
        $utc = Carbon::parse('2026-10-25 02:25:33', 'UTC');

        $this->assertSame('24/10/2026 20:25', HoraLocal::formato($utc));
        $this->assertSame('24/10/2026 20:25:33', HoraLocal::formato($utc, 'd/m/Y H:i:s'));
        $this->assertSame('2026-10-25 02:25:33', $utc->format('Y-m-d H:i:s'));
    }

    public function test_sin_fecha_devuelve_vacio(): void
    {
        $this->assertSame('', HoraLocal::formato(null));
        $this->assertNull(HoraLocal::de(null));
    }

    public function test_el_detalle_de_ventas_muestra_la_hora_de_mexico(): void
    {
        $admin = User::create(['name' => 'Admin', 'email' => 'admin@test.com', 'password' => Hash::make('secret')]);
        $admin->assignRole('Admin');
        $cliente = Customers::create([
            'nombre' => 'Doña', 'apellido' => 'Juanita', 'correo' => 'juanita@test.com',
            'password' => Hash::make('cliente123'), 'estatus' => 'activo',
        ]);
        $venta = Sale::create([
            'customer_id' => $cliente->id,
            'folio' => '20261025-0001',
            'fecha_venta' => Carbon::parse('2026-10-25 02:25:33', 'UTC'),
            'aviso_mandadito_aceptado_at' => Carbon::parse('2026-10-25 02:25:00', 'UTC'),
            'total' => 20,
        ]);

        Livewire::actingAs($admin)
            ->test(VentasController::class)
            ->call('viewDetail', $venta->id)
            ->assertSee('24/10/2026 20:25:33')
            ->assertSee('Aceptado el 24/10/2026 20:25')
            ->assertDontSee('25/10/2026 02:25');
    }
}
