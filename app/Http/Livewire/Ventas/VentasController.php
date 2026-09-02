<?php

namespace App\Http\Livewire\Ventas;

use Livewire\Component;
use Livewire\WithPagination;
use App\Models\Sale;
use App\Models\Customers;
use App\Models\Product;
use App\Services\InventoryService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class VentasController extends Component
{
    use WithPagination;

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
            $this->fechaFin = date('Y-m-d');
        }

        if (empty($this->fechaInicio)) {
            $this->fechaInicio = date('Y-m-d', strtotime('-30 days'));
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
        try {
            DB::transaction(function () use ($saleId) {
                $sale = Sale::with('details')->lockForUpdate()->find($saleId);

                if (!$sale) {
                    throw new \RuntimeException('Venta no encontrada');
                }

                if ($sale->estatus === 'cancelada') {
                    throw new \RuntimeException('La venta ya está cancelada');
                }

                // Devolver stock a los productos
                foreach ($sale->details as $detail) {
                    $product = Product::find($detail->product_id);
                    if ($product) {
                        app(InventoryService::class)->restoreCancelledSale($product, $detail, auth()->id());
                    }
                }

                // Actualizar estadísticas del cliente
                $customer = Customers::find($sale->customer_id);
                if ($customer) {
                    $customer->total_compras = max(0, (float) $customer->total_compras - (float) $sale->total);
                    $customer->numero_compras = max(0, (int) $customer->numero_compras - 1);
                    $customer->save();
                }

                // Cambiar estatus
                $sale->estatus = 'cancelada';
                $sale->save();
            });

            $this->emit('sale-cancelled', 'Venta cancelada exitosamente');
        } catch (Throwable $e) {
            Log::error('Error al cancelar venta', [
                'sale_id' => $saleId,
                'error' => $e->getMessage(),
            ]);

            $this->emit('sale-error', 'Error al cancelar la venta: ' . $e->getMessage());
        }
    }

    public function clearFilters()
    {
        $this->search = '';
        $this->filtroEstatus = '';
        $this->filtroMetodoPago = '';
        $this->fechaInicio = date('Y-m-d', strtotime('-30 days'));
        $this->fechaFin = date('Y-m-d');
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

        $totales = [
            'total_ventas' => (clone $totalesQuery)
                ->where('estatus', '!=', 'cancelada')
                ->sum('total'),
            'numero_ventas' => (clone $totalesQuery)
                ->where('estatus', '!=', 'cancelada')
                ->count(),
            'ventas_hoy' => (clone $totalesQuery)
                ->whereDate('fecha_venta', today())
                ->where('estatus', '!=', 'cancelada')
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

        if ($this->fechaInicio && $this->fechaFin) {
            $query->whereBetween('fecha_venta', [$this->fechaInicio . ' 00:00:00', $this->fechaFin . ' 23:59:59']);
        } elseif ($this->fechaInicio) {
            $query->where('fecha_venta', '>=', $this->fechaInicio . ' 00:00:00');
        } elseif ($this->fechaFin) {
            $query->where('fecha_venta', '<=', $this->fechaFin . ' 23:59:59');
        }

        return $query;
    }
}
