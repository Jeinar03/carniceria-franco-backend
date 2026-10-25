<?php

namespace Tests\Feature;

use App\Http\Livewire\Dash;
use App\Models\Customers;
use App\Models\Sale;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * El Dashboard cuenta los pedidos por el dia de la tienda (Mexico), no por el dia UTC.
 * Usa MySQL porque el dashboard emplea TIMESTAMPDIFF.
 */
class DashboardDiaMexicoTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

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

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_el_dashboard_cuenta_el_dia_y_el_dia_de_la_semana_de_mexico(): void
    {
        // Sabado 24 de octubre de 2026, 8:25 pm en Mexico (domingo 02:25 UTC).
        Carbon::setTestNow(Carbon::parse('2026-10-25 02:30:00', 'UTC'));

        $cliente = Customers::create([
            'nombre' => 'C', 'apellido' => 'X', 'correo' => 'c@test.com',
            'password' => Hash::make('cliente123'), 'estatus' => 'activo',
        ]);
        Sale::create([
            'customer_id' => $cliente->id,
            'fecha_venta' => Carbon::parse('2026-10-25 02:25:33', 'UTC'),
            'subtotal' => 20, 'total' => 20, 'estatus' => 'completada',
        ]);

        $admin = User::create(['name' => 'Admin', 'email' => 'a@test.com', 'password' => Hash::make('secret')]);
        $admin->assignRole('Admin');

        $componente = Livewire::actingAs($admin)->test(Dash::class);

        $this->assertSame(1, $componente->get('kpis')['pedidos_hoy']);
        $this->assertSame(['24/10'], $componente->get('dailyOrdersLabels'));
        $this->assertSame([1], $componente->get('dailyOrdersData'));
        // Sabado = indice 5 (lunes = 0); en UTC habria caido en domingo (6).
        $this->assertSame(20.0, $componente->get('weekSalesData')[5]);
        $this->assertSame(0.0, $componente->get('weekSalesData')[6]);
        $this->assertSame(20.0, $componente->get('salesByMonthData')[9]);
    }
}
