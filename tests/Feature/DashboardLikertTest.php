<?php

namespace Tests\Feature;

use App\Http\Livewire\Dash;
use App\Models\Customers;
use App\Models\IndicadorPregunta;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * El Dashboard mide la satisfaccion sobre la escala Likert de 1 a 5.
 * Usa MySQL porque el dashboard emplea TIMESTAMPDIFF.
 */
class DashboardLikertTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('database.default', 'mysql');
        config()->set('database.connections.mysql.database', 'carniceria_test');

        try {
            $admin = new \PDO(
                sprintf('mysql:host=%s;port=%s', config('database.connections.mysql.host'), config('database.connections.mysql.port')),
                config('database.connections.mysql.username'),
                (string) config('database.connections.mysql.password')
            );
            $admin->exec('CREATE DATABASE IF NOT EXISTS carniceria_test CHARACTER SET utf8mb4');
        } catch (\Throwable $e) {
            $this->markTestSkipped('MySQL no disponible: ' . $e->getMessage());
        }

        DB::purge('mysql');
        DB::reconnect('mysql');
        Artisan::call('migrate:fresh', ['--force' => true, '--no-interaction' => true]);
        Artisan::call('db:seed', ['--force' => true, '--no-interaction' => true]);
    }

    private function responder(IndicadorPregunta $pregunta, Customers $cliente, int $valor): void
    {
        $venta = Sale::create([
            'customer_id' => $cliente->id,
            'fecha_venta' => now(),
            'subtotal' => 1,
            'total' => 1,
            'estado_envio' => Sale::ENVIO_ENVIADO,
        ]);
        DB::table('indicador_respuestas')->insert([
            'pregunta_id' => $pregunta->id,
            'sale_id' => $venta->id,
            'customer_id' => $cliente->id,
            'respuesta' => $valor,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_el_dashboard_calcula_promedio_distribucion_y_aceptacion_en_escala_1_a_5(): void
    {
        $cliente = Customers::create([
            'nombre' => 'C', 'apellido' => 'X', 'correo' => 'c@test.com',
            'password' => Hash::make('cliente123'), 'estatus' => 'activo',
        ]);
        $general = IndicadorPregunta::create(['pregunta' => 'Que tan satisfecho estas?', 'activo' => true, 'mostrar_al_finalizar_pedido' => true, 'orden' => 1]);
        $recomendaciones = IndicadorPregunta::create(['pregunta' => 'Las recomendaciones fueron acertadas', 'activo' => true, 'mostrar_al_finalizar_pedido' => true, 'orden' => 2]);

        foreach ([5, 4, 3, 1] as $valor) {
            $this->responder($general, $cliente, $valor);
        }
        // Recomendaciones: 2 aceptadas (4 y 5) de 4 respuestas.
        foreach ([5, 4, 3, 2] as $valor) {
            $this->responder($recomendaciones, $cliente, $valor);
        }

        $admin = User::create(['name' => 'Admin', 'email' => 'a@test.com', 'password' => Hash::make('secret')]);
        $admin->assignRole('Admin');

        $componente = Livewire::actingAs($admin)->test(Dash::class);

        $this->assertCount(5, $componente->get('satisfactionDistributionData'));
        // 8 respuestas: 1 uno, 1 dos, 2 tres, 2 cuatros, 2 cincos.
        $this->assertSame([1, 1, 2, 2, 2], $componente->get('satisfactionDistributionData'));
        $this->assertEqualsWithDelta(3.4, $componente->get('kpis')['satisfaccion_promedio'], 0.05);
        $this->assertEqualsWithDelta(50.0, $componente->get('kpis')['aceptacion_recomendaciones'], 0.05);
    }

    public function test_el_dashboard_muestra_la_escala_1_a_5(): void
    {
        $admin = User::create(['name' => 'Admin', 'email' => 'a@test.com', 'password' => Hash::make('secret')]);
        $admin->assignRole('Admin');

        $this->actingAs($admin)
            ->get('/home')
            ->assertOk()
            ->assertSee('1 - 5')
            ->assertDontSee('1 - 10');
    }
}
