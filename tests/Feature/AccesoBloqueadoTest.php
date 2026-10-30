<?php

namespace Tests\Feature;

use App\Models\Customers;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Acceso: usuarios bloqueados del panel y contrasena fija de clientes creados desde el panel.
 * Ver: Carnicería Franco/Auditorias consolidadas.md (A3/S-04, S-01).
 */
class AccesoBloqueadoTest extends TestCase
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

    private function empleado(string $status): User
    {
        return User::create([
            'name' => 'Empleado',
            'email' => 'emp-' . strtolower($status) . '@test.com',
            'password' => Hash::make('secreto-123'),
            'profile' => 'EMPLOYEE',
            'status' => $status,
        ]);
    }

    public function test_un_usuario_bloqueado_no_inicia_sesion_en_el_panel(): void
    {
        $bloqueado = $this->empleado('LOCKED');

        $this->post('/login', ['email' => $bloqueado->email, 'password' => 'secreto-123'])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_un_usuario_activo_si_inicia_sesion_en_el_panel(): void
    {
        $activo = $this->empleado('ACTIVE');

        $this->post('/login', ['email' => $activo->email, 'password' => 'secreto-123']);

        $this->assertAuthenticatedAs($activo);
    }

    public function test_el_comando_quita_la_contrasena_fija_y_cierra_sesiones(): void
    {
        $conFija = Customers::create([
            'nombre' => 'Panel', 'apellido' => 'Creado', 'correo' => 'fija@test.com',
            'password' => Hash::make('defaultpassword'), 'estatus' => 'activo',
        ]);
        $propia = Customers::create([
            'nombre' => 'Propia', 'apellido' => 'Clave', 'correo' => 'propia@test.com',
            'password' => Hash::make('mi-clave-segura'), 'estatus' => 'activo',
        ]);
        $conFija->createToken('tienda', ['cliente']);

        $this->artisan('clientes:resetear-password-default')->assertExitCode(0);

        $this->assertFalse(Hash::check('defaultpassword', $conFija->fresh()->password));
        $this->assertSame(0, $conFija->tokens()->count());
        $this->assertTrue(Hash::check('mi-clave-segura', $propia->fresh()->password));
    }

    public function test_un_cliente_suspendido_no_puede_iniciar_sesion_en_la_tienda(): void
    {
        $cliente = Customers::create([
            'nombre' => 'S', 'apellido' => 'U', 'correo' => 'susp@test.com',
            'password' => Hash::make('cliente123'), 'estatus' => 'suspendido',
        ]);

        $this->postJson('/api/v1/usuarios/login', ['correo' => $cliente->correo, 'password' => 'cliente123'])
            ->assertStatus(403);
    }
}
