<div class="row sales layout-top-spacing">
    <div class="col-sm-12">
        <div class="widget widget-chart-one">
            <div class="widget-heading">
                <h4 class="card-title">
                    <b>{{ $componentName }} | {{ $pageTitle }}</b>
                </h4>
                <ul class="tabs tab-pills">
                    <li>
                        <a href="javascript:void(0)" class="btn btn-primary btn-rounded mb-2" data-toggle="modal"
                            data-target="#theModal">Agregar</a>
                    </li>
                </ul>
            </div>

            <div class="row justify-content-between">
                <div class="col-lg-4 col-md-4 col-sm-12">
                    <div class="input-group mb-4">
                        <div class="input-group-prepend">
                            <span class="input-group-text input-gp">
                                <i class="fas fa-search"></i>
                            </span>
                        </div>
                        <input type="text" wire:model="search" placeholder="Buscar producto (nombre o código)"
                            class="form-control">
                    </div>
                </div>
                <div class="col-lg-4 col-md-4 col-sm-12">
                    <select wire:model="filtroCliente" class="form-control mb-4">
                        <option value="">Todos los clientes</option>
                        @foreach ($customers as $c)
                            <option value="{{ $c->id }}">{{ trim($c->nombre . ' ' . $c->apellido) }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="widget-content">
                <div class="table-responsive">
                    <table class="table table-bordered table-striped mt-1">
                        <thead style="background: #3B3F5C">
                            <tr>
                                <th class="table-th text-center">CLIENTE</th>
                                <th class="table-th text-center">PRODUCTO</th>
                                <th class="table-th text-center">UNIDAD</th>
                                <th class="table-th text-center">PRECIO BASE</th>
                                <th class="table-th text-center">PRECIO ESPECIAL</th>
                                <th class="table-th text-center">ESTADO</th>
                                <th class="table-th text-center">ACCIONES</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($data as $precio)
                                <tr>
                                    <td class="text-center">
                                        <h6>{{ $precio->customer ? trim($precio->customer->nombre . ' ' . $precio->customer->apellido) : 'N/A' }}</h6>
                                    </td>
                                    <td class="text-center">
                                        <h6>{{ $precio->product->nombre ?? 'N/A' }}</h6>
                                        <small class="text-muted">{{ $precio->product->codigo ?? '' }}</small>
                                    </td>
                                    <td class="text-center">
                                        <h6>{{ $precio->product->unidad_venta ?? '-' }}</h6>
                                    </td>
                                    <td class="text-center">
                                        <h6>${{ number_format($precio->product->precio ?? 0, 2) }}</h6>
                                    </td>
                                    <td class="text-center">
                                        <h6><b class="text-success">${{ number_format($precio->precio_especial, 2) }}</b></h6>
                                    </td>
                                    <td class="text-center">
                                        @if ($precio->activo)
                                            <span class="badge badge-success">Activo</span>
                                        @else
                                            <span class="badge badge-danger">Inactivo</span>
                                        @endif
                                    </td>
                                    <td class="text-center">
                                        <a href="javascript:void(0)" wire:click="toggleActivo({{ $precio->id }})"
                                            class="btn btn-warning btn-rounded mb-2" title="Activar / Desactivar">
                                            <i class="fas fa-power-off"></i>
                                        </a>
                                        <a href="javascript:void(0)" wire:click="edit({{ $precio->id }})"
                                            class="btn btn-primary btn-rounded mb-2" title="Editar">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <a href="javascript:void(0)" onclick="Confirm('{{ $precio->id }}')"
                                            class="btn btn-danger btn-rounded mb-2" title="Eliminar">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="text-center">Sin precios especiales registrados.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                    {{ $data->links() }}
                </div>
            </div>
        </div>
    </div>
    @include('livewire.precios-especiales.form')
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {

        // Bootstrap 4 + Livewire: tras el re-render que sigue a guardar, el
        // .modal-backdrop a veces no se elimina y deja la pantalla "congelada".
        // Se limpia a mano, igual que el botón CERRAR del footer.
        function cerrarModal() {
            $('#theModal').modal('hide');
            setTimeout(function () {
                $('.modal-backdrop').remove();
                $('body').removeClass('modal-open').css('padding-right', '');
            }, 200);
        }

        window.livewire.on('precio-added', Msg => {
            cerrarModal()
            noty(Msg)
        })
        window.livewire.on('precio-updated', Msg => {
            cerrarModal()
            noty(Msg)
        })
        window.livewire.on('precio-deleted', Msg => {
            noty(Msg)
        })
        window.livewire.on('precio-error', Msg => {
            noty(Msg, 2)
        })
        window.livewire.on('hide-modal', Msg => {
            cerrarModal()
        })
        window.livewire.on('show-modal', Msg => {
            $('#theModal').modal('show')
        })
    })

    function Confirm(id) {
        swal({
            title: 'CONFIRMAR',
            text: '¿CONFIRMAS ELIMINAR EL PRECIO ESPECIAL?',
            type: 'warning',
            showCancelButton: true,
            cancelButtonText: 'Cerrar',
            cancelButtonColor: '#fff',
            confirmButtonColor: '#3B3F5C',
            confirmButtonText: 'Aceptar'
        }).then(function(result) {
            if (result.value) {
                window.livewire.emit('deleteRow', id)
                swal.close()
            }
        })
    }
</script>
