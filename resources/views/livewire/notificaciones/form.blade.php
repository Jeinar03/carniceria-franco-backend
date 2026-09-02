@include('common.modalHead')

<div class="row">
    <div class="col-sm-12 col-md-6">
        <div class="form-group">
            <label>Nombre</label>
            <input type="text" wire:model.lazy="nombre" class="form-control" placeholder="Ej: Notificación de actualización">
            @error('nombre') <span class="text-danger er">{{ $message }}</span>@enderror
        </div>
    </div>

    <div class="col-sm-12 col-md-6">
        <div class="form-group">
            <label>Título de Notificación</label>
            <input type="text" wire:model.lazy="titulo_notificacion" class="form-control" placeholder="Ej: Nueva actualización disponible">
            @error('titulo_notificacion') <span class="text-danger er">{{ $message }}</span>@enderror
        </div>
    </div>

    <div class="col-sm-12">
        <div class="form-group">
            <label>Descripción</label>
            <textarea wire:model.lazy="descripcion" class="form-control"
                placeholder="Ej: Se ha lanzado una nueva versión con mejoras..."></textarea>
            @error('descripcion') <span class="text-danger er">{{ $message }}</span>@enderror
        </div>
    </div>

    <div class="col-sm-12 col-md-6">
        <div class="form-group">
            <label>Fecha de Envío</label>
            <input type="datetime-local" wire:model.lazy="fecha_envio" class="form-control">
            @error('fecha_envio') <span class="text-danger er">{{ $message }}</span>@enderror
        </div>
    </div>

    <div class="col-sm-12 col-md-6">
    <div class="form-group">
        <label>Logo (Imagen)</label>
        <input type="file" wire:model="logo" class="form-control">
        @error('logo') <span class="text-danger er">{{ $message }}</span>@enderror
    </div>

    <!-- Vista previa de la imagen subida -->
    @if ($logo)
        <div class="mt-2">
            <label>Vista previa:</label><br>
            <img src="{{ $logo->temporaryUrl() }}" class="img-thumbnail" width="150">
        </div>
    @endif
</div>

</div>

@include('common.modalFooter')
