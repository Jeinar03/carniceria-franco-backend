<div class="row sales layout-top-spacing">
    <div class="col-sm-12">
        <div class="widget widget-chart-one ventas-widget">
            <div class="widget-heading ventas-heading">
                <h4 class="card-title mb-0 d-flex flex-wrap align-items-center">
                    <span class="ventas-title">{{ $componentName }}</span>
                    <span class="ventas-total-badge ml-2">{{ $ventas->total() }} registros</span>
                </h4>
            </div>

            <div class="widget-content pt-3 px-3 px-md-4">
                <div class="row mb-3">
                    <div class="col-md-4 mb-3 mb-md-0">
                        <div class="card stat-card stat-card-gold">
                            <div class="card-body">
                                <p class="stat-label mb-1">Total periodo</p>
                                <h3 class="stat-value mb-0">${{ number_format($totales['total_ventas'], 2) }}</h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4 mb-3 mb-md-0">
                        <div class="card stat-card stat-card-dark">
                            <div class="card-body">
                                <p class="stat-label mb-1">Numero de ventas</p>
                                <h3 class="stat-value mb-0">{{ $totales['numero_ventas'] }}</h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card stat-card stat-card-bronze">
                            <div class="card-body">
                                <p class="stat-label mb-1">Ventas hoy</p>
                                <h3 class="stat-value mb-0">${{ number_format($totales['ventas_hoy'], 2) }}</h3>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card border-0 shadow-sm mb-3 ventas-filters-card">
                    <div class="card-body pb-2">
                        <div class="row align-items-end">
                            <div class="col-xl-3 col-lg-4 col-md-6 col-sm-12 mb-3">
                                <label class="text-muted filter-label">Buscar</label>
                                <input type="text" wire:model="search" class="form-control" placeholder="Folio o cliente...">
                            </div>
                            <div class="col-xl-2 col-lg-4 col-md-6 col-sm-12 mb-3">
                                <label class="text-muted filter-label">Estatus</label>
                                <select wire:model="filtroEstatus" class="form-control">
                                    <option value="">Todos</option>
                                    <option value="pendiente">Pendiente</option>
                                    <option value="completada">Completada</option>
                                    <option value="cancelada">Cancelada</option>
                                    <option value="entregada">Entregada</option>
                                </select>
                            </div>
                            <div class="col-xl-2 col-lg-4 col-md-6 col-sm-12 mb-3">
                                <label class="text-muted filter-label">Metodo de pago</label>
                                <select wire:model="filtroMetodoPago" class="form-control">
                                    <option value="">Todos</option>
                                    <option value="efectivo">Efectivo</option>
                                    <option value="tarjeta">Tarjeta</option>
                                    <option value="transferencia">Transferencia</option>
                                    <option value="credito">Credito</option>
                                </select>
                            </div>
                            <div class="col-xl-2 col-lg-3 col-md-6 col-sm-12 mb-3">
                                <label class="text-muted filter-label">Desde</label>
                                <input type="date" wire:model="fechaInicio" class="form-control">
                            </div>
                            <div class="col-xl-2 col-lg-3 col-md-6 col-sm-12 mb-3">
                                <label class="text-muted filter-label">Hasta</label>
                                <input type="date" wire:model="fechaFin" class="form-control">
                            </div>
                            <div class="col-xl-1 col-lg-2 col-md-6 col-sm-12 mb-3 d-flex">
                                <button wire:click="clearFilters" class="btn btn-dark btn-block" title="Limpiar filtros">
                                    <i class="fas fa-redo"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="table-responsive ventas-table-wrap">
                    <table class="table table-bordered mt-1 mb-0 ventas-table">
                        <thead>
                            <tr>
                                <th class="table-th">FOLIO</th>
                                <th class="table-th">FECHA</th>
                                <th class="table-th">CLIENTE</th>
                                <th class="table-th text-center">ITEMS</th>
                                <th class="table-th text-right">SUBTOTAL</th>
                                <th class="table-th text-right">DESCUENTO</th>
                                <th class="table-th text-right">TOTAL</th>
                                <th class="table-th">METODO PAGO</th>
                                <th class="table-th">ESTATUS</th>
                                <th class="table-th text-center">ACCIONES</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($ventas as $index => $venta)
                                @php
                                    $clienteNombre = $venta->customer ? $venta->customer->nombre . ' ' . $venta->customer->apellido : 'Cliente general';
                                    $clienteEmail = $venta->customer->email ?? 'Sin email';
                                @endphp
                                <tr class="{{ $index % 2 == 0 ? 'ventas-row-even' : '' }}">
                                    <td>
                                        <h6 class="mb-0 font-weight-bold">{{ $venta->folio }}</h6>
                                    </td>
                                    <td>{{ $venta->fecha_venta->format('d/m/Y H:i') }}</td>
                                    <td>
                                        <div class="font-weight-600">{{ $clienteNombre }}</div>
                                        <small class="text-muted">{{ $clienteEmail }}</small>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge badge-info">{{ $venta->details->count() }} items</span>
                                    </td>
                                    <td class="text-right">${{ number_format($venta->subtotal, 2) }}</td>
                                    <td class="text-right text-danger">-${{ number_format($venta->descuento, 2) }}</td>
                                    <td class="text-right">
                                        <h6 class="mb-0 text-success font-weight-bold">${{ number_format($venta->total, 2) }}</h6>
                                    </td>
                                    <td>
                                        @if($venta->metodo_pago == 'efectivo')
                                            <span class="badge badge-success">Efectivo</span>
                                        @elseif($venta->metodo_pago == 'tarjeta')
                                            <span class="badge badge-primary">Tarjeta</span>
                                        @elseif($venta->metodo_pago == 'transferencia')
                                            <span class="badge badge-info">Transferencia</span>
                                        @else
                                            <span class="badge badge-warning">Credito</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if($venta->estatus == 'completada')
                                            <span class="badge badge-success">Completada</span>
                                        @elseif($venta->estatus == 'pendiente')
                                            <span class="badge badge-warning">Pendiente</span>
                                        @elseif($venta->estatus == 'cancelada')
                                            <span class="badge badge-danger">Cancelada</span>
                                        @else
                                            <span class="badge badge-info">Entregada</span>
                                        @endif
                                    </td>
                                    <td class="text-center">
                                        <button wire:click="viewDetail({{ $venta->id }})" class="btn btn-sm btn-primary mtmobile"
                                            title="Ver Detalle"
                                            wire:loading.attr="disabled"
                                            wire:target="viewDetail({{ $venta->id }})">
                                            <span wire:loading.remove wire:target="viewDetail({{ $venta->id }})">
                                                <i class="fas fa-eye"></i>
                                            </span>
                                            <span wire:loading wire:target="viewDetail({{ $venta->id }})">
                                                <i class="fas fa-spinner fa-spin"></i>
                                            </span>
                                        </button>
                                        @if($venta->estatus != 'cancelada')
                                            <button onclick="confirmCancel({{ $venta->id }}, this)"
                                                class="btn btn-sm btn-outline-danger mtmobile" title="Cancelar">
                                                <i class="fas fa-ban"></i>
                                            </button>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>

                    <div class="d-flex flex-column flex-md-row justify-content-between align-items-center mt-3 px-2">
                        <small class="text-muted mb-2 mb-md-0">
                            Mostrando {{ $ventas->firstItem() ?? 0 }} - {{ $ventas->lastItem() ?? 0 }} de {{ $ventas->total() }} ventas
                        </small>
                        <div>
                            {{ $ventas->links() }}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Detalle -->
    @if($selectedSaleId)
        @include('livewire.ventas.detail-form')
    @endif
