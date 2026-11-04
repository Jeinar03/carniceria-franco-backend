<?php

namespace Tests\Feature;

use App\Http\Controllers\MercadoPagoController;
use App\Http\Livewire\Despachos\DespachosController;
use App\Http\Livewire\Ventas\VentasController;
use App\Mail\OrderStatusMail;
use App\Models\Customers;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleDetail;
use App\Models\User;
use App\Services\InventoryService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Cancelacion de pedidos, pedidos sin pagar y devolucion de Mercado Pago.
 * Ver: Carnicería Franco/Spec - Cancelacion de pedidos y reembolsos.md
 */
class CancelacionPedidosTest extends TestCase
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

    // ---- Cancelacion por el cliente ----

    public function test_el_cliente_cancela_un_mercado_pago_sin_pagar_sin_tocar_stock_ni_estadisticas(): void
    {
        $cliente = $this->cliente();
        $sale = $this->venta($cliente);
        $stockAntes = $this->stock($sale);

        Sanctum::actingAs($cliente, ['cliente']);
        $this->putJson("/api/v1/ventas/{$sale->id}/cancelar")
            ->assertOk()
            ->assertJsonPath('data.estatus', 'cancelada');

        $sale->refresh();
        $this->assertSame('cancelada', $sale->estatus);
        $this->assertSame('cliente', $sale->cancelado_por_tipo);
        $this->assertSame($stockAntes, $this->stock($sale));
        $this->assertEquals(0, $cliente->fresh()->numero_compras);
    }

    public function test_el_cliente_cancela_una_transferencia_sin_comprobante_y_se_repone_el_stock(): void
    {
        $cliente = $this->cliente();
        $sale = $this->venta($cliente, ['metodo_pago' => 'transferencia', 'transferencia_estado' => 'pendiente'], true);
        $this->sumarEstadisticas($cliente);
        $stockConVenta = $this->stock($sale);

        Sanctum::actingAs($cliente, ['cliente']);
        $this->putJson("/api/v1/ventas/{$sale->id}/cancelar")->assertOk();

        $this->assertSame($stockConVenta + 1.0, $this->stock($sale));
        $this->assertEquals(0, $cliente->fresh()->numero_compras);
        $this->assertEquals(0, $cliente->fresh()->total_compras);
    }

    public function test_el_cliente_no_cancela_directo_un_pedido_pagado_ni_con_comprobante(): void
    {
        $cliente = $this->cliente();
        Sanctum::actingAs($cliente, ['cliente']);

        $pagada = $this->venta($cliente, ['estatus' => 'completada', 'mercadopago_payment_id' => '55', 'mercadopago_status' => 'approved'], true);
        $conComprobante = $this->venta($cliente, ['metodo_pago' => 'transferencia', 'transferencia_estado' => 'pendiente', 'transferencia_evidencia_path' => 'x/y.jpg'], true);

        foreach ([$pagada, $conComprobante] as $sale) {
            $this->putJson("/api/v1/ventas/{$sale->id}/cancelar")->assertStatus(422);
            $this->assertNotSame('cancelada', $sale->fresh()->estatus);
        }
    }

    public function test_no_se_cancela_un_pedido_que_ya_se_esta_preparando(): void
    {
        $cliente = $this->cliente();
        $sale = $this->venta($cliente, ['estado_envio' => 'Procesando']);

        Sanctum::actingAs($cliente, ['cliente']);
        $this->putJson("/api/v1/ventas/{$sale->id}/cancelar")->assertStatus(422);
        $this->postJson("/api/v1/ventas/{$sale->id}/solicitar-cancelacion", [])->assertStatus(422);
        $this->assertSame('pendiente', $sale->fresh()->estatus);
    }

    public function test_un_mercado_pago_con_pago_en_proceso_no_se_cancela(): void
    {
        $cliente = $this->cliente();
        $sale = $this->venta($cliente, ['mercadopago_payment_id' => '9', 'mercadopago_status' => 'in_process']);

        Sanctum::actingAs($cliente, ['cliente']);
        $this->putJson("/api/v1/ventas/{$sale->id}/cancelar")->assertStatus(422);
    }

    public function test_el_listado_dice_que_botones_corresponden_a_cada_pedido(): void
    {
        $cliente = $this->cliente();
        $sinPagar = $this->venta($cliente);
        $pagada = $this->venta($cliente, ['estatus' => 'completada', 'mercadopago_payment_id' => '55', 'mercadopago_status' => 'approved'], true);

        Sanctum::actingAs($cliente, ['cliente']);
        $res = $this->getJson("/api/v1/ventas/{$sinPagar->id}")->assertOk();
        $this->assertTrue($res->json('data.opciones_cancelacion.puede_cancelar'));
        $this->assertFalse($res->json('data.opciones_cancelacion.puede_solicitar'));

        $res = $this->getJson("/api/v1/ventas/{$pagada->id}")->assertOk();
        $this->assertFalse($res->json('data.opciones_cancelacion.puede_cancelar'));
        $this->assertTrue($res->json('data.opciones_cancelacion.puede_solicitar'));
    }

    // ---- Solicitud y aprobacion del Admin ----

    public function test_el_cliente_solicita_cancelar_un_pedido_pagado_y_queda_bloqueado_en_despachos(): void
    {
        $cliente = $this->cliente();
        $sale = $this->venta($cliente, ['estatus' => 'completada', 'mercadopago_payment_id' => '55', 'mercadopago_status' => 'approved'], true);

        Sanctum::actingAs($cliente, ['cliente']);
        $this->postJson("/api/v1/ventas/{$sale->id}/solicitar-cancelacion", ['motivo' => 'Me equivoqué'])->assertOk();

        $sale->refresh();
        $this->assertNotNull($sale->cancelacion_solicitada_at);
        $this->assertSame('Me equivoqué', $sale->cancelacion_motivo);
        $this->assertSame('completada', $sale->estatus);

        $this->assertFalse(app(DespachosController::class)->canManageDispatch($sale));
        // Pedir dos veces no se puede.
        $this->postJson("/api/v1/ventas/{$sale->id}/solicitar-cancelacion", [])->assertStatus(422);
    }

    public function test_el_admin_aprueba_y_se_devuelve_el_pago_de_mercado_pago_completo(): void
    {
        Http::fake([
            'api.mercadopago.com/v1/payments/55/refunds' => Http::response(['id' => 987, 'amount' => 100], 201),
            'api.mercadopago.com/v1/payments/55' => Http::response(['status' => 'approved'], 200),
        ]);

        $cliente = $this->cliente();
        $sale = $this->venta($cliente, ['estatus' => 'completada', 'mercadopago_payment_id' => '55', 'mercadopago_status' => 'approved', 'cancelacion_solicitada_at' => now()], true);
        $this->sumarEstadisticas($cliente);
        $stockConVenta = $this->stock($sale);

        Livewire::actingAs($this->admin())->test(VentasController::class)
            ->call('aprobarCancelacion', $sale->id)
            ->assertEmitted('sale-cancelled');

        $sale->refresh();
        $this->assertSame('cancelada', $sale->estatus);
        $this->assertSame('hecho', $sale->reembolso_estado);
        $this->assertSame('987', $sale->reembolso_id);
        $this->assertSame('admin', $sale->cancelado_por_tipo);
        $this->assertSame($stockConVenta + 1.0, $this->stock($sale));
        $this->assertEquals(0, $cliente->fresh()->numero_compras);

        Http::assertSent(fn ($req) => $req->hasHeader('X-Idempotency-Key', 'reembolso-venta-' . $sale->id)
            && str_contains($req->url(), '/v1/payments/55/refunds'));
    }

    public function test_si_la_devolucion_falla_el_pedido_no_se_cancela(): void
    {
        Http::fake([
            'api.mercadopago.com/v1/payments/55/refunds' => Http::response(['message' => 'sin saldo'], 400),
            'api.mercadopago.com/v1/payments/55' => Http::response(['status' => 'approved'], 200),
        ]);

        $cliente = $this->cliente();
        $sale = $this->venta($cliente, ['estatus' => 'completada', 'mercadopago_payment_id' => '55', 'mercadopago_status' => 'approved', 'cancelacion_solicitada_at' => now()], true);

        Livewire::actingAs($this->admin())->test(VentasController::class)
            ->call('aprobarCancelacion', $sale->id)
            ->assertEmitted('sale-error');

        $this->assertSame('completada', $sale->fresh()->estatus);
        $this->assertNull($sale->fresh()->reembolso_estado);
    }

    public function test_el_admin_rechaza_la_solicitud_y_el_pedido_sigue(): void
    {
        $cliente = $this->cliente();
        $sale = $this->venta($cliente, ['estatus' => 'completada', 'mercadopago_payment_id' => '55', 'mercadopago_status' => 'approved', 'cancelacion_solicitada_at' => now()], true);

        Livewire::actingAs($this->admin())->test(VentasController::class)
            ->call('rechazarCancelacion', $sale->id)
            ->assertEmitted('sale-cancelled');

        $sale->refresh();
        $this->assertSame('completada', $sale->estatus);
        $this->assertNull($sale->cancelacion_solicitada_at);
        $this->assertTrue(app(DespachosController::class)->canManageDispatch($sale));
    }

    private function renderCorreo(OrderStatusMail $mail): string
    {
        return view('emails.order-status', [
            'sale' => $mail->sale,
            'config' => $mail->statusConfig,
            'order' => $mail->orderSummary,
            'customer' => $mail->sale->customer,
            'datosBancarios' => null,
        ])->render();
    }

    public function test_al_rechazar_la_respuesta_del_admin_llega_en_el_correo_al_cliente(): void
    {
        $cliente = $this->cliente();
        $sale = $this->venta($cliente, ['estatus' => 'completada', 'mercadopago_payment_id' => '56', 'mercadopago_status' => 'approved', 'cancelacion_solicitada_at' => now(), 'cancelacion_motivo' => 'Ya no lo quiero'], true);

        Livewire::actingAs($this->admin())->test(VentasController::class)
            ->call('rechazarCancelacion', $sale->id, '  Tu pedido ya salió con el mandadito.  ')
            ->assertEmitted('sale-cancelled');

        Mail::assertSent(OrderStatusMail::class, function ($mail) {
            return ($mail->statusConfig['respuesta_admin'] ?? null) === 'Tu pedido ya salió con el mandadito.'
                && str_contains($this->renderCorreo($mail), 'Tu pedido ya salió con el mandadito.');
        });
    }

    public function test_al_rechazar_sin_respuesta_el_correo_no_lleva_el_bloque_de_mensaje(): void
    {
        $cliente = $this->cliente();
        $sale = $this->venta($cliente, ['estatus' => 'completada', 'mercadopago_payment_id' => '57', 'mercadopago_status' => 'approved', 'cancelacion_solicitada_at' => now()], true);

        Livewire::actingAs($this->admin())->test(VentasController::class)
            ->call('rechazarCancelacion', $sale->id, '')
            ->assertEmitted('sale-cancelled');

        Mail::assertSent(OrderStatusMail::class, function ($mail) {
            return !isset($mail->statusConfig['respuesta_admin'])
                && !str_contains($this->renderCorreo($mail), 'Mensaje de la carnicería');
        });
    }

    public function test_una_venta_cancelada_no_aparece_con_entrega_pendiente(): void
    {
        $cliente = $this->cliente();
        $sale = $this->venta($cliente, ['estatus' => 'cancelada', 'cancelado_at' => now(), 'cancelado_por_tipo' => 'admin', 'estado_envio' => 'Pendiente'], true);

        Livewire::actingAs($this->admin())->test(VentasController::class)
            ->assertSee('Sin entrega')
            ->call('viewDetail', $sale->id)
            ->assertSee('Sin entrega (cancelada)')
            ->assertDontSee('Entrega: Pendiente');
    }

    public function test_el_detalle_muestra_el_motivo_que_escribio_el_cliente(): void
    {
        $cliente = $this->cliente();
        $sale = $this->venta($cliente, ['estatus' => 'completada', 'mercadopago_payment_id' => '58', 'mercadopago_status' => 'approved', 'cancelacion_solicitada_at' => now(), 'cancelacion_motivo' => 'Soy bien indeciso'], true);

        Livewire::actingAs($this->admin())->test(VentasController::class)
            ->call('viewDetail', $sale->id)
            ->assertSee('El cliente pidió cancelar')
            ->assertSee('Soy bien indeciso');
    }

    public function test_cancelar_una_transferencia_con_comprobante_queda_marcada_para_devolver_a_mano(): void
    {
        $cliente = $this->cliente();
        $sale = $this->venta($cliente, ['metodo_pago' => 'transferencia', 'transferencia_estado' => 'pendiente', 'transferencia_evidencia_path' => 'x/y.jpg', 'cancelacion_solicitada_at' => now()], true);

        Livewire::actingAs($this->admin())->test(VentasController::class)
            ->call('aprobarCancelacion', $sale->id)
            ->assertEmitted('sale-cancelled');

        $this->assertSame('manual', $sale->fresh()->reembolso_estado);
        $this->assertSame('cancelada', $sale->fresh()->estatus);
    }

    public function test_solo_el_admin_resuelve_cancelaciones(): void
    {
        $cajero = User::create(['name' => 'Caja', 'email' => 'caja@test.com', 'password' => Hash::make('x'), 'profile' => 'EMPLOYEE', 'status' => 'ACTIVE']);
        $cajero->assignRole('Cajero');

        Livewire::actingAs($cajero)->test(VentasController::class)
            ->call('aprobarCancelacion', 1)
            ->assertForbidden();
    }

    public function test_si_mercado_pago_ya_devolvio_el_pago_se_cancela_sin_pedir_otra_devolucion(): void
    {
        Http::fake([
            'api.mercadopago.com/v1/payments/55/refunds' => Http::response(['message' => 'no deberia llamarse'], 500),
            'api.mercadopago.com/v1/payments/55' => Http::response(['status' => 'refunded'], 200),
        ]);

        $cliente = $this->cliente();
        $sale = $this->venta($cliente, ['estatus' => 'completada', 'mercadopago_payment_id' => '55', 'mercadopago_status' => 'approved', 'cancelacion_solicitada_at' => now()], true);

        Livewire::actingAs($this->admin())->test(VentasController::class)
            ->call('aprobarCancelacion', $sale->id)
            ->assertEmitted('sale-cancelled');

        $sale->refresh();
        $this->assertSame('cancelada', $sale->estatus);
        $this->assertSame('hecho', $sale->reembolso_estado);
        Http::assertNotSent(fn ($req) => str_contains($req->url(), '/refunds'));
    }

    public function test_cancelar_un_mercado_pago_marcado_entregada_tambien_resta_las_estadisticas(): void
    {
        Http::fake([
            'api.mercadopago.com/v1/payments/55/refunds' => Http::response(['id' => 1, 'amount' => 100], 201),
            'api.mercadopago.com/v1/payments/55' => Http::response(['status' => 'approved'], 200),
        ]);

        $cliente = $this->cliente();
        $sale = $this->venta($cliente, ['estatus' => 'entregada', 'mercadopago_payment_id' => '55', 'mercadopago_status' => 'approved'], true);
        $this->sumarEstadisticas($cliente);

        app(\App\Services\CancelacionVentaService::class)->cancelarPorAdmin($sale->id, $this->admin()->id);

        $this->assertEquals(0, $cliente->fresh()->numero_compras);
    }

    // ---- Despachos ----

    public function test_un_mercado_pago_sin_pagar_no_aparece_en_la_cola_ni_se_puede_despachar(): void
    {
        $cliente = $this->cliente();
        $sinPagar = $this->venta($cliente);
        $pagada = $this->venta($cliente, ['estatus' => 'completada', 'mercadopago_payment_id' => '55', 'mercadopago_status' => 'approved'], true);
        $transferencia = $this->venta($cliente, ['metodo_pago' => 'transferencia', 'transferencia_estado' => 'pendiente'], true);

        $cola = Sale::enColaDespacho()->pluck('id')->all();

        $this->assertNotContains($sinPagar->id, $cola);
        $this->assertContains($pagada->id, $cola);
        $this->assertContains($transferencia->id, $cola);

        $despachos = app(DespachosController::class);
        $this->assertFalse($despachos->canManageDispatch($sinPagar));
        $this->assertTrue($despachos->canManageDispatch($pagada));
        $this->assertFalse($despachos->canManageDispatch($transferencia));
    }

    public function test_editar_un_mercado_pago_sin_pagar_no_lo_marca_como_cobrado(): void
    {
        $cliente = $this->cliente();
        $sale = $this->venta($cliente, ['fecha_venta' => now()]);

        $componente = Livewire::actingAs($this->admin())->test(DespachosController::class)
            ->call('openEditOrderModal', $sale->id);

        $componente->call('updateOrder');

        $this->assertSame('pendiente', $sale->fresh()->estatus);
    }

    public function test_no_se_valida_la_transferencia_de_un_pedido_cancelado(): void
    {
        $cliente = $this->cliente();
        $sale = $this->venta($cliente, ['metodo_pago' => 'transferencia', 'transferencia_estado' => 'pendiente', 'transferencia_evidencia_path' => 'x/y.jpg', 'estatus' => 'cancelada']);

        Livewire::actingAs($this->admin())->test(DespachosController::class)
            ->set('transferValidationSaleId', $sale->id)
            ->call('approveTransfer')
            ->assertEmitted('despacho-error');

        $this->assertSame('cancelada', $sale->fresh()->estatus);
    }

    // ---- Webhook ----

    public function test_un_pago_aprobado_sobre_una_venta_cancelada_no_la_revive_y_queda_por_devolver(): void
    {
        $cliente = $this->cliente();
        $sale = $this->venta($cliente, ['estatus' => 'cancelada']);

        $resultado = (new \ReflectionMethod(MercadoPagoController::class, 'procesarPagoAprobado'));
        $resultado->setAccessible(true);
        $objeto = (new \ReflectionClass(MercadoPagoController::class))->newInstanceWithoutConstructor();
        $ok = $resultado->invoke($objeto, $sale, (object) [
            'id' => 77, 'status' => 'approved', 'status_detail' => 'accredited',
            'transaction_amount' => 100.0, 'currency_id' => 'MXN',
        ]);

        $sale->refresh();
        $this->assertFalse($ok);
        $this->assertSame('cancelada', $sale->estatus);
        $this->assertSame('pendiente', $sale->reembolso_estado);
        $this->assertTrue($sale->requiere_revision);
        $this->assertSame('77', (string) $sale->mercadopago_payment_id);
    }

    public function test_el_admin_devuelve_con_un_clic_un_pago_llegado_tarde(): void
    {
        Http::fake([
            'api.mercadopago.com/v1/payments/77/refunds' => Http::response(['id' => 5, 'amount' => 100], 201),
            'api.mercadopago.com/v1/payments/77' => Http::response(['status' => 'approved'], 200),
        ]);

        $cliente = $this->cliente();
        $sale = $this->venta($cliente, [
            'estatus' => 'cancelada', 'mercadopago_payment_id' => '77', 'mercadopago_status' => 'approved',
            'reembolso_estado' => 'pendiente', 'requiere_revision' => true,
        ]);

        Livewire::actingAs($this->admin())->test(VentasController::class)
            ->call('devolverPago', $sale->id)
            ->assertEmitted('sale-cancelled');

        $sale->refresh();
        $this->assertSame('hecho', $sale->reembolso_estado);
        $this->assertFalse($sale->requiere_revision);
    }

    // ---- Expiracion ----

    public function test_el_comando_cancela_lo_vencido_y_respeta_lo_que_tiene_comprobante_o_es_reciente(): void
    {
        Http::fake(['api.mercadopago.com/v1/payments/search*' => Http::response(['results' => []], 200)]);

        $cliente = $this->cliente();
        $vieja = now()->subHours(30);

        $mpVencido = $this->venta($cliente, ['fecha_venta' => $vieja]);
        $transferVencida = $this->venta($cliente, ['metodo_pago' => 'transferencia', 'transferencia_estado' => 'pendiente', 'fecha_venta' => $vieja], true);
        $conComprobante = $this->venta($cliente, ['metodo_pago' => 'transferencia', 'transferencia_estado' => 'pendiente', 'transferencia_evidencia_path' => 'x/y.jpg', 'fecha_venta' => $vieja], true);
        $reciente = $this->venta($cliente, ['fecha_venta' => now()->subHours(2)]);
        $pagada = $this->venta($cliente, ['estatus' => 'completada', 'fecha_venta' => $vieja], true);

        $this->artisan('ventas:expirar-pendientes')->assertExitCode(0);

        $this->assertSame('cancelada', $mpVencido->fresh()->estatus);
        $this->assertSame('sistema', $mpVencido->fresh()->cancelado_por_tipo);
        $this->assertSame('cancelada', $transferVencida->fresh()->estatus);
        $this->assertSame('pendiente', $conComprobante->fresh()->estatus);
        $this->assertSame('pendiente', $reciente->fresh()->estatus);
        $this->assertSame('completada', $pagada->fresh()->estatus);
    }

    public function test_el_comando_no_cancela_un_mercado_pago_si_hay_un_pago_vivo_o_no_puede_confirmarlo(): void
    {
        $cliente = $this->cliente();
        $sale = $this->venta($cliente, ['fecha_venta' => now()->subHours(30)]);

        Http::fake(['api.mercadopago.com/v1/payments/search*' => Http::response(['results' => [['status' => 'approved']]], 200)]);
        $this->artisan('ventas:expirar-pendientes')->assertExitCode(0);
        $this->assertSame('pendiente', $sale->fresh()->estatus);

        Http::fake(['api.mercadopago.com/v1/payments/search*' => Http::response([], 500)]);
        $this->artisan('ventas:expirar-pendientes')->assertExitCode(0);
        $this->assertSame('pendiente', $sale->fresh()->estatus);
    }

    public function test_dry_run_no_cancela_nada(): void
    {
        Http::fake(['api.mercadopago.com/v1/payments/search*' => Http::response(['results' => []], 200)]);

        $sale = $this->venta($this->cliente(), ['fecha_venta' => now()->subHours(30)]);

        $this->artisan('ventas:expirar-pendientes --dry-run')->assertExitCode(0);

        $this->assertSame('pendiente', $sale->fresh()->estatus);
    }
}
