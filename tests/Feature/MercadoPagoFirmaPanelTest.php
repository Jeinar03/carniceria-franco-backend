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
 * Panel Sistema, Mercado Pago: la clave secreta de firma del webhook.
 * Ver: Carnicería Franco/Spec - Firma del webhook de Mercado Pago.md
 */
class MercadoPagoFirmaPanelTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite.database', ':memory:');
        DB::purge('sqlite');
        DB::reconnect('sqlite');

        Artisan::call('migrate', ['--force' => true, '--no-interaction' => true]);

        Http::fake(['api.mercadopago.com/users/me' => Http::response(['nickname' => 'X', 'site_id' => 'MLM'])]);
    }

    private function guardarConClave(string $clave)
    {
        return Livewire::test(MercadoPagoPanel::class)
            ->set('accessToken', 'APP_USR-token-de-prueba-123456')
            ->set('publicKey', 'APP_USR-llave-publica-123456')
            ->set('webhookSecret', $clave)
            ->call('save');
    }

    public function test_la_clave_de_firma_se_guarda_cifrada_y_se_muestra_enmascarada(): void
    {
        $this->guardarConClave('clave-secreta-de-firma-987654')
            ->assertSet('webhookSecret', '')
            ->assertSet('webhookSecretMasked', MercadoPagoSetting::mask('clave-secreta-de-firma-987654'));

        $this->assertSame('clave-secreta-de-firma-987654', MercadoPagoSetting::active()->webhook_secret);
        $this->assertStringNotContainsString(
            'clave-secreta-de-firma-987654',
            (string) DB::table('mercado_pago_settings')->value('webhook_secret')
        );
    }

    public function test_dejar_vacia_la_clave_de_firma_conserva_la_anterior(): void
    {
        $this->guardarConClave('clave-secreta-de-firma-987654');

        Livewire::test(MercadoPagoPanel::class)
            ->set('name', 'Otro nombre')
            ->call('save');

        $this->assertSame('clave-secreta-de-firma-987654', MercadoPagoSetting::active()->webhook_secret);
    }

    public function test_sin_clave_guardada_el_panel_lo_indica(): void
    {
        Livewire::test(MercadoPagoPanel::class)
            ->assertSet('webhookSecretMasked', 'No configurada');
    }

    public function test_la_clave_de_firma_no_aparece_en_la_serializacion_del_modelo(): void
    {
        $setting = MercadoPagoSetting::create([
            'name' => 'Principal',
            'access_token' => 'APP_USR-token-de-prueba-123456',
            'webhook_secret' => 'clave-secreta-de-firma-987654',
        ]);

        $this->assertArrayNotHasKey('webhook_secret', $setting->toArray());
    }
}
