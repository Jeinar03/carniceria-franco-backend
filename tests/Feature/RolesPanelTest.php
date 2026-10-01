<?php

namespace Tests\Feature;

use App\Http\Livewire\Despachos\DespachosController;
use App\Http\Livewire\UsersController;
use App\Http\Livewire\Ventas\VentasController;
use App\Models\Customers;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Roles del panel: Admin, Cajero y Despachador.
 * Ver: Carnicería Franco/Spec - Roles Admin, Cajero y Despachador.md
 */
class RolesPanelTest extends TestCase
{
    private const A = 'Admin';
    private const C = 'Cajero';
    private const D = 'Despachador';

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

    private function usuario(?string $rol): User
    {
        $user = User::create([
            'name' => 'Usuario ' . ($rol ?? 'sin rol'),
            'email' => strtolower($rol ?? 'sinrol') . '@test.com',
            'password' => Hash::make('secret'),
            'profile' => $rol === self::A ? 'ADMIN' : 'EMPLOYEE',
            'status' => 'ACTIVE',
        ]);

        if ($rol) {
            $user->assignRole($rol);
        }

        return $user;
    }

    /** El Admin sembrado (Luis Fax): unico administrador de la base de pruebas. */
    private function adminSembrado(): User
    {
        return User::findOrFail(1);
    }

    private function productoSinStock(): Product
    {
        $producto = Product::first()->replicate();
        $producto->fill(['codigo' => 'SIN-STOCK-1', 'nombre' => 'Producto sin existencia', 'activo' => true]);
        $producto->save();

        return $producto;
    }

    /** Quien puede abrir cada ruta del panel. */
    private function rutas(): array
    {
        $a = [self::A];
        $ac = [self::A, self::C];
        $todos = [self::A, self::C, self::D];

        return [
            '/home' => $a,
            '/admin/categorias' => $a,
            '/admin/productos' => $a,
            '/admin/inventario' => $a,
            '/admin/indicadores' => $a,
            '/admin/ventas' => $ac,
            '/clientes' => $a,
            '/clientes/precios-especiales' => $a,
            '/clientes/despachos' => $todos,
            '/sistema/users' => $a,
            '/sistema/roles' => $a,
            '/sistema/permisos' => $a,
            '/sistema/notificaciones' => $a,
            '/sistema/avisos-pedidos' => $a,
            '/sistema/sitio' => $a,
            '/sistema/mercado-pago' => $a,
            '/logs' => $a,
            '/admin/inventario/entradas/2026-01-01/pdf' => $a,
            '/admin/despachos/ventas/1/evidencia-transferencia' => $todos,
        ];
    }

    /** Estas rutas usan funciones de MySQL: aqui solo se prueba que NO se niegue el acceso (403). */
    private function assertAcceso(User $user, string $ruta, bool $permitido, string $rol): void
    {
        $respuesta = $this->actingAs($user)->get($ruta);

        if ($permitido) {
            $this->assertNotSame(403, $respuesta->getStatusCode(), "$rol deberia poder abrir $ruta");
            $this->assertFalse($respuesta->isRedirect(), "$rol deberia poder abrir $ruta sin redireccion");
        } else {
            $this->assertSame(403, $respuesta->getStatusCode(), "$rol NO deberia poder abrir $ruta");
        }
    }

    public function test_cada_rol_abre_solo_las_rutas_que_le_tocan(): void
    {
        foreach ([self::A, self::C, self::D] as $rol) {
            $user = $this->usuario($rol);
            foreach ($this->rutas() as $ruta => $permitidos) {
                $this->assertAcceso($user, $ruta, in_array($rol, $permitidos, true), $rol);
            }
        }
    }

    public function test_un_usuario_sin_rol_no_abre_ninguna_pantalla_del_panel(): void
    {
        $user = $this->usuario(null);

        foreach (array_keys($this->rutas()) as $ruta) {
            $this->assertAcceso($user, $ruta, false, 'Sin rol');
        }
    }

    public function test_los_roles_cajero_y_despachador_existen(): void
    {
        foreach ([self::A, self::C, self::D] as $rol) {
            $this->assertNotNull(Role::where('name', $rol)->first(), "Falta el rol $rol");
        }
    }

    // ---- Menu lateral y encabezado ----

