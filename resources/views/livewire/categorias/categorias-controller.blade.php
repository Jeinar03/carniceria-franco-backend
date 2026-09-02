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
                        <input type="text" wire:model="search" placeholder="Buscar categoría" class="form-control">
                    </div>
                </div>
            </div>

            <div class="widget-content">
                <div class="table-responsive">
                    <table class="table table-bordered table-striped mt-1">
                        <thead style="background: #3B3F5C">
                            <tr>
                                <th class="table-th text-center">IMAGEN</th>
                                <th class="table-th text-center">NOMBRE</th>
                                <th class="table-th text-center">DESCRIPCIÓN</th>
                                <th class="table-th text-center">ORDEN</th>
                                <th class="table-th text-center">PRODUCTOS</th>
                                <th class="table-th text-center">ESTADO</th>
                                <th class="table-th text-center">ACCIONES</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($data as $category)
                                <tr>
                                    <td class="text-center">
                                        @if($category->imagen)
                                            <img src="{{ asset('storage/categories/' . $category->imagen) }}"
                                                 alt="{{ $category->nombre }}"
                                                 style="width: 50px; height: 50px; object-fit: cover; border-radius: 5px;">
                                        @else
                                            <i class="fas fa-image" style="font-size: 30px; color: #ccc;"></i>
                                        @endif
                                    </td>
                                    <td class="text-center">
                                        <h6>{{ $category->nombre }}</h6>
                                    </td>
                                    <td class="text-center">
                                        <h6>
                                            {{
                                                $category->descripcion
                                                    ? (strlen($category->descripcion) > 50
                                                        ? substr($category->descripcion, 0, 50) . '...'
                                                        : $category->descripcion)
                                                    : 'N/A'
                                            }}
                                        </h6>
                                    </td>
                                    <td class="text-center">
                                        <h6>{{ $category->orden }}</h6>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge badge-info">{{ $category->products_count }}</span>
                                    </td>
                                    <td class="text-center">
                                        @if ($category->activo)
                                            <span class="badge badge-success">Activo</span>
                                        @else
                                            <span class="badge badge-danger">Inactivo</span>
                                        @endif
                                    </td>
                                    <td class="text-center">
                                        <a href="javascript:void(0)" wire:click="edit({{ $category->id }})"
                                            class="btn btn-primary btn-rounded mb-2" title="Editar">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <a href="javascript:void(0)" onclick="Confirm('{{ $category->id }}')"
                                            class="btn btn-danger btn-rounded mb-2" title="Eliminar">
                                            <i class="fas fa-trash"></i>
                                        </a>
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
    @include('livewire.categorias.form')
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        window.livewire.on('category-added', Msg => {
            $('#theModal').modal('hide')
            noty(Msg)
        })
        window.livewire.on('category-updated', Msg => {
            $('#theModal').modal('hide')
            noty(Msg)
        })
        window.livewire.on('category-deleted', Msg => {
            noty(Msg)
        })
        window.livewire.on('category-error', Msg => {
            noty(Msg, 2)
        })
        window.livewire.on('hide-modal', Msg => {
            $('#theModal').modal('hide')
        })
        window.livewire.on('show-modal', Msg => {
            $('#theModal').modal('show')
        })
    })

    function Confirm(id) {
        swal({
            title: 'CONFIRMAR',
            text: '¿CONFIRMAS ELIMINAR LA CATEGORÍA?',
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
