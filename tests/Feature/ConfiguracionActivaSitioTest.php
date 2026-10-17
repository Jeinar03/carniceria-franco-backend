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
 * Siempre debe quedar una sola configuracion del sitio activa.
 * En produccion habia dos activas; se agrega Desactivar y una migracion que las limpia.
 */
class ConfiguracionActivaSitioTest extends TestCase
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

    private function panel()
    {
        return Livewire::actingAs($this->admin())->test(SitioController::class);
    }

    /** Deja dos configuraciones activas, como estaba produccion. */
    private function dosActivas(): array
    {
        SiteConfig::query()->delete();
        $vieja = SiteConfig::create(['nombre' => 'Vieja', 'activo' => true]);
        $nueva = SiteConfig::create(['nombre' => 'Nueva', 'activo' => true]);
        DB::table('site_configs')->where('id', $vieja->id)->update(['updated_at' => now()->subDay()]);

        return [$vieja, $nueva];
    }

    private function totalActivas(): int
    {
        return SiteConfig::where('activo', true)->count();
    }

    public function test_con_dos_activas_se_puede_desactivar_una(): void
    {
        [$vieja] = $this->dosActivas();

        $this->panel()
            ->assertSee('deactivateConfig(' . $vieja->id . ')')
            ->call('deactivateConfig', $vieja->id)
            ->assertEmitted('sitio-success');

        $this->assertFalse($vieja->fresh()->activo);
        $this->assertSame(1, $this->totalActivas());
    }

    public function test_la_ultima_activa_no_se_puede_desactivar(): void
    {
        $activa = SiteConfig::activa();

        $this->panel()
            ->assertDontSee('deactivateConfig(')
            ->call('deactivateConfig', $activa->id)
            ->assertEmitted('sitio-error');

        $this->assertTrue($activa->fresh()->activo);
    }

    public function test_activar_otra_deja_solo_esa_activa(): void
    {
        $otra = SiteConfig::create(['nombre' => 'Otra']);

        $this->panel()->call('activateConfig', $otra->id);

        $this->assertTrue($otra->fresh()->activo);
        $this->assertSame(1, $this->totalActivas());
    }

    public function test_una_nueva_queda_activa_solo_si_no_habia_ninguna(): void
    {
        $this->panel()->call('openConfigModal')->set('configNombre', 'Segunda')->call('saveConfig');
        $this->assertFalse(SiteConfig::where('nombre', 'Segunda')->firstOrFail()->activo);
        $this->assertSame(1, $this->totalActivas());

        SiteConfig::query()->update(['activo' => false]);

        $this->panel()->call('openConfigModal')->set('configNombre', 'Tercera')->call('saveConfig');
        $this->assertTrue(SiteConfig::where('nombre', 'Tercera')->firstOrFail()->activo);
    }

    public function test_la_migracion_deja_activa_solo_la_que_usa_la_tienda(): void
    {
        [$vieja, $nueva] = $this->dosActivas();
        $this->assertSame($nueva->id, SiteConfig::activa()->id);

        (new \DejarUnaSolaConfiguracionActivaDelSitio)->up();

        $this->assertTrue($nueva->fresh()->activo);
        $this->assertFalse($vieja->fresh()->activo);
        $this->assertSame(1, $this->totalActivas());
    }
}
