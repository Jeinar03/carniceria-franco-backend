<?php

namespace App\Http\Livewire\Despachos;

use App\Models\PanelSetting;
use App\Models\Sale;
use Livewire\Component;

/**
 * Aviso "Por terminar" del encabezado del panel: cuantos pedidos siguen sin
 * despacharse y cuanto lleva esperando el mas viejo.
 * Ver: Carnicería Franco/Spec - Aviso de pedidos por terminar.md
 */
class PedidosPorTerminar extends Component
{
    /** Maximo de pedidos que se listan en el desplegable. */
    private const MAX_LISTADO = 15;

    /** "12 min", "1 h 20 min", "2 h". */
    public static function formatearTiempo(int $minutos): string
    {
        $minutos = max(0, $minutos);

        if ($minutos < 60) {
            return $minutos . ' min';
        }

        $horas = intdiv($minutos, 60);
        $resto = $minutos % 60;

        return $resto === 0 ? $horas . ' h' : $horas . ' h ' . $resto . ' min';
    }

    public function render()
    {
        // Pedidos sin despachar, ni cancelados, ni programados a un dia posterior, ni
        // con transferencia rechazada. Una transferencia por validar SI cuenta.
        $pedidos = Sale::with('customer')
            ->enColaDespacho()
            ->entregaInmediata()
            ->where(function ($query) {
                $query->whereNull('transferencia_estado')
                    ->orWhere('transferencia_estado', '!=', 'rechazada');
            })
            ->orderBy('fecha_venta', 'asc')
            ->get();

        $amarillo = (int) PanelSetting::get('aviso_pedidos_amarillo_min', 30);
        $rojo = (int) PanelSetting::get('aviso_pedidos_rojo_min', 60);

        $masViejo = $pedidos->first();
        $minutosMasViejo = $masViejo ? (int) $masViejo->fecha_venta->diffInMinutes(now()) : 0;

        if (! $masViejo) {
            $color = 'secondary';
        } elseif ($minutosMasViejo > $rojo) {
            $color = 'danger';
        } elseif ($minutosMasViejo >= $amarillo) {
            $color = 'warning';
        } else {
            $color = 'success';
        }

        $listado = $pedidos->take(self::MAX_LISTADO)->map(function (Sale $venta) use ($amarillo, $rojo) {
            $minutos = (int) $venta->fecha_venta->diffInMinutes(now());

            return [
                'folio' => $venta->folio,
                'cliente' => $venta->customer
                    ? trim($venta->customer->nombre . ' ' . $venta->customer->apellido)
                    : 'Cliente General',
                'tiempo' => self::formatearTiempo($minutos),
                'color' => $minutos > $rojo ? 'danger' : ($minutos >= $amarillo ? 'warning' : 'success'),
                'estado' => str_replace('_', ' ', $venta->estado_envio),
                'transferencia_por_validar' => $venta->metodo_pago === 'transferencia'
                    && $venta->transferencia_estado === 'pendiente',
            ];
        });

        return view('livewire.despachos.pedidos-por-terminar', [
            'total' => $pedidos->count(),
            'color' => $color,
            'listado' => $listado,
            'masViejoTexto' => $masViejo ? self::formatearTiempo($minutosMasViejo) : null,
        ]);
    }
}
