<?php

namespace Tests\Feature;

use App\Http\Livewire\Ventas\VentasController;
use App\Models\Customers;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleDetail;
use App\Models\User;
use App\Services\CancelacionVentaService;
use App\Services\InventoryService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Pruebas adicionales y casos límite de cancelación de pedidos.
 */
class CancelacionPedidosExtraTest extends TestCase
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

        config()->set('mercadopago.access_token', 'APP_USR-token-de-prueba-0000');
        Mail::fake();
    }

    private function cliente(string $correo = 'a@test.com'): Customers
    {
        return Customers::create([
            'nombre' => 'C',
            'apellido' => 'X',
            'correo' => $correo,
            'password' => Hash::make('cliente123'),
            'estatus' => 'activo',
            'total_compras' => 0,
            'numero_compras' => 0,
        ]);
    }

    private function admin(): User
    {
        return User::findOrFail(1);
    }

    /**
     * Crea un pedido con una linea. $conSalida simula que ya se descontó el stock
     * (transferencia al crear, Mercado Pago al pagarse).
     */
    private function venta(Customers $cliente, array $extra = [], bool $conSalida = false): Sale
    {
        $producto = Product::where('activo', true)->where('en_oferta', false)->first();

        $sale = Sale::create(array_merge([
            'customer_id' => $cliente->id,
            'folio' => 'T' . random_int(100000, 999999),
            'fecha_venta' => now(),
            'metodo_pago' => 'mercado_pago',
            'estatus' => 'pendiente',
            'estado_envio' => 'Pendiente',
            'subtotal' => 100,
            'total' => 100,
        ], $extra));

        $detalle = SaleDetail::create([
            'sale_id' => $sale->id,
            'product_id' => $producto->id,
            'cantidad' => 1,
            'precio_unitario' => 100,
            'subtotal' => 100,
            'total' => 100,
            'producto_nombre' => $producto->nombre,
            'producto_codigo' => $producto->codigo,
        ]);

        if ($conSalida) {
            app(InventoryService::class)->addSaleExit($producto, $detalle->setRelation('sale', $sale));
        }

        return $sale->fresh();
    }

    private function stock(Sale $sale): float
    {
        return app(InventoryService::class)->currentStock($sale->details()->first()->product_id);
    }

    /** Suma las estadisticas como lo hace la tienda al crear/aprobar la compra. */
    private function sumarEstadisticas(Customers $cliente, float $total = 100): void
    {
        $cliente->update(['total_compras' => $total, 'numero_compras' => 1]);
    }

    // ---- Casos límite y adicionales de cancelación ----

    /**
     * 1. Un cliente no puede cancelar ni solicitar la cancelacion de un pedido de OTRO cliente (403) con Sanctum::actingAs.
     */
    public function test_un_cliente_no_puede_cancelar_ni_solicitar_la_cancelacion_de_un_pedido_de_otro_cliente(): void
    {
        $clienteA = $this->cliente('dueno@test.com');
        $clienteB = $this->cliente('ajeno@test.com');

        $saleA = $this->venta($clienteA);

        Sanctum::actingAs($clienteB, ['cliente']);

        $this->putJson("/api/v1/ventas/{$saleA->id}/cancelar")
            ->assertStatus(403);

        $this->postJson("/api/v1/ventas/{$saleA->id}/solicitar-cancelacion", ['motivo' => 'Intento ajeno'])
            ->assertStatus(403);

        $saleA->refresh();
        $this->assertSame('pendiente', $saleA->estatus);
        $this->assertNull($saleA->cancelacion_solicitada_at);
    }

    /**
     * 2. Cancelar dos veces el mismo pedido sin pagar: la segunda respuesta es 422 y el stock no se repone dos veces.
     */
    public function test_cancelar_dos_veces_el_mismo_pedido_sin_pagar_la_segunda_es_422_y_no_repone_stock_dos_veces(): void
    {
        $cliente = $this->cliente();
        // Transferencia sin comprobante: pedido sin pagar con salida de stock previa
        $sale = $this->venta($cliente, ['metodo_pago' => 'transferencia', 'transferencia_estado' => 'pendiente'], true);
        $this->sumarEstadisticas($cliente);
        $stockConVenta = $this->stock($sale);

        Sanctum::actingAs($cliente, ['cliente']);

        // Primera cancelación: exitosa y repone stock
        $this->putJson("/api/v1/ventas/{$sale->id}/cancelar")
            ->assertOk()
            ->assertJsonPath('data.estatus', 'cancelada');

        $this->assertSame($stockConVenta + 1.0, $this->stock($sale));

        // Segunda cancelación: 422 y el stock se mantiene igual
        $this->putJson("/api/v1/ventas/{$sale->id}/cancelar")
            ->assertStatus(422);

        $this->assertSame($stockConVenta + 1.0, $this->stock($sale));
    }

    /**
     * 3. Un pedido con estado_envio Enviado o Entregado ni se cancela ni se solicita (422), pagado o no.
     */
    public function test_un_pedido_con_estado_envio_enviado_o_entregado_ni_se_cancela_ni_se_solicita(): void
    {
        $cliente = $this->cliente();
        Sanctum::actingAs($cliente, ['cliente']);

        foreach (['Enviado', 'Entregado'] as $estadoEnvio) {
            // Sin pagar
            $sinPagar = $this->venta($cliente, [
                'estado_envio' => $estadoEnvio,
            ]);

            $this->putJson("/api/v1/ventas/{$sinPagar->id}/cancelar")
                ->assertStatus(422);
            $this->postJson("/api/v1/ventas/{$sinPagar->id}/solicitar-cancelacion", ['motivo' => 'Ya no lo quiero'])
                ->assertStatus(422);
            $this->assertSame('pendiente', $sinPagar->fresh()->estatus);

            // Pagado
            $pagado = $this->venta($cliente, [
                'estatus' => 'completada',
                'estado_envio' => $estadoEnvio,
                'mercadopago_payment_id' => '55',
                'mercadopago_status' => 'approved',
            ], true);

            $this->putJson("/api/v1/ventas/{$pagado->id}/cancelar")
                ->assertStatus(422);
            $this->postJson("/api/v1/ventas/{$pagado->id}/solicitar-cancelacion", ['motivo' => 'Ya no lo quiero'])
                ->assertStatus(422);
            $this->assertSame('completada', $pagado->fresh()->estatus);
            $this->assertNull($pagado->fresh()->cancelacion_solicitada_at);
        }
    }

    /**
     * 4. Un pedido de efectivo o tarjeta (mostrador) no se puede cancelar desde la tienda (422).
     */
    public function test_un_pedido_de_efectivo_o_tarjeta_no_se_puede_cancelar_desde_la_tienda(): void
    {
        $cliente = $this->cliente();
        Sanctum::actingAs($cliente, ['cliente']);

        foreach (['efectivo', 'tarjeta'] as $metodoPago) {
            $sale = $this->venta($cliente, [
                'metodo_pago' => $metodoPago,
            ]);

            $this->putJson("/api/v1/ventas/{$sale->id}/cancelar")
                ->assertStatus(422);

            $this->postJson("/api/v1/ventas/{$sale->id}/solicitar-cancelacion", ['motivo' => 'Cancelar compra'])
                ->assertStatus(422);

            $this->assertNotSame('cancelada', $sale->fresh()->estatus);
        }
    }

    /**
     * 5. Rechazar una solicitud (rechazarCancelacion en VentasController) limpia cancelacion_solicitada_at y cancelacion_motivo; rechazar un pedido SIN solicitud emite sale-error.
     */
    public function test_rechazar_solicitud_limpia_fechas_y_motivo_y_rechazar_sin_solicitud_emite_error(): void
    {
        $cliente = $this->cliente();

        // 1. Pedido con solicitud: al rechazar se limpia fecha y motivo
        $saleConSolicitud = $this->venta($cliente, [
            'estatus' => 'completada',
            'mercadopago_payment_id' => '55',
            'mercadopago_status' => 'approved',
            'cancelacion_solicitada_at' => now(),
            'cancelacion_motivo' => 'Motivo de prueba',
        ], true);

        Livewire::actingAs($this->admin())->test(VentasController::class)
            ->call('rechazarCancelacion', $saleConSolicitud->id)
            ->assertEmitted('sale-cancelled');

        $saleConSolicitud->refresh();
        $this->assertNull($saleConSolicitud->cancelacion_solicitada_at);
        $this->assertNull($saleConSolicitud->cancelacion_motivo);
        $this->assertSame('completada', $saleConSolicitud->estatus);

        // 2. Pedido SIN solicitud: al rechazar emite sale-error
        $saleSinSolicitud = $this->venta($cliente, [
            'estatus' => 'completada',
            'mercadopago_payment_id' => '56',
            'mercadopago_status' => 'approved',
            'cancelacion_solicitada_at' => null,
            'cancelacion_motivo' => null,
        ], true);

        Livewire::actingAs($this->admin())->test(VentasController::class)
            ->call('rechazarCancelacion', $saleSinSolicitud->id)
            ->assertEmitted('sale-error');
    }

    /**
     * 6. Un Cajero no puede ejecutar cancelSale ni devolverPago (assertForbidden).
     */
    public function test_un_cajero_no_puede_ejecutar_cancel_sale_ni_devolver_pago(): void
    {
        $cliente = $this->cliente();
        $sale = $this->venta($cliente);

        $cajero = User::create([
            'name' => 'Caja Extra',
            'email' => 'caja_extra@test.com',
            'password' => Hash::make('secret123'),
            'profile' => 'EMPLOYEE',
            'status' => 'ACTIVE',
        ]);
        $cajero->assignRole('Cajero');

        Livewire::actingAs($cajero)->test(VentasController::class)
            ->call('cancelSale', $sale->id)
            ->assertForbidden();

        Livewire::actingAs($cajero)->test(VentasController::class)
            ->call('devolverPago', $sale->id)
            ->assertForbidden();
    }

    /**
     * 7. devolverPago sobre un pedido sin reembolso_estado=pendiente emite sale-error y no llama a Mercado Pago (Http::assertNothingSent).
     */
    public function test_devolver_pago_sobre_pedido_sin_reembolso_pendiente_emite_sale_error_y_no_llama_a_mercado_pago(): void
    {
        Http::fake();

        $cliente = $this->cliente();
        $sale = $this->venta($cliente, [
            'estatus' => 'cancelada',
            'mercadopago_payment_id' => '77',
            'mercadopago_status' => 'approved',
            'reembolso_estado' => null,
            'requiere_revision' => false,
        ]);

        Livewire::actingAs($this->admin())->test(VentasController::class)
            ->call('devolverPago', $sale->id)
            ->assertEmitted('sale-error');

        Http::assertNothingSent();
    }

    /**
     * 8. Un motivo de mas de 255 caracteres se rechaza (422) sin tocar el pedido; uno con HTML dentro del
     * limite se guarda tal cual en la base (se escapa al mostrarlo en el panel).
     */
    public function test_un_motivo_de_mas_de_255_caracteres_se_rechaza_y_el_html_se_guarda_tal_cual(): void
    {
        $cliente = $this->cliente();
        $sale = $this->venta($cliente, [
            'estatus' => 'completada',
            'mercadopago_payment_id' => '55',
            'mercadopago_status' => 'approved',
        ], true);

        Sanctum::actingAs($cliente, ['cliente']);

        $this->postJson("/api/v1/ventas/{$sale->id}/solicitar-cancelacion", [
            'motivo' => str_repeat('a', 300),
        ])->assertStatus(422);
        $this->assertNull($sale->fresh()->cancelacion_solicitada_at);

        $html = '<b>Pedido defectuoso</b>: <script>alert("xss")</script>';
        $this->postJson("/api/v1/ventas/{$sale->id}/solicitar-cancelacion", ['motivo' => $html])->assertOk();

        $sale->refresh();
        $this->assertNotNull($sale->cancelacion_solicitada_at);
        $this->assertSame($html, $sale->cancelacion_motivo);
    }

    /**
     * 9. El comando ventas:expirar-pendientes --horas=48 NO cancela un pedido de 30 horas y SI cancela uno de 50.
     */
    public function test_el_comando_con_parametro_horas_no_cancela_pedido_reciente_y_si_vencido(): void
    {
        Http::fake(['api.mercadopago.com/v1/payments/search*' => Http::response(['results' => []], 200)]);

        $cliente = $this->cliente();

        $pedido30Horas = $this->venta($cliente, [
            'fecha_venta' => now()->subHours(30),
        ]);
        $pedido50Horas = $this->venta($cliente, [
            'fecha_venta' => now()->subHours(50),
        ]);

        $this->artisan('ventas:expirar-pendientes --horas=48')->assertExitCode(0);

        $this->assertSame('pendiente', $pedido30Horas->fresh()->estatus);
        $this->assertSame('cancelada', $pedido50Horas->fresh()->estatus);
        $this->assertSame('sistema', $pedido50Horas->fresh()->cancelado_por_tipo);
    }

    /**
     * 10. El comando no cancela una transferencia sin comprobante pero con cancelacion_solicitada_at puesto.
     */
    public function test_el_comando_no_cancela_transferencia_sin_comprobante_con_solicitud_de_cancelacion(): void
    {
        Http::fake(['api.mercadopago.com/v1/payments/search*' => Http::response(['results' => []], 200)]);

        $cliente = $this->cliente();
        $sale = $this->venta($cliente, [
            'metodo_pago' => 'transferencia',
            'transferencia_estado' => 'pendiente',
            'transferencia_evidencia_path' => null,
            'fecha_venta' => now()->subHours(30),
            'cancelacion_solicitada_at' => now()->subHours(5),
        ], true);

        $this->artisan('ventas:expirar-pendientes')->assertExitCode(0);

        $this->assertSame('pendiente', $sale->fresh()->estatus);
        $this->assertNotNull($sale->fresh()->cancelacion_solicitada_at);
    }

    /**
     * 11. Una transferencia cancelada por el comando repone el stock y resta las estadisticas del cliente.
     */
    public function test_transferencia_cancelada_por_el_comando_repone_stock_y_resta_estadisticas(): void
    {
        Http::fake(['api.mercadopago.com/v1/payments/search*' => Http::response(['results' => []], 200)]);

        $cliente = $this->cliente();
        $sale = $this->venta($cliente, [
            'metodo_pago' => 'transferencia',
            'transferencia_estado' => 'pendiente',
            'fecha_venta' => now()->subHours(30),
        ], true);
        $this->sumarEstadisticas($cliente, 100);
        $stockConVenta = $this->stock($sale);

        $this->artisan('ventas:expirar-pendientes')->assertExitCode(0);

        $sale->refresh();
        $this->assertSame('cancelada', $sale->estatus);
        $this->assertSame('sistema', $sale->cancelado_por_tipo);
        $this->assertSame($stockConVenta + 1.0, $this->stock($sale));
        $this->assertEquals(0, $cliente->fresh()->numero_compras);
        $this->assertEquals(0, $cliente->fresh()->total_compras);
    }

    /**
     * 12. opcionesParaCliente de un pedido ya cancelado devuelve puede_cancelar=false y puede_solicitar=false.
     */
    public function test_opciones_para_cliente_de_un_pedido_ya_cancelado_devuelve_puede_cancelar_y_solicitar_en_false(): void
    {
        $cliente = $this->cliente();
        $sale = $this->venta($cliente, [
            'estatus' => 'cancelada',
            'cancelado_at' => now(),
            'cancelado_por_tipo' => 'cliente',
        ]);

        // Verificación en el servicio
        $opciones = app(CancelacionVentaService::class)->opcionesParaCliente($sale);
        $this->assertFalse($opciones['puede_cancelar']);
        $this->assertFalse($opciones['puede_solicitar']);
        $this->assertSame('El pedido ya está cancelado.', $opciones['motivo_bloqueo']);

        // Verificación en el endpoint API
        Sanctum::actingAs($cliente, ['cliente']);
        $res = $this->getJson("/api/v1/ventas/{$sale->id}")->assertOk();
        $this->assertFalse($res->json('data.opciones_cancelacion.puede_cancelar'));
        $this->assertFalse($res->json('data.opciones_cancelacion.puede_solicitar'));
    }
}
