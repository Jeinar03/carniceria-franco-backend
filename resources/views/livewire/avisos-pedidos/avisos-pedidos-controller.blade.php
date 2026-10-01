<div class="row sales layout-top-spacing">
    <div class="col-sm-12">
        <div class="widget widget-chart-one">
            <div class="widget-heading">
                <h4 class="card-title">
                    <b>{{ $componentName }} | {{ $pageTitle }}</b>
                </h4>
            </div>

            <div class="widget-content">
                <p class="text-muted">
                    El aviso "Por terminar" del encabezado cuenta los pedidos que faltan por despachar y cambia de color
                    segun cuanto tiempo lleva esperando el pedido mas viejo.
                </p>

                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label for="amarilloMin"><span class="badge badge-warning">Amarillo</span> a partir de (minutos)</label>
                        <input id="amarilloMin" type="number" min="1" max="1440" class="form-control" wire:model.defer="amarilloMin">
                        @error('amarilloMin') <span class="text-danger er">{{ $message }}</span> @enderror
                    </div>

                    <div class="col-md-4 mb-3">
                        <label for="rojoMin"><span class="badge badge-danger">Rojo</span> cuando pase de (minutos)</label>
                        <input id="rojoMin" type="number" min="2" max="1440" class="form-control" wire:model.defer="rojoMin">
                        @error('rojoMin') <span class="text-danger er">{{ $message }}</span> @enderror
                    </div>
                </div>

                <p class="text-muted small">
                    Verde: menos de {{ (int) $amarilloMin }} min. Amarillo: de {{ (int) $amarilloMin }} a {{ (int) $rojoMin }} min.
                    Rojo: mas de {{ (int) $rojoMin }} min. Gris: no hay pedidos por terminar.
                </p>

                <button type="button" class="btn btn-dark" wire:click="save">Guardar</button>
            </div>
        </div>
    </div>
</div>
