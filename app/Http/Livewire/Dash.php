<?php

namespace App\Http\Livewire;

use App\Models\Sale;
use App\Models\SaleDetail;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Component;

class Dash extends Component
{
    public $year;
    public $fechaInicio;
    public $fechaFin;
    public $soloDiasConPedidos = true;
    public $kpis = [];
    public $dailyOrdersLabels = [];
    public $dailyOrdersData = [];
    public $satisfactionDistributionData = [];
    public $questionSatisfactionLabels = [];
    public $questionSatisfactionData = [];
    public $salesByMonthData = [];
    public $top5Labels = [];
    public $top5Data = [];
    public $weekSalesData = [];

    protected $queryString = [
        'fechaInicio' => ['except' => ''],
        'fechaFin' => ['except' => ''],
        'soloDiasConPedidos' => ['except' => true],
    ];

    public function mount()
    {
        $this->year = Carbon::now()->year;

        if (empty($this->fechaInicio)) {
            $this->fechaInicio = Carbon::now()->startOfMonth()->format('Y-m-d');
        }

        if (empty($this->fechaFin)) {
            $this->fechaFin = Carbon::now()->endOfMonth()->format('Y-m-d');
        }

        $this->refreshDashboard(false);
    }

    public function updatedFechaInicio()
    {
        $this->refreshDashboard();
    }

    public function updatedFechaFin()
    {
        $this->refreshDashboard();
    }

    public function updatedSoloDiasConPedidos()
    {
        $this->soloDiasConPedidos = filter_var($this->soloDiasConPedidos, FILTER_VALIDATE_BOOLEAN);
        $this->refreshDashboard();
    }

    public function resetDateFilters()
    {
        $this->fechaInicio = Carbon::now()->startOfMonth()->format('Y-m-d');
        $this->fechaFin = Carbon::now()->endOfMonth()->format('Y-m-d');
        $this->refreshDashboard();
    }

    public function refreshDashboard(bool $dispatchBrowserEvent = true)
    {
        [$periodStart] = $this->periodBounds();
        $this->year = $periodStart->year;
        $this->loadIndicatorKpis();
        $this->loadDailyOrders();
        $this->loadSatisfactionCharts();
        $this->loadSalesByMonth();
        $this->loadTopProducts();
        $this->loadWeekSales();

        if ($dispatchBrowserEvent) {
            $this->dispatchBrowserEvent('dashboard-charts-updated', [
                'salesByMonthData' => $this->salesByMonthData,
                'top5Labels' => $this->top5Labels,
                'top5Data' => $this->top5Data,
                'weekSalesData' => $this->weekSalesData,
                'dailyOrdersLabels' => $this->dailyOrdersLabels,
                'dailyOrdersData' => $this->dailyOrdersData,
                'satisfactionDistributionData' => $this->satisfactionDistributionData,
                'questionSatisfactionLabels' => $this->questionSatisfactionLabels,
                'questionSatisfactionData' => $this->questionSatisfactionData,
                'periodo' => $this->kpis['periodo'] ?? '',
            ]);
        }
    }

