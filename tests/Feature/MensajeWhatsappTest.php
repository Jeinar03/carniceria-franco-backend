<?php

namespace Tests\Feature;

use App\Http\Livewire\Sitio\SitioController;
use App\Models\SiteConfig;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * El mensaje que aparece escrito al abrir WhatsApp desde la tienda se captura en Sistema, Sitio Web.
 */
class MensajeWhatsappTest extends TestCase
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

    private function editarActiva(string $mensaje)
    {
        return Livewire::actingAs($this->admin())->test(SitioController::class)
            ->call('openConfigModal', SiteConfig::activa()->id)
            ->set('configMensajeWhatsapp', $mensaje)
            ->call('saveConfig');
    }

    public function test_el_mensaje_se_guarda_y_la_api_lo_devuelve(): void
    {
        $this->editarActiva('  Hola, quiero pedir arrachera  ')->assertHasNoErrors();

        $this->assertSame('Hola, quiero pedir arrachera', SiteConfig::activa()->mensaje_whatsapp);
        $this->getJson('/api/v1/sitio/config')
            ->assertOk()
            ->assertJsonPath('data.mensaje_whatsapp', 'Hola, quiero pedir arrachera');
    }

    public function test_vacio_se_guarda_como_nulo_para_que_la_tienda_use_el_de_omision(): void
    {
        $this->editarActiva('Algo');
        $this->editarActiva('   ')->assertHasNoErrors();

        $this->assertNull(SiteConfig::activa()->mensaje_whatsapp);
    }

    public function test_no_acepta_mas_de_300_caracteres(): void
    {
        $this->editarActiva(str_repeat('a', 301))->assertHasErrors(['configMensajeWhatsapp' => 'max']);
    }

    public function test_al_editar_se_carga_el_mensaje_guardado(): void
    {
        SiteConfig::activa()->update(['mensaje_whatsapp' => 'Buen dia']);

        Livewire::actingAs($this->admin())->test(SitioController::class)
            ->call('openConfigModal', SiteConfig::activa()->id)
            ->assertSet('configMensajeWhatsapp', 'Buen dia');
    }
}
