@include('common.modalHead')

<div class="row">
    <div class="col-sm-12">
        <div class="form-group">
            <label>Pregunta <span class="text-danger">*</span></label>
            <textarea wire:model.lazy="pregunta" class="form-control" rows="4"
                      placeholder="Pregunta que vera el cliente"></textarea>
            @error('pregunta')
                <span class="text-danger er">{{ $message }}</span>
            @enderror
        </div>
    </div>

    <div class="col-sm-12">
        <div class="form-group">
            <label>Descripcion</label>
            <textarea wire:model.lazy="descripcion" class="form-control" rows="2"
                      placeholder="Contexto interno o descripcion corta"></textarea>
            @error('descripcion')
                <span class="text-danger er">{{ $message }}</span>
            @enderror
        </div>
    </div>

    <div class="col-sm-12 col-md-4">
        <div class="form-group">
            <label>Orden <span class="text-danger">*</span></label>
            <input type="number" wire:model.lazy="orden" class="form-control" min="0">
            @error('orden')
                <span class="text-danger er">{{ $message }}</span>
            @enderror
        </div>
    </div>

    <div class="col-sm-12 col-md-4">
        <div class="form-group">
            <label>Estado <span class="text-danger">*</span></label>
            <select wire:model.lazy="activo" class="form-control">
                <option value="1">Activa</option>
                <option value="0">Inactiva</option>
            </select>
            @error('activo')
                <span class="text-danger er">{{ $message }}</span>
            @enderror
        </div>
    </div>

    <div class="col-sm-12 col-md-4">
        <div class="form-group">
            <label>Mostrar al finalizar pedido <span class="text-danger">*</span></label>
            <select wire:model.lazy="mostrar_al_finalizar_pedido" class="form-control">
                <option value="1">Si</option>
                <option value="0">No</option>
            </select>
            @error('mostrar_al_finalizar_pedido')
                <span class="text-danger er">{{ $message }}</span>
            @enderror
        </div>
    </div>

    <div class="col-sm-12">
        <div class="alert alert-info mb-0">
            Las respuestas de clientes se guardan con escala Likert de 1 a 10.
        </div>
    </div>
</div>

@include('common.modalFooter')
