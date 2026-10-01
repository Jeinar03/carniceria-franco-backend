<?php

namespace App\Services;

use App\Models\Sale;
use Illuminate\Support\Facades\Mail;
use App\Mail\OrderStatusMail;
use Illuminate\Support\Facades\Log;

class OrderNotificationService
{
    /**
     * Enviar notificación basada en el estado del pedido
     */
    public static function sendStatusNotification(Sale $sale)
    {
        // Verificar que el cliente tenga email
        if (!$sale->customer || !$sale->customer->correo) {
            return false;
        }

        try {
            $statusConfig = self::getStatusConfig($sale->estado_envio);

            if (!$statusConfig) {
                return false;
            }

            Mail::to($sale->customer->correo)->send(
                new OrderStatusMail($sale, $statusConfig)
            );

            return true;
        } catch (\Exception $e) {
            Log::error('Error enviando notificación de pedido: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Obtener configuración del email según el estado
     */
    private static function getStatusConfig($estado)
    {
        $configs = [
            'Procesando' => [
                'subject' => 'Tu pedido está siendo procesado - Carnicería Franko',
                'title' => 'Tu pedido está siendo procesado',
                'status_display' => 'Procesando',
                'message' => 'Estimado cliente, te informamos que hemos recibido tu pedido y nuestro equipo ha comenzado a procesarlo. Estamos preparando cuidadosamente todos los productos que solicitaste para garantizar la mejor calidad.',
                'color' => '#ffc107',
                'icon' => '',
                'next_step' => 'Te notificaremos tan pronto como tu pedido esté listo para el envío.',
                'estimated_time' => 'Tiempo estimado de preparación: 30-60 minutos'
            ],
            'Listo_para_enviar' => [
                'subject' => 'Tu pedido está listo para envío - Carnicería Franko',
                'title' => 'Tu pedido está listo para envío',
                'status_display' => 'Listo para envío',
                'message' => 'Nos complace informarte que tu pedido ha sido completamente procesado y empacado. Todos los productos han sido cuidadosamente seleccionados y están listos en perfectas condiciones para su envío.',
                'color' => '#28a745',
                'icon' => '',
                'next_step' => 'Nuestro equipo de reparto saldrá hacia tu domicilio en los próximos minutos.',
                'estimated_time' => 'Tiempo estimado de envío: 15-30 minutos'
            ],
            'Enviado' => [
                'subject' => 'Tu pedido está en camino - Carnicería Franko',
                'title' => 'Tu pedido está en camino',
                'status_display' => 'En camino',
                'message' => 'Tu pedido ha salido de nuestro establecimiento y se encuentra en camino hacia la dirección que nos proporcionaste. Nuestro repartidor se dirigirá directamente a tu domicilio.',
                'color' => '#007bff',
                'icon' => '',
                'next_step' => 'Por favor, mantente disponible para recibir tu pedido.',
                'estimated_time' => 'El tiempo de llegada dependerá de la distancia y las condiciones del tráfico'
            ],
            // Transferencia registrada: el pago todavia no se valida, asi que NO es una compra completada.
            'recibido_transferencia' => [
                'subject' => 'Recibimos tu pedido - Carnicería Franko',
                'title' => 'Recibimos tu pedido',
                'status_display' => 'Pendiente de validar la transferencia',
                'message' => 'Gracias por tu pedido en Carnicería Franko. Ya lo tenemos registrado y estamos por validar tu transferencia para empezar a prepararlo. Si todavía no subiste tu comprobante, puedes hacerlo desde "Mis compras" en la tienda.',
                'color' => '#ffc107',
                'icon' => '',
                'next_step' => 'Cuando confirmemos tu pago te avisaremos por correo.',
                'estimated_time' => 'La validación de la transferencia se hace en horario de atención.',
                // Este correo lleva los datos bancarios del sitio (si estan capturados).
                'mostrar_datos_bancarios' => true
            ],
            'transferencia_aprobada' => [
                'subject' => 'Confirmamos tu pago - Carnicería Franko',
                'title' => 'Confirmamos tu pago',
                'status_display' => 'Pago confirmado',
                'message' => 'Validamos tu transferencia: tu pago quedó confirmado y tu pedido pasa a preparación. Gracias por tu compra.',
                'color' => '#28a745',
                'icon' => '',
                'next_step' => 'Te avisaremos cuando tu pedido salga en camino.',
                'estimated_time' => 'Te mantendremos informado de cada paso de tu pedido.'
            ],
            // Siempre generico: nunca se incluye el motivo que escribio el empleado en el panel.
            'transferencia_rechazada' => [
                'subject' => 'No pudimos validar tu transferencia - Carnicería Franko',
                'title' => 'No pudimos validar tu transferencia',
                'status_display' => 'Transferencia no validada',
                'message' => 'No pudimos validar tu transferencia con el comprobante que recibimos. Tu pedido sigue registrado, pero no lo prepararemos hasta confirmar el pago.',
                'color' => '#dc3545',
                'icon' => '',
                'next_step' => 'Puedes subir otro comprobante desde "Mis compras" en la tienda o escribirnos por WhatsApp.',
                'estimated_time' => 'Si ya pagaste y crees que es un error, escríbenos y lo revisamos.'
            ],
            'completada' => [
                'subject' => 'Confirmación de compra - Carnicería Franko',
                'title' => 'Compra realizada exitosamente',
                'status_display' => 'Compra completada',
                'message' => 'Gracias por elegir Carnicería Franko. Tu compra ha sido procesada exitosamente. A continuación encontrarás el detalle completo de tu pedido para tu referencia.',
                'color' => '#28a745',
                'icon' => '',
                'next_step' => 'Conserva este correo como comprobante de tu compra.',
                'estimated_time' => 'Tu pedido será procesado y enviado en las próximas horas'
            ]
        ];

        return $configs[$estado] ?? null;
    }

    /**
     * Obtener información resumida del pedido para el email
     */
    public static function getOrderSummary(Sale $sale)
    {
        $productos = $sale->details->map(function ($detail) {
            return [
                'nombre' => $detail->producto_nombre,
                'cantidad' => number_format($detail->cantidad, 2),
                'unidad' => $detail->unidad_venta,
                'precio' => number_format($detail->precio_unitario, 2),
                'subtotal' => number_format($detail->total, 2)
            ];
        });

        return [
            'folio' => $sale->folio,
            'fecha' => $sale->fecha_venta->format('d/m/Y H:i'),
            'productos' => $productos,
            'total' => number_format($sale->total, 2),
            'cantidad_items' => $sale->details->count()
        ];
    }

    /**
     * Correo al registrar una compra (tienda o panel). Una transferencia que aun no se valida manda
     * "Recibimos tu pedido"; cualquier otra compra ya pagada manda "Confirmacion de compra".
     */
    public static function sendPurchaseCompletedNotification(Sale $sale)
    {
        $pendienteDeValidar = $sale->metodo_pago === 'transferencia' && $sale->transferencia_estado !== 'aprobada';

        return self::enviarCorreo($sale, $pendienteDeValidar ? 'recibido_transferencia' : 'completada');
    }

    /** Correo cuando el panel aprueba la transferencia. */
    public static function sendTransferApprovedNotification(Sale $sale)
    {
        return self::enviarCorreo($sale, 'transferencia_aprobada');
    }

    /** Correo cuando el panel rechaza la transferencia. Es generico: no lleva el motivo del empleado. */
    public static function sendTransferRejectedNotification(Sale $sale)
    {
        return self::enviarCorreo($sale, 'transferencia_rechazada');
    }

    private static function enviarCorreo(Sale $sale, string $clave): bool
    {
        // Sin cliente (venta de mostrador) o sin correo no hay a quien avisar.
        if (!$sale->customer || !$sale->customer->correo) {
            return false;
        }

        try {
            $statusConfig = self::getStatusConfig($clave);

            if (!$statusConfig) {
                return false;
            }

            Mail::to($sale->customer->correo)->send(
                new OrderStatusMail($sale, $statusConfig)
            );

            return true;
        } catch (\Exception $e) {
            Log::error("Error enviando el correo '{$clave}' del pedido: " . $e->getMessage());
            return false;
        }
    }
}
