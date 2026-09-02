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
                'subject' => 'Tu pedido está siendo procesado - Carnicería Franco',
                'title' => 'Tu pedido está siendo procesado',
                'status_display' => 'Procesando',
                'message' => 'Estimado cliente, te informamos que hemos recibido tu pedido y nuestro equipo ha comenzado a procesarlo. Estamos preparando cuidadosamente todos los productos que solicitaste para garantizar la mejor calidad.',
                'color' => '#ffc107',
                'icon' => '',
                'next_step' => 'Te notificaremos tan pronto como tu pedido esté listo para el envío.',
                'estimated_time' => 'Tiempo estimado de preparación: 30-60 minutos'
            ],
            'Listo_para_enviar' => [
                'subject' => 'Tu pedido está listo para envío - Carnicería Franco',
                'title' => 'Tu pedido está listo para envío',
                'status_display' => 'Listo para envío',
                'message' => 'Nos complace informarte que tu pedido ha sido completamente procesado y empacado. Todos los productos han sido cuidadosamente seleccionados y están listos en perfectas condiciones para su envío.',
                'color' => '#28a745',
                'icon' => '',
                'next_step' => 'Nuestro equipo de reparto saldrá hacia tu domicilio en los próximos minutos.',
                'estimated_time' => 'Tiempo estimado de envío: 15-30 minutos'
            ],
            'Enviado' => [
                'subject' => 'Tu pedido está en camino - Carnicería Franco',
                'title' => 'Tu pedido está en camino',
                'status_display' => 'En camino',
                'message' => 'Tu pedido ha salido de nuestro establecimiento y se encuentra en camino hacia la dirección que nos proporcionaste. Nuestro repartidor se dirigirá directamente a tu domicilio.',
                'color' => '#007bff',
                'icon' => '',
                'next_step' => 'Por favor, mantente disponible para recibir tu pedido.',
                'estimated_time' => 'El tiempo de llegada dependerá de la distancia y las condiciones del tráfico'
            ],
            'completada' => [
                'subject' => 'Confirmación de compra - Carnicería Franco',
                'title' => 'Compra realizada exitosamente',
                'status_display' => 'Compra completada',
                'message' => 'Gracias por elegir Carnicería Franco. Tu compra ha sido procesada exitosamente. A continuación encontrarás el detalle completo de tu pedido para tu referencia.',
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
     * Enviar notificación de compra completada (para ventas API)
     */
    public static function sendPurchaseCompletedNotification(Sale $sale)
    {
        // Verificar que el cliente tenga email
        if (!$sale->customer || !$sale->customer->correo) {
            return false;
        }

        try {
            $statusConfig = self::getStatusConfig('completada');

            if (!$statusConfig) {
                return false;
            }

            Mail::to($sale->customer->correo)->send(
                new OrderStatusMail($sale, $statusConfig)
            );

            return true;
        } catch (\Exception $e) {
            Log::error('Error enviando notificación de compra completada: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Verificar si debe enviar notificación (evitar spam)
     */
    public static function shouldSendNotification($estadoAnterior, $estadoNuevo)
    {
        $estadosNotificables = ['Procesando', 'Listo_para_enviar', 'Enviado'];

        return in_array($estadoNuevo, $estadosNotificables) && $estadoAnterior !== $estadoNuevo;
    }
}
