<?php

namespace Tests\Feature;

use App\Http\Livewire\Despachos\DespachosController;
use App\Http\Livewire\Sitio\SitioController;
use App\Mail\OrderStatusMail;
use App\Models\Customers;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SiteConfig;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Direccion de entrega en el panel (Despachos, Crear y Editar Orden), en los correos y
 * ubicacion de la carniceria en Sistema, Sitio Web.
 */
class DireccionEntregaPanelTest extends TestCase
{
    private Customers $cliente;
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite.database', ':memory:');
        DB::purge('sqlite');
        DB::reconnect('sqlite');

        Artisan::call('migrate', ['--force' => true, '--no-interaction' => true]);
        Artisan::call('db:seed', ['--force' => true, '--no-interaction' => true]);

        $this->cliente = Customers::create([
            'nombre' => 'Doña',
            'apellido' => 'Juanita',
            'correo' => 'juanita@test.com',
            'password' => Hash::make('cliente123'),
            'telefono' => '7531234567',
            'direccion' => 'Calle Vieja 10',
            'referencias' => 'Casa verde',
            'ubicacion_url' => 'https://maps.app.goo.gl/Vieja',
            'estatus' => 'activo',
        ]);

        $this->admin = User::create(['name' => 'Admin', 'email' => 'admin@test.com', 'password' => Hash::make('secret')]);
        $this->admin->assignRole('Admin');
    }

    private function producto(): Product
    {
        return Product::where('activo', true)->where('en_oferta', false)->firstOrFail();
    }

    private function comprar(array $extra = []): Sale
    {
        Sanctum::actingAs($this->cliente, ['cliente']);

        $res = $this->postJson('/api/v1/ventas', array_merge([
            'metodo_pago' => 'transferencia',
            'tipo_entrega' => 'mandadito',
            'acepta_aviso_mandadito' => true,
            'direccion_entrega' => 'Av. Juárez 25, Centro',
            'productos' => [['product_id' => $this->producto()->id, 'cantidad' => 1]],
        ], $extra))->assertStatus(201);

        return Sale::findOrFail($res->json('data.id'));
    }

    private function htmlDe(OrderStatusMail $mail): string
    {
        return view('emails.order-status', [
            'sale' => $mail->sale,
            'config' => $mail->statusConfig,
            'order' => $mail->orderSummary,
            'customer' => $mail->sale->customer,
            'datosBancarios' => null,
        ])->render();
    }

    // ---------- Modelo: de donde sale la direccion ----------

    public function test_el_pedido_usa_su_propia_direccion_y_no_la_del_perfil(): void
    {
        $venta = $this->comprar(['referencias_entrega' => 'Portón negro', 'ubicacion_url' => 'https://maps.app.goo.gl/Nueva']);

        $this->assertSame('Av. Juárez 25, Centro', $venta->direccionDeEntrega());
        $this->assertSame('Portón negro', $venta->referenciasDeEntrega());
        $this->assertSame('https://maps.app.goo.gl/Nueva', $venta->enlaceMapaEntrega());
    }

    public function test_un_pedido_sin_direccion_propia_usa_la_del_cliente(): void
    {
        $venta = $this->comprar();
        $venta->update(['direccion_entrega' => null, 'referencias_entrega' => null, 'ubicacion_url' => null]);
        $venta = $venta->fresh('customer');

        $this->assertSame('Calle Vieja 10', $venta->direccionDeEntrega());
        $this->assertSame('Casa verde', $venta->referenciasDeEntrega());
        $this->assertSame('https://maps.app.goo.gl/Vieja', $venta->enlaceMapaEntrega());
    }

    public function test_sin_link_se_arma_una_busqueda_en_maps_con_la_direccion(): void
    {
        $venta = $this->comprar();

        $this->assertSame(
            'https://www.google.com/maps/search/?api=1&query=' . urlencode('Av. Juárez 25, Centro'),
            $venta->enlaceMapaEntrega()
        );
    }

    public function test_el_texto_para_el_mandadito_trae_cliente_telefono_direccion_y_mapa(): void
    {
        $venta = $this->comprar(['referencias_entrega' => 'Portón negro']);
        $texto = $venta->textoParaMandadito();

        $this->assertStringContainsString($venta->folio, $texto);
        $this->assertStringContainsString('Doña Juanita', $texto);
        $this->assertStringContainsString('7531234567', $texto);
        $this->assertStringContainsString('Av. Juárez 25, Centro', $texto);
        $this->assertStringContainsString('Portón negro', $texto);
        $this->assertStringContainsString('google.com/maps', $texto);
    }

    // ---------- Despachos ----------

    public function test_despachos_muestra_a_donde_entregar_y_los_botones(): void
    {
        $venta = $this->comprar(['referencias_entrega' => 'Portón negro']);
        $venta->update(['transferencia_estado' => 'aprobada', 'estatus' => Sale::ESTATUS_COMPLETADA]);

        Livewire::actingAs($this->admin)
            ->test(DespachosController::class)
            ->call('openModal', $venta->id)
            ->assertSee('Entregar en')
            ->assertSee('Av. Juárez 25, Centro')
            ->assertSee('Portón negro')
            ->assertSee('Abrir en Google Maps')
            ->assertSee('Copiar datos para el mandadito')
            ->assertDontSee('Calle Vieja 10');
    }

    public function test_despachos_de_un_pedido_para_recoger_no_muestra_botones_de_mandadito(): void
    {
        $venta = $this->comprar(['tipo_entrega' => 'recoger']);
        $venta->update(['transferencia_estado' => 'aprobada', 'estatus' => Sale::ESTATUS_COMPLETADA]);

        Livewire::actingAs($this->admin)
            ->test(DespachosController::class)
            ->call('openModal', $venta->id)
            ->assertSee('El cliente pasa a recogerlo a la carnicería')
            ->assertDontSee('Copiar datos para el mandadito');
    }

    public function test_el_detalle_de_ventas_muestra_la_direccion_del_pedido_y_no_la_del_perfil(): void
    {
        $venta = $this->comprar(['referencias_entrega' => 'Portón negro']);

        Livewire::actingAs($this->admin)
            ->test(\App\Http\Livewire\Ventas\VentasController::class)
            ->call('viewDetail', $venta->id)
            ->assertSee('Entregar en')
            ->assertSee('Av. Juárez 25, Centro')
            ->assertSee('Portón negro')
            ->assertSee('Abrir en Google Maps')
            ->assertSee('Aviso del mandadito');
    }

    // ---------- Panel: crear y editar ----------

    public function test_el_panel_guarda_la_direccion_de_un_pedido_por_mandadito(): void
    {
        Mail::fake();

        Livewire::actingAs($this->admin)
            ->test(DespachosController::class)
            ->set('createCustomerId', (string) $this->cliente->id)
            ->set('createTipoEntrega', 'mandadito')
            ->set('createDireccionEntrega', '  Calle Nueva 99  ')
            ->set('createReferenciasEntrega', 'Frente al parque')
            ->set('createUbicacionUrl', 'https://maps.app.goo.gl/Panel')
            ->call('addProductToCart', $this->producto()->id)
            ->call('createOrder')
            ->assertEmitted('pedido-creado');

        $venta = Sale::latest('id')->firstOrFail();
        $this->assertSame('Calle Nueva 99', $venta->direccion_entrega);
        $this->assertSame('Frente al parque', $venta->referencias_entrega);
        $this->assertSame('https://maps.app.goo.gl/Panel', $venta->ubicacion_url);
    }

    public function test_el_panel_rechaza_un_link_que_no_es_de_maps(): void
    {
        Mail::fake();

        Livewire::actingAs($this->admin)
            ->test(DespachosController::class)
            ->set('createCustomerId', (string) $this->cliente->id)
            ->set('createUbicacionUrl', 'https://evil.example.com')
            ->call('addProductToCart', $this->producto()->id)
            ->call('createOrder')
            ->assertEmitted('despacho-error');

        $this->assertSame(0, Sale::count());
    }

    public function test_el_panel_para_recoger_no_guarda_direccion(): void
    {
        Mail::fake();

        Livewire::actingAs($this->admin)
            ->test(DespachosController::class)
            ->set('createCustomerId', (string) $this->cliente->id)
            ->set('createTipoEntrega', 'recoger')
            ->set('createDireccionEntrega', 'Calle Nueva 99')
            ->call('addProductToCart', $this->producto()->id)
            ->call('createOrder')
            ->assertEmitted('pedido-creado');

        $this->assertNull(Sale::latest('id')->firstOrFail()->direccion_entrega);
    }

    public function test_editar_el_pedido_carga_y_cambia_la_direccion(): void
    {
        Mail::fake();
        $venta = $this->comprar();

        Livewire::actingAs($this->admin)
            ->test(DespachosController::class)
            ->call('openEditOrderModal', $venta->id)
            ->assertSet('editDireccionEntrega', 'Av. Juárez 25, Centro')
            ->set('editDireccionEntrega', 'Otra calle 7')
            ->call('updateOrder');

        $this->assertSame('Otra calle 7', $venta->fresh()->direccion_entrega);
    }

    // ---------- Correos ----------

    public function test_el_correo_de_mandadito_lleva_la_direccion_del_pedido_y_el_aviso_del_costo(): void
    {
        Mail::fake();
        $venta = $this->comprar(['referencias_entrega' => 'Portón negro']);
        $venta->update(['transferencia_estado' => 'aprobada', 'estatus' => Sale::ESTATUS_COMPLETADA, 'estado_envio' => Sale::ENVIO_ENVIADO]);

        \App\Services\OrderNotificationService::sendStatusNotification($venta->fresh(['customer', 'details']));

        Mail::assertSent(OrderStatusMail::class, function ($mail) {
            $html = $this->htmlDe($mail);

            return str_contains($html, 'Av. Juárez 25, Centro')
                && ! str_contains($html, 'Calle Vieja 10')
                && str_contains($html, 'Portón negro')
                && str_contains($html, 'Ver en Google Maps')
                && str_contains($html, 'costo aparte');
        });
    }

    public function test_el_correo_de_recoger_trae_como_llegar_con_el_link_de_la_carniceria(): void
    {
        Mail::fake();
        SiteConfig::activa()->update(['direccion' => 'Calle Hidalgo 45', 'ubicacion_url' => 'https://maps.app.goo.gl/Carniceria']);
        $venta = $this->comprar(['tipo_entrega' => 'recoger']);
        $venta->update(['transferencia_estado' => 'aprobada', 'estatus' => Sale::ESTATUS_COMPLETADA, 'estado_envio' => Sale::ENVIO_ENVIADO]);

        \App\Services\OrderNotificationService::sendStatusNotification($venta->fresh(['customer', 'details']));

        Mail::assertSent(OrderStatusMail::class, function ($mail) {
            $html = $this->htmlDe($mail);

            return str_contains($html, 'Cómo llegar')
                && str_contains($html, 'https://maps.app.goo.gl/Carniceria')
                && ! str_contains($html, 'costo aparte');
        });
    }

    // ---------- Sistema, Sitio Web ----------

    private function editarActiva(string $link)
    {
        return Livewire::actingAs($this->admin)->test(SitioController::class)
            ->call('openConfigModal', SiteConfig::activa()->id)
            ->set('configUbicacionUrl', $link)
            ->call('saveConfig');
    }

    public function test_el_admin_captura_el_link_de_maps_de_la_carniceria_y_la_api_lo_devuelve(): void
    {
        $this->editarActiva('  https://maps.app.goo.gl/Carniceria  ')->assertHasNoErrors();

        $this->assertSame('https://maps.app.goo.gl/Carniceria', SiteConfig::activa()->ubicacion_url);
        $this->getJson('/api/v1/sitio/config')
            ->assertOk()
            ->assertJsonPath('data.ubicacion_url', 'https://maps.app.goo.gl/Carniceria')
            ->assertJsonPath('data.enlace_mapa', 'https://maps.app.goo.gl/Carniceria');
    }

    public function test_sin_link_la_api_arma_la_busqueda_con_la_direccion(): void
    {
        SiteConfig::activa()->update(['direccion' => 'Calle Hidalgo 45', 'ubicacion_url' => null]);

        $this->getJson('/api/v1/sitio/config')
            ->assertOk()
            ->assertJsonPath('data.enlace_mapa', 'https://www.google.com/maps/search/?api=1&query=' . urlencode('Calle Hidalgo 45'));
    }

    public function test_el_admin_no_puede_guardar_un_link_que_no_es_de_maps(): void
    {
        $this->editarActiva('https://evil.example.com')->assertHasErrors(['configUbicacionUrl']);
    }

    public function test_vacio_se_guarda_como_nulo(): void
    {
        $this->editarActiva('https://maps.app.goo.gl/Carniceria');
        $this->editarActiva('   ')->assertHasNoErrors();

        $this->assertNull(SiteConfig::activa()->ubicacion_url);
    }
}
