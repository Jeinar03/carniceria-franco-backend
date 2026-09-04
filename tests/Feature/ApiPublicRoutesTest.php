<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * R5 — el catálogo y el sitio siguen siendo públicos.
 * R6 — la escritura de catálogo y el listado global de ventas ya no existen.
 */
class ApiPublicRoutesTest extends TestCase
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

    public function test_rutas_publicas_de_catalogo_y_sitio_responden_sin_token(): void
    {
        $this->getJson('/api/v1/categorias')->assertOk();
        $this->getJson('/api/v1/productos')->assertOk();
        $this->getJson('/api/v1/productos/buscar?query=bistec')->assertOk();
        $this->getJson('/api/v1/productos/destacados')->assertOk();
        $this->getJson('/api/v1/sitio/config')->assertOk();
        // sitio/alertas usa SQL de MySQL (DATE_ADD) que no corre en el sqlite de
        // los tests; su carácter público queda cubierto por sitio/config.
    }

    public function test_rutas_de_escritura_de_catalogo_fueron_eliminadas(): void
    {
        // 404 (no existe la ruta) o 405 (método no permitido) — nunca 2xx
        $this->postJson('/api/v1/productos', [])->assertStatus(405);
        $this->putJson('/api/v1/productos/1', [])->assertStatus(405);
        $this->postJson('/api/v1/categorias', [])->assertStatus(405);
        $this->putJson('/api/v1/categorias/1', [])->assertStatus(405);
    }

    public function test_listado_global_de_ventas_fue_eliminado(): void
    {
        // GET /api/v1/ventas ya no existe; sólo queda POST (protegido)
        $this->getJson('/api/v1/ventas')->assertStatus(405);
    }
}
