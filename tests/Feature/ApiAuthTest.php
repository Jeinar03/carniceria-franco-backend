<?php

namespace Tests\Feature;

use App\Models\Customers;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * R1, R2, R8, R12 — el login emite un token Sanctum real, las rutas de cliente
 * exigen ese token, y logout lo revoca.
 */
class ApiAuthTest extends TestCase
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

    private function crearCliente(array $extra = []): Customers
    {
        return Customers::create(array_merge([
            'nombre' => 'Juan',
            'apellido' => 'Perez',
            'correo' => 'juan@test.com',
            'password' => Hash::make('cliente123'),
            'estatus' => 'activo',
        ], $extra));
    }

    public function test_login_valido_devuelve_un_token_sanctum_utilizable(): void
    {
        $this->crearCliente();

        $login = $this->postJson('/api/v1/clientes/login', [
            'correo' => 'juan@test.com',
            'password' => 'cliente123',
        ]);

        $login->assertOk()->assertJsonPath('success', true);
        $token = $login->json('data.token');

        $this->assertIsString($token);
        $this->assertStringContainsString('|', $token, 'Un token Sanctum tiene la forma "<id>|<hash>"');
        $this->assertDatabaseCount('personal_access_tokens', 1);

        $this->withToken($token)
            ->getJson('/api/v1/clientes/data')
            ->assertOk()
            ->assertJsonPath('data.correo', 'juan@test.com');
    }

    public function test_login_invalido_no_devuelve_token(): void
    {
        $this->crearCliente();

        $this->postJson('/api/v1/clientes/login', [
            'correo' => 'juan@test.com',
            'password' => 'incorrecta',
        ])->assertStatus(401)->assertJsonPath('data.token', null);

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_ruta_de_cliente_sin_token_da_401(): void
    {
        $this->getJson('/api/v1/clientes/data')->assertStatus(401);
        $this->putJson('/api/v1/clientes/1', ['nombre' => 'X'])->assertStatus(401);
        $this->postJson('/api/v1/ventas', [])->assertStatus(401);
    }

    public function test_registro_devuelve_token_y_crea_la_cuenta(): void
    {
        $res = $this->postJson('/api/v1/clientes/registro', [
            'nombre' => 'Ana',
            'apellido' => 'Lopez',
            'correo' => 'ana@test.com',
            'password' => 'secreto123',
        ]);

        $res->assertStatus(201)->assertJsonPath('success', true);
        $this->assertIsString($res->json('data.token'));
        $this->assertDatabaseHas('customers', ['correo' => 'ana@test.com']);
    }

    public function test_logout_revoca_el_token(): void
    {
        $this->crearCliente();
        $token = $this->postJson('/api/v1/clientes/login', [
            'correo' => 'juan@test.com',
            'password' => 'cliente123',
        ])->json('data.token');

        $this->withToken($token)->postJson('/api/v1/clientes/logout')->assertOk();

        $this->assertDatabaseCount('personal_access_tokens', 0);

        // El guard cachea el usuario resuelto dentro del mismo proceso de test;
        // en producción cada request arranca limpio. Forzamos ese estado.
        $this->app['auth']->forgetGuards();

        $this->withToken($token)->getJson('/api/v1/clientes/data')->assertStatus(401);
    }
}
