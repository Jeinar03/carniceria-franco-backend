<?php

namespace App\Services;

use App\Models\Customers;
use App\Models\Product;
use App\Models\Sale;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Todas las reglas para cancelar un pedido en un solo lugar: lo usan la API de la tienda,
 * el panel (Ventas y Despachos) y el comando que vence los pedidos sin pagar.
 *
 * Pagado = estatus completada. Mercado Pago pendiente o transferencia sin aprobar es "sin pagar".
 */
class CancelacionVentaService
{
    public const POR_CLIENTE = 'cliente';
    public const POR_ADMIN = 'admin';
    public const POR_SISTEMA = 'sistema';

    public function __construct(
        private InventoryService $inventory,
        private MercadoPagoReembolso $reembolso
    ) {
    }

    /**
     * Que puede hacer el cliente con su pedido. La tienda solo pinta los botones segun esto.
     *
     * @return array{puede_cancelar: bool, puede_solicitar: bool, cancelacion_solicitada: bool, motivo_bloqueo: ?string}
     */
    public function opcionesParaCliente(Sale $sale): array
    {
        $res = [
            'puede_cancelar' => false,
            'puede_solicitar' => false,
            'cancelacion_solicitada' => $sale->tieneCancelacionSolicitada(),
            'motivo_bloqueo' => null,
        ];

        if ($sale->estaCancelada()) {
            $res['motivo_bloqueo'] = 'El pedido ya está cancelado.';
            return $res;
        }

        if ($res['cancelacion_solicitada']) {
            $res['motivo_bloqueo'] = 'Ya solicitaste la cancelación; la estamos revisando.';
            return $res;
        }

        if (!in_array($sale->metodo_pago, ['mercado_pago', 'transferencia'], true)) {
            $res['motivo_bloqueo'] = 'Este pedido no se puede cancelar desde la tienda. Escríbenos por WhatsApp.';
            return $res;
        }

        if ($sale->estado_envio !== Sale::ENVIO_PENDIENTE) {
            $res['motivo_bloqueo'] = 'Tu pedido ya se está preparando o va en camino. Escríbenos por WhatsApp.';
            return $res;
        }

        if ($sale->metodo_pago === 'mercado_pago') {
            if ($sale->estaPagada()) {
                $res['puede_solicitar'] = true;
            } elseif ($sale->mercadopago_payment_id && !in_array($sale->mercadopago_status, ['rejected', 'cancelled'], true)) {
                $res['motivo_bloqueo'] = 'Tu pago está en proceso. Espera a que se confirme y vuelve a intentarlo.';
            } else {
                $res['puede_cancelar'] = true;
            }
            return $res;
        }

        // Transferencia: sin comprobante se cancela directo; con comprobante o ya aprobada, se solicita.
        if ($sale->estaPagada() || $sale->transferencia_evidencia_path) {
            $res['puede_solicitar'] = true;
        } else {
            $res['puede_cancelar'] = true;
        }

        return $res;
    }

    /**
     * El cliente cancela un pedido sin pagar. Devuelve la venta ya cancelada.
     *
     * @throws RuntimeException
     */
    public function cancelarPorCliente(int $saleId): Sale
    {
        $sale = DB::transaction(function () use ($saleId) {
            $sale = Sale::with('details')->lockForUpdate()->findOrFail($saleId);

            $opciones = $this->opcionesParaCliente($sale);
            if (!$opciones['puede_cancelar']) {
                throw new RuntimeException($opciones['motivo_bloqueo']
                    ?: 'Este pedido ya no se puede cancelar directamente. Solicita la cancelación.');
            }

            $this->revertirYCancelar($sale, self::POR_CLIENTE, null);

            return $sale;
        });

        OrderNotificationService::sendCancelledNotification(Sale::with(['customer', 'details'])->find($sale->id));

        return $sale;
    }

    /**
     * El cliente pide cancelar un pedido ya pagado (o con comprobante en revision).
     *
     * @throws RuntimeException
     */
    public function solicitar(int $saleId, ?string $motivo): Sale
    {
        $sale = DB::transaction(function () use ($saleId, $motivo) {
            $sale = Sale::lockForUpdate()->findOrFail($saleId);

            $opciones = $this->opcionesParaCliente($sale);
            if (!$opciones['puede_solicitar']) {
                throw new RuntimeException($opciones['motivo_bloqueo']
                    ?: 'Este pedido no admite solicitud de cancelación.');
            }

            $sale->cancelacion_solicitada_at = now();
            $sale->cancelacion_motivo = $motivo !== null ? mb_substr(trim($motivo), 0, 255) : null;
            $sale->save();

            return $sale;
        });

        OrderNotificationService::sendCancellationRequestedNotification(Sale::with(['customer', 'details'])->find($sale->id));

        return $sale;
    }

    /** El Admin rechaza la solicitud: el pedido sigue su curso normal. */
    public function rechazarSolicitud(int $saleId): Sale
    {
        $sale = DB::transaction(function () use ($saleId) {
            $sale = Sale::lockForUpdate()->findOrFail($saleId);

            if (!$sale->tieneCancelacionSolicitada()) {
                throw new RuntimeException('Este pedido no tiene una solicitud de cancelación pendiente.');
            }

            $sale->cancelacion_solicitada_at = null;
            $sale->cancelacion_motivo = null;
            $sale->save();

            return $sale;
        });

        OrderNotificationService::sendCancellationRejectedNotification(Sale::with(['customer', 'details'])->find($sale->id));

        return $sale;
    }