    private function loadIndicatorKpis()
    {
        [$periodStart, $periodEnd] = $this->periodBounds();

        $ordersQuery = Sale::query()
            ->whereBetween('fecha_venta', [$periodStart, $periodEnd]);

        $totalPedidosPeriodo = (clone $ordersQuery)->count();
        $pedidosAtendidos = (clone $ordersQuery)
            ->where('estatus', '!=', 'cancelada')
            ->count();

        $pedidosCancelados = (clone $ordersQuery)
            ->where('estatus', 'cancelada')
            ->count();

        $ticketPromedio = (clone $ordersQuery)
            ->where('estatus', '!=', 'cancelada')
            ->avg('total');

        $avgProcessingMinutes = Sale::query()
            ->whereBetween('fecha_venta', [$periodStart, $periodEnd])
            ->where(function ($query) {
                $query->whereIn('estado_envio', ['Enviado', 'Entregado', 'Entregada'])
                    ->orWhereIn('estatus', ['entregado', 'entregada']);
            })
            ->whereNotNull('fecha_venta')
            ->whereNotNull('updated_at')
            ->avg(DB::raw('TIMESTAMPDIFF(MINUTE, fecha_venta, updated_at)'));

        $satisfaccionPromedio = null;
        $aceptacionRecomendaciones = null;
        $totalEncuestas = 0;

        if (Schema::hasTable('indicador_respuestas') && Schema::hasTable('indicador_preguntas')) {
            $totalEncuestas = DB::table('indicador_respuestas')
                ->whereBetween('created_at', [$periodStart, $periodEnd])
                ->select('sale_id', 'customer_id')
                ->distinct()
                ->count();

            $satisfaccionPromedio = DB::table('indicador_respuestas')
                ->whereBetween('created_at', [$periodStart, $periodEnd])
                ->avg('respuesta');

            $recomendacionesQuery = DB::table('indicador_respuestas as ir')
                ->join('indicador_preguntas as ip', 'ip.id', '=', 'ir.pregunta_id')
                ->whereBetween('ir.created_at', [$periodStart, $periodEnd])
                ->where(function ($query) {
                    $query->where('ip.pregunta', 'like', '%recomend%')
                        ->orWhere('ip.descripcion', 'like', '%recomend%');
                });

            $totalRecomendaciones = (clone $recomendacionesQuery)->count();
            if ($totalRecomendaciones > 0) {
                $aceptadas = (clone $recomendacionesQuery)
                    ->where('ir.respuesta', '>=', 8)
                    ->count();

                $aceptacionRecomendaciones = ($aceptadas / $totalRecomendaciones) * 100;
            }
        }

        $this->kpis = [
            'periodo' => $periodStart->format('d/m/Y') . ' - ' . $periodEnd->format('d/m/Y'),
            'pedidos_hoy' => Sale::whereDate('fecha_venta', Carbon::today())
                ->where('estatus', '!=', 'cancelada')
                ->count(),
            'pedidos_atendidos' => $pedidosAtendidos,
            'tasa_errores' => $totalPedidosPeriodo > 0 ? ($pedidosCancelados / $totalPedidosPeriodo) * 100 : 0,
            'tiempo_promedio_minutos' => $avgProcessingMinutes ? round((float) $avgProcessingMinutes, 1) : 0,
            'ticket_promedio' => $ticketPromedio ? round((float) $ticketPromedio, 2) : 0,
            'satisfaccion_promedio' => $satisfaccionPromedio ? round((float) $satisfaccionPromedio, 1) : 0,
            'total_encuestas' => $totalEncuestas,
            'aceptacion_recomendaciones' => $aceptacionRecomendaciones !== null ? round($aceptacionRecomendaciones, 1) : null,
        ];
    }

    private function loadDailyOrders()
    {
        [$start, $end] = $this->periodBounds();

        $ordersByDay = Sale::query()
            ->selectRaw('DATE(fecha_venta) as day, COUNT(*) as total')
            ->whereBetween('fecha_venta', [$start, $end])
            ->where('estatus', '!=', 'cancelada')
            ->groupByRaw('DATE(fecha_venta)')
            ->pluck('total', 'day')
            ->toArray();

        $this->dailyOrdersLabels = [];
        $this->dailyOrdersData = [];

        for ($date = $start->copy(); $date <= $end; $date->addDay()) {
            $key = $date->format('Y-m-d');
            $total = (int) ($ordersByDay[$key] ?? 0);

            if ($this->soloDiasConPedidos && $total === 0) {
                continue;
            }

            $this->dailyOrdersLabels[] = $date->format('d/m');
            $this->dailyOrdersData[] = $total;
        }

        if (count($this->dailyOrdersData) === 0) {
            $this->dailyOrdersLabels = ['Sin pedidos'];
            $this->dailyOrdersData = [0];
        }
    }

    private function loadSatisfactionCharts()
    {
        [$periodStart, $periodEnd] = $this->periodBounds();

        $this->satisfactionDistributionData = array_fill(0, 10, 0);
        $this->questionSatisfactionLabels = ['Sin datos'];
        $this->questionSatisfactionData = [0];

        if (!Schema::hasTable('indicador_respuestas') || !Schema::hasTable('indicador_preguntas')) {
            return;
        }

        $distribution = DB::table('indicador_respuestas')
            ->selectRaw('respuesta, COUNT(*) as total')
            ->whereBetween('created_at', [$periodStart, $periodEnd])
            ->groupBy('respuesta')
            ->pluck('total', 'respuesta')
            ->toArray();

        for ($score = 1; $score <= 10; $score++) {
            $this->satisfactionDistributionData[$score - 1] = (int) ($distribution[$score] ?? 0);
        }

        $questionAverages = DB::table('indicador_respuestas as ir')
            ->join('indicador_preguntas as ip', 'ip.id', '=', 'ir.pregunta_id')
            ->selectRaw('ip.pregunta, AVG(ir.respuesta) as promedio')
            ->whereBetween('ir.created_at', [$periodStart, $periodEnd])
            ->groupBy('ip.id', 'ip.pregunta')
            ->orderBy('ip.orden')
            ->limit(5)
            ->get();

        if ($questionAverages->count() > 0) {
            $this->questionSatisfactionLabels = $questionAverages->pluck('pregunta')->map(function ($pregunta) {
                return strlen($pregunta) > 38 ? substr($pregunta, 0, 38) . '...' : $pregunta;
            })->toArray();

            $this->questionSatisfactionData = $questionAverages->pluck('promedio')->map(function ($promedio) {
                return round((float) $promedio, 1);
            })->toArray();
        }
    }

