<?php

namespace App\Console\Commands;

use App\Models\MercadoPagoSetting;
use App\Models\Sale;
use App\Services\CancelacionVentaService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Vence los pedidos que nunca se pagaron para liberar el stock apartado y limpiar el panel.
 *
 * - Mercado Pago pendiente: antes de cancelar consulta en Mercado Pago si entro un pago por esa venta;
 *   si no puede confirmarlo (sin token o sin red) no cancela nada.
 * - Transferencia sin comprobante: se cancela y repone el stock.
 * - Transferencia con comprobante en revision o con solicitud de cancelacion: no se toca.
 *
 * Se programa con cron cada hora: 0 * * * * php artisan ventas:expirar-pendientes
 */
class ExpirarVentasPendientes extends Command
{
    protected $signature = 'ventas:expirar-pendientes {--horas=24 : Horas sin pagar antes de vencer} {--dry-run : Solo muestra lo que cancelaria}';

    protected $description = 'Cancela los pedidos pendientes de pago que ya pasaron el tiempo limite';

    public function handle(CancelacionVentaService $cancelaciones): int
    {
        $horas = max(1, (int) $this->option('horas'));
        $dryRun = (bool) $this->option('dry-run');
        $limite = now()->subHours($horas);

        $vencidas = Sale::query()
            ->where('estatus', Sale::ESTATUS_PENDIENTE)
            ->where('fecha_venta', '<', $limite)
            ->whereNull('cancelacion_solicitada_at')
            ->whereIn('metodo_pago', ['mercado_pago', 'transferencia'])
            ->where(function ($q) {
                $q->where('metodo_pago', 'mercado_pago')
                    ->orWhereNull('transferencia_evidencia_path');
            })
            ->orderBy('id')
            ->get();

        $token = MercadoPagoSetting::credentials()['access_token'] ?? null;
        $canceladas = 0;
        $omitidas = 0;

        foreach ($vencidas as $sale) {
            if ($sale->metodo_pago === 'mercado_pago' && !$this->sinPagoEnMercadoPago($sale, $token)) {
                $omitidas++;
                continue;
            }

            if ($dryRun) {
                $this->line("Cancelaria la venta {$sale->id} ({$sale->folio}, {$sale->metodo_pago})");
                $canceladas++;
                continue;
            }

            if ($cancelaciones->cancelarPorSistema($sale->id)) {
                $canceladas++;
            } else {
                $omitidas++;
            }
        }

        $this->info("Pedidos vencidos: {$canceladas} cancelados, {$omitidas} omitidos");
        Log::info('ventas:expirar-pendientes', ['canceladas' => $canceladas, 'omitidas' => $omitidas, 'dry_run' => $dryRun]);

        return self::SUCCESS;
    }

    /**
     * true solo si Mercado Pago confirma que no hay un pago aprobado o en proceso para la venta.
     * Ante cualquier duda devuelve false para no cancelar un pedido que si se pago.
     */
    private function sinPagoEnMercadoPago(Sale $sale, ?string $token): bool
    {
        if (!$token) {
            Log::warning('Expiracion omitida: Mercado Pago sin Access Token', ['sale_id' => $sale->id]);
            return false;
        }

        try {
            $respuesta = Http::withToken($token)
                ->acceptJson()
                ->timeout(20)
                ->get('https://api.mercadopago.com/v1/payments/search', ['external_reference' => (string) $sale->id]);
        } catch (\Throwable $e) {
            Log::warning('Expiracion omitida: sin respuesta de Mercado Pago', ['sale_id' => $sale->id, 'error' => $e->getMessage()]);
            return false;
        }

        if (!$respuesta->successful()) {
            Log::warning('Expiracion omitida: Mercado Pago respondio ' . $respuesta->status(), ['sale_id' => $sale->id]);
            return false;
        }

        foreach ((array) $respuesta->json('results', []) as $pago) {
            if (in_array($pago['status'] ?? '', ['approved', 'authorized', 'in_process', 'pending', 'in_mediation'], true)) {
                Log::warning('Expiracion omitida: hay un pago vivo en Mercado Pago', ['sale_id' => $sale->id, 'status' => $pago['status']]);
                return false;
            }
        }

        return true;
    }
}