    /**
     * El Admin cancela un pedido (desde Ventas) o aprueba una solicitud del cliente.
     * Si el pedido es de Mercado Pago ya cobrado, primero devuelve el pago completo; si la devolucion
     * falla, NO se cancela (para no quedarse sin pedido y sin haber devuelto el dinero).
     *
     * @throws RuntimeException
     */
    public function cancelarPorAdmin(int $saleId, int $userId): Sale
    {
        $sale = Sale::findOrFail($saleId);
        $reembolso = null;

        if ($this->requiereDevolucionMp($sale)) {
            // La llamada a Mercado Pago va fuera de la transaccion; es idempotente por venta.
            $reembolso = $this->reembolso->devolverTodo($sale);
        }

        $sale = DB::transaction(function () use ($saleId, $userId, $reembolso) {
            $sale = Sale::with('details')->lockForUpdate()->findOrFail($saleId);

            $this->revertirYCancelar($sale, self::POR_ADMIN, $userId);

            if ($reembolso) {
                $sale->reembolso_id = $reembolso['id'];
                $sale->reembolso_estado = 'hecho';
                $sale->reembolso_monto = $reembolso['monto'];
                $sale->reembolsado_at = now();
                $sale->requiere_revision = false;
            } elseif ($sale->metodo_pago === 'transferencia' && $sale->transferencia_evidencia_path) {
                // Pudo haber un deposito real: el Admin lo devuelve a mano por transferencia.
                $sale->reembolso_estado = 'manual';
            }
            $sale->save();

            return $sale;
        });

        $completa = Sale::with(['customer', 'details'])->find($sale->id);
        if ($reembolso) {
            OrderNotificationService::sendRefundDoneNotification($completa);
        } else {
            OrderNotificationService::sendCancelledNotification($completa);
        }

        return $sale;
    }

    /**
     * Pago aprobado que llego a una venta ya cancelada: el Admin lo devuelve con un clic.
     *
     * @throws RuntimeException
     */
    public function devolverPagoPendiente(int $saleId): Sale
    {
        $sale = Sale::findOrFail($saleId);

        if ($sale->reembolso_estado !== 'pendiente') {
            throw new RuntimeException('Este pedido no tiene una devolución pendiente.');
        }

        $reembolso = $this->reembolso->devolverTodo($sale);

        $sale->reembolso_id = $reembolso['id'];
        $sale->reembolso_estado = 'hecho';
        $sale->reembolso_monto = $reembolso['monto'];
        $sale->reembolsado_at = now();
        $sale->requiere_revision = false;
        $sale->save();

        OrderNotificationService::sendRefundDoneNotification(Sale::with(['customer', 'details'])->find($sale->id));

        return $sale;
    }

    /**
     * Vence un pedido sin pagar (lo usa el comando por tiempo). Vuelve a revisar la venta bloqueada
     * por si el pago o el comprobante entraron mientras tanto.
     */
    public function cancelarPorSistema(int $saleId): bool
    {
        $cancelada = DB::transaction(function () use ($saleId) {
            $sale = Sale::with('details')->lockForUpdate()->find($saleId);

            if (!$sale || $sale->estatus !== Sale::ESTATUS_PENDIENTE || $sale->tieneCancelacionSolicitada()) {
                return false;
            }
            if ($sale->metodo_pago === 'transferencia' && $sale->transferencia_evidencia_path) {
                return false;
            }

            $this->revertirYCancelar($sale, self::POR_SISTEMA, null);

            return true;
        });

        if ($cancelada) {
            OrderNotificationService::sendCancelledNotification(Sale::with(['customer', 'details'])->find($saleId));
        }

        return $cancelada;
    }

    /** Cobro de Mercado Pago ya aprobado que hay que devolver antes de cancelar. */
    public function requiereDevolucionMp(Sale $sale): bool
    {
        return $sale->metodo_pago === 'mercado_pago'
            && $sale->estaPagada()
            && !empty($sale->mercadopago_payment_id)
            && $sale->mercadopago_status === 'approved'
            && $sale->reembolso_estado !== 'hecho';
    }

    /**
     * Repone el stock (solo si hubo salida), resta las estadisticas del cliente (solo si se habian sumado)
     * y deja la venta cancelada. Debe llamarse dentro de una transaccion con la venta bloqueada.
     */
    private function revertirYCancelar(Sale $sale, string $porTipo, ?int $userId): void
    {
        if ($sale->estaCancelada()) {
            throw new RuntimeException('El pedido ya está cancelado.');
        }

        foreach ($sale->details as $detail) {
            $product = Product::find($detail->product_id);
            if ($product) {
                $this->inventory->restoreCancelledSale($product, $detail, $userId);
            }
        }

        if ($this->estadisticasSumadas($sale)) {
            $customer = Customers::find($sale->customer_id);
            if ($customer) {
                $customer->total_compras = max(0, (float) $customer->total_compras - (float) $sale->total);
                $customer->numero_compras = max(0, (int) $customer->numero_compras - 1);
                $customer->save();
            }
        }

        $sale->estatus = Sale::ESTATUS_CANCELADA;
        $sale->cancelado_at = now();
        $sale->cancelado_por_tipo = $porTipo;
        $sale->cancelacion_solicitada_at = null;
        $sale->save();

        Log::info('Venta cancelada', ['sale_id' => $sale->id, 'por' => $porTipo, 'usuario_id' => $userId]);
    }

    /**
     * Las estadisticas del cliente se suman al crear la venta (transferencia, mostrador) o al aprobarse
     * el pago (Mercado Pago). Un Mercado Pago sin pagar nunca las sumo.
     */
    private function estadisticasSumadas(Sale $sale): bool
    {
        return $sale->metodo_pago !== 'mercado_pago' || $sale->estatus !== Sale::ESTATUS_PENDIENTE;
    }
}
