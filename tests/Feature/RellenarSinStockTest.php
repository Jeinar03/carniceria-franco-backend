<?php

namespace Tests\Feature;

use App\Http\Livewire\Inventario\InventarioController;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Boton "Rellenar" del desplegable Sin stock: lleva a Inventario con la ventana
 * de Registrar entrada abierta y el producto buscado.
 * Ver: Carnicería Franco/Spec - Aviso de pedidos por terminar.md (seccion Rellenar)
 */
class RellenarSinStockTest extends TestCase
{
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

        $this->admin = User::create([
            'name' => 'Admin',
            'email' => 'admin@test.com',
            'password' => Hash::make('secret'),
        ]);
        $this->admin->assignRole('Admin');
    }

    /** Producto activo sin ningun movimiento de inventario: cuenta como "sin stock". */
    private function productoSinStock(array $extra = []): Product
    {
        $producto = Product::first()->replicate();
        $producto->fill(array_merge([
            'codigo' => 'SIN-STOCK-1',
            'nombre' => 'Producto sin existencia',
            'activo' => true,
        ], $extra));
        $producto->save();

        return $producto;
    }

    public function test_el_desplegable_sin_stock_tiene_el_boton_rellenar_por_producto(): void
    {
        $producto = $this->productoSinStock();

        $this->actingAs($this->admin)
            ->get('/clientes/despachos')
            ->assertStatus(200)
            ->assertSee('Producto sin existencia')
            ->assertSee('Rellenar')
            ->assertSee('admin/inventario?rellenar=' . $producto->id, false);
    }

    public function test_con_rellenar_la_ventana_de_entrada_queda_abierta_con_el_producto_buscado(): void
    {
        $producto = $this->productoSinStock();

        Livewire::withQueryParams(['rellenar' => $producto->id])
            ->actingAs($this->admin)
            ->test(InventarioController::class)
            ->assertSet('abrirEntrada', true)
            ->assertSet('productSearch', 'SIN-STOCK-1')
            ->assertSee('Producto sin existencia');
    }

    public function test_la_pagina_abre_la_ventana_sola_solo_cuando_viene_rellenar(): void
    {
        $producto = $this->productoSinStock();

        $this->actingAs($this->admin)
            ->get('/admin/inventario?rellenar=' . $producto->id)
            ->assertStatus(200)
            ->assertSee('auto-abrir entrada desde Sin stock', false);

        $this->actingAs($this->admin)
            ->get('/admin/inventario')
            ->assertStatus(200)
            ->assertDontSee('auto-abrir entrada desde Sin stock', false);
    }

    public function test_sin_el_parametro_no_se_abre_nada_y_el_buscador_queda_vacio(): void
    {
        Livewire::actingAs($this->admin)
            ->test(InventarioController::class)
            ->assertSet('abrirEntrada', false)
            ->assertSet('productSearch', '');
    }

    public function test_un_id_que_no_existe_o_no_es_numerico_se_ignora(): void
    {
        foreach (['999999', 'abc', '0', '-5'] as $valor) {
            Livewire::withQueryParams(['rellenar' => $valor])
                ->actingAs($this->admin)
                ->test(InventarioController::class)
                ->assertSet('abrirEntrada', false)
                ->assertSet('productSearch', '');
        }
    }

    public function test_un_producto_inactivo_se_ignora(): void
    {
        $producto = $this->productoSinStock(['activo' => false]);

        Livewire::withQueryParams(['rellenar' => $producto->id])
            ->actingAs($this->admin)
            ->test(InventarioController::class)
            ->assertSet('abrirEntrada', false);
    }

    public function test_sin_codigo_busca_por_nombre(): void
    {
        $producto = $this->productoSinStock(['codigo' => null]);

        Livewire::withQueryParams(['rellenar' => $producto->id])
            ->actingAs($this->admin)
            ->test(InventarioController::class)
            ->assertSet('abrirEntrada', true)
            ->assertSet('productSearch', 'Producto sin existencia');
    }

    public function test_guardar_la_entrada_desde_rellenar_sube_el_stock_como_siempre(): void
    {
        $producto = $this->productoSinStock();

        Livewire::withQueryParams(['rellenar' => $producto->id])
            ->actingAs($this->admin)
            ->test(InventarioController::class)
            ->set('entryQuantities.' . $producto->id, 5)
            ->call('saveEntries')
            ->assertHasNoErrors();

        $stock = Product::withInventoryStock()->find($producto->id)->inventory_stock;
        $this->assertEquals(5, (float) $stock);
    }
}
