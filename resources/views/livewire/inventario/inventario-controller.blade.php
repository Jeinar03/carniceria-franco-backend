<div class="row sales layout-top-spacing">
    <div class="col-sm-12">
        <div class="widget widget-chart-one">
            <div class="widget-heading align-items-center">
                <h4 class="card-title mb-0"><b>Inventario | Movimientos de stock</b></h4>
                @if($activeTab === 'entradas')
                    <button type="button" wire:click="openEntryModal" class="btn btn-primary btn-rounded">
                        <i class="fas fa-plus mr-1"></i> Nueva entrada
                    </button>
                @endif
            </div>

            <div class="widget-content mt-3">
                <ul class="nav nav-tabs mb-4" role="tablist">
                    <li class="nav-item">
                        <a href="javascript:void(0)" wire:click="setTab('entradas')"
                           class="nav-link {{ $activeTab === 'entradas' ? 'active' : '' }}">
                            <i class="fas fa-arrow-down text-success mr-1"></i> Entradas
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="javascript:void(0)" wire:click="setTab('salidas')"
                           class="nav-link {{ $activeTab === 'salidas' ? 'active' : '' }}">
                            <i class="fas fa-arrow-up text-danger mr-1"></i> Salidas
                        </a>
                    </li>
                </ul>

                @php($dailyData = $activeTab === 'entradas' ? $entriesByDay : $exitsByDay)
                @php($movementType = $activeTab === 'entradas' ? 'entrada' : 'salida')

                <div class="table-responsive">
                    <table class="table table-bordered table-striped mt-1">
                        <thead style="background: #3B3F5C">
                            <tr>
                                <th class="table-th">FECHA</th>
                                <th class="table-th text-center">MOVIMIENTOS</th>
                                <th class="table-th">STOCK {{ $activeTab === 'entradas' ? 'RECIBIDO' : 'VENDIDO' }}</th>
                                <th class="table-th text-center">DETALLE</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($dailyData as $day)
                                <tr>
                                    <td>
                                        <h6 class="mb-0">{{ \Carbon\Carbon::parse($day->date)->locale('es')->translatedFormat('d \d\e F \d\e Y') }}</h6>
                                        <small class="text-muted">Último movimiento {{ \Carbon\Carbon::parse($day->last_movement)->format('H:i') }}</small>
                                    </td>
                                    <td class="text-center"><span class="badge badge-info">{{ $day->movement_count }}</span></td>
                                    <td>
                                        @foreach($day->totals as $total)
                                            <span class="badge {{ $activeTab === 'entradas' ? 'badge-success' : 'badge-danger' }} mr-1 mb-1">
                                                {{ number_format($total->quantity, 3) }} {{ ucfirst($total->unit) }}
                                            </span>
                                        @endforeach
                                    </td>
                                    <td class="text-center">
                                        <button type="button" class="btn btn-dark btn-sm btn-rounded"
                                                wire:click="showDayDetail('{{ $day->date }}', '{{ $movementType }}')">
                                            <i class="fas fa-eye mr-1"></i> Ver detalle
                                        </button>
                                        @if($activeTab === 'entradas' && $day->has_receipt)
                                            <a href="{{ route('inventory.entries.pdf', ['date' => $day->date]) }}"
                                               target="_blank" class="btn btn-danger btn-sm btn-rounded ml-1">
                                                <i class="fas fa-file-pdf mr-1"></i> Generar PDF
                                            </a>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="text-center py-5">
                                        <i class="fas fa-box-open fa-2x text-muted mb-2"></i>
                                        <h6>No hay {{ $activeTab }} de inventario registradas.</h6>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div wire:ignore.self class="modal fade" id="entryModal" tabindex="-1" role="dialog">
        <div class="modal-dialog modal-xl" role="document">
            <div class="modal-content">
                <div class="modal-header bg-dark">
                    <h5 class="modal-title text-white"><b>Inventario</b> | NUEVA ENTRADA</h5>
                    <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-info">
                        Captura únicamente los productos recibidos. La cantidad se registra en la unidad de venta indicada.
                    </div>
                    <div class="input-group mb-3">
                        <div class="input-group-prepend"><span class="input-group-text"><i class="fas fa-search"></i></span></div>
                        <input type="text" wire:model.debounce.300ms="productSearch" class="form-control" placeholder="Buscar por nombre o código">
                    </div>
                    @error('entryQuantities') <div class="alert alert-danger">{{ $message }}</div> @enderror
                    <div class="table-responsive inventory-product-list">
                        <table class="table table-hover table-bordered">
                            <thead style="background: #3B3F5C">
                                <tr>
                                    <th class="table-th">PRODUCTO</th>
                                    <th class="table-th text-center">EXISTENCIA ACTUAL</th>
                                    <th class="table-th text-right">PRECIO</th>
                                    <th class="table-th text-center" style="width: 250px;">CANTIDAD DE ENTRADA</th>
                                    <th class="table-th text-right">COSTO</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($products as $product)
                                    <tr>
                                        <td>
                                            <strong>{{ $product->nombre }}</strong><br>
                                            <small class="text-muted">{{ $product->codigo ?: 'Sin código' }}</small>
                                        </td>
                                        <td class="text-center">
                                            {{ number_format($product->stock, 3) }} {{ ucfirst($product->unidad_venta) }}
                                        </td>
                                        <td class="text-right">${{ number_format($product->precio, 2) }}</td>
                                        <td>
                                            <div class="input-group">
                                                <input type="number" min="0" step="0.001"
                                                       wire:model.defer="entryQuantities.{{ $product->id }}"
                                                       oninput="updateEntryCost(this, {{ (float) $product->precio }}, 'entryCost{{ $product->id }}')"
                                                       class="form-control" placeholder="0">
                                                <div class="input-group-append">
                                                    <span class="input-group-text">{{ ucfirst($product->unidad_venta) }}</span>
                                                </div>
                                            </div>
                                            @error('entryQuantities.' . $product->id)
                                                <small class="text-danger">{{ $message }}</small>
                                            @enderror
                                        </td>
                                        <td class="text-right">
                                            <strong id="entryCost{{ $product->id }}" class="text-success">
                                                ${{ number_format(((float) ($entryQuantities[$product->id] ?? 0)) * (float) $product->precio, 2) }}
                                            </strong>
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="5" class="text-center">No se encontraron productos activos.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    <div class="form-group mt-3">
                        <label>Notas de recepción</label>
                        <textarea wire:model.defer="entryNotes" class="form-control" rows="2" maxlength="500" placeholder="Proveedor, factura u observaciones (opcional)"></textarea>
                        @error('entryNotes') <small class="text-danger">{{ $message }}</small> @enderror
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">CERRAR</button>
                    <button type="button" wire:click="saveEntries" wire:loading.attr="disabled" class="btn btn-dark">
                        <span wire:loading.remove wire:target="saveEntries">GUARDAR ENTRADAS</span>
                        <span wire:loading wire:target="saveEntries">GUARDANDO...</span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div wire:ignore.self class="modal fade" id="inventoryDetailModal" tabindex="-1" role="dialog">
        <div class="modal-dialog modal-xl" role="document">
            <div class="modal-content">
                <div class="modal-header bg-dark">
                    <h5 class="modal-title text-white">
                        Detalle de {{ $detailType === 'entrada' ? 'entradas' : 'salidas' }}
                        @if($detailDate) | {{ \Carbon\Carbon::parse($detailDate)->format('d/m/Y') }} @endif
                    </h5>
                    <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
                </div>
                <div class="modal-body">
                    <div class="table-responsive">
                        <table class="table table-bordered table-striped">
                            <thead style="background: #3B3F5C">
                                <tr>
                                    <th class="table-th">HORA</th>
                                    <th class="table-th">PRODUCTO</th>
                                    <th class="table-th text-center">CANTIDAD</th>
                                    <th class="table-th text-center">SALDO</th>
                                    <th class="table-th text-right">COSTO</th>
                                    <th class="table-th">REFERENCIA / HISTORIAL</th>
                                    <th class="table-th text-center">ACCIONES</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($detailMovements as $movement)
                                    <tr class="{{ $movement->status === \App\Models\InventoryMovement::STATUS_CANCELLED ? 'table-danger' : '' }}">
                                        <td>{{ $movement->moved_at->format('H:i') }}</td>
                                        <td>
                                            <strong>{{ $movement->product->nombre ?? 'Producto eliminado' }}</strong><br>
                                            <small class="text-muted">{{ $movement->product->codigo ?? '' }}</small>
                                            @if($movement->status === \App\Models\InventoryMovement::STATUS_CANCELLED)
                                                <br><span class="badge badge-danger mt-1">CANCELADA</span>
                                            @endif
                                        </td>
                                        <td class="text-center">{{ number_format($movement->quantity, 3) }} {{ ucfirst($movement->unit) }}</td>
                                        <td class="text-center">{{ number_format($movement->balance_after, 3) }}</td>
                                        <td class="text-right">
                                            @if($movement->total_cost !== null)
                                                ${{ number_format($movement->total_cost, 2) }}
                                            @else
                                                —
                                            @endif
                                        </td>
                                        <td>
                                            @if($movement->sale)
                                                <span class="badge badge-info">Venta {{ $movement->sale->folio }}</span><br>
                                            @endif
                                            <small>{{ $movement->notes ?: ucfirst($movement->origin) }}</small>
                                            @if($movement->user)
                                                <br><small class="text-muted">Creada por {{ $movement->user->name }}</small>
                                            @endif
                                            @foreach($movement->audits as $audit)
                                                <div class="inventory-audit-line mt-1">
                                                    <i class="fas fa-history mr-1"></i>
                                                    @if($audit->action === 'edited')
                                                        Editada: {{ number_format($audit->old_quantity, 3) }} → {{ number_format($audit->new_quantity, 3) }}
                                                    @else
                                                        Entrada cancelada
                                                    @endif
                                                    · {{ optional($audit->user)->name ?: 'Usuario no disponible' }}
                                                    · {{ $audit->created_at->format('d/m/Y H:i') }}
                                                </div>
                                            @endforeach
                                            @if($movement->status === \App\Models\InventoryMovement::STATUS_CANCELLED)
                                                <div class="text-danger small mt-1">
                                                    Cancelada por {{ optional($movement->cancelledBy)->name ?: 'Usuario no disponible' }}
                                                    el {{ optional($movement->cancelled_at)->format('d/m/Y H:i') }}
                                                    @if($movement->cancellation_reason) · {{ $movement->cancellation_reason }} @endif
                                                </div>
                                            @endif
                                        </td>
                                        <td class="text-center">
                                            @if($movement->type === 'entrada' && $movement->origin === 'compra' && $movement->status === \App\Models\InventoryMovement::STATUS_ACTIVE)
                                                <button type="button" wire:click="openEditEntry({{ $movement->id }})"
                                                        class="btn btn-warning btn-sm btn-rounded mb-1" title="Editar entrada">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button type="button" onclick="ConfirmCancelEntry({{ $movement->id }})"
                                                        class="btn btn-danger btn-sm btn-rounded mb-1" title="Cancelar entrada">
                                                    <i class="fas fa-ban"></i>
                                                </button>
                                            @else
                                                <span class="text-muted">—</span>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="7" class="text-center">No hay movimientos para mostrar.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-dismiss="modal">CERRAR</button></div>
            </div>
        </div>
    </div>

    <div wire:ignore.self class="modal fade" id="editEntryModal" tabindex="-1" role="dialog">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header bg-dark">
                    <h5 class="modal-title text-white"><i class="fas fa-edit mr-2"></i>Editar entrada de inventario</h5>
                    <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-warning">
                        El sistema conservará el valor anterior en el historial. Si la reducción provocara stock negativo en cualquier venta posterior, no permitirá guardar.
                    </div>
                    <div class="form-group">
                        <label>Nueva cantidad <span class="text-danger">*</span></label>
                        <input type="number" wire:model.defer="editQuantity" min="0.001" step="0.001"
                               class="form-control @error('editQuantity') is-invalid @enderror">
                        @error('editQuantity') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="form-group mb-0">
                        <label>Notas de recepción</label>
                        <textarea wire:model.defer="editNotes" class="form-control" rows="3" maxlength="500"></textarea>
                        @error('editNotes') <small class="text-danger">{{ $message }}</small> @enderror
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">CERRAR</button>
                    <button type="button" wire:click="updateEntry" wire:loading.attr="disabled" class="btn btn-dark">
                        <span wire:loading.remove wire:target="updateEntry">GUARDAR CAMBIOS</span>
                        <span wire:loading wire:target="updateEntry">VALIDANDO...</span>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    .inventory-product-list { max-height: 430px; overflow-y: auto; }
    .nav-tabs .nav-link.active { color: #3b3f5c; font-weight: 700; border-bottom: 3px solid #3b3f5c; }
    .inventory-audit-line { color: #59627a; font-size: 11px; padding: 3px 5px; background: #eef1f6; border-radius: 4px; }
</style>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        window.livewire.on('show-entry-modal', function () { $('#entryModal').modal('show') })
        window.livewire.on('hide-entry-modal', function () { $('#entryModal').modal('hide') })
        window.livewire.on('show-inventory-detail-modal', function () { $('#inventoryDetailModal').modal('show') })
        window.livewire.on('show-edit-entry-modal', function () {
            $('#inventoryDetailModal').modal('hide')
            setTimeout(function () { $('#editEntryModal').modal('show') }, 250)
        })
        window.livewire.on('hide-edit-entry-modal', function () {
            $('#editEntryModal').modal('hide')
            setTimeout(function () { $('#inventoryDetailModal').modal('show') }, 250)
        })
        window.livewire.on('inventory-saved', function (message) { noty(message) })
        window.livewire.on('inventory-error', function (message) { noty(message, 2) })
    })

    function updateEntryCost(input, price, targetId) {
        var quantity = parseFloat(input.value) || 0
        var target = document.getElementById(targetId)
        if (target) {
            target.textContent = new Intl.NumberFormat('es-MX', {
                style: 'currency',
                currency: 'MXN'
            }).format(quantity * price)
        }
    }

    function ConfirmCancelEntry(id) {
        swal({
            title: 'CANCELAR ENTRADA',
            text: 'Se descontará del inventario la cantidad recibida. No será posible si alguna venta posterior deja el stock negativo.',
            type: 'warning',
            input: 'textarea',
            inputPlaceholder: 'Motivo de cancelación (opcional)',
            showCancelButton: true,
            cancelButtonText: 'Cerrar',
            confirmButtonColor: '#e7515a',
            confirmButtonText: 'Cancelar entrada'
        }).then(function (result) {
            if (result.value !== undefined && result.dismiss === undefined) {
                window.livewire.emit('cancelInventoryEntry', id, result.value || '')
            }
        })
    }
</script>
