<?php

namespace Tests\Feature;

use App\Models\Customers;
use App\Models\Product;
use App\Models\Sale;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Pedidos por mandadito: aviso de costo aparte aceptado por el cliente y direccion de entrega.
 * Spec: "Spec - Direccion de entrega y aviso del mandadito" en el vault.
 */
class DireccionEntregaTest extends TestCase
{
    private Customers $cliente;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite.database', ':memory:');
        DB::purge('sqlite');
        DB::reconnect('sqlite');

        Artisan::call('migrate', ['--force' => true, '--no-interaction' => true]);
        Artisan::call('db:seed', ['--force' => true, '--no-interaction' => true]);

        $this->cliente = Customers::create([
            'nombre' => 'Doña',
            'apellido' => 'Juanita',
            'correo' => 'juanita@test.com',
            'password' => Hash::make('cliente123'),
            'telefono' => '7531234567',
            'direccion' => 'Calle Vieja 10',
            'estatus' => 'activo',
        ]);
    }

    private function producto(): Product
    {
        return Product::where('activo', true)->where('en_oferta', false)->firstOrFail();
    }

    private function comprar(array $extra = [])
    {
        Sanctum::actingAs($this->cliente, ['cliente']);

        return $this->postJson('/api/v1/ventas', array_merge([
            'metodo_pago' => 'transferencia',
            'productos' => [['product_id' => $this->producto()->id, 'cantidad' => 1]],
        ], $extra));
    }

    private function mandadito(array $extra = []): array
    {
        return array_merge([
            'tipo_entrega' => 'mandadito',
            'acepta_aviso_mandadito' => true,
            'direccion_entrega' => 'Av. Juárez 25, Centro',
        ], $extra);
    }

    // ---------- Aviso obligatorio ----------

    public function test_mandadito_sin_aceptar_el_aviso_no_crea_la_venta(): void
    {
        $this->comprar(['tipo_entrega' => 'mandadito', 'direccion_entrega' => 'Av. Juárez 25'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('acepta_aviso_mandadito', 'data');

        $this->assertSame(0, Sale::count());
    }

    public function test_el_aviso_en_falso_tampoco_cuenta(): void
    {
        $this->comprar($this->mandadito(['acepta_aviso_mandadito' => false]))->assertStatus(422);

        $this->assertSame(0, Sale::count());
    }

    public function test_sin_tipo_de_entrega_se_trata_como_mandadito_y_exige_el_aviso(): void
    {
        $this->comprar()->assertStatus(422);

        $this->assertSame(0, Sale::count());
    }

    public function test_mandadito_sin_direccion_no_crea_la_venta(): void
    {
        $this->comprar(['tipo_entrega' => 'mandadito', 'acepta_aviso_mandadito' => true])->assertStatus(422);

        $this->assertSame(0, Sale::count());
    }

    // ---------- Se guarda en la venta ----------

    public function test_se_guarda_la_direccion_las_referencias_el_link_y_la_hora_del_aviso(): void
    {
        $res = $this->comprar($this->mandadito([
            'referencias_entrega' => 'Casa azul, portón negro',
            'ubicacion_url' => 'https://maps.app.goo.gl/AbC123',
        ]))->assertStatus(201);

        $venta = Sale::findOrFail($res->json('data.id'));
        $this->assertSame('Av. Juárez 25, Centro', $venta->direccion_entrega);
        $this->assertSame('Casa azul, portón negro', $venta->referencias_entrega);
        $this->assertSame('https://maps.app.goo.gl/AbC123', $venta->ubicacion_url);
        $this->assertNotNull($venta->aviso_mandadito_aceptado_at);
    }

    public function test_los_links_de_google_maps_validos_se_aceptan(): void
    {
        foreach ([
            'https://maps.app.goo.gl/AbC123',
            'https://goo.gl/maps/AbC123',
            'https://www.google.com/maps/place/Carniceria/@19.4,-99.1,17z',
            'https://google.com/maps?q=19.4,-99.1',
            'https://maps.google.com/?q=19.4,-99.1',
            'https://www.google.com.mx/maps/@19.4,-99.1,15z',
        ] as $link) {
            $this->comprar($this->mandadito(['ubicacion_url' => $link]))->assertStatus(201);
        }
    }

    public function test_un_link_que_no_es_de_google_maps_se_rechaza(): void
    {
        foreach ([
            'https://evil.example.com/maps',
            'https://google.com.evil.com/maps/x',
            'https://www.google.com/search?q=maps',
            'https://goo.gl/otra-cosa',
            'javascript:alert(1)',
            'no es un link',
        ] as $link) {
            $this->comprar($this->mandadito(['ubicacion_url' => $link]))
                ->assertStatus(422)
                ->assertJsonValidationErrors('ubicacion_url', 'data');
        }

        $this->assertSame(0, Sale::count());
    }

    public function test_el_link_es_opcional(): void
    {
        $res = $this->comprar($this->mandadito(['ubicacion_url' => '']))->assertStatus(201);

        $this->assertNull(Sale::findOrFail($res->json('data.id'))->ubicacion_url);
    }

    // ---------- Recoger ----------

    public function test_recoger_no_pide_aviso_ni_direccion(): void
    {
        $res = $this->comprar(['tipo_entrega' => 'recoger'])->assertStatus(201);

        $venta = Sale::findOrFail($res->json('data.id'));
        $this->assertSame('recoger', $venta->tipo_entrega);
        $this->assertNull($venta->direccion_entrega);
        $this->assertNull($venta->aviso_mandadito_aceptado_at);
    }

    public function test_recoger_ignora_la_direccion_si_la_mandan(): void
    {
        $res = $this->comprar([
            'tipo_entrega' => 'recoger',
            'direccion_entrega' => 'Otra calle 5',
            'guardar_como_mi_direccion' => true,
        ])->assertStatus(201);

        $this->assertNull(Sale::findOrFail($res->json('data.id'))->direccion_entrega);
        $this->assertSame('Calle Vieja 10', $this->cliente->fresh()->direccion);
    }

    // ---------- Guardar como mi direccion ----------

    public function test_guardar_como_mi_direccion_actualiza_el_perfil(): void
    {
        $this->comprar($this->mandadito([
            'referencias_entrega' => 'Frente al parque',
            'ubicacion_url' => 'https://maps.app.goo.gl/AbC123',
            'guardar_como_mi_direccion' => true,
        ]))->assertStatus(201);

        $cliente = $this->cliente->fresh();
        $this->assertSame('Av. Juárez 25, Centro', $cliente->direccion);
        $this->assertSame('Frente al parque', $cliente->referencias);
        $this->assertSame('https://maps.app.goo.gl/AbC123', $cliente->ubicacion_url);
    }

    public function test_sin_la_casilla_el_perfil_no_cambia(): void
    {
        $this->comprar($this->mandadito())->assertStatus(201);

        $this->assertSame('Calle Vieja 10', $this->cliente->fresh()->direccion);
    }

    // La misma regla para Mercado Pago se prueba en MercadoPagoFlowTest (necesita su controlador falso).

    // ---------- Registro y perfil ----------

    public function test_el_registro_exige_telefono(): void
    {
        $this->postJson('/api/v1/clientes/registro', [
            'nombre' => 'Ana',
            'apellido' => 'Lopez',
            'correo' => 'ana@test.com',
            'password' => 'secreto123',
        ])->assertStatus(422)->assertJsonValidationErrors('telefono', 'data');

        $this->assertDatabaseMissing('customers', ['correo' => 'ana@test.com']);
    }

    public function test_el_registro_guarda_direccion_referencias_y_link(): void
    {
        $this->postJson('/api/v1/clientes/registro', [
            'nombre' => 'Ana',
            'apellido' => 'Lopez',
            'correo' => 'ana@test.com',
            'password' => 'secreto123',
            'telefono' => '7531112233',
            'direccion' => 'Calle Luna 3',
            'referencias' => 'Tienda de la esquina',
            'ubicacion_url' => 'https://maps.app.goo.gl/XyZ',
        ])->assertStatus(201);

        $this->assertDatabaseHas('customers', [
            'correo' => 'ana@test.com',
            'direccion' => 'Calle Luna 3',
            'referencias' => 'Tienda de la esquina',
            'ubicacion_url' => 'https://maps.app.goo.gl/XyZ',
        ]);
    }

    public function test_el_registro_rechaza_un_link_que_no_es_de_maps(): void
    {
        $this->postJson('/api/v1/clientes/registro', [
            'nombre' => 'Ana',
            'apellido' => 'Lopez',
            'correo' => 'ana@test.com',
            'password' => 'secreto123',
            'telefono' => '7531112233',
            'ubicacion_url' => 'https://evil.example.com',
        ])->assertStatus(422)->assertJsonValidationErrors('ubicacion_url', 'data');
    }

    public function test_el_perfil_actualiza_referencias_y_link(): void
    {
        Sanctum::actingAs($this->cliente, ['cliente']);

        $this->putJson("/api/v1/clientes/{$this->cliente->id}", [
            'referencias' => 'Portón verde',
            'ubicacion_url' => 'https://www.google.com/maps/place/Casa',
        ])->assertStatus(200);

        $cliente = $this->cliente->fresh();
        $this->assertSame('Portón verde', $cliente->referencias);
        $this->assertSame('https://www.google.com/maps/place/Casa', $cliente->ubicacion_url);
    }
}
