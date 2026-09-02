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
                            <span class="input-group-text input-gp" style="cursor: pointer;" wire:click="buscar">
                                <i class="fas fa-search"></i>
                            </span>
                        </div>
                        <input type="text" wire:model="search" placeholder="Buscar" class="form-control">
                    </div>
                </div>
            </div>

            <div class="widget-content">

                <div class="table-responsive">
                    <table class="table table-bordered table-striped mt-1">
                        <thead style="background: #3B3F5C">
                            <tr>
                                <th class="table-th text-center">NOMBRE</th>
                                <th class="table-th text-center">CORREO</th>
                                <th class="table-th text-center">TELÉFONO</th>
                                <th class="table-th text-center">CIUDAD</th>
                                <th class="table-th text-center">TIPO</th>
                                <th class="table-th text-center">COMPRAS</th>
                                <th class="table-th text-center">TOTAL</th>
                                <th class="table-th text-center">ESTATUS</th>
                                <th class="table-th text-center">ACCIONES</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($data as $r)
                                <tr>
                                    <td class="text-center">
                                        <h6>{{ $r->nombre }} {{ $r->apellido }} {{ $r->apellido2 }}</h6>
                                    </td>
                                    <td class="text-center">
                                        <h6>{{ $r->correo }}</h6>
                                    </td>
                                    <td class="text-center">
                                        <h6>{{ $r->telefono ?? 'N/A' }}</h6>
                                    </td>
                                    <td class="text-center">
                                        <h6>{{ $r->ciudad ?? 'N/A' }}</h6>
                                    </td>
                                    <td class="text-center">
                                        @if ($r->tipo_cliente == 'mayorista')
                                            <span class="badge badge-info">Mayorista</span>
                                        @elseif ($r->tipo_cliente == 'distribuidor')
                                            <span class="badge badge-warning">Distribuidor</span>
                                        @else
                                            <span class="badge badge-secondary">Minorista</span>
                                        @endif
                                    </td>
                                    <td class="text-center">
                                        <h6>{{ $r->numero_compras ?? 0 }}</h6>
                                    </td>
                                    <td class="text-center">
                                        <h6>${{ number_format($r->total_compras ?? 0, 2) }}</h6>
                                    </td>
                                    <td class="text-center">
                                        @if ($r->estatus == 'activo')
                                            <span class="badge badge-success">Activo</span>
                                        @elseif ($r->estatus == 'suspendido')
                                            <span class="badge badge-warning">Suspendido</span>
                                        @else
                                            <span class="badge badge-danger">Inactivo</span>
                                        @endif
                                    </td>
                                    <td class="text-center">
                                        <a href="javascript:void(0)" wire:click="edit({{ $r->id }})"
                                            class="btn btn-primary btn-rounded mb-2" title="Editar">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        @if (Auth()->user()->id != $r->id)
                                            <a href="javascript:void(0)" onclick="Confirm('{{ $r->id }}')"
                                                class="btn btn-danger btn-rounded mb-2" title="Eliminar cliente">
                                                <i class="fas fa-trash"></i>
                                            </a>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                    {{ $data->links() }}
                </div>
            </div>
        </div>
    </div>
    @include('livewire.clientes.form')
</div>


<script>
    document.addEventListener('DOMContentLoaded', function() {
        window.livewire.on('user-added', Msg => {
            $('#theModal').modal('hide')
            resetInputFile()
            noty(Msg)
        })
        window.livewire.on('user-updated', Msg => {
            $('#theModal').modal('hide')
            resetInputFile()
            noty(Msg)
        })
        window.livewire.on('user-deleted', Msg => {
            noty(Msg)
        })
        window.livewire.on('hide-modal', Msg => {
            $('#theModal').modal('hide')
        })
        window.livewire.on('show-modal', Msg => {
            $('#theModal').modal('show')
        })
        window.livewire.on('user-withsales', Msg => {
            noty(Msg)
        })

    })

    function resetInputFile() {
        $('input[type=file]').val('');
    }


    function Confirm(id) {

        swal({
            title: 'CONFIRMAR',
            text: '¿Confirmas eliminar el cliente? Si tiene ventas, se inactivará en lugar de borrarse.',
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
