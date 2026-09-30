<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Bootstrap debe cargarse una sola vez en el panel. Si se carga dos veces, cada
 * desplegable (Sin stock, Por terminar) registra dos manejadores de clic y se abre
 * y se cierra al instante.
 */
class LayoutScriptsTest extends TestCase
{
    public function test_el_panel_carga_bootstrap_una_sola_vez(): void
    {
        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite.database', ':memory:');
        DB::purge('sqlite');
        DB::reconnect('sqlite');
        Artisan::call('migrate', ['--force' => true, '--no-interaction' => true]);
        Artisan::call('db:seed', ['--force' => true, '--no-interaction' => true]);

        $user = User::create(['name' => 'Admin', 'email' => 'admin@test.com', 'password' => Hash::make('secret')]);
        $user->assignRole('Admin');

        $html = $this->actingAs($user)->get('/clientes/despachos')->getContent();

        preg_match_all('#<script[^>]+src="[^"]*/bootstrap(?:\.bundle)?(?:\.min)?\.js"#i', $html, $bootstrap);
        $this->assertCount(1, $bootstrap[0], 'Bootstrap debe aparecer una sola vez en la pagina');
    }
}
