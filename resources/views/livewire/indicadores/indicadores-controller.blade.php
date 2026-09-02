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
                           data-target="#theModal">Agregar pregunta</a>
                    </li>
                </ul>
            </div>

            <div class="row mb-3">
                <div class="col-lg-3 col-md-6 col-sm-12 mb-2">
                    <div class="widget p-3">
                        <small class="text-muted">Preguntas activas</small>
                        <h4 class="mb-0">{{ $resumen['preguntas_activas'] }}</h4>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6 col-sm-12 mb-2">
                    <div class="widget p-3">
                        <small class="text-muted">Al finalizar pedido</small>
                        <h4 class="mb-0">{{ $resumen['preguntas_finalizar'] }}</h4>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6 col-sm-12 mb-2">
                    <div class="widget p-3">
                        <small class="text-muted">Respuestas</small>
                        <h4 class="mb-0">{{ $resumen['respuestas_total'] }}</h4>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6 col-sm-12 mb-2">
                    <div class="widget p-3">
                        <small class="text-muted">Promedio general</small>
                        <h4 class="mb-0">{{ $resumen['promedio_general'] ? number_format($resumen['promedio_general'], 1) : '0.0' }}/10</h4>
                    </div>
                </div>
            </div>

            <div class="row justify-content-between">
                <div class="col-lg-4 col-md-4 col-sm-12">
                    <div class="input-group mb-4">
                        <div class="input-group-prepend">
                            <span class="input-group-text input-gp">
                                <i class="fas fa-search"></i>
                            </span>
                        </div>
                        <input type="text" wire:model="search" placeholder="Buscar pregunta" class="form-control">
                    </div>
                </div>
            </div>

            <div class="widget-content">
                <div class="table-responsive">
                    <table class="table table-bordered table-striped mt-1">
                        <thead style="background: #3B3F5C">
                            <tr>
                                <th class="table-th text-center">ORDEN</th>
                                <th class="table-th text-center">PREGUNTA</th>
                                <th class="table-th text-center">SALDRA AL FINALIZAR</th>
                                <th class="table-th text-center">ESTADO</th>
                                <th class="table-th text-center">RESPUESTAS</th>
                                <th class="table-th text-center">PROMEDIO</th>
                                <th class="table-th text-center">ACCIONES</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($preguntas as $item)
                                <tr>
                                    <td class="text-center"><h6>{{ $item->orden }}</h6></td>
                                    <td>
                                        <h6 class="mb-1">{{ $item->pregunta }}</h6>
                                        @if ($item->descripcion)
                                            <small class="text-muted">{{ $item->descripcion }}</small>
                                        @endif
                                    </td>
                                    <td class="text-center">
                                        @if ($item->mostrar_al_finalizar_pedido)
                                            <span class="badge badge-info">Si</span>
                                        @else
                                            <span class="badge badge-secondary">No</span>
                                        @endif
                                    </td>
                                    <td class="text-center">
                                        @if ($item->activo)
                                            <span class="badge badge-success">Activa</span>
                                        @else
                                            <span class="badge badge-danger">Inactiva</span>
                                        @endif
                                    </td>
                                    <td class="text-center">
                                        <span class="badge badge-primary">{{ $item->respuestas_count }}</span>
                                    </td>
                                    <td class="text-center">
                                        <h6>{{ $item->promedio_respuestas ? number_format($item->promedio_respuestas, 1) : '0.0' }}/10</h6>
                                    </td>
                                    <td class="text-center">
                                        <a href="javascript:void(0)" wire:click="toggleActivo({{ $item->id }})"
                                           class="btn btn-secondary btn-rounded mb-2" title="{{ $item->activo ? 'Desactivar' : 'Activar' }}">
                                            <i class="fas fa-toggle-{{ $item->activo ? 'off' : 'on' }}"></i>
                                        </a>
                                        <a href="javascript:void(0)" wire:click="edit({{ $item->id }})"
                                           class="btn btn-primary btn-rounded mb-2" title="Editar">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <a href="javascript:void(0)" onclick="Confirm('{{ $item->id }}')"
                                           class="btn btn-danger btn-rounded mb-2" title="Eliminar">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="text-center">
                                        <h6>No hay preguntas registradas.</h6>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                    {{ $preguntas->links() }}
                </div>
            </div>
        </div>
    </div>

    @include('livewire.indicadores.form')
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        window.livewire.on('indicador-added', Msg => {
            $('#theModal').modal('hide')
            noty(Msg)
        })
        window.livewire.on('indicador-updated', Msg => {
            $('#theModal').modal('hide')
            noty(Msg)
        })
        window.livewire.on('indicador-deleted', Msg => {
            noty(Msg)
        })
        window.livewire.on('show-modal', () => {
            $('#theModal').modal('show')
        })
        window.livewire.on('hide-modal', () => {
            $('#theModal').modal('hide')
        })
    })

    function Confirm(id) {
        swal({
            title: 'CONFIRMAR',
            text: 'CONFIRMAS ELIMINAR LA PREGUNTA?',
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
