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
 * Panel Sistema → Mercado Pago: al guardar se comprueba el Access Token contra Mercado Pago.
 */
class MercadoPagoSettingsTest extends TestCase
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

    private function guardar(array $respuestaMp, int $status = 200)
    {
        Http::fake(['api.mercadopago.com/users/me' => Http::response($respuestaMp, $status)]);

        return Livewire::test(MercadoPagoPanel::class)
            ->set('accessToken', 'APP_USR-token-de-prueba-123456')
            ->set('publicKey', 'APP_USR-llave-publica-123456')
            ->call('save');
    }

    public function test_un_token_valido_se_guarda_cifrado_y_muestra_la_cuenta_conectada(): void
    {
        $this->guardar(['id' => 1, 'nickname' => 'TESTUSER123', 'site_id' => 'MLM'])
            ->assertEmitted('mercadopago-success');

        $this->assertSame('APP_USR-token-de-prueba-123456', MercadoPagoSetting::active()->access_token);
        $this->assertStringNotContainsString(
            'APP_USR-token-de-prueba-123456',
            DB::table('mercado_pago_settings')->value('access_token')
        );
        Http::assertSent(fn ($request) => $request->hasHeader('Authorization', 'Bearer APP_USR-token-de-prueba-123456'));
    }

    public function test_un_token_rechazado_se_guarda_pero_avisa_del_error(): void
    {
        $this->guardar(['message' => 'invalid_token'], 401)
            ->assertEmitted('mercadopago-error');

        $this->assertNotNull(MercadoPagoSetting::active());
    }
}
