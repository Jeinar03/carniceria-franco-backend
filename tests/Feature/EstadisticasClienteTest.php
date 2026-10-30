<?php

namespace Tests\Feature;

use App\Models\Customers;
use App\Models\Sale;
use App\Services\CancelacionVentaService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Total gastado y Compras realizadas del cliente: solo cuentan ventas pagadas (completada o entregada).
 */
class EstadisticasClienteTest extends TestCase
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

    private function cliente(): Customers
    {
        return Customers::create([
            'nombre' => 'C',
            'apellido' => 'X',
            'correo' => 'stats@test.com',
            'password' => Hash::make('cliente123'),
            'estatus' => 'activo',
        ]);
    }

    private function venta(Customers $cliente, string $estatus, float $total, string $metodo = 'transferencia'): Sale
    {
        return Sale::create([
            'customer_id' => $cliente->id,
            'fecha_venta' => now(),
            'metodo_pago' => $metodo,
            'estatus' => $estatus,
            'estado_envio' => 'Pendiente',
            'subtotal' => $total,
            'total' => $total,
        ]);
    }

    public function test_una_transferencia_sin_pagar_no_suma_hasta_que_se_aprueba(): void
    {
        $cliente = $this->cliente();
        $sale = $this->venta($cliente, 'pendiente', 100);

        $cliente->refresh();
        $this->assertEquals(0, $cliente->total_compras);
        $this->assertSame(0, $cliente->numero_compras);

        $sale->estatus = 'completada';
        $sale->save();

        $cliente->refresh();
        $this->assertEquals(100, $cliente->total_compras);
        $this->assertSame(1, $cliente->numero_compras);
        $this->assertNotNull($cliente->fecha_ultima_compra);
    }

    public function test_un_mercado_pago_sin_pagar_no_suma_y_al_pagarse_si(): void
    {
        $cliente = $this->cliente();
        $sale = $this->venta($cliente, 'pendiente', 50, 'mercado_pago');

        $this->assertEquals(0, $cliente->fresh()->total_compras);

        $sale->update(['estatus' => 'completada']);

        $this->assertEquals(50, $cliente->fresh()->total_compras);
    }

    public function test_cancelar_una_venta_pagada_resta_y_cancelar_una_sin_pagar_no_toca_nada(): void
    {
        $cliente = $this->cliente();
        $pagada = $this->venta($cliente, 'completada', 100);
        $sinPagar = $this->venta($cliente, 'pendiente', 70);

        $this->assertEquals(100, $cliente->fresh()->total_compras);

        app(CancelacionVentaService::class)->cancelarPorAdmin($sinPagar->id, 1);
        $this->assertEquals(100, $cliente->fresh()->total_compras);
        $this->assertSame(1, $cliente->fresh()->numero_compras);

        app(CancelacionVentaService::class)->cancelarPorAdmin($pagada->id, 1);
        $this->assertEquals(0, $cliente->fresh()->total_compras);
        $this->assertSame(0, $cliente->fresh()->numero_compras);
    }

    public function test_cambiar_el_cliente_o_el_total_de_una_venta_pagada_recalcula_a_ambos(): void
    {
        $a = $this->cliente();
        $b = Customers::create(['nombre' => 'B', 'apellido' => 'Y', 'correo' => 'b@test.com', 'password' => Hash::make('x12345678'), 'estatus' => 'activo']);
        $sale = $this->venta($a, 'completada', 100);

        $sale->update(['total' => 120]);
        $this->assertEquals(120, $a->fresh()->total_compras);

        $sale->update(['customer_id' => $b->id]);
        $this->assertEquals(0, $a->fresh()->total_compras);
        $this->assertEquals(120, $b->fresh()->total_compras);
    }

    public function test_el_comando_corrige_a_los_clientes_con_estadisticas_infladas(): void
    {
        $cliente = $this->cliente();
        $this->venta($cliente, 'completada', 100);
        $cliente->update(['total_compras' => 620, 'numero_compras' => 9]);

        Artisan::call('clientes:recalcular-estadisticas', ['--dry-run' => true]);
        $this->assertEquals(620, $cliente->fresh()->total_compras);

        Artisan::call('clientes:recalcular-estadisticas');
        $this->assertEquals(100, $cliente->fresh()->total_compras);
        $this->assertSame(1, $cliente->fresh()->numero_compras);
    }
}
