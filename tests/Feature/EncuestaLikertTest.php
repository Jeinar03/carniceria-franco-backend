<?php

namespace Tests\Feature;

use App\Models\Customers;
use App\Models\IndicadorPregunta;
use App\Models\IndicadorRespuesta;
use App\Models\Sale;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Encuesta de satisfaccion en escala Likert de 1 a 5.
 * Ver: Carnicería Franco/Spec - Encuesta de satisfaccion en escala Likert 1 a 5.md
 */
class EncuestaLikertTest extends TestCase
{
    private Customers $cliente;
    private Sale $venta;
    private IndicadorPregunta $pregunta;

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
            'estatus' => 'activo',
        ]);

        $this->venta = Sale::create([
            'customer_id' => $this->cliente->id,
            'fecha_venta' => now(),
            'subtotal' => 100,
            'total' => 100,
            'metodo_pago' => 'transferencia',
            'estatus' => Sale::ESTATUS_COMPLETADA,
            'estado_envio' => Sale::ENVIO_ENVIADO,
        ]);

        $this->pregunta = IndicadorPregunta::create([
            'pregunta' => 'Que tan satisfecho estas con la rapidez del proceso?',
            'activo' => true,
            'mostrar_al_finalizar_pedido' => true,
            'orden' => 1,
        ]);

        Sanctum::actingAs($this->cliente, ['cliente']);
    }

    private function responder($valor)
    {
        return $this->postJson("/api/v1/indicadores/pedidos/{$this->venta->id}/respuestas", [
            'respuestas' => [['pregunta_id' => $this->pregunta->id, 'respuesta' => $valor]],
        ]);
    }

    public function test_acepta_respuestas_de_1_a_5(): void
    {
        foreach ([1, 2, 3, 4, 5] as $valor) {
            $this->responder($valor)->assertStatus(201);
            $this->assertSame($valor, (int) IndicadorRespuesta::first()->respuesta);
        }
    }

    public function test_rechaza_respuestas_fuera_de_la_escala(): void
    {
        foreach ([0, 6, 7, 10, -1] as $valor) {
            $this->responder($valor)->assertStatus(422);
        }

        $this->assertSame(0, IndicadorRespuesta::count());
    }

    public function test_la_tienda_recibe_la_escala_de_1_a_5_con_las_etiquetas_de_los_extremos(): void
    {
        $this->getJson("/api/v1/indicadores/pedidos/{$this->venta->id}/preguntas?customer_id={$this->cliente->id}")
            ->assertOk()
            ->assertJsonPath('data.escala.min', 1)
            ->assertJsonPath('data.escala.max', 5)
            ->assertJsonPath('data.escala.etiqueta_min', 'Muy insatisfecho')
            ->assertJsonPath('data.escala.etiqueta_max', 'Muy satisfecho');
    }

    public function test_la_migracion_convierte_las_respuestas_de_1_a_10_a_1_a_5(): void
    {
        // Datos viejos, en escala 1 a 10.
        $esperado = [1 => 1, 2 => 1, 3 => 2, 4 => 2, 5 => 3, 6 => 3, 7 => 4, 8 => 4, 9 => 5, 10 => 5];

        foreach (array_keys($esperado) as $i => $viejo) {
            $venta = Sale::create([
                'customer_id' => $this->cliente->id,
                'fecha_venta' => now(),
                'subtotal' => 1,
                'total' => 1,
                'estado_envio' => Sale::ENVIO_ENVIADO,
            ]);
            DB::table('indicador_respuestas')->insert([
                'pregunta_id' => $this->pregunta->id,
                'sale_id' => $venta->id,
                'customer_id' => $this->cliente->id,
                'respuesta' => $viejo,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        require_once database_path('migrations/2026_09_30_020000_convert_indicador_respuestas_to_likert_1_5.php');
        (new \ConvertIndicadorRespuestasToLikert15())->up();

        $obtenido = DB::table('indicador_respuestas')->orderBy('id')->pluck('respuesta')->map(fn ($v) => (int) $v)->all();
        $this->assertSame(array_values($esperado), $obtenido);
    }
}
