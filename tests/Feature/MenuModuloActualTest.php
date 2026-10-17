<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * El menu lateral marca el modulo en el que esta el usuario,
 * para que al abrir el panel sepa donde se encuentra.
 */
class MenuModuloActualTest extends TestCase
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

    private function admin(): User
    {
        $admin = User::create(['name' => 'Admin', 'email' => 'admin@test.com', 'password' => Hash::make('secret'), 'profile' => 'ADMIN', 'status' => 'ACTIVE']);
        $admin->assignRole('Admin');

        return $admin;
    }

    public function test_en_sitio_web_se_marcan_sistema_y_sitio_web(): void
    {
        $html = $this->actingAs($this->admin())->get('/sistema/sitio')->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/<li class="menu active">\s*<a href="#sistema"/', $html);
        $this->assertMatchesRegularExpression('/<div class="submenu show" id="sistema">/', $html);
        $this->assertMatchesRegularExpression('/<li class="active">\s*<a href="[^"]*\/sistema\/sitio"/', $html);
        $this->assertSame(1, substr_count($html, '<li class="menu active">'));
        $this->assertSame(1, substr_count($html, '<li class="active">'));
    }

    public function test_en_despachos_se_marca_despachos_y_no_clientes(): void
    {
        $html = $this->actingAs($this->admin())->get('/clientes/despachos')->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/<li class="menu active">\s*<a href="#clientes"/', $html);
        $this->assertMatchesRegularExpression('/<li class="active">\s*<a href="[^"]*\/clientes\/despachos"/', $html);
        $this->assertSame(1, substr_count($html, '<li class="active">'));
    }
}