</div>

<style>
    .ventas-widget {
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        border-radius: 12px;
        overflow: hidden;
    }

    .ventas-heading {
        background: #3B3F5C;
        padding: 20px;
    }

    .ventas-title {
        color: #ffffff;
        font-weight: 700;
        font-size: 22px;
    }

    .ventas-total-badge {
        background: #f8f9fa;
        color: #3B3F5C;
        border-radius: 20px;
        padding: 4px 12px;
        font-size: 13px;
        font-weight: 700;
    }

    .stat-card {
        border: 0;
        border-radius: 12px;
    }

    .stat-card .card-body {
        padding: 18px;
    }

    .stat-card-gold {
        background: linear-gradient(135deg, #e7c77d 0%, #d5af59 100%);
        color: #2c2c2c;
    }

    .stat-card-dark {
        background: #3B3F5C;
        color: #ffffff;
    }

    .stat-card-bronze {
        background: linear-gradient(135deg, #d7b07b 0%, #bd9157 100%);
        color: #2c2c2c;
    }

    .stat-label {
        font-size: 13px;
        font-weight: 600;
        opacity: .9;
    }

    .stat-value {
        font-size: 26px;
        font-weight: 800;
    }

    .ventas-filters-card {
        border-radius: 10px;
    }

    .filter-label {
        font-size: 12px;
        font-weight: 600;
        margin-bottom: 6px;
    }

    .ventas-table-wrap {
        margin-top: 12px;
    }

    .ventas-table {
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
        border-radius: 10px;
        overflow: hidden;
    }

    .ventas-table thead {
        background: #3B3F5C;
    }

    .ventas-table .table-th {
        color: #ffffff;
        font-weight: 700;
        padding: 12px;
        border: none;
        font-size: 12px;
        letter-spacing: .3px;
        vertical-align: middle;
    }

    .ventas-table tbody td {
        padding: 12px;
        vertical-align: middle;
        font-size: 13px;
    }

    .ventas-row-even {
        background-color: #f8f9fa;
    }
</style>

<script>
    let cancellingButton = null;

    function setCancelButtonLoading(btn) {
        if (!btn) return;
        cancellingButton = btn;
        btn.disabled = true;
        btn.dataset.originalHtml = btn.innerHTML;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
    }

    function resetCancelButtonLoading() {
        if (!cancellingButton) return;
        cancellingButton.disabled = false;
        cancellingButton.innerHTML = cancellingButton.dataset.originalHtml || '<i class="fas fa-ban"></i>';
        cancellingButton = null;
    }

    function confirmCancel(saleId, btn) {
        setCancelButtonLoading(btn);
        window.livewire.emit('cancelSale', saleId);
    }

    document.addEventListener('DOMContentLoaded', function() {
        const cleanModalOverlay = () => {
            $('body').removeClass('modal-open');
            $('.modal-backdrop').remove();
        };

        window.livewire.on('sale-cancelled', msg => {
            resetCancelButtonLoading();
            Swal.fire({
                icon: 'success',
                title: 'Éxito',
                text: msg
            });
        });

        window.livewire.on('sale-error', msg => {
            resetCancelButtonLoading();
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: msg
            });
        });

        window.livewire.on('show-detail-modal', () => {
            $('#detailModal').modal('show');
        });

        window.livewire.on('hide-detail-modal', () => {
            $('#detailModal').modal('hide');
        });

        $(document).on('hidden.bs.modal', '#detailModal', function () {
            window.livewire.emit('detailModalClosed');
            cleanModalOverlay();
        });

        $(document).on('show.bs.modal', '#detailModal', function () {
            cleanModalOverlay();
        });
    });
</script>
