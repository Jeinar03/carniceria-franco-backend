<?php

namespace Tests\Feature;

use App\Http\Livewire\Sitio\SitioController;
use App\Mail\OrderStatusMail;
use App\Models\Customers;
use App\Models\Sale;
use App\Models\SaleDetail;
use App\Models\SiteConfig;
use App\Models\User;
use App\Rules\ClabeInterbancaria;
use App\Services\OrderNotificationService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Datos bancarios para transferencias: se capturan en Sistema, Sitio Web y se muestran al cliente.
 * Ver: Carnicería Franco/Spec - Datos bancarios para transferencias.md
 */
class DatosBancariosTest extends TestCase
{
    /** CLABE con digito verificador correcto. */
    private const CLABE = '012180001183597172';

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

    private ?User $adminCreado = null;

    private function admin(): User
    {
        if ($this->adminCreado) {
            return $this->adminCreado;
        }

        $admin = User::create(['name' => 'Admin', 'email' => 'admin@test.com', 'password' => Hash::make('secret'), 'profile' => 'ADMIN', 'status' => 'ACTIVE']);
        $admin->assignRole('Admin');

        return $this->adminCreado = $admin;
    }

    /** Formulario de Sitio Web con los campos obligatorios llenos y los bancarios que se pasen. */
    private function guardar(array $bancarios)
    {
        $c = Livewire::actingAs($this->admin())->test(SitioController::class)
            ->call('openConfigModal')
            ->set('configNombre', 'Carniceria Franco');

        foreach ($bancarios as $campo => $valor) {
            $c->set($campo, $valor);
        }

        return $c->call('saveConfig');
    }

    /** El seeder ya crea una configuracion del sitio: la que guarda cada prueba es la ultima. */
    private function ultima(): SiteConfig
    {
        return SiteConfig::orderByDesc('id')->firstOrFail();
    }

    private function configActiva(): SiteConfig
    {
        $config = $this->ultima();
        SiteConfig::query()->update(['activo' => false]);
        $config->update(['activo' => true]);

        return $config->fresh();
    }

    private function crearConfigActiva(array $datos): void
    {
        SiteConfig::query()->update(['activo' => false]);
        SiteConfig::create(array_merge(['nombre' => 'Carniceria', 'activo' => true], $datos));
    }

    // ---- Regla de la CLABE ----

    public function test_la_regla_acepta_una_clabe_valida_y_rechaza_las_demas(): void
    {
        $valida = fn ($v) => Validator::make(['c' => $v], ['c' => [new ClabeInterbancaria()]])->passes();

        $this->assertTrue($valida(self::CLABE));
        $this->assertTrue($valida('032180000118359748'));
        $this->assertFalse($valida('012180001183597173'), 'digito verificador malo');
        $this->assertFalse($valida('01218000118359717'), '17 digitos');
        $this->assertFalse($valida('0121800011835971722'), '19 digitos');
        $this->assertFalse($valida('01218000118359717A'), 'con letra');
        $this->assertFalse($valida(''.'abc'), 'texto');
    }

    // ---- Captura en Sitio Web ----

    public function test_se_guardan_los_datos_bancarios_y_la_api_publica_los_devuelve(): void
    {
        $this->guardar([
            'configBanco' => 'BBVA',
            'configTitular' => 'Juan Perez Lopez',
            'configNumeroCuenta' => '1183597172',
            'configClabe' => self::CLABE,
        ])->assertHasNoErrors();

        $this->configActiva();

        $this->getJson('/api/v1/sitio/config')
            ->assertOk()
            ->assertJsonPath('data.banco', 'BBVA')
            ->assertJsonPath('data.titular_cuenta', 'Juan Perez Lopez')
            ->assertJsonPath('data.numero_cuenta', '1183597172')
            ->assertJsonPath('data.clabe', self::CLABE);
    }

    public function test_sin_datos_bancarios_la_api_los_devuelve_vacios(): void
    {
        $this->guardar([])->assertHasNoErrors();
        $this->configActiva();

        $this->getJson('/api/v1/sitio/config')
            ->assertOk()
            ->assertJsonPath('data.banco', null)
            ->assertJsonPath('data.titular_cuenta', null)
            ->assertJsonPath('data.numero_cuenta', null)
            ->assertJsonPath('data.clabe', null);
    }

    public function test_una_clabe_mala_se_rechaza(): void
    {
        $antes = SiteConfig::count();

        foreach (['01218000118359717', '0121800011835971722', '01218000118359717A', '012180001183597173'] as $mala) {
            $this->guardar(['configClabe' => $mala])->assertHasErrors(['configClabe']);
        }

        $this->assertSame($antes, SiteConfig::count());
    }

    public function test_clabe_y_cuenta_con_espacios_o_guiones_se_limpian_y_se_aceptan(): void
    {
        $this->guardar([
            'configNumeroCuenta' => '1183 5971-72',
            'configClabe' => '0121 8000 1183 5971 72',
        ])->assertHasNoErrors();

        $config = $this->ultima();
        $this->assertSame('1183597172', $config->numero_cuenta);
        $this->assertSame(self::CLABE, $config->clabe);
    }

