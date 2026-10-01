<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * El dashboard (/home) muestra indicadores del negocio: solo con sesion iniciada.
 */
class HomeRequiereLoginTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // El dashboard usa TIMESTAMPDIFF (solo MySQL), asi que estas pruebas usan una base MySQL desechable.
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

    public function test_sin_sesion_el_dashboard_redirige_al_login(): void
    {
        $this->get('/home')->assertRedirect('/login');
    }

    public function test_con_sesion_el_dashboard_carga(): void
    {
        $user = User::create([
            'name' => 'Admin',
            'email' => 'admin@test.com',
            'password' => Hash::make('secret'),
        ]);
        $user->assignRole('Admin');

        $this->actingAs($user)->get('/home')->assertStatus(200)->assertSee('Dashboard');
    }

    /** Ninguna ruta del panel debe abrirse sin sesion. */
    public function test_las_rutas_del_panel_redirigen_al_login_sin_sesion(): void
    {
        foreach (['/home', '/clientes/despachos', '/admin/inventario', '/admin/ventas', '/sistema/users', '/sistema/avisos-pedidos', '/logs'] as $ruta) {
            $this->get($ruta)->assertRedirect('/login');
        }
    }
}
