@php
    $sale = \App\Models\Sale::with(['customer', 'details.product'])->find($selectedSaleId);
@endphp

@if($sale)
<div wire:ignore.self id="detailModal" class="modal fade" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-xl sales-detail-modal-dialog" role="document">
        <div class="modal-content sales-detail-modal-content">
            <div class="modal-header sales-detail-header">
                <h5 class="modal-title sales-detail-title">
                    <i class="fas fa-receipt"></i> Detalle de Venta | {{ $sale->folio }}
                </h5>
                <h6 class="ml-auto mr-3 mb-0">
                    @if($sale->estatus == 'completada')
                        <span class="badge badge-success sales-badge-status">Completada</span>
                    @elseif($sale->estatus == 'pendiente')
                        <span class="badge badge-warning sales-badge-status">Pendiente</span>
                    @elseif($sale->estatus == 'cancelada')
                        <span class="badge badge-danger sales-badge-status">Cancelada</span>
                    @else
                        <span class="badge badge-info sales-badge-status">Entregada</span>
                    @endif
                </h6>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close" wire:click="requestCloseDetail">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body sales-detail-body">
                <div class="row mb-3">
                    <div class="col-xl-3 col-md-6 mb-2">
                        <div class="sales-summary-card">
                            <small class="text-muted d-block">Fecha</small>
                            <strong>{{ $sale->fecha_venta->format('d/m/Y H:i:s') }}</strong>
                        </div>
                    </div>
                    <div class="col-xl-3 col-md-6 mb-2">
                        <div class="sales-summary-card">
                            <small class="text-muted d-block">Cliente</small>
                            <strong>{{ $sale->customer ? $sale->customer->nombre . ' ' . $sale->customer->apellido : 'Cliente general' }}</strong>
                        </div>
                    </div>
                    <div class="col-xl-2 col-md-6 mb-2">
                        <div class="sales-summary-card">
                            <small class="text-muted d-block">Items</small>
                            <strong>{{ $sale->details->count() }}</strong>
                        </div>
                    </div>
                    <div class="col-xl-4 col-md-6 mb-2">
                        <div class="sales-summary-card total">
                            <small class="text-muted d-block">Total venta</small>
                            <strong>${{ number_format($sale->total, 2) }}</strong>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-lg-8 mb-3 mb-lg-0">
                        <div class="card sales-detail-card h-100">
                            <div class="card-header sales-detail-card-header">
                                <h6 class="mb-0 font-weight-bold"><i class="fas fa-list"></i> Productos de la venta</h6>
                            </div>
                            <div class="card-body p-0">
                                <div class="table-responsive">
                                    <table class="table table-bordered mb-0 sales-detail-products-table">
                                        <thead>
                                            <tr>
                                                <th>Codigo</th>
                                                <th>Producto</th>
                                                <th>Unidad</th>
                                                <th class="text-right">Cantidad</th>
                                                <th class="text-right">P. Unit.</th>
                                                <th class="text-right">P. Oferta</th>
                                                <th class="text-right">Subtotal</th>
                                                <th class="text-right">Desc.</th>
                                                <th class="text-right">Total</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach($sale->details as $index => $detail)
                                                <tr class="{{ $index % 2 == 0 ? 'detail-row-even' : '' }}">
                                                    <td class="font-weight-bold">{{ $detail->producto_codigo }}</td>
                                                    <td>
                                                        <div class="font-weight-600">{{ $detail->producto_nombre }}</div>
                                                        @if($detail->product && $detail->product->imagen)
                                                            <img src="{{ asset($detail->product->imagen) }}"
                                                                 alt="{{ $detail->producto_nombre }}"
                                                                 class="rounded mt-1"
                                                                 style="width: 38px; height: 38px; object-fit: cover;">
                                                        @endif
                                                    </td>
                                                    <td>{{ ucfirst($detail->unidad_venta) }}</td>
                                                    <td class="text-right">{{ $detail->cantidad }}</td>
                                                    <td class="text-right">${{ number_format($detail->precio_unitario, 2) }}</td>
                                                    <td class="text-right">
                                                        @if($detail->precio_oferta)
                                                            <span class="text-success font-weight-bold">${{ number_format($detail->precio_oferta, 2) }}</span>
                                                        @else
                                                            <span class="text-muted">-</span>
                                                        @endif
                                                    </td>
                                                    <td class="text-right">${{ number_format($detail->subtotal, 2) }}</td>
                                                    <td class="text-right text-danger">-${{ number_format($detail->descuento, 2) }}</td>
                                                    <td class="text-right font-weight-bold">${{ number_format($detail->total, 2) }}</td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-4">
                        <div class="card sales-detail-card mb-3">
                            <div class="card-header sales-detail-card-header-light">
                                <h6 class="mb-0 font-weight-bold"><i class="fas fa-user"></i> Cliente</h6>
                            </div>
                            <div class="card-body py-3 px-3 sales-info-grid">
                                <div><small class="text-muted">Nombre</small><strong class="d-block">{{ $sale->customer ? $sale->customer->nombre . ' ' . $sale->customer->apellido : 'Cliente general' }}</strong></div>
                                <div><small class="text-muted">Email</small><strong class="d-block">{{ $sale->customer->email ?? 'Sin email' }}</strong></div>
                                <div><small class="text-muted">Telefono</small><strong class="d-block">{{ $sale->customer->telefono ?? 'N/A' }}</strong></div>
                                <div><small class="text-muted">Direccion</small><strong class="d-block">{{ $sale->customer->direccion ?? 'N/A' }}</strong></div>
                                <div><small class="text-muted">Ciudad</small><strong class="d-block">{{ ($sale->customer->ciudad ?? 'N/A') . ', ' . ($sale->customer->estado ?? '') }}</strong></div>
                                <div><small class="text-muted">Tipo</small><strong class="d-block">{{ ucfirst($sale->customer->tipo_cliente ?? 'minorista') }}</strong></div>
                            </div>
                        </div>

                        <div class="card sales-detail-card mb-3">
                            <div class="card-header sales-detail-card-header-light">
                                <h6 class="mb-0 font-weight-bold"><i class="fas fa-credit-card"></i> Venta</h6>
                            </div>
                            <div class="card-body py-3 px-3 sales-info-grid">
                                <div><small class="text-muted">Metodo pago</small><strong class="d-block">{{ ucfirst($sale->metodo_pago) }}</strong></div>
                                <div><small class="text-muted">Notas</small><strong class="d-block">{{ $sale->notas ?? 'Sin notas' }}</strong></div>
                            </div>
                        </div>

                        <div class="card sales-detail-card">
                            <div class="card-header sales-detail-card-header-light">
                                <h6 class="mb-0 font-weight-bold"><i class="fas fa-calculator"></i> Totales</h6>
                            </div>
                            <div class="card-body py-3 px-3">
                                <div class="d-flex justify-content-between mb-2"><span>Subtotal</span><strong>${{ number_format($sale->subtotal, 2) }}</strong></div>
                                <div class="d-flex justify-content-between mb-2"><span>Descuento</span><strong class="text-danger">-${{ number_format($sale->descuento, 2) }}</strong></div>
                                <div class="d-flex justify-content-between mb-2"><span>Impuestos (IVA 16%)</span><strong>${{ number_format($sale->impuestos, 2) }}</strong></div>
                                <hr class="my-2">
                                <div class="d-flex justify-content-between align-items-center">
                                    <span class="font-weight-bold">TOTAL</span>
                                    <h4 class="mb-0 text-success font-weight-bold">${{ number_format($sale->total, 2) }}</h4>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer sales-detail-footer">
                <button type="button" class="btn btn-outline-secondary" data-dismiss="modal" wire:click="requestCloseDetail">
                    <i class="fas fa-times"></i> Cerrar
                </button>
                <button type="button" class="btn btn-primary" onclick="window.print()">
                    <i class="fas fa-print"></i> Imprimir
                </button>
            </div>
        </div>
    </div>