    private function loadSalesByMonth()
    {
        [$periodStart, $periodEnd] = $this->periodBounds();

        $monthlyTotals = Sale::query()
            ->selectRaw('MONTH(fecha_venta) as month, COALESCE(SUM(total),0) as total')
            ->whereBetween('fecha_venta', [$periodStart, $periodEnd])
            ->where('estatus', 'completada')
            ->groupByRaw('MONTH(fecha_venta)')
            ->pluck('total', 'month')
            ->toArray();

        $this->salesByMonthData = [];
        for ($month = 1; $month <= 12; $month++) {
            $this->salesByMonthData[] = (float) ($monthlyTotals[$month] ?? 0);
        }
    }

    private function loadTopProducts()
    {
        [$periodStart, $periodEnd] = $this->periodBounds();

        $topProducts = SaleDetail::query()
            ->selectRaw('COALESCE(producto_nombre, "Producto") as product, COALESCE(SUM(cantidad),0) as total')
            ->whereHas('sale', function ($q) use ($periodStart, $periodEnd) {
                $q->whereBetween('fecha_venta', [$periodStart, $periodEnd])
                    ->where('estatus', 'completada');
            })
            ->groupBy('producto_nombre')
            ->orderByDesc('total')
            ->limit(5)
            ->get();

        $this->top5Labels = $topProducts->pluck('product')->map(function ($item) {
            return (string) $item;
        })->toArray();

        $this->top5Data = $topProducts->pluck('total')->map(function ($item) {
            return (float) $item;
        })->toArray();

        if (count($this->top5Data) === 0) {
            $this->top5Labels = ['Sin datos'];
            $this->top5Data = [0];
        }
    }

    private function loadWeekSales()
    {
        [$periodStart, $periodEnd] = $this->periodBounds();

        $weekTotals = Sale::query()
            ->selectRaw('WEEKDAY(fecha_venta) as day_index, COALESCE(SUM(total),0) as total')
            ->whereBetween('fecha_venta', [$periodStart, $periodEnd])
            ->where('estatus', 'completada')
            ->groupByRaw('WEEKDAY(fecha_venta)')
            ->pluck('total', 'day_index')
            ->toArray();

        $this->weekSalesData = [];
        for ($day = 0; $day <= 6; $day++) {
            $this->weekSalesData[] = (float) ($weekTotals[$day] ?? 0);
        }
    }

    private function periodBounds(): array
    {
        try {
            $start = Carbon::parse($this->fechaInicio ?: Carbon::now()->startOfMonth())->startOfDay();
        } catch (\Throwable $e) {
            $start = Carbon::now()->startOfMonth()->startOfDay();
            $this->fechaInicio = $start->format('Y-m-d');
        }

        try {
            $end = Carbon::parse($this->fechaFin ?: Carbon::now()->endOfMonth())->endOfDay();
        } catch (\Throwable $e) {
            $end = Carbon::now()->endOfMonth()->endOfDay();
            $this->fechaFin = $end->format('Y-m-d');
        }

        if ($start->gt($end)) {
            $end = $start->copy()->endOfDay();
            $this->fechaFin = $end->format('Y-m-d');
        }

        return [$start, $end];
    }

    public function render()
    {
        return view('livewire.dash.component', [
            'year' => $this->year,
            'kpis' => $this->kpis,
            'dailyOrdersLabels' => $this->dailyOrdersLabels,
            'dailyOrdersData' => $this->dailyOrdersData,
            'satisfactionDistributionData' => $this->satisfactionDistributionData,
            'questionSatisfactionLabels' => $this->questionSatisfactionLabels,
            'questionSatisfactionData' => $this->questionSatisfactionData,
            'salesByMonthData' => $this->salesByMonthData,
            'top5Labels' => $this->top5Labels,
            'top5Data' => $this->top5Data,
            'weekSalesData' => $this->weekSalesData,
        ])->extends('layouts.theme.app')
            ->section('content');
    }
}