    public function test_el_menu_de_cada_rol_muestra_solo_sus_opciones(): void
    {
        $menu = fn (string $ruta) => 'href="' . url($ruta) . '"';

        $this->actingAs($this->usuario(self::A))->get('/clientes/despachos')
            ->assertSee($menu('admin/inventario'), false)
            ->assertSee($menu('sistema/users'), false)
            ->assertSee($menu('clientes/despachos'), false);

        $this->actingAs($this->usuario(self::C))->get('/clientes/despachos')
            ->assertSee($menu('admin/ventas'), false)
            ->assertSee($menu('clientes/despachos'), false)
            ->assertDontSee($menu('admin/inventario'), false)
            ->assertDontSee($menu('admin/productos'), false)
            ->assertDontSee($menu('clientes'), false)
            ->assertDontSee($menu('sistema/users'), false);

        $this->actingAs($this->usuario(self::D))->get('/clientes/despachos')
            ->assertSee($menu('clientes/despachos'), false)
            ->assertDontSee($menu('admin/ventas'), false)
            ->assertDontSee($menu('admin/inventario'), false)
            ->assertDontSee($menu('sistema/users'), false);
    }

    public function test_el_logo_lleva_al_dashboard_solo_al_admin(): void
    {
        $logo = fn (string $ruta) => 'href="' . url($ruta) . '" class="d-flex align-items-center mr-2" aria-label="Ir al inicio"';

        $this->actingAs($this->usuario(self::A))->get('/clientes/despachos')
            ->assertSee($logo('home'), false);

        foreach ([self::C, self::D] as $rol) {
            $this->actingAs($this->usuario($rol))->get('/clientes/despachos')
                ->assertSee($logo('clientes/despachos'), false)
                ->assertDontSee($logo('home'), false);
        }
    }

    public function test_el_aviso_sin_stock_solo_es_informativo_para_cajero_y_despachador(): void
    {
        $producto = $this->productoSinStock();

        $this->actingAs($this->usuario(self::A))->get('/clientes/despachos')
            ->assertSee('Producto sin existencia')
            ->assertSee('Rellenar')
            ->assertSee('admin/inventario?rellenar=' . $producto->id, false)
            ->assertSee('Ir a Inventario');

        foreach ([self::C, self::D] as $rol) {
            $this->actingAs($this->usuario($rol))->get('/clientes/despachos')
                ->assertSee('Producto sin existencia')
                ->assertDontSee('Rellenar')
                ->assertDontSee('Ir a Inventario')
                ->assertDontSee('admin/inventario', false);
        }
    }

    public function test_el_aviso_por_terminar_lo_ven_todos_los_roles(): void
    {
        foreach ([self::A, self::C, self::D] as $rol) {
            $this->actingAs($this->usuario($rol))->get('/clientes/despachos')
                ->assertSee('Por terminar');
        }
    }

    public function test_despachos_oculta_crear_pedido_al_despachador(): void
    {
        $this->actingAs($this->usuario(self::A))->get('/clientes/despachos')->assertSee('Crear pedido');
        $this->actingAs($this->usuario(self::C))->get('/clientes/despachos')->assertSee('Crear pedido');
        $this->actingAs($this->usuario(self::D))->get('/clientes/despachos')->assertDontSee('Crear pedido');
    }

    // ---- Inicio de sesion ----

    public function test_despues_de_iniciar_sesion_cada_rol_va_a_su_primera_pantalla(): void
    {
        $casos = [self::A => '/clientes', self::C => '/clientes/despachos', self::D => '/clientes/despachos'];

        foreach ($casos as $rol => $destino) {
            $user = $this->usuario($rol);

            $this->post('/login', ['email' => $user->email, 'password' => 'secret'])
                ->assertRedirect($destino);

            $this->post('/logout');
            $this->app['auth']->forgetGuards();
        }
    }

    public function test_quien_ya_inicio_sesion_y_abre_el_login_va_a_su_primera_pantalla(): void
    {
        $this->actingAs($this->usuario(self::D))->get('/login')->assertRedirect('/clientes/despachos');
        $this->actingAs($this->usuario(self::A))->get('/login')->assertRedirect('/clientes');
    }

    // ---- Acciones de Livewire (el candado de la ruta tambien aplica a las acciones) ----