</div>

<style>
    .sales-detail-modal-dialog {
        width: 94vw;
        max-width: 1500px;
    }

    .sales-detail-modal-content {
        border: 0;
        border-radius: 12px;
    }

    .sales-detail-header {
        background: #3B3F5C;
        border: none;
    }

    .sales-detail-title {
        color: #ffffff;
        font-weight: 700;
        font-size: 22px;
    }

    .sales-badge-status {
        font-size: 12px;
        padding: 7px 14px;
        border-radius: 14px;
    }

    .sales-detail-body {
        padding: 16px;
    }

    .sales-summary-card {
        background: #f8f9fa;
        border: 1px solid #e9ecef;
        border-radius: 10px;
        padding: 10px 12px;
        min-height: 70px;
    }

    .sales-summary-card.total strong {
        color: #28a745;
        font-size: 22px;
    }

    .sales-detail-card {
        border: 1px solid #e9ecef;
        border-radius: 10px;
    }

    .sales-detail-card-header {
        background: #3B3F5C;
        color: #fff;
        border-bottom: 0;
    }

    .sales-detail-card-header-light {
        background: #f8f9fa;
        color: #3B3F5C;
        border-bottom: 1px solid #e9ecef;
    }

    .sales-detail-products-table thead {
        background: #3B3F5C;
    }

    .sales-detail-products-table thead th {
        color: #fff;
        font-size: 12px;
        font-weight: 700;
        border: none;
        padding: 10px;
    }

    .sales-detail-products-table tbody td {
        padding: 9px 10px;
        font-size: 12px;
        vertical-align: middle;
    }

    .detail-row-even {
        background-color: #f8f9fa;
    }

    .sales-info-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 10px 12px;
    }

    .sales-detail-footer {
        border-top: 1px solid #e9ecef;
        padding: 14px 16px;
    }

    @media (max-width: 992px) {
        .sales-detail-modal-dialog {
            width: 98vw;
            max-width: 98vw;
            margin: 0.5rem auto;
        }

        .sales-info-grid {
            grid-template-columns: 1fr;
        }
    }
</style>
@endif
