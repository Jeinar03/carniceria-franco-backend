<div>
    <div class="row layout-top-spacing mt-4">
        <div class="col-sm-12">
            <div class="widget widget-chart-one dashboard-header">
                <div class="dashboard-header-content">
                    <h3 class="dashboard-title mb-1">Dashboard de indicadores</h3>
                    <p class="dashboard-subtitle mb-0">{{ $kpis['periodo'] ?? 'Ultimos 30 dias' }}</p>
                </div>
                <div class="dashboard-filters">
                    <div class="dashboard-filter-field">
                        <label>Fecha inicio</label>
                        <input type="date" wire:model.lazy="fechaInicio" class="form-control">
                    </div>
                    <div class="dashboard-filter-field">
                        <label>Fecha fin</label>
                        <input type="date" wire:model.lazy="fechaFin" class="form-control">
                    </div>
                    <button type="button" wire:click="resetDateFilters" class="btn btn-light dashboard-reset-btn" title="Mes actual">
                        <i class="fas fa-redo"></i>
                    </button>
                    <button type="button" class="btn btn-light dashboard-download-all-btn" title="Descargar todas las graficas" onclick="downloadAllDashboardCharts()">
                        <i class="fas fa-download"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="row mt-3">
        <div class="col-xl-3 col-md-6 col-sm-12 mb-3">
            <div class="indicator-card">
                <span class="indicator-label">Tiempo medio de procesamiento</span>
                <strong>{{ number_format($kpis['tiempo_promedio_minutos'] ?? 0, 1) }} min</strong>
                <small>Pedido creado a enviado/entregado</small>
            </div>
        </div>
        <div class="col-xl-3 col-md-6 col-sm-12 mb-3">
            <div class="indicator-card">
                <span class="indicator-label">Tasa de errores en pedidos</span>
                <strong>{{ number_format($kpis['tasa_errores'] ?? 0, 1) }}%</strong>
                <small>Pedidos cancelados sobre procesados</small>
            </div>
        </div>
        <div class="col-xl-3 col-md-6 col-sm-12 mb-3">
            <div class="indicator-card">
                <span class="indicator-label">Pedidos atendidos</span>
                <strong>{{ $kpis['pedidos_atendidos'] ?? 0 }}</strong>
                <small>{{ $kpis['pedidos_hoy'] ?? 0 }} pedidos hoy</small>
            </div>
        </div>
        <div class="col-xl-3 col-md-6 col-sm-12 mb-3">
            <div class="indicator-card">
                <span class="indicator-label">Ticket promedio</span>
                <strong>${{ number_format($kpis['ticket_promedio'] ?? 0, 2) }}</strong>
                <small>Promedio por venta atendida</small>
            </div>
        </div>
        <div class="col-xl-3 col-md-6 col-sm-12 mb-3">
            <div class="indicator-card">
                <span class="indicator-label">Satisfaccion del cliente</span>
                <strong>{{ number_format($kpis['satisfaccion_promedio'] ?? 0, 1) }}/10</strong>
                <small>{{ $kpis['total_encuestas'] ?? 0 }} cuestionarios respondidos</small>
            </div>
        </div>
        <div class="col-xl-3 col-md-6 col-sm-12 mb-3">
            <div class="indicator-card">
                <span class="indicator-label">Aceptacion de recomendaciones</span>
                <strong>
                    @if (($kpis['aceptacion_recomendaciones'] ?? null) === null)
                        N/A
                    @else
                        {{ number_format($kpis['aceptacion_recomendaciones'], 1) }}%
                    @endif
                </strong>
                <small>Proxy: respuestas 8 a 10 en recomendacion</small>
            </div>
        </div>
        <div class="col-xl-3 col-md-6 col-sm-12 mb-3">
            <div class="indicator-card">
                <span class="indicator-label">Escala Likert</span>
                <strong>1 - 10</strong>
                <small>Satisfaccion por pregunta</small>
            </div>
        </div>
        <div class="col-xl-3 col-md-6 col-sm-12 mb-3">
            <div class="indicator-card">
                <span class="indicator-label">Ventas anuales</span>
                <strong>${{ number_format(array_sum($salesByMonthData ?? []), 2) }}</strong>
                <small>{{ $year }}</small>
            </div>
        </div>
    </div>

    <div class="row mt-2">
        <div class="col-xl-7 col-lg-12 mb-4">
            <div class="widget widget-chart-one dashboard-chart">
                <div class="chart-card-heading">
                    <h4 class="text-theme-1 font-bold mb-0">Volumen diario de pedidos</h4>
                    <label class="chart-check mb-0">
                        <input type="checkbox" wire:model="soloDiasConPedidos">
                        <span>Solo dias con pedidos</span>
                    </label>
                    <button type="button" class="btn btn-sm btn-outline-dark chart-download-btn" title="Descargar grafica" onclick="downloadDashboardChart('dailyOrders')">
                        <i class="fas fa-download"></i>
                    </button>
                </div>
                <div id="chartDailyOrders" wire:ignore></div>
            </div>
        </div>
        <div class="col-xl-5 col-lg-12 mb-4">
            <div class="widget widget-chart-one dashboard-chart">
                <div class="chart-card-heading">
                    <h4 class="text-theme-1 font-bold mb-0">Distribucion de satisfaccion</h4>
                    <button type="button" class="btn btn-sm btn-outline-dark chart-download-btn" title="Descargar grafica" onclick="downloadDashboardChart('satisfactionDistribution')">
                        <i class="fas fa-download"></i>
                    </button>
                </div>
                <div id="chartSatisfactionDistribution" wire:ignore></div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-xl-6 col-lg-12 mb-4">
            <div class="widget widget-chart-one dashboard-chart">
                <div class="chart-card-heading">
                    <h4 class="text-theme-1 font-bold mb-0">Satisfaccion por pregunta</h4>
                    <button type="button" class="btn btn-sm btn-outline-dark chart-download-btn" title="Descargar grafica" onclick="downloadDashboardChart('questionSatisfaction')">
                        <i class="fas fa-download"></i>
                    </button>
                </div>
                <div id="chartQuestionSatisfaction" wire:ignore></div>
            </div>
        </div>
        <div class="col-xl-6 col-lg-12 mb-4">
            <div class="widget widget-chart-one dashboard-chart">
                <div class="chart-card-heading">
                    <h4 class="text-theme-1 font-bold mb-0">Ventas de la semana</h4>
                    <button type="button" class="btn btn-sm btn-outline-dark chart-download-btn" title="Descargar grafica" onclick="downloadDashboardChart('weekSales')">
                        <i class="fas fa-download"></i>
                    </button>
                </div>
                <div id="areaChart" wire:ignore></div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-sm-12 col-md-5 mb-4">
            <div class="widget widget-chart-one dashboard-chart">
                <div class="chart-card-heading">
                    <h4 class="text-theme-1 font-bold mb-0">Top 5 mas vendidos</h4>
                    <button type="button" class="btn btn-sm btn-outline-dark chart-download-btn" title="Descargar grafica" onclick="downloadDashboardChart('top5')">
                        <i class="fas fa-download"></i>
                    </button>
                </div>
                <div id="chartTop5" wire:ignore></div>
            </div>
        </div>
        <div class="col-sm-12 col-md-7 mb-4">
            <div class="widget widget-chart-one dashboard-chart">
                <div class="chart-card-heading">
                    <h4 class="text-theme-1 font-bold mb-0">Ventas anuales {{ $year }}</h4>
                    <button type="button" class="btn btn-sm btn-outline-dark chart-download-btn" title="Descargar grafica" onclick="downloadDashboardChart('monthSales')">
                        <i class="fas fa-download"></i>
                    </button>
                </div>
                <div id="chartMonth" wire:ignore></div>
            </div>
        </div>
    </div>

    <style>
        .dashboard-header {
            background: #3B3F5C;
            color: #fff;
            padding: 20px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            flex-wrap: wrap;
        }

        .dashboard-header-content {
            min-width: 220px;
        }

        .dashboard-title {
            color: #fff;
            font-size: 24px;
            font-weight: 800;
        }

        .dashboard-subtitle {
            color: rgba(255,255,255,.78);
            font-size: 13px;
        }

        .dashboard-filters {
            display: flex;
            align-items: flex-end;
            gap: 10px;
            flex-wrap: wrap;
        }

        .dashboard-filter-field {
            min-width: 160px;
        }

        .dashboard-filter-field label {
            color: rgba(255,255,255,.82);
            display: block;
            font-size: 11px;
            font-weight: 700;
            margin-bottom: 5px;
            text-transform: uppercase;
        }

        .dashboard-filter-field .form-control {
            border: 0;
            min-height: 38px;
        }

        .dashboard-reset-btn,
        .dashboard-download-all-btn {
            min-height: 38px;
            min-width: 42px;
        }

        .indicator-card {
            background: #fff;
            border: 1px solid #e8eaf1;
            border-radius: 8px;
            padding: 18px;
            min-height: 128px;
            box-shadow: 0 2px 10px rgba(31,45,61,.06);
        }

        .indicator-card strong {
            color: #1f2937;
            display: block;
            font-size: 26px;
            font-weight: 800;
            line-height: 1.2;
            margin-top: 8px;
        }

        .indicator-card small,
        .indicator-label {
            color: #697086;
            display: block;
            font-size: 12px;
            font-weight: 600;
        }

        .indicator-label {
            color: #3B3F5C;
            text-transform: uppercase;
        }

        .dashboard-chart {
            min-height: 390px;
            border-radius: 8px;
        }

        .chart-card-heading {
            align-items: center;
            display: flex;
            gap: 12px;
            justify-content: space-between;
            padding: 16px 18px 8px;
        }

        .chart-card-heading h4 {
            flex: 1;
            font-size: 20px;
            text-align: center;
        }

        .chart-download-btn {
            min-height: 32px;
            min-width: 36px;
        }

        .chart-check {
            align-items: center;
            color: #3B3F5C;
            display: flex;
            font-size: 12px;
            font-weight: 700;
            gap: 6px;
            white-space: nowrap;
        }

        .chart-check input {
            height: 16px;
            width: 16px;
        }

        @media (max-width: 575.98px) {
            .dashboard-filter-field,
            .dashboard-filters,
            .dashboard-reset-btn {
                width: 100%;
            }

            .chart-card-heading {
                align-items: flex-start;
                flex-direction: column;
            }

            .chart-card-heading h4 {
                text-align: left;
            }
        }
    </style>

    @include('livewire.dash.script')
</div>
