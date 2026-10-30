<?php

namespace App\Services;

use App\Models\MercadoPagoSetting;
use App\Models\Sale;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Devolucion completa de un pago de Mercado Pago.
 *
 * Usa la configuracion activa de Sistema, Mercado Pago (el Access Token va cifrado en la base).
 * La llave de idempotencia es fija por venta: si la peticion se repite, Mercado Pago no devuelve dos veces.
 */
class MercadoPagoReembolso
{
    private const URL = 'https://api.mercadopago.com/v1/payments/%s/refunds';
    private const URL_PAGO = 'https://api.mercadopago.com/v1/payments/%s';

    /**
     * @return array{id: string, monto: float}
     *
     * @throws RuntimeException con un mensaje apto para mostrar en el panel
     */
    public function devolverTodo(Sale $sale): array
    {
        if (!$sale->mercadopago_payment_id) {
            throw new RuntimeException('El pedido no tiene un pago de Mercado Pago registrado.');
        }

        $token = MercadoPagoSetting::credentials()['access_token'] ?? null;
        if (!$token) {
            throw new RuntimeException('Mercado Pago no tiene Access Token en Sistema, Mercado Pago.');
        }

        // Si el pago ya aparece devuelto (desde el panel de Mercado Pago, o un intento anterior que si llego
        // pero no alcanzo a cancelar el pedido), no se vuelve a pedir: se da por hecho y se sigue con la cancelacion.
        if ($this->yaDevuelto($token, (string) $sale->mercadopago_payment_id)) {
            return ['id' => 'ya-devuelto', 'monto' => (float) $sale->total];
        }

        try {
            $respuesta = Http::withToken($token)
                ->withHeaders(['X-Idempotency-Key' => 'reembolso-venta-' . $sale->id])
                ->acceptJson()
                ->timeout(20)
                ->withBody('{}', 'application/json') // sin monto = devolucion completa
                ->post(sprintf(self::URL, $sale->mercadopago_payment_id));
        } catch (\Throwable $e) {
            Log::error('Reembolso MP: sin respuesta', ['sale_id' => $sale->id, 'error' => $e->getMessage()]);
            throw new RuntimeException('No se pudo contactar a Mercado Pago. Intenta de nuevo en unos minutos.');
        }

        if (!$respuesta->successful()) {
            Log::error('Reembolso MP rechazado', [
                'sale_id' => $sale->id,
                'status' => $respuesta->status(),
                'body' => $respuesta->json('message') ?? $respuesta->json('error'),
            ]);
            throw new RuntimeException('Mercado Pago no aceptó la devolución (' . ($respuesta->json('message') ?? $respuesta->status()) . ').');
        }

        return [
            'id' => (string) $respuesta->json('id'),
            'monto' => (float) ($respuesta->json('amount') ?? $sale->total),
        ];
    }

    /** true solo si Mercado Pago confirma que el pago ya esta devuelto; ante cualquier duda, false. */
    private function yaDevuelto(string $token, string $paymentId): bool
    {
        try {
            $respuesta = Http::withToken($token)->acceptJson()->timeout(15)->get(sprintf(self::URL_PAGO, $paymentId));
        } catch (\Throwable $e) {
            return false;
        }

        return $respuesta->successful() && $respuesta->json('status') === 'refunded';
    }
}
