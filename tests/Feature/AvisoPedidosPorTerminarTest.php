<?php

namespace Tests\Feature;

use App\Http\Livewire\AvisosPedidos\AvisosPedidosController;
use App\Http\Livewire\Despachos\DespachosController;
use App\Http\Livewire\Despachos\PedidosPorTerminar;
use App\Models\Customers;
use App\Models\PanelSetting;
use App\Models\Sale;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Aviso "Por terminar" en el encabezado del panel.
 * Ver: Carnicería Franco/Spec - Aviso de pedidos por terminar.md
 */
class AvisoPedidosPorTerminarTest extends TestCase
{
    private Customers $cliente;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite.database', ':memory:');
        DB::purge('sqlite');
        DB::reconnect('sqlite');

        Artisan::call('migrate', ['--force' => true, '--no-interaction' => true]);
        Artisan::call('db:seed', ['--force' => true, '--no-interaction' => true]);

        Carbon::setTestNow(Carbon::parse('2026-09-30 12:00:00'));

        $this->cliente = Customers::create([
            'nombre' => 'Cliente',
            'apellido' => 'Prueba',
            'correo' => 'cliente@test.com',
            'password' => Hash::make('secreto123'),
            'estatus' => 'activo',
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function usuario(bool $admin = false): User
    {
        $user = User::create([
            'name' => $admin ? 'Admin' : 'Empleado',
            'email' => ($admin ? 'admin' : 'empleado') . '@test.com',
            'password' => Hash::make('secret'),
        ]);
        if ($admin) {
            $user->assignRole('Admin');
        }

        return $user;
    }

    private function venta(array $extra = []): Sale
    {
        return Sale::create(array_merge([
            'customer_id' => $this->cliente->id,
            'fecha_venta' => now()->subMinutes(10),
            'subtotal' => 100,
            'total' => 100,
            'metodo_pago' => 'efectivo',
            'estatus' => Sale::ESTATUS_COMPLETADA,
            'estado_envio' => Sale::ENVIO_PENDIENTE,
        ], $extra));
    }

    /** Criterio 1 */
    public function test_cuenta_pendiente_procesando_y_listo_para_enviar(): void
    {
        $this->venta(['estado_envio' => Sale::ENVIO_PENDIENTE]);
        $this->venta(['estado_envio' => Sale::ENVIO_PROCESANDO]);
        $this->venta(['estado_envio' => Sale::ENVIO_LISTO]);

        Livewire::actingAs($this->usuario())
            ->test(PedidosPorTerminar::class)
            ->assertViewHas('total', 3);
    }

    /** Criterio 2 */
    public function test_no_cuenta_enviados_ni_entregados(): void
    {
        $this->venta(['estado_envio' => Sale::ENVIO_ENVIADO]);
        $this->venta(['estado_envio' => Sale::ENVIO_ENTREGADO]);

        Livewire::actingAs($this->usuario())
            ->test(PedidosPorTerminar::class)
            ->assertViewHas('total', 0);
    }

    /** Criterio 3 */
    public function test_no_cuenta_cancelados_y_despachos_ya_no_los_muestra(): void
    {
        $cancelada = $this->venta(['estatus' => Sale::ESTATUS_CANCELADA]);
        $activa = $this->venta();
        $admin = $this->usuario();

        Livewire::actingAs($admin)
            ->test(PedidosPorTerminar::class)
            ->assertViewHas('total', 1);

        Livewire::actingAs($admin)
            ->test(DespachosController::class)
            ->assertSee($activa->folio)
            ->assertDontSee($cancelada->folio);
    }

    /** Criterio 4 */
    public function test_pedido_programado_a_futuro_no_cuenta_y_el_de_hoy_si(): void
    {
        $this->venta(['fecha_entrega' => now()->addDays(2)->toDateString()]);
        $this->venta(['fecha_entrega' => now()->toDateString()]);

        Livewire::actingAs($this->usuario())
            ->test(PedidosPorTerminar::class)
            ->assertViewHas('total', 1);
    }

    /** Criterio 5 */
    public function test_color_segun_el_pedido_mas_viejo(): void
    {
        $admin = $this->usuario();

        $v = $this->venta(['fecha_venta' => now()->subMinutes(10)]);
        Livewire::actingAs($admin)->test(PedidosPorTerminar::class)->assertViewHas('color', 'success');

        $v->update(['fecha_venta' => now()->subMinutes(45)]);
        Livewire::actingAs($admin)->test(PedidosPorTerminar::class)->assertViewHas('color', 'warning');

        $v->update(['fecha_venta' => now()->subMinutes(90)]);
        Livewire::actingAs($admin)->test(PedidosPorTerminar::class)->assertViewHas('color', 'danger');
    }

    /** Criterio 6 */
    public function test_sin_pedidos_muestra_cero_y_gris(): void
    {
        Livewire::actingAs($this->usuario())
            ->test(PedidosPorTerminar::class)
            ->assertViewHas('total', 0)
            ->assertViewHas('color', 'secondary');
    }

    /** Criterio 7 */
    public function test_transferencias_pendiente_aprobada_y_rechazada(): void
    {
        $pendiente = $this->venta([
            'metodo_pago' => 'transferencia',
            'estatus' => Sale::ESTATUS_PENDIENTE,
            'transferencia_estado' => 'pendiente',
        ]);
        $this->venta([
            'metodo_pago' => 'transferencia',
            'estatus' => Sale::ESTATUS_COMPLETADA,
            'transferencia_estado' => 'aprobada',
        ]);
        $this->venta([
            'metodo_pago' => 'transferencia',
            'estatus' => Sale::ESTATUS_PENDIENTE,
            'transferencia_estado' => 'rechazada',
        ]);

        Livewire::actingAs($this->usuario())
            ->test(PedidosPorTerminar::class)
            ->assertViewHas('total', 2)
            ->assertSee('Transferencia por validar')
            ->assertSee($pendiente->folio);
    }

    /** Criterio 8 */
    public function test_formato_del_tiempo(): void
    {
        $this->assertSame('12 min', PedidosPorTerminar::formatearTiempo(12));
        $this->assertSame('1 h 20 min', PedidosPorTerminar::formatearTiempo(80));
        $this->assertSame('2 h', PedidosPorTerminar::formatearTiempo(120));
        $this->assertSame('0 min', PedidosPorTerminar::formatearTiempo(0));
    }

    /** Criterio 9 */
    public function test_se_actualiza_solo_cada_30_segundos(): void
    {
        Livewire::actingAs($this->usuario())
            ->test(PedidosPorTerminar::class)
            ->assertSeeHtml('wire:poll.30s');
    }

    /** Criterio 7b: umbrales guardados */
    public function test_los_tiempos_guardados_cambian_el_color(): void
    {
        PanelSetting::set('aviso_pedidos_amarillo_min', 5);
        PanelSetting::set('aviso_pedidos_rojo_min', 8);
        $this->venta(['fecha_venta' => now()->subMinutes(10)]);

        Livewire::actingAs($this->usuario())
            ->test(PedidosPorTerminar::class)
            ->assertViewHas('color', 'danger');
    }

    /** Criterio 7b: valores iniciales */
    public function test_valores_iniciales_son_30_y_60(): void
    {
        $this->assertSame(30, (int) PanelSetting::get('aviso_pedidos_amarillo_min', 0));
        $this->assertSame(60, (int) PanelSetting::get('aviso_pedidos_rojo_min', 0));
    }

    /** Criterio 7b: pantalla de ajustes */
    public function test_pantalla_de_ajustes_guarda_y_valida(): void
    {
        $admin = $this->usuario(true);

        Livewire::actingAs($admin)
            ->test(AvisosPedidosController::class)
            ->set('amarilloMin', 20)
            ->set('rojoMin', 15)
            ->call('save')
            ->assertHasErrors(['rojoMin']);

        $this->assertSame(30, (int) PanelSetting::get('aviso_pedidos_amarillo_min', 0));

        Livewire::actingAs($admin)
            ->test(AvisosPedidosController::class)
            ->set('amarilloMin', 20)
            ->set('rojoMin', 50)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(20, (int) PanelSetting::get('aviso_pedidos_amarillo_min', 0));
        $this->assertSame(50, (int) PanelSetting::get('aviso_pedidos_rojo_min', 0));
    }

    /** Criterio 7b: solo administrador */
    public function test_solo_el_administrador_entra_a_la_pantalla_de_ajustes(): void
    {
        $this->actingAs($this->usuario(false))->get('/sistema/avisos-pedidos')->assertStatus(403);
    }

    public function test_el_administrador_entra_a_la_pantalla_de_ajustes(): void
    {
        $this->actingAs($this->usuario(true))->get('/sistema/avisos-pedidos')->assertStatus(200);
    }

    /** El aviso aparece en el encabezado del panel */
    public function test_el_aviso_aparece_en_el_encabezado_del_panel(): void
    {
        $this->venta();

        $this->actingAs($this->usuario(true))
            ->get('/clientes/despachos')
            ->assertStatus(200)
            ->assertSee('Por terminar');
    }
}
