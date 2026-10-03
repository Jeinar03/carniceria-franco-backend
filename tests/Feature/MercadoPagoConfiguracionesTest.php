<?php

namespace Tests\Feature;

use App\Http\Livewire\MercadoPago\MercadoPagoController as MercadoPagoPanel;
use App\Models\MercadoPagoSetting;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Panel Sistema, Mercado Pago: varias configuraciones guardadas (prueba y produccion)
 * y elegir cual esta en uso sin volver a capturar las credenciales.
 */
class MercadoPagoConfiguracionesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite.database', ':memory:');
        DB::purge('sqlite');
        DB::reconnect('sqlite');

        Artisan::call('migrate', ['--force' => true, '--no-interaction' => true]);

        Http::fake(['api.mercadopago.com/users/me' => Http::response(['nickname' => 'CUENTA', 'site_id' => 'MLM'])]);
    }

    private function crear(string $nombre, string $token, bool $sandbox)
    {
        return Livewire::test(MercadoPagoPanel::class)
            ->call('newSetting')
            ->set('name', $nombre)
            ->set('accessToken', $token)
            ->set('publicKey', 'APP_USR-llave-publica-' . $nombre)
            ->set('sandbox', $sandbox ? 1 : 0)
            ->call('save');
    }

    public function test_la_primera_configuracion_queda_en_uso_y_las_siguientes_no(): void
    {
        $this->crear('Produccion', 'APP_USR-token-produccion-0001', false);
        $this->crear('Pruebas', 'APP_USR-token-pruebas-0002', true);

        $this->assertSame('Produccion', MercadoPagoSetting::active()->name);
        $this->assertSame(2, MercadoPagoSetting::count());
    }

    public function test_guardar_una_nueva_no_pisa_las_credenciales_de_la_que_ya_existe(): void
    {
        $this->crear('Produccion', 'APP_USR-token-produccion-0001', false);
        $this->crear('Pruebas', 'APP_USR-token-pruebas-0002', true);

        $produccion = MercadoPagoSetting::where('name', 'Produccion')->first();
        $this->assertSame('APP_USR-token-produccion-0001', $produccion->access_token);
        $this->assertFalse((bool) $produccion->sandbox);
    }

    public function test_usar_cambia_las_credenciales_en_uso_sin_volver_a_capturarlas(): void
    {
        $this->crear('Produccion', 'APP_USR-token-produccion-0001', false);
        $this->crear('Pruebas', 'APP_USR-token-pruebas-0002', true);
        $pruebas = MercadoPagoSetting::where('name', 'Pruebas')->first();

        Livewire::test(MercadoPagoPanel::class)
            ->call('activate', $pruebas->id)
            ->assertEmitted('mercadopago-success');

        $credenciales = MercadoPagoSetting::credentials();
        $this->assertSame('APP_USR-token-pruebas-0002', $credenciales['access_token']);
        $this->assertTrue($credenciales['sandbox']);
        $this->assertSame(1, MercadoPagoSetting::where('active', true)->count());

        // Y de regreso a produccion, de un clic.
        $produccion = MercadoPagoSetting::where('name', 'Produccion')->first();
        Livewire::test(MercadoPagoPanel::class)->call('activate', $produccion->id);

        $credenciales = MercadoPagoSetting::credentials();
        $this->assertSame('APP_USR-token-produccion-0001', $credenciales['access_token']);
        $this->assertFalse($credenciales['sandbox']);
    }

    public function test_no_se_puede_eliminar_la_configuracion_en_uso_pero_si_las_demas(): void
    {
        $this->crear('Produccion', 'APP_USR-token-produccion-0001', false);
        $this->crear('Pruebas', 'APP_USR-token-pruebas-0002', true);
        $enUso = MercadoPagoSetting::active();
        $otra = MercadoPagoSetting::where('active', false)->first();

        Livewire::test(MercadoPagoPanel::class)
            ->call('deleteSetting', $enUso->id)
            ->assertEmitted('mercadopago-error');
        $this->assertNotNull(MercadoPagoSetting::find($enUso->id));

        Livewire::test(MercadoPagoPanel::class)
            ->call('deleteSetting', $otra->id)
            ->assertEmitted('mercadopago-success');
        $this->assertNull(MercadoPagoSetting::find($otra->id));
    }

    public function test_editar_una_configuracion_conserva_su_token_si_se_deja_vacio(): void
    {
        $this->crear('Produccion', 'APP_USR-token-produccion-0001', false);
        $this->crear('Pruebas', 'APP_USR-token-pruebas-0002', true);
        $pruebas = MercadoPagoSetting::where('name', 'Pruebas')->first();

        Livewire::test(MercadoPagoPanel::class)
            ->call('edit', $pruebas->id)
            ->assertSet('settingId', $pruebas->id)
            ->assertSet('sandbox', 1)
            ->set('name', 'Pruebas renombradas')
            ->call('save');

        $pruebas->refresh();
        $this->assertSame('Pruebas renombradas', $pruebas->name);
        $this->assertSame('APP_USR-token-pruebas-0002', $pruebas->access_token);
    }

    public function test_el_panel_avisa_con_claridad_cuando_esta_en_modo_prueba(): void
    {
        $this->crear('Pruebas', 'APP_USR-token-pruebas-0002', true);

        Livewire::test(MercadoPagoPanel::class)->assertSee('MODO PRUEBA activo');

        $this->crear('Produccion', 'APP_USR-token-produccion-0001', false);
        $produccion = MercadoPagoSetting::where('name', 'Produccion')->first();

        Livewire::test(MercadoPagoPanel::class)
            ->call('activate', $produccion->id)
            ->assertDontSee('MODO PRUEBA activo')
            ->assertSee('PRODUCCION:');
    }
}
