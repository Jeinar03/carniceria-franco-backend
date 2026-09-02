<div class="row sales layout-top-spacing">

    <div class="col-sm-12">
        <div class="widget widget-chart-one">
            <div class="widget-heading">
                <h4 class="card-title">
                    <b>{{ $componentName }} | {{ $pageTitle }}</b>
                </h4>
<div class="form-group">
                    <label for="clienteSelect">Selecciona un cliente:</label>
                    <select wire:model="selectedClient" id="clienteSelect" class="form-control">
                        <option value="">Todos los clientes</option>
                        @foreach($clientes as $cliente)
                        <option value="{{ $cliente->id }}">{{ $cliente->nombre }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="widget-content ">
                <!-- Dropdown para seleccionar cliente -->


                <!-- Tabla -->
                <div class="table-responsive">
                    <table class="table table-bordered table striped mt-1">
                        <thead class="text-white" style="background: #3B3F5C">
                            <tr>
                                <th class="table-th text-center">ID</th>
                                <th class="table-th text-center">Cliente</th>
                                <th class="table-th text-center">Acción</th>
                                <th class="table-th text-center">Contenido</th>
                                <th class="table-th text-center">Creado</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($logs as $log)
                            <tr>
                                <td class="text-center">
                                    <h6>{{ $log->id }}</h6>
                                </td>
                                <td class="text-center">
                                    <h6>{{ $log->customer->nombre ?? 'No asignado' }}</h6>
                                </td>
                                <td class="text-center">
                                    <h6>{{ $log->accion }}</h6>
                                </td>
                                <td class="text-center">
                                    <h6>{{ $log->contenido }}</h6>
                                </td>
                                <td class="text-center">
                                    <h6>{{ $log->created_at }}</h6>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="5" class="text-center">
                                    <h6>No hay logs disponibles para este cliente.</h6>
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

            </div>
        </div>
    </div>

</div>
