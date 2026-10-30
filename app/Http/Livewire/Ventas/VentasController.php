<?php

namespace App\Http\Livewire\Ventas;

use App\Http\Livewire\Concerns\RequiresPanelRole;
use Livewire\Component;
use Livewire\WithPagination;
use App\Models\Sale;
use App\Models\Customers;
use App\Models\Product;
use App\Services\CancelacionVentaService;
use App\Services\InventoryService;
use App\Support\HoraLocal;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class VentasController extends Component
{
    use WithPagination;
    use RequiresPanelRole;

    private $perPage = 10;

    public $search = '';
    public $filtroEstatus = '';
    public $filtroMetodoPago = '';
    public $fechaInicio = '';
    public $fechaFin = '';
    public $selectedSaleId = null;
    public $componentName = 'Ventas';

    protected $queryString = [
        'search' => ['except' => ''],
        'filtroEstatus' => ['except' => ''],
        'filtroMetodoPago' => ['except' => ''],
        'fechaInicio' => ['except' => ''],
        'fechaFin' => ['except' => ''],
    ];

    protected $paginationTheme = 'bootstrap';

    protected $listeners = [
        'cancelSale' => 'cancelSale',
        'aprobarCancelacion' => 'aprobarCancelacion',
        'rechazarCancelacion' => 'rechazarCancelacion',
        'devolverPago' => 'devolverPago',
        'detailModalClosed' => 'closeDetail'
    ];

    public function paginationView()
    {
        return 'vendor.livewire.bootstrap';
    }

    public function mount()
    {
        // Establecer fechas por defecto solo si no vienen definidas.
        if (empty($this->fechaFin)) {
            $this->fechaFin = HoraLocal::hoy();
        }

        if (empty($this->fechaInicio)) {
            $this->fechaInicio = HoraLocal::ahora()->subDays(30)->format('Y-m-d');
        }
    }

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function updatingFiltroEstatus()
    {
        $this->resetPage();
    }

    public function updatingFiltroMetodoPago()
    {
        $this->resetPage();
    }

    public function updatingFechaInicio()
    {
        $this->resetPage();
    }

    public function updatingFechaFin()
    {
        $this->resetPage();
    }

    public function viewDetail($saleId)
    {
        $this->selectedSaleId = $saleId;
        $this->emit('show-detail-modal');
    }

    public function closeDetail()
    {
        $this->selectedSaleId = null;
    }

    public function requestCloseDetail()
    {
        $this->emit('hide-detail-modal');
    }

    public function cancelSale($saleId)
    {
        $this->requirePanelRole('Admin');

        $this->ejecutarCancelacion(function (CancelacionVentaService $cancelaciones) use ($saleId) {
            $sale = $cancelaciones->cancelarPorAdmin((int) $saleId, (int) auth()->id());

            return $sale->reembolso_estado === 'hecho'
                ? 'Venta cancelada y pago devuelto por Mercado Pago'
                : ($sale->reembolso_estado === 'manual'
                    ? 'Venta cancelada. Devuelve la transferencia al cliente a mano'
                    : 'Venta cancelada exitosamente');
        }, $saleId);
    }

    /** Aprueba la solicitud del cliente: devuelve el pago de Mercado Pago (si hubo) y cancela. */
    public function aprobarCancelacion($saleId)
    {
        $this->requirePanelRole('Admin');

        $this->ejecutarCancelacion(function (CancelacionVentaService $cancelaciones) use ($saleId) {
            $sale = Sale::findOrFail((int) $saleId);
            if (!$sale->tieneCancelacionSolicitada()) {
                throw new \RuntimeException('Este pedido no tiene una solicitud de cancelación pendiente');
            }

            $sale = $cancelaciones->cancelarPorAdmin((int) $saleId, (int) auth()->id());

            return $sale->reembolso_estado === 'manual'
                ? 'Solicitud aprobada. Devuelve la transferencia al cliente a mano'
                : 'Solicitud aprobada y pedido cancelado';
        }, $saleId);
    }

    public function rechazarCancelacion($saleId)
    {
        $this->requirePanelRole('Admin');

        $this->ejecutarCancelacion(function (CancelacionVentaService $cancelaciones) use ($saleId) {
            $cancelaciones->rechazarSolicitud((int) $saleId);

            return 'Solicitud rechazada; el pedido sigue su curso';
        }, $saleId);
    }

    /** Pago aprobado que llego a una venta ya cancelada: se devuelve con un clic. */
    public function devolverPago($saleId)
    {
        $this->requirePanelRole('Admin');

        $this->ejecutarCancelacion(function (CancelacionVentaService $cancelaciones) use ($saleId) {
            $cancelaciones->devolverPagoPendiente((int) $saleId);

            return 'Pago devuelto por Mercado Pago';
        }, $saleId);
    }

    private function ejecutarCancelacion(callable $accion, $saleId): void
    {
        try {
            $mensaje = $accion(app(CancelacionVentaService::class));

            $this->emit('sale-cancelled', $mensaje);
        } catch (Throwable $e) {
            Log::error('Error al cancelar venta', [
                'sale_id' => $saleId,
                'error' => $e->getMessage(),
            ]);

            $this->emit('sale-error', 'No se pudo completar: ' . $e->getMessage());
        }
    }

    public function clearFilters()
    {
        $this->search = '';
        $this->filtroEstatus = '';
        $this->filtroMetodoPago = '';
        $this->fechaInicio = HoraLocal::ahora()->subDays(30)->format('Y-m-d');
        $this->fechaFin = HoraLocal::hoy();
        $this->resetPage();
    }

    public function render()
    {
        $query = Sale::with(['customer', 'details'])->orderBy('fecha_venta', 'desc');
        $query = $this->applyFilters($query);

        $ventas = $query->paginate($this->perPage);

        // Calcular totales
        $totalesQuery = Sale::query();
        $totalesQuery = $this->applyFilters($totalesQuery);

        // Solo cuentan los pedidos pagados: ni cancelados ni pendientes de pago (Mercado Pago sin pagar, transferencia sin validar).
        $estatusPagados = ['completada', 'entregada'];

        $totales = [
            'total_ventas' => (clone $totalesQuery)
                ->whereIn('estatus', $estatusPagados)
                ->sum('total'),
            'numero_ventas' => (clone $totalesQuery)
                ->whereIn('estatus', $estatusPagados)
                ->count(),
            'ventas_hoy' => (clone $totalesQuery)
                ->whereDate('fecha_venta', today())
                ->whereIn('estatus', $estatusPagados)
                ->sum('total'),
        ];

        return view('livewire.ventas.ventas-controller', [
            'ventas' => $ventas,
            'totales' => $totales
        ])->extends('layouts.theme.app')
            ->section('content');
    }

    private function applyFilters($query)
    {
        $search = trim((string) $this->search);

        if ($search !== '') {
            $query->where(function ($q) {
                $q->where('folio', 'like', '%' . $this->search . '%')
                    ->orWhereHas('customer', function ($subQ) {
                        $subQ->where('nombre', 'like', '%' . $this->search . '%')
                            ->orWhere('apellido', 'like', '%' . $this->search . '%');
                    });
            });
        }

        if ($this->filtroEstatus) {
            $query->where('estatus', $this->filtroEstatus);
        }

        if ($this->filtroMetodoPago) {
            $query->where('metodo_pago', $this->filtroMetodoPago);
        }

        // Los dias del filtro son de la tienda (Mexico); fecha_venta se guarda en UTC.
        if ($this->fechaInicio && $this->fechaFin) {
            [$desde, $hasta] = HoraLocal::rangoUtc($this->fechaInicio, $this->fechaFin);
            $query->whereBetween('fecha_venta', [$desde, $hasta]);
        } elseif ($this->fechaInicio) {
            [$desde] = HoraLocal::rangoUtc($this->fechaInicio, $this->fechaInicio);
            $query->where('fecha_venta', '>=', $desde);
        } elseif ($this->fechaFin) {
            [, $hasta] = HoraLocal::rangoUtc($this->fechaFin, $this->fechaFin);
            $query->where('fecha_venta', '<=', $hasta);
        }

        return $query;
    }
}
