@include('common.modalHead')

<div class="row" style="row-gap: 4px;">
    <div class="col-sm-12 col-md-6 col-lg-3">
        <div class="form-group">
            <label>Categoría <span class="text-danger">*</span></label>
            <select wire:model.lazy="category_id" class="form-control">
                <option value="">Seleccionar</option>
                @foreach($categories as $cat)
                    <option value="{{ $cat->id }}">{{ $cat->nombre }}</option>
                @endforeach
            </select>
            @error('category_id')
                <span class="text-danger er">{{ $message }}</span>
            @enderror
        </div>
    </div>

    <div class="col-sm-12 col-md-6 col-lg-3">
        <div class="form-group">
            <label>Nombre <span class="text-danger">*</span></label>
            <input type="text" wire:model.lazy="nombre" class="form-control" placeholder="ej: Bistec de res">
            @error('nombre')
                <span class="text-danger er">{{ $message }}</span>
            @enderror
        </div>
    </div>

    <div class="col-sm-12 col-md-6 col-lg-3">
        <div class="form-group">
            <label>Código</label>
            <input type="text" wire:model.lazy="codigo" class="form-control" placeholder="ej: PROD-001">
            @error('codigo')
                <span class="text-danger er">{{ $message }}</span>
            @enderror
        </div>
    </div>

    <div class="col-sm-12 col-md-6 col-lg-3">
        <div class="form-group">
            <label>Unidad de Venta <span class="text-danger">*</span></label>
            <select wire:model="unidad_venta" class="form-control">
                <option value="kilogramo">Kilogramo</option>
                <option value="pieza">Pieza</option>
                <option value="pesos">Pesos</option>
            </select>
            @error('unidad_venta')
                <span class="text-danger er">{{ $message }}</span>
            @enderror
        </div>
    </div>

    <div class="col-sm-12 col-md-6 col-lg-3">
        <div class="form-group">
            <label class="d-block">Venta por gramos</label>
            <div class="custom-control custom-checkbox mt-2">
                <input type="checkbox" wire:model="venta_por_gramos" class="custom-control-input" id="ventaPorGramosCheck" @if($unidad_venta === 'pieza') disabled @endif>
                <label class="custom-control-label" for="ventaPorGramosCheck">Sí, se vende por gramos</label>
            </div>
            @if($unidad_venta === 'pieza')
                <small class="text-muted">No aplica para productos por pieza.</small>
            @endif
        </div>
    </div>

    <div class="col-sm-12">
        <div class="form-group">
            <label>Descripción</label>
            <input type="text" wire:model.lazy="descripcion" class="form-control" placeholder="Descripción del producto">
            @error('descripcion')
                <span class="text-danger er">{{ $message }}</span>
            @enderror
        </div>
    </div>

    <div class="col-sm-12 col-md-6 col-lg-3">
        <div class="form-group">
            <label>{{ $venta_por_gramos ? 'Precio del kilo' : 'Precio' }} <span class="text-danger">*</span></label>
            <input type="number" wire:model.lazy="precio" class="form-control" placeholder="0.00" step="0.01" min="0">
            @error('precio')
                <span class="text-danger er">{{ $message }}</span>
            @enderror
        </div>
    </div>

    <div class="col-sm-12 col-md-6 col-lg-3">
        <div class="form-group">
            <label>¿En Oferta?</label>
            <select wire:model.lazy="en_oferta" class="form-control">
                <option value="0">No</option>
                <option value="1">Sí</option>
            </select>
            @error('en_oferta')
                <span class="text-danger er">{{ $message }}</span>
            @enderror
        </div>
    </div>

    @if((int) $en_oferta === 1)
        <div class="col-sm-12 col-md-6 col-lg-3">
            <div class="form-group">
                <label>Precio Oferta <span class="text-danger">*</span></label>
                <input type="number" wire:model.lazy="precio_oferta" class="form-control" placeholder="0.00" step="0.01" min="0">
                @error('precio_oferta')
                    <span class="text-danger er">{{ $message }}</span>
                @enderror
            </div>
        </div>
    @endif



    <div class="col-sm-12 col-md-6 col-lg-3">
        <div class="form-group">
            <label>Stock Mínimo <span class="text-danger">*</span></label>
            <input type="number" wire:model.lazy="stock_minimo" class="form-control" placeholder="0" step="0.01" min="0">
            @error('stock_minimo')
                <span class="text-danger er">{{ $message }}</span>
            @enderror
        </div>
    </div>

    <div class="col-sm-12 col-md-6 col-lg-3">
        <div class="form-group">
            <label>Peso Promedio (kg)</label>
            <input type="number" wire:model.lazy="peso_promedio" class="form-control" placeholder="0.00" step="0.01" min="0" @if($unidad_venta !== 'pieza') disabled @endif>
            @error('peso_promedio')
                <span class="text-danger er">{{ $message }}</span>
            @enderror
            @if($unidad_venta !== 'pieza')
                <small class="text-muted">Solo aplica cuando la unidad de venta es pieza.</small>
            @endif
        </div>
    </div>

    <div class="col-sm-12 col-md-6 col-lg-3">
        <div class="form-group">
            <label>Estado</label>
            <select wire:model.lazy="activo" class="form-control">
                <option value="1">Activo</option>
                <option value="0">Inactivo</option>
            </select>
            @error('activo')
                <span class="text-danger er">{{ $message }}</span>
            @enderror
        </div>
    </div>

    <div class="col-sm-12 col-md-6 col-lg-3">
        <div class="form-group">
            <label>Destacado</label>
            <select wire:model.lazy="destacado" class="form-control">
                <option value="0">No</option>
                <option value="1">Sí</option>
            </select>
            @error('destacado')
                <span class="text-danger er">{{ $message }}</span>
            @enderror
        </div>
    </div>

    <div class="col-sm-12 col-md-6 col-lg-3">
        <div class="form-group">
            <label>Venta por monto (pesos)</label>
            <input type="number" wire:model.lazy="monto_venta" class="form-control" placeholder="Ej: 50" min="0" step="0.01">
        </div>
    </div>

    <div class="col-sm-12 col-md-6 col-lg-3">
        <div class="form-group">
            <label>Cantidad (gramos/kilos)</label>
            <input type="number" wire:model.lazy="cantidad_venta" class="form-control" placeholder="Ej: 250" min="0" step="0.01">
        </div>
    </div>

    <div class="col-sm-12 col-md-6 col-lg-3">
        <div class="form-group">
            <label>Etiquetas</label>
            <input type="text" wire:model.lazy="etiquetas" class="form-control" placeholder="premium, especial, importado">
            @error('etiquetas')
                <span class="text-danger er">{{ $message }}</span>
            @enderror
        </div>
    </div>

    <div class="col-sm-12 col-md-6 col-lg-3">
        <div class="form-group">
            <label>Imagen</label>
            <input type="file" wire:model="imagen" class="form-control" accept="image/*">
            @error('imagen')
                <span class="text-danger er">{{ $message }}</span>
            @enderror

            @if ($imagen)
                <div class="mt-2">
                    <img src="{{ $imagen->temporaryUrl() }}" style="width: 56px; height: 56px; object-fit: cover; border-radius: 6px;">
                </div>
            @endif
        </div>
    </div>

    @if($monto_venta && $cantidad_venta)
        <div class="col-12">
            <small class="text-info">Equivale a {{ number_format($cantidad_venta, 2) }} gramos.</small>
        </div>
    @endif
</div>

@include('common.modalFooter')
