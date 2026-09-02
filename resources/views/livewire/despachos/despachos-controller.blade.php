<div class="row sales layout-top-spacing">
    <div class="col-sm-12">
        <div class="widget widget-chart-one">
            <div class="widget-heading">
                <h4 class="card-title">
                    <b>{{ $componentName }} | {{ $pageTitle }}</b>
                </h4>
            </div>

            <div class="widget-content pt-2">
                <div class="card border-0 shadow-sm mb-3 despachos-toolbar">
                    <div class="card-body pb-2">
                        <div class="row align-items-end">
                            <div class="col-xl-3 col-lg-4 col-md-6 col-sm-12 mb-3">
                                <label class="text-muted filter-label">Folio</label>
                                <input type="text" wire:model="filtroFolio" placeholder="Buscar folio..." class="form-control">
                            </div>
                            <div class="col-xl-3 col-lg-4 col-md-6 col-sm-12 mb-3">
                                <label class="text-muted filter-label">Cliente</label>
                                <input type="text" wire:model="filtroCliente" placeholder="Buscar cliente..." class="form-control">
                            </div>
                            <div class="col-xl-2 col-lg-4 col-md-6 col-sm-12 mb-3">
                                <label class="text-muted filter-label">Estado</label>
                                <select wire:model="filtroEstado" class="form-control">
                                    <option value="">Todos</option>
                                    <option value="Pendiente">Pendiente</option>
                                    <option value="Procesando">Procesando</option>
                                    <option value="Listo_para_enviar">Listo para enviar</option>
                                </select>
                            </div>
                            <div class="col-xl-2 col-lg-3 col-md-6 col-sm-12 mb-3">
                                <label class="text-muted filter-label">Registros</label>
                                <select wire:model="perPage" class="form-control">
                                    <option value="10">10</option>
                                    <option value="15">15</option>
                                    <option value="25">25</option>
                                    <option value="50">50</option>
                                </select>
                            </div>
                            <div class="col-xl-2 col-lg-3 col-md-6 col-sm-12 mb-3 d-flex">
                                <button wire:click="clearFilters" class="btn btn-dark btn-block">
                                    <i class="fas fa-redo"></i> Limpiar
                                </button>
                            </div>
                        </div>

                        <div class="row align-items-center pt-1">
                            <div class="col-md-7 col-sm-12 mb-2 mb-md-0">
                                <span class="badge badge-warning px-3 py-2">
                                    <i class="fas fa-exclamation-triangle"></i> Urgente: +3 horas
                                </span>
                            </div>
                            <div class="col-md-5 col-sm-12 text-md-right text-left">
                                <button class="btn btn-success"
                                        wire:click="openCreateOrderModal"
                                        wire:loading.attr="disabled"
                                        wire:target="openCreateOrderModal">
                                    <span wire:loading.remove wire:target="openCreateOrderModal">
                                        <i class="fas fa-cart-plus"></i> Crear pedido
                                    </span>
                                    <span wire:loading wire:target="openCreateOrderModal">
                                        <i class="fas fa-spinner fa-spin"></i> Abriendo...
                                    </span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-bordered table-striped mt-1">
                        <thead style="background: #3B3F5C">
                            <tr>
                                <th class="table-th text-center">FOLIO</th>
                                <th class="table-th text-center">CLIENTE</th>
                                <th class="table-th text-center">FECHA VENTA</th>
                                <th class="table-th text-center">TOTAL</th>
                                <th class="table-th text-center">ESTADO</th>
                                <th class="table-th text-center">ITEMS</th>
                                <th class="table-th text-center">TIEMPO</th>
                                <th class="table-th text-center">ACCIONES</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($ventas as $venta)
                                @php
                                    $horasTranscurridas = \Carbon\Carbon::parse($venta->fecha_venta)->diffInHours(\Carbon\Carbon::now());
                                    $isUrgent = in_array($venta->id, $ventasUrgentes);
                                    $badgeClass = '';
                                    $badgeText = '';

                                    switch($venta->estado_envio) {
                                        case 'Pendiente':
                                            $badgeClass = 'badge-secondary';
                                            $badgeText = 'Pendiente';
                                            break;
                                        case 'Procesando':
                                            $badgeClass = 'badge-warning';
                                            $badgeText = 'Procesando';
                                            break;
                                        case 'Listo_para_enviar':
                                            $badgeClass = 'badge-success';
                                            $badgeText = 'Listo para enviar';
                                            break;
                                    }
                                @endphp
                                <tr class="{{ $isUrgent ? 'table-danger' : '' }}"
                                    style="{{ $isUrgent ? 'animation: blink 2s infinite;' : '' }}">
                                    <td class="text-center">
                                        <h6><strong>{{ $venta->folio }}</strong></h6>
                                        @if($isUrgent)
                                            <span class="badge badge-danger badge-sm">
                                                <i class="fas fa-exclamation-triangle"></i> URGENTE
                                            </span>
                                        @endif
                                    </td>
                                    <td class="text-center">
                                        <h6>{{ $venta->customer ? $venta->customer->nombre . ' ' . $venta->customer->apellido : 'Cliente General' }}</h6>
                                    </td>
                                    <td class="text-center">
                                        <h6>{{ $venta->fecha_venta->format('d/m/Y H:i') }}</h6>
                                    </td>
                                    <td class="text-center">
                                        <h6><strong style="color: #28a745;">${{ number_format($venta->total, 2) }}</strong></h6>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge {{ $badgeClass }}">{{ $badgeText }}</span>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge badge-info">{{ $venta->details->count() }} items</span>
                                    </td>
                                    <td class="text-center">
                                        <h6 class="{{ $isUrgent ? 'text-danger font-weight-bold' : 'text-muted' }}">
                                            {{ $horasTranscurridas }}h {{ \Carbon\Carbon::parse($venta->fecha_venta)->diffInMinutes(\Carbon\Carbon::now()) % 60 }}m
                                        </h6>
                                    </td>
                                    <td class="text-center">
                                        @php
                                            $transferenciaPendiente = $venta->metodo_pago === 'transferencia' && $venta->transferencia_estado !== 'aprobada';
                                        @endphp

                                        @if($transferenciaPendiente)
                                            <button class="btn btn-secondary btn-rounded mb-2" disabled title="Debes validar la transferencia primero">
                                                <i class="fas fa-boxes"></i>
                                            </button>
                                            <a href="javascript:void(0)" wire:click="openTransferValidationModal({{ $venta->id }})"
                                                wire:loading.attr="disabled"
                                                wire:target="openTransferValidationModal({{ $venta->id }})"
                                                class="btn btn-warning btn-rounded mb-2" title="Validar transferencia">
                                                <span wire:loading.remove wire:target="openTransferValidationModal({{ $venta->id }})">
                                                    <i class="fas fa-file-invoice-dollar"></i>
                                                </span>
                                                <span wire:loading wire:target="openTransferValidationModal({{ $venta->id }})">
                                                    <i class="fas fa-spinner fa-spin"></i>
                                                </span>
                                            </a>
                                        @else
                                            <a href="javascript:void(0)" wire:click="openModal({{ $venta->id }})"
                                                wire:loading.attr="disabled"
                                                wire:target="openModal({{ $venta->id }})"
                                                class="btn btn-primary btn-rounded mb-2" title="Gestionar Despacho">
                                                <span wire:loading.remove wire:target="openModal({{ $venta->id }})">
                                                    <i class="fas fa-boxes"></i>
                                                </span>
                                                <span wire:loading wire:target="openModal({{ $venta->id }})">
                                                    <i class="fas fa-spinner fa-spin"></i>
                                                </span>
                                            </a>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="text-center text-muted">
                                        <h5>No hay pedidos pendientes de despacho</h5>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between mt-3 pagination-wrapper">
                    <div class="text-muted mb-2 mb-md-0">
                        Mostrando {{ $ventas->firstItem() ?? 0 }} a {{ $ventas->lastItem() ?? 0 }} de {{ $ventas->total() }} pedidos
                    </div>
                    <div>
                        {{ $ventas->onEachSide(1)->links() }}
                    </div>
                </div>
            </div>
        </div>
    </div>
    @include('livewire.despachos.modal')
    @include('livewire.despachos.transfer-validation-modal')
    @include('livewire.despachos.create-order-modal')
