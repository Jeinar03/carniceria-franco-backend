@include('common.modalHead')

<div class="row">
    <div class="col-sm-12 col-md-6">
        <div class="form-group">
            <label>Nombre <span class="text-danger">*</span></label>
            <input type="text" wire:model.lazy="nombre" class="form-control" placeholder="ej: Carnes">
            @error('nombre')
                <span class="text-danger er">{{ $message }}</span>
            @enderror
        </div>
    </div>

    <div class="col-sm-12 col-md-3">
        <div class="form-group">
            <label>Orden <span class="text-danger">*</span></label>
            <input type="number" wire:model.lazy="orden" class="form-control" placeholder="0" min="0">
            @error('orden')
                <span class="text-danger er">{{ $message }}</span>
            @enderror
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

    <div class="col-sm-12">
        <div class="form-group">
            <label>Descripción</label>
            <textarea wire:model.lazy="descripcion" class="form-control" rows="3"
                placeholder="Descripción de la categoría"></textarea>
            @error('descripcion')
                <span class="text-danger er">{{ $message }}</span>
            @enderror
        </div>
    </div>

    <div class="col-sm-12">
        <div class="form-group">
            <label>Imagen</label>
            <input type="file" wire:model="imagen" class="form-control" accept="image/*">
            @error('imagen')
                <span class="text-danger er">{{ $message }}</span>
            @enderror

            @if ($imagen)
                <div class="mt-2">
                    <img src="{{ $imagen->temporaryUrl() }}" style="width: 100px; height: 100px; object-fit: cover; border-radius: 5px;">
                </div>
            @endif
        </div>
    </div>
</div>

@include('common.modalFooter')
