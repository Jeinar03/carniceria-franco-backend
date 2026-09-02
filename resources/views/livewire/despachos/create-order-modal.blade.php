<div wire:ignore.self class="modal fade" id="createOrderModal" tabindex="-1" role="dialog" style="backdrop-filter: blur(10px); z-index: 1210;">
    <div class="modal-dialog modal-xl" role="document">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title">
                    <b>{{ $componentName }}</b> | Crear pedido
                </h5>
            </div>

            <div class="modal-body">
                <div class="alert alert-light border mb-3">
                    <strong>Flujo:</strong> 1) Completa los datos del pedido, 2) busca y agrega productos, 3) valida el carrito y guarda.
                </div>

                <div class="row">
                    <div class="col-lg-5 col-md-12 mb-3">
                        <div class="card h-100">
                            <div class="card-body">
                                <h6 class="mb-3 section-title"><span class="step-dot">1</span> <i class="fas fa-user"></i> Datos del pedido</h6>

                                <div class="form-group">
                                    <label>Cliente</label>
                                    <select wire:model="createCustomerId" class="form-control">
                                        <option value="">Selecciona un cliente</option>
                                        @foreach($customers as $customer)
                                            <option value="{{ $customer->id }}">{{ $customer->nombre }} {{ $customer->apellido }}</option>
                                        @endforeach
                                    </select>
                                </div>

                                <div class="form-group">
                                    <label>Método de pago</label>
                                    <select wire:model="createMetodoPago" class="form-control">
                                        <option value="efectivo">Efectivo</option>
                                        <option value="tarjeta">Tarjeta</option>
                                        <option value="transferencia">Transferencia</option>
                                        <option value="credito">Credito</option>
                                    </select>
                                </div>

                                <div class="form-group">
                                    <label>Descuento</label>
                                    <input type="number" min="0" step="0.01" wire:model="createDescuento" class="form-control" placeholder="0.00">
                                </div>

                                <div class="form-group mb-0">
                                    <label>Notas</label>
                                    <textarea wire:model="createNotas" class="form-control" rows="3" placeholder="Notas internas del pedido..."></textarea>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-7 col-md-12 mb-3">
                        <div class="card h-100">
                            <div class="card-body">
                                <h6 class="mb-3 section-title"><span class="step-dot">2</span> <i class="fas fa-search"></i> Buscar productos</h6>

                                <div class="form-group">
                                    <input type="text"
                                           wire:model.debounce.300ms="productSearch"
                                           class="form-control"
                                           placeholder="Buscar por código o nombre...">
                                    <small class="text-muted d-block mt-1">Selecciona productos con el botón + para agregarlos al carrito.</small>
                                </div>

                                <div class="table-responsive" style="max-height: 280px; overflow-y: auto;">
                                    <table class="table table-sm table-striped mb-0">
                                        <thead class="thead-dark" style="position: sticky; top: 0; z-index: 1;">
                                            <tr>
                                                <th>Código</th>
                                                <th>Producto</th>
                                                <th class="text-right">Precio</th>
                                                <th class="text-center">Stock</th>
                                                <th class="text-center">Acción</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @forelse($products as $product)
                                                @php
                                                    $price = $product->en_oferta ? $product->precio_oferta : $product->precio;
                                                @endphp
                                                <tr>
                                                    <td>{{ $product->codigo }}</td>
                                                    <td>
                                                        <strong>{{ $product->nombre }}</strong>
                                                        <br>
                                                        <small class="text-muted">{{ $product->unidad_venta }}</small>
                                                    </td>
                                                    <td class="text-right">${{ number_format($price, 2) }}</td>
                                                    <td class="text-center">
                                                        <span class="badge {{ $product->stock > 0 ? 'badge-success' : 'badge-danger' }}">
                                                            {{ number_format($product->stock, 2) }}
                                                        </span>
                                                    </td>
                                                    <td class="text-center">
                                                        <button class="btn btn-sm btn-outline-primary"
                                                                wire:click="addProductToCart({{ $product->id }})"
                                                                wire:loading.attr="disabled"
                                                                wire:target="addProductToCart({{ $product->id }})"
                                                                {{ $product->stock <= 0 ? 'disabled' : '' }}>
                                                            <span wire:loading.remove wire:target="addProductToCart({{ $product->id }})">
                                                                <i class="fas fa-plus"></i>
                                                            </span>
                                                            <span wire:loading wire:target="addProductToCart({{ $product->id }})">
                                                                <i class="fas fa-spinner fa-spin"></i>
                                                            </span>
                                                        </button>
                                                    </td>
                                                </tr>
                                            @empty
                                                <tr>
                                                    <td colspan="5" class="text-center text-muted">No se encontraron productos.</td>
                                                </tr>
                                            @endforelse
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <h6 class="mb-0 section-title"><span class="step-dot">3</span> <i class="fas fa-shopping-cart"></i> Carrito</h6>
                            <span class="badge badge-info">{{ $this->cartProductsCount }} unidades</span>
                        </div>

                        <div class="table-responsive {{ count($cart) >= 4 ? 'cart-table-scroll' : '' }}">
                            <table class="table table-bordered table-sm mb-0">
                                <thead style="background: #3B3F5C; color: #fff; position: sticky; top: 0; z-index: 1;">
                                    <tr>
                                        <th>Producto</th>
                                        <th class="text-center" style="width: 150px;">Cantidad</th>
                                        <th class="text-right">Precio</th>
                                        <th class="text-right">Subtotal</th>
                                        <th class="text-center" style="width: 70px;">Quitar</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($cart as $item)
                                        <tr>
                                            <td>
                                                <strong>{{ $item['nombre'] }}</strong>
                                                <br>
                                                <small class="text-muted">{{ $item['codigo'] }} | Stock: {{ number_format($item['stock'], 2) }}</small>
                                            </td>
                                            <td class="text-center">
                                                <div class="input-group input-group-sm">
                                                    <div class="input-group-prepend">
                                                        <button class="btn btn-outline-secondary" wire:click="decreaseQty({{ $item['product_id'] }})">-</button>
                                                    </div>
                                                    <input type="number"
                                                           min="0"
                                                           step="0.01"
                                                           class="form-control text-center"
                                                           value="{{ $item['cantidad'] }}"
                                                           wire:change="updateQty({{ $item['product_id'] }}, $event.target.value)">
                                                    <div class="input-group-append">
                                                        <button class="btn btn-outline-secondary" wire:click="increaseQty({{ $item['product_id'] }})">+</button>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="text-right">${{ number_format($item['precio_final'], 2) }}</td>
                                            <td class="text-right">${{ number_format($item['precio_final'] * $item['cantidad'], 2) }}</td>
                                            <td class="text-center">
                                                <button class="btn btn-sm btn-outline-danger" wire:click="removeFromCart({{ $item['product_id'] }})">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="5" class="text-center text-muted">No hay productos agregados.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>

                        <div class="row mt-3">
                            <div class="col-md-8"></div>
                            <div class="col-md-4">
                                <div class="d-flex justify-content-between">
                                    <span>Subtotal:</span>
                                    <strong>${{ number_format($this->cartSubtotal, 2) }}</strong>
                                </div>
                                <div class="d-flex justify-content-between">
                                    <span>Descuento:</span>
                                    <strong>-${{ number_format(max(0, (float)$createDescuento), 2) }}</strong>
                                </div>
                                <div class="d-flex justify-content-between">
                                    <span>Impuestos (16%):</span>
                                    <strong>${{ number_format($this->cartTaxes, 2) }}</strong>
                                </div>
                                <hr>
                                <div class="d-flex justify-content-between">
                                    <span>Total final:</span>
                                    <h5 class="mb-0 text-success"><strong>${{ number_format($this->cartTotal, 2) }}</strong></h5>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" onclick="confirmCloseCreateOrderModal()">Cerrar</button>
                <button type="button"
                        class="btn btn-success"
                        wire:click="createOrder"
                        wire:loading.attr="disabled"
                        wire:target="createOrder">
                    <span wire:loading.remove wire:target="createOrder">
                        <i class="fas fa-save"></i> Guardar pedido
                    </span>
                    <span wire:loading wire:target="createOrder">
                        <i class="fas fa-spinner fa-spin"></i> Guardando...
                    </span>
                </button>
            </div>
        </div>
    </div>
</div>

<style>
    #createOrderModal {
        z-index: 1210 !important;
    }

    #createOrderModal + .modal-backdrop.show {
        z-index: 1205 !important;
    }

    #createOrderModal .modal-content {
        border: 0;
        border-radius: 10px;
    }

    .cart-table-scroll {
        max-height: 280px;
        overflow-y: auto;
        border: 1px solid #e9ecef;
    }

    .section-title {
        font-weight: 700;
        color: #2f3542;
    }

    .step-dot {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 22px;
        height: 22px;
        border-radius: 50%;
        background: #28a745;
        color: #fff;
        font-size: 12px;
        margin-right: 6px;
    }

    #createOrderModal .modal-footer {
        justify-content: space-between;
    }
</style>
