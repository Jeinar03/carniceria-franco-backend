@include('common.modalHead')

<div class="row">
    <div class="col-sm-12 col-md-6">
        <div class="form-group">
            <label>Cliente <span class="text-danger">*</span></label>
            <select wire:model="customer_id" class="form-control">
                <option value="">— Selecciona un cliente —</option>
                @foreach ($customers as $c)
                    <option value="{{ $c->id }}">{{ trim($c->nombre . ' ' . $c->apellido) }}</option>
                @endforeach
            </select>
            @error('customer_id')
                <span class="text-danger er">{{ $message }}</span>
            @enderror
        </div>
    </div>

    <div class="col-sm-12 col-md-6">
        <div class="form-group">
            <label>Producto <span class="text-danger">*</span></label>
            <select wire:model="product_id" class="form-control">
                <option value="">— Selecciona un producto —</option>
                @foreach ($products as $p)
                    <option value="{{ $p->id }}">
                        {{ $p->nombre }} ({{ $p->codigo }}) — normal ${{ number_format($p->precio, 2) }} / {{ $p->unidad_venta }}
                    </option>
                @endforeach
            </select>
            @error('product_id')
                <span class="text-danger er">{{ $message }}</span>
            @enderror
        </div>
    </div>

    <div class="col-sm-12 col-md-4">
        <div class="form-group">
            <label>Precio especial ($) <span class="text-danger">*</span></label>
            <input type="number" step="0.01" min="0.01" wire:model.lazy="precio_especial" class="form-control"
                placeholder="ej: 180.00">
            @error('precio_especial')
                <span class="text-danger er">{{ $message }}</span>
            @enderror
            <small class="text-muted">Precio por unidad de venta del producto (kilo, pieza, etc.).</small>
        </div>
    </div>

    <div class="col-sm-12 col-md-3">
        <div class="form-group">
            <label>Estado <span class="text-danger">*</span></label>
            <select wire:model.lazy="activo" class="form-control">
                <option value="1">Activo</option>
                <option value="0">Inactivo</option>
            </select>
            @error('activo')
                <span class="text-danger er">{{ $message }}</span>
            @enderror
        </div>
    </div>

    <div class="col-sm-12 col-md-5">
        <div class="form-group">
            <label>Notas</label>
            <input type="text" wire:model.lazy="notas" class="form-control"
                placeholder="ej: acuerdo mayoreo doña Juanita">
            @error('notas')
                <span class="text-danger er">{{ $message }}</span>
            @enderror
        </div>
    </div>
</div>

@include('common.modalFooter')
