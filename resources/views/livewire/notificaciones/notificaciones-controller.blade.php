<div class="row sales layout-top-spacing">

    <div class="col-sm-12">
        <div class="widget widget-chart-one">
            <div class="widget-heading">
                <h4 class="card-title">
                    <b>{{ $componentName }} | {{ $pageTitle }}</b>
                </h4>
                <!-- Dropdown para seleccionar usuario -->
 <ul class="tabs tab-pills">
                    <li>
                        <a href="javascript:void(0)" class="btn btn-primary btn-rounded mb-2" data-toggle="modal"
                            data-target="#theModal">Agregar Notificacion</a>
                    </li>
                </ul>
            </div>

            <div class="widget-content">
                <!-- Tabla de Notificaciones -->
                <div class="table-responsive">
                    <table class="table table-bordered table-striped mt-1">
                        <thead class="text-white" style="background: #3B3F5C">
                            <tr>
                                <th class="table-th text-center">ID</th>
                                <th class="table-th text-center">Logo</th>
                                <th class="table-th text-center">Título</th>
                                <th class="table-th text-center">Descripción</th>
                                <th class="table-th text-center">Fecha de Envío</th>
                                <th class="table-th text-center">Creado</th>
                                 <th class="table-th text-center">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($notificaciones as $notificacion)
                            <tr>
                                <td class="text-center">
                                    <h6>{{ $notificacion->id }}</h6>
                                </td>
                                <td class="text-center">
                                  <img src="data:image/png;base64,{{ $notificacion->logo }}" class="img-thumbnail" width="150">
                                </td>
                                <td class="text-center">
                                    <h6>{{ $notificacion->titulo_notificacion }}</h6>
                                </td>
                                <td class="text-center">
                                    <h6>{{ $notificacion->descripcion }}</h6>
                                </td>
                                <td class="text-center">
                                    <h6>{{ $notificacion->fecha_envio }}</h6>
                                </td>
                                <td class="text-center">
                                    <h6>{{ $notificacion->created_at }}</h6>
                                </td>
                                <td class="text-center">
                                    <a href="javascript:void(0)" onclick="Confirm('{{ $notificacion->id }}')"
                                            class="btn btn-danger btn-rounded mb-2" title="Eliminar">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                </td>

                            </tr>
                            @empty
                            <tr>
                                <td colspan="6" class="text-center">
                                    <h6>No hay notificaciones.</h6>
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

            </div>
        </div>
    </div>
      @include('livewire.notificaciones.form')

</div>
<script>
    document.addEventListener('DOMContentLoaded', function(){
        window.livewire.on('video-added', Msg => {
            $('#theModal').modal('hide');
            resetInputFile();
            noty(Msg);
        });
        window.livewire.on('video-updated', Msg => {
            $('#theModal').modal('hide');
            resetInputFile();
            noty(Msg);
        });
        window.livewire.on('video-deleted', Msg => {
            noty(Msg);
        });
        window.livewire.on('hide-modal', Msg => {
            $('#theModal').modal('hide');
        });
        window.livewire.on('show-modal', Msg => {
            $('#theModal').modal('show');
        });
    });

    function resetInputFile()
    {
        $('input[type=file]').val('');
    }

    function Confirm(id)
    {
        swal({
            title: 'CONFIRMAR',
            text: '¿CONFIRMAS ELIMINAR EL REGISTRO?',
            type: 'warning',
            showCancelButton: true,
            cancelButtonText: 'Cerrar',
            cancelButtonColor: '#fff',
            confirmButtonColor: '#3B3F5C',
            confirmButtonText: 'Aceptar'
        }).then(function(result) {
            if(result.value){
                window.livewire.emit('deleteRow', id);
                swal.close();
            }
        });
    }
</script>
