<div class="row sales layout-top-spacing">
    <div class="col-sm-12">
        <div class="widget widget-chart-one">
            <div class="widget-heading">
                <h4 class="card-title">
                    <b>{{ $componentName }} | {{ $pageTitle }}</b>
                </h4>
            </div>

            <div class="widget-content">
                {{-- Aviso permanente de qué credenciales están en uso --}}
                @if (! $enUso)
                    <div class="alert alert-warning mb-3">
                        <b>No hay ninguna configuracion en uso.</b> Los pagos con Mercado Pago no estan disponibles
                        (se usa el respaldo del archivo .env, si existe). Guarda una y presiona "Usar".
                    </div>
                @elseif ($enUso->sandbox)
                    <div class="alert alert-danger mb-3">
                        <b>MODO PRUEBA activo:</b> estas usando "{{ $enUso->name }}". Los pagos NO son reales.
                        Antes de abrir al publico presiona "Usar" en la configuracion de Produccion.
                    </div>
                @else
                    <div class="alert alert-success mb-3">
                        <b>PRODUCCION:</b> estas usando "{{ $enUso->name }}". Los pagos son reales.
                    </div>
                @endif

                {{-- Configuraciones guardadas --}}
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h5 class="mb-0">Configuraciones guardadas</h5>
                    <button type="button" class="btn btn-outline-primary btn-sm" wire:click="newSetting">
                        <i class="fas fa-plus mr-1"></i> Nueva configuracion
                    </button>
                </div>

                <div class="table-responsive mb-4">
                    <table class="table table-bordered table-sm mb-0">
                        <thead>
                            <tr>
                                <th>Nombre</th>
                                <th>Tipo</th>
                                <th>Access Token</th>
                                <th>Firma del webhook</th>
                                <th class="text-center">Estado</th>
                                <th class="text-center" style="width: 230px">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($settings as $item)
                                <tr class="{{ $settingId === $item->id ? 'table-active' : '' }}">
                                    <td class="align-middle"><b>{{ $item->name }}</b></td>
                                    <td class="align-middle">
                                        <span class="badge {{ $item->sandbox ? 'badge-warning' : 'badge-primary' }}">
                                            {{ $item->sandbox ? 'Prueba' : 'Produccion' }}
                                        </span>
                                    </td>
                                    <td class="align-middle"><code>{{ \App\Models\MercadoPagoSetting::mask($item->access_token) }}</code></td>
                                    <td class="align-middle">{{ $item->webhook_secret ? 'Configurada' : 'No configurada' }}</td>
                                    <td class="align-middle text-center">
                                        @if ($item->active)
                                            <span class="badge badge-success">En uso</span>
                                        @else
                                            <span class="badge badge-secondary">Guardada</span>
                                        @endif
                                    </td>
                                    <td class="align-middle text-center">
                                        @if (! $item->active)
                                            <button type="button" class="btn btn-success btn-sm"
                                                    wire:click="activate({{ $item->id }})"
                                                    @if (! $item->sandbox)
                                                        onclick="confirm('Se cobrara dinero real. Usar esta configuracion de PRODUCCION?') || event.stopImmediatePropagation()"
                                                    @endif>
                                                <i class="fas fa-check mr-1"></i> Usar
                                            </button>
                                        @endif
                                        <button type="button" class="btn btn-info btn-sm" wire:click="edit({{ $item->id }})">
                                            <i class="fas fa-edit"></i> Editar
                                        </button>
                                        @if (! $item->active)
                                            <button type="button" class="btn btn-danger btn-sm"
                                                    wire:click="deleteSetting({{ $item->id }})"
                                                    onclick="confirm('Eliminar la configuracion {{ e($item->name) }}?') || event.stopImmediatePropagation()">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="text-center text-muted">Aun no hay configuraciones guardadas.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                {{-- Formulario: editar la seleccionada o capturar una nueva --}}
                <h5 class="mb-3">
                    {{ $settingId ? 'Editando: ' . optional($settings->firstWhere('id', $settingId))->name : 'Nueva configuracion' }}
                </h5>

                @if ($settingId)
                    <div class="alert alert-warning py-2">
                        Estas modificando una configuracion que <b>ya existe</b>: lo que pegues aqui la reemplaza.
                        Para agregar otra (por ejemplo Produccion) presiona primero <b>+ Nueva configuracion</b>.
                    </div>
                @endif

                <div class="row">
                    <div class="col-lg-4 col-md-12 mb-3">
                        <div class="mp-credential-preview">
                            <small>Access Token</small>
                            <code>{{ $accessTokenMasked }}</code>
                        </div>

                        <div class="mp-credential-preview mt-2">
                            <small>Public Key</small>
                            <code>{{ $publicKeyMasked }}</code>
                        </div>

                        <div class="mp-credential-preview mt-2">
                            <small>Clave de firma del webhook</small>
                            <code>{{ $webhookSecretMasked }}</code>
                        </div>
                    </div>

                    <div class="col-lg-8 col-md-12">
                        <div class="row">
                            <div class="col-md-12 form-group">
                                <label>Nombre de la configuracion</label>
                                <input type="text" class="form-control @error('name') is-invalid @enderror"
                                       wire:model.lazy="name" placeholder="Ej. Pruebas o Produccion">
                                @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>

                            <div class="col-md-12 form-group">
                                <label>MERCADOPAGO_ACCESS_TOKEN</label>
                                <input type="password" class="form-control @error('accessToken') is-invalid @enderror"
                                       wire:model.defer="accessToken"
                                       autocomplete="new-password"
                                       placeholder="{{ $settingId ? 'Dejar vacio para conservar el token actual' : 'Pega el Access Token' }}">
                                @error('accessToken') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>

                            <div class="col-md-12 form-group">
                                <label>MERCADOPAGO_PUBLIC_KEY</label>
                                <input type="password" class="form-control @error('publicKey') is-invalid @enderror"
                                       wire:model.defer="publicKey"
                                       autocomplete="new-password"
                                       placeholder="{{ $settingId ? 'Dejar vacio para conservar la public key actual' : 'Pega la Public Key' }}">
                                @error('publicKey') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>

                            <div class="col-md-12 form-group">
                                <label>MERCADOPAGO_WEBHOOK_SECRET (clave secreta de firma, opcional)</label>
                                <input type="password" class="form-control @error('webhookSecret') is-invalid @enderror"
                                       wire:model.defer="webhookSecret"
                                       autocomplete="new-password"
                                       placeholder="{{ $webhookSecretMasked !== 'No configurada' ? 'Dejar vacio para conservar la clave actual' : 'Pega la clave secreta del webhook' }}">
                                <small class="text-muted d-block mt-1">
                                    Se copia en Mercado Pago, Tus integraciones, Webhooks, Configurar notificaciones.
                                    Con la clave guardada, el sistema rechaza los avisos que no traigan una firma valida.
                                    Sin clave, no se valida la firma.
                                </small>
                                @error('webhookSecret') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>

                            <div class="col-md-6 form-group">
                                <label>Tipo de credenciales</label>
                                <select class="form-control" wire:model="sandbox">
                                    <option value="1">Prueba (usuarios y tarjetas de prueba)</option>
                                    <option value="0">Produccion (cobra dinero real)</option>
                                </select>
                                <small class="text-muted d-block mt-1">
                                    Elige el tipo segun las credenciales que pegaste. En Prueba no se manda el correo
                                    del cliente a Mercado Pago; en Produccion si.
                                </small>
                            </div>

                            <div class="col-md-12 mt-2">
                                <button type="button" class="btn btn-primary btn-rounded" wire:click="save" wire:loading.attr="disabled">
                                    <span wire:loading wire:target="save">
                                        <span class="spinner-border spinner-border-sm mr-1" role="status"></span>
                                        Guardando...
                                    </span>
                                    <span wire:loading.remove wire:target="save">
                                        <i class="fas fa-save mr-1"></i> {{ $settingId ? 'Guardar cambios' : 'Guardar configuracion' }}
                                    </span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        window.livewire.on('mercadopago-success', Msg => noty(Msg))
        window.livewire.on('mercadopago-error', Msg => noty(Msg, 2))
    })
</script>

<style>
    .mp-credential-preview small {
        color: #697086;
        display: block;
        font-weight: 700;
        letter-spacing: 0;
        text-transform: uppercase;
    }

    .mp-credential-preview {
        background: #fff;
        border: 1px solid #e2e6f0;
        border-radius: 8px;
        padding: 14px;
    }

    .mp-credential-preview code {
        color: #3B3F5C;
        display: block;
        font-size: .9rem;
        margin-top: 6px;
        white-space: normal;
        word-break: break-all;
    }
</style>