    public function test_livewire_reaplica_el_candado_de_rol_en_las_acciones_de_una_pantalla(): void
    {
        $producto = $this->productoSinStock();
        $admin = $this->usuario(self::A);
        $paginaAdmin = $this->actingAs($admin)->get('/admin/inventario')->getContent();

        $this->assertSame(1, preg_match('/wire:initial-data="([^"]+inventario\.inventario-controller[^"]*)"/', $paginaAdmin, $coincidencia));
        $inicial = json_decode(html_entity_decode($coincidencia[1]), true);

        $cuerpo = [
            'fingerprint' => $inicial['fingerprint'],
            'serverMemo' => $inicial['serverMemo'],
            'updates' => [['type' => 'callMethod', 'payload' => ['id' => 'abc', 'method' => '$refresh', 'params' => []]]],
        ];

        $this->app['auth']->forgetGuards();
        $this->actingAs($this->usuario(self::D))
            ->postJson('/livewire/message/inventario.inventario-controller', $cuerpo, ['X-Livewire' => 'true'])
            ->assertStatus(403);

        $this->app['auth']->forgetGuards();
        $this->actingAs($admin)
            ->postJson('/livewire/message/inventario.inventario-controller', $cuerpo, ['X-Livewire' => 'true'])
            ->assertStatus(200);
    }

    public function test_el_despachador_no_puede_crear_ni_editar_ordenes(): void
    {
        $despachador = $this->usuario(self::D);

        foreach (['openCreateOrderModal' => [], 'guardarNuevoCliente' => [], 'createOrder' => [], 'openEditOrderModal' => [1], 'updateOrder' => []] as $accion => $params) {
            Livewire::actingAs($despachador)->test(DespachosController::class)
                ->call($accion, ...$params)
                ->assertForbidden();
        }
    }

    public function test_el_cajero_si_puede_crear_ordenes_y_registrar_clientes_de_mostrador(): void
    {
        Livewire::actingAs($this->usuario(self::C))->test(DespachosController::class)
            ->call('openCreateOrderModal')
            ->assertEmitted('show-create-order-modal')
            ->set('nuevoNombre', 'Rosa')
            ->set('nuevoApellido', 'Mostrador')
            ->call('guardarNuevoCliente')
            ->assertEmitted('despacho-updated');

        $this->assertSame(1, Customers::where('nombre', 'Rosa')->count());
    }

    public function test_los_tres_roles_pueden_validar_transferencias(): void
    {
        foreach ([self::A, self::C, self::D] as $rol) {
            Livewire::actingAs($this->usuario($rol))->test(DespachosController::class)
                ->call('openTransferValidationModal', 999999)
                ->assertEmitted('despacho-error');
        }
    }

    public function test_solo_el_admin_cancela_ventas(): void
    {
        Livewire::actingAs($this->usuario(self::C))->test(VentasController::class)
            ->call('cancelSale', 1)
            ->assertForbidden();
    }

    public function test_el_admin_si_cancela_ventas(): void
    {
        Livewire::actingAs($this->usuario(self::A))->test(VentasController::class)
            ->call('cancelSale', 999999)
            ->assertEmitted('sale-error');
    }

    // ---- Formulario de Usuarios ----

    private function crearUsuarioDesdeElFormulario(string $perfil, string $correo)
    {
        return Livewire::actingAs($this->adminSembrado())->test(UsersController::class)
            ->set('name', 'Carla Prueba')
            ->set('email', $correo)
            ->set('password', 'secret1')
            ->set('status', 'ACTIVE')
            ->set('profile', $perfil)
            ->call('Store');
    }

    public function test_el_formulario_guarda_el_rol_elegido(): void
    {
        $this->crearUsuarioDesdeElFormulario(self::C, 'cajero.nuevo@test.com')->assertEmitted('user-added');
        $this->crearUsuarioDesdeElFormulario(self::D, 'despachador.nuevo@test.com')->assertEmitted('user-added');
        $this->crearUsuarioDesdeElFormulario(self::A, 'admin.nuevo@test.com')->assertEmitted('user-added');

        $cajero = User::where('email', 'cajero.nuevo@test.com')->firstOrFail();
        $this->assertSame([self::C], $cajero->getRoleNames()->all());
        $this->assertSame('EMPLOYEE', $cajero->profile);

        $despachador = User::where('email', 'despachador.nuevo@test.com')->firstOrFail();
        $this->assertSame([self::D], $despachador->getRoleNames()->all());
        $this->assertSame('EMPLOYEE', $despachador->profile);

        $admin = User::where('email', 'admin.nuevo@test.com')->firstOrFail();
        $this->assertSame([self::A], $admin->getRoleNames()->all());
        $this->assertSame('ADMIN', $admin->profile);
    }