    public function test_el_numero_de_cuenta_solo_lleva_digitos(): void
    {
        $this->guardar(['configNumeroCuenta' => '11835ABC72'])->assertHasErrors(['configNumeroCuenta']);
        $this->guardar(['configNumeroCuenta' => '123'])->assertHasErrors(['configNumeroCuenta']);
    }

    public function test_los_datos_bancarios_son_opcionales(): void
    {
        $this->guardar(['configBanco' => 'BBVA'])->assertHasNoErrors();

        $config = $this->ultima();
        $this->assertSame('BBVA', $config->banco);
        $this->assertNull($config->clabe);
        $this->assertNull($config->numero_cuenta);
    }

    public function test_al_editar_se_cargan_los_datos_bancarios_guardados(): void
    {
        $this->guardar(['configBanco' => 'BBVA', 'configTitular' => 'Juan Perez', 'configClabe' => self::CLABE]);
        $config = $this->ultima();

        Livewire::actingAs($this->admin())->test(SitioController::class)
            ->call('openConfigModal', $config->id)
            ->assertSet('configBanco', 'BBVA')
            ->assertSet('configTitular', 'Juan Perez')
            ->assertSet('configClabe', self::CLABE);
    }

    public function test_aviso_del_whatsapp_dice_diez_digitos_sin_el_codigo_del_pais(): void
    {
        $this->actingAs($this->admin())->get('/sistema/sitio')
            ->assertOk()
            ->assertSee('10 dígitos')
            ->assertDontSee('5491112345678');
    }

    // ---- Correo de transferencia pendiente ----

    private function ventaTransferencia(string $estadoTransferencia = null): Sale
    {
        $cliente = new Customers(['nombre' => 'Maria', 'apellido' => 'Lopez', 'correo' => 'maria@example.com']);
        $venta = new Sale(['folio' => 'V-0042', 'total' => 100, 'metodo_pago' => 'transferencia', 'transferencia_estado' => $estadoTransferencia]);
        $venta->fecha_venta = now();
        $venta->setRelation('details', collect([new SaleDetail(['producto_nombre' => 'Arrachera', 'cantidad' => 1, 'unidad_venta' => 'kg', 'precio_unitario' => 100, 'total' => 100])]));
        $venta->setRelation('customer', $cliente);

        return $venta;
    }

    private function datosEnElSitio(): void
    {
        $this->crearConfigActiva(['banco' => 'BBVA', 'titular_cuenta' => 'Juan Perez Lopez', 'numero_cuenta' => '1183597172', 'clabe' => self::CLABE]);
    }

    /** Ejecuta la accion con el correo falso y devuelve el HTML del correo que se habria enviado. */
    private function correoEnviado(callable $accion): string
    {
        Mail::fake();
        $accion();

        $enviado = null;
        Mail::assertSent(OrderStatusMail::class, function (OrderStatusMail $mail) use (&$enviado) {
            $enviado = $mail;

            return true;
        });

        // Mail::fake() reemplaza al servicio que dibuja el correo: se dibuja su vista directamente.
        $enviado->build();

        return view($enviado->view, $enviado->viewData)->render();
    }

    public function test_el_correo_de_transferencia_pendiente_trae_los_datos_y_la_leyenda_del_folio(): void
    {
        $this->datosEnElSitio();

        $html = $this->correoEnviado(fn () => OrderNotificationService::sendPurchaseCompletedNotification($this->ventaTransferencia()));

        foreach (['BBVA', 'Juan Perez Lopez', '1183597172', self::CLABE, 'V-0042'] as $dato) {
            $this->assertStringContainsString($dato, $html);
        }
        $this->assertStringContainsString('concepto', $html);
    }

    public function test_el_correo_de_transferencia_pendiente_sin_datos_no_muestra_el_recuadro(): void
    {
        $this->crearConfigActiva([]);

        $html = $this->correoEnviado(fn () => OrderNotificationService::sendPurchaseCompletedNotification($this->ventaTransferencia()));

        $this->assertStringContainsString('Recibimos tu pedido', $html);
        $this->assertStringNotContainsString('CLABE', $html);
        $this->assertStringNotContainsString('concepto', $html);
    }

    public function test_solo_los_campos_capturados_se_muestran(): void
    {
        $this->crearConfigActiva(['clabe' => self::CLABE]);

        $html = $this->correoEnviado(fn () => OrderNotificationService::sendPurchaseCompletedNotification($this->ventaTransferencia()));

        $this->assertStringContainsString(self::CLABE, $html);
        $this->assertStringNotContainsString('Titular', $html);
        $this->assertStringNotContainsString('Número de cuenta', $html);
    }

    public function test_los_otros_correos_no_traen_los_datos_bancarios(): void
    {
        $this->datosEnElSitio();

        $aprobada = $this->correoEnviado(fn () => OrderNotificationService::sendTransferApprovedNotification($this->ventaTransferencia('aprobada')));
        $this->assertStringNotContainsString(self::CLABE, $aprobada);

        $rechazada = $this->correoEnviado(fn () => OrderNotificationService::sendTransferRejectedNotification($this->ventaTransferencia('rechazada')));
        $this->assertStringNotContainsString(self::CLABE, $rechazada);
    }
}