</div>

<style>
    @keyframes blink {
        0%, 50% { background-color: #f8d7da; }
        51%, 100% { background-color: #ffffff; }
    }

    .despachos-toolbar {
        border-radius: 10px;
    }

    .filter-label {
        font-size: 12px;
        font-weight: 600;
        margin-bottom: 6px;
    }

    .pagination-wrapper {
        gap: 10px;
    }

    .swal2-container {
        z-index: 3000 !important;
    }

    .table-success {
        background-color: #d4edda !important;
    }

    .custom-switch .custom-control-label::before {
        width: 2.25rem;
        height: 1.25rem;
        background-color: #adb5bd;
        border: none;
    }

    .custom-switch .custom-control-input:checked ~ .custom-control-label::before {
        background-color: #28a745;
    }

    .custom-switch .custom-control-label::after {
        width: 1rem;
        height: 1rem;
        top: 0.125rem;
        left: -2.25rem;
    }
</style>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        window.livewire.on('show-modal', function() {
            $('#theModal').modal('show');
        });

        window.livewire.on('despacho-updated', Msg => {
            noty(Msg, 1);
        });

        window.livewire.on('despacho-error', Msg => {
            noty(Msg, 2);
        });

        window.livewire.on('pedido-enviado', Msg => {
            $('#theModal').modal('hide');
            noty(Msg);
            setTimeout(() => {
                location.reload();
            }, 1500);
        });

        window.livewire.on('hide-modal', function() {
            $('#theModal').modal('hide');
        });

        window.livewire.on('show-create-order-modal', function() {
            $('#createOrderModal').modal('show');
        });

        window.livewire.on('show-transfer-validation-modal', function() {
            $('#transferValidationModal').modal('show');
        });

        window.livewire.on('hide-transfer-validation-modal', function() {
            $('#transferValidationModal').modal('hide');
        });

        window.livewire.on('hide-create-order-modal', function() {
            $('#createOrderModal').modal('hide');
        });

        window.livewire.on('pedido-creado', Msg => {
            $('#createOrderModal').modal('hide');
            noty(Msg, 1);
        });

        $(document).on('hidden.bs.modal', '#theModal', function () {
            window.livewire.emit('despachoModalClosed');
            $('body').removeClass('modal-open');
            $('.modal-backdrop').remove();
        });

        $(document).on('hidden.bs.modal', '#createOrderModal', function () {
            window.livewire.emit('createOrderModalClosed');
            $('body').removeClass('modal-open');
            $('.modal-backdrop').remove();
        });

        $(document).on('hidden.bs.modal', '#transferValidationModal', function () {
            window.livewire.emit('transferValidationModalClosed');
            $('body').removeClass('modal-open');
            $('.modal-backdrop').remove();
        });
    });

    function noty(msg, type = 1) {
        var n = new Noty({
            type: type == 1 ? 'success' : type == 2 ? 'error' : 'info',
            layout: 'topRight',
            text: msg,
            timeout: 3000
        }).show();
    }

    function confirmCloseCreateOrderModal() {
        const closeModal = () => {
            window.livewire.emit('hide-create-order-modal');
        };

        if (typeof Swal !== 'undefined' && Swal.fire) {
            Swal.fire({
                title: 'Cancelar orden',
                text: 'Si cierras ahora, se perderan los productos agregados. ¿Deseas continuar?',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Si, cancelar',
                cancelButtonText: 'No, continuar',
                reverseButtons: true
            }).then((result) => {
                if (result.isConfirmed) {
                    closeModal();
                }
            });
            return;
        }

        if (confirm('Si cierras ahora, se perderan los productos agregados. ¿Deseas continuar?')) {
            closeModal();
        }
    }
</script>