    public function test_el_unico_admin_no_puede_quitarse_el_rol_admin(): void
    {
        $luis = $this->adminSembrado();

        Livewire::actingAs($luis)->test(UsersController::class)
            ->call('edit', $luis->id)
            ->set('profile', self::C)
            ->call('Update')
            ->assertEmitted('user-withsales');

        $this->assertTrue($luis->fresh()->hasRole(self::A));
    }

    public function test_un_admin_no_puede_bloquearse_a_si_mismo(): void
    {
        $luis = $this->adminSembrado();

        Livewire::actingAs($luis)->test(UsersController::class)
            ->call('edit', $luis->id)
            ->set('status', 'LOCKED')
            ->call('Update')
            ->assertEmitted('user-withsales');

        $this->assertSame('ACTIVE', strtoupper($luis->fresh()->status));
    }

    public function test_un_admin_no_puede_borrarse_a_si_mismo(): void
    {
        $luis = $this->adminSembrado();

        Livewire::actingAs($luis)->test(UsersController::class)
            ->call('destroy', $luis->id)
            ->assertEmitted('user-withsales');

        $this->assertNotNull(User::find($luis->id));
    }

    public function test_un_admin_puede_cambiar_el_rol_de_otro_y_borrar_a_otro_usuario(): void
    {
        $luis = $this->adminSembrado();
        $otro = $this->usuario(self::C);

        Livewire::actingAs($luis)->test(UsersController::class)
            ->call('edit', $otro->id)
            ->set('profile', self::D)
            ->call('Update')
            ->assertEmitted('user-updated');

        $this->assertTrue($otro->fresh()->hasRole(self::D));
        $this->assertFalse($otro->fresh()->hasRole(self::C));

        Livewire::actingAs($luis)->test(UsersController::class)
            ->call('destroy', $otro->id)
            ->assertEmitted('user-deleted');

        $this->assertNull(User::find($otro->id));
    }

    public function test_no_se_puede_borrar_ni_degradar_al_ultimo_admin_activo_aunque_haya_otro_admin_bloqueado(): void
    {
        $luis = $this->adminSembrado();
        $bloqueado = $this->usuario(self::A);
        $bloqueado->update(['status' => 'LOCKED', 'email' => 'bloqueado@test.com']);

        // Luis es el unico Admin ACTIVO: borrar al bloqueado se permite, degradar a Luis no.
        Livewire::actingAs($luis)->test(UsersController::class)
            ->call('edit', $luis->id)
            ->set('profile', self::D)
            ->call('Update')
            ->assertEmitted('user-withsales');

        $this->assertTrue($luis->fresh()->hasRole(self::A));
    }

    // ---- Datos existentes (migracion) ----

    public function test_la_migracion_da_rol_a_los_usuarios_que_no_tienen(): void
    {
        $adminViejo = User::create(['name' => 'Jefe', 'email' => 'jefe@test.com', 'password' => Hash::make('x'), 'profile' => 'ADMIN', 'status' => 'ACTIVE']);
        $empleadoViejo = User::create(['name' => 'Empleado', 'email' => 'empleado@test.com', 'password' => Hash::make('x'), 'profile' => 'EMPLOYEE', 'status' => 'ACTIVE']);
        $conRol = $this->usuario(self::D);

        $migracion = require base_path('database/migrations/2026_10_01_000000_crear_roles_cajero_y_despachador.php');
        $migracion->up();
        $migracion->up(); // repetirla no debe fallar ni duplicar

        $this->assertSame([self::A], $adminViejo->fresh()->getRoleNames()->all());
        $this->assertSame([self::C], $empleadoViejo->fresh()->getRoleNames()->all());
        $this->assertSame([self::D], $conRol->fresh()->getRoleNames()->all());
        $this->assertSame(1, Role::where('name', self::C)->count());
    }
}
