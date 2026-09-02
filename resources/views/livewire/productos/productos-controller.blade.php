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
                        <input type="text" wire:model="search" placeholder="Buscar producto" class="form-control">
                    </div>
                </div>
                <div class="col-lg-3 col-md-3 col-sm-12">
                    <select wire:model="filterCategory" class="form-control">
                        <option value="">Todas las categorías</option>
                        @foreach($categories as $cat)
                            <option value="{{ $cat->id }}">{{ $cat->nombre }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="widget-content">
                <div class="table-responsive">
                    <table class="table table-bordered table-striped mt-1">
                        <thead style="background: #3B3F5C">
                            <tr>
                                <th class="table-th text-center">IMAGEN</th>
                                <th class="table-th text-center">CÓDIGO</th>
                                <th class="table-th text-center">NOMBRE</th>
                                <th class="table-th text-center">CATEGORÍA</th>
                                <th class="table-th text-center">PRECIO</th>
                                <th class="table-th text-center">STOCK</th>
                                <th class="table-th text-center">UNIDAD</th>
                                <th class="table-th text-center">ESTADO</th>
                                <th class="table-th text-center">ACCIONES</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($data as $product)
                                <tr>
                                    <td class="text-center">
                                        <img src="{{ $product->imagen_url }}"
                                             alt="{{ $product->nombre }}"
                                             onerror="this.onerror=null;this.src='{{ asset('productos/carne_default.png') }}';"
                                             style="width: 50px; height: 50px; object-fit: cover; border-radius: 5px;">
                                    </td>
                                    <td class="text-center">
                                        <h6>{{ $product->codigo ?? 'N/A' }}</h6>
                                    </td>
                                    <td class="text-center">
                                        <h6>{{ $product->nombre }}</h6>
                                        @if($product->en_oferta)
                                            <span class="badge badge-warning">EN OFERTA</span>
                                        @endif
                                        @if($product->destacado)
                                            <span class="badge badge-info">DESTACADO</span>
                                        @endif
                                    </td>
                                    <td class="text-center">
                                        <h6>{{ $product->category->nombre ?? 'N/A' }}</h6>
                                    </td>
                                    <td class="text-center">
                                        @if($product->en_oferta && $product->precio_oferta)
                                            <h6>
                                                <s style="color: #999;">${{ number_format($product->precio, 2) }}</s><br>
                                                <span style="color: #e7515a; font-weight: bold;">${{ number_format($product->precio_oferta, 2) }}</span>
                                            </h6>
                                        @else
                                            <h6>${{ number_format($product->precio, 2) }}</h6>
                                        @endif
                                    </td>
                                    <td class="text-center">
                                        @if($product->stock <= $product->stock_minimo)
                                            <span class="badge badge-danger">{{ $product->stock }}</span>
                                        @else
                                            <span class="badge badge-success">{{ $product->stock }}</span>
                                        @endif
                                    </td>
                                    <td class="text-center">
                                        <h6>{{ ucfirst($product->unidad_venta) }}</h6>
                                    </td>
                                    <td class="text-center">
                                        @if ($product->activo)
                                            <span class="badge badge-success">Activo</span>
                                        @else
                                            <span class="badge badge-danger">Inactivo</span>
                                        @endif
                                    </td>
                                    <td class="text-center">
                                        <a href="javascript:void(0)" wire:click="edit({{ $product->id }})"
                                            class="btn btn-primary btn-rounded mb-2" title="Editar">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <a href="javascript:void(0)" onclick="Confirm('{{ $product->id }}')"
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
    @include('livewire.productos.form')
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        window.livewire.on('product-added', Msg => {
            $('#theModal').modal('hide')
            noty(Msg)
        })
        window.livewire.on('product-updated', Msg => {
            $('#theModal').modal('hide')
            noty(Msg)
        })
        window.livewire.on('product-deleted', Msg => {
            noty(Msg)
        })
        window.livewire.on('product-error', Msg => {
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
            text: '¿CONFIRMAS ELIMINAR EL PRODUCTO?',
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
