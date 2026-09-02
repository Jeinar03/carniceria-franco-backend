<div class="row sales layout-top-spacing">
    <div class="col-sm-12">
        <div class="widget widget-chart-one">
            <div class="widget-heading">
                <h4 class="card-title">
                    <b>{{ $componentName }} | {{ $pageTitle }}</b>
                </h4>
            </div>

            <div class="widget-content">
                <div class="row">
                    <div class="col-lg-4 col-md-12 mb-3">
                        <div class="mp-status-card">
                            <div class="mp-status-icon">
                                <i class="fas fa-credit-card"></i>
                            </div>
                            <div>
                                <small>Configuracion actual</small>
                                <h5 class="mb-1">{{ $name }}</h5>
                                <span class="badge {{ $active ? 'badge-success' : 'badge-secondary' }}">
                                    {{ $active ? 'Activa' : 'Inactiva' }}
                                </span>
                                <span class="badge {{ $sandbox ? 'badge-warning' : 'badge-primary' }}">
                                    {{ $sandbox ? 'Sandbox' : 'Produccion' }}
                                </span>
                            </div>
                        </div>

                        <div class="mp-credential-preview mt-3">
                            <small>Access Token</small>
                            <code>{{ $accessTokenMasked }}</code>
                        </div>

                        <div class="mp-credential-preview mt-2">
                            <small>Public Key</small>
                            <code>{{ $publicKeyMasked }}</code>
                        </div>
                    </div>

                    <div class="col-lg-8 col-md-12">
                        <div class="row">
                            <div class="col-md-12 form-group">
                                <label>Nombre de la configuracion</label>
                                <input type="text" class="form-control @error('name') is-invalid @enderror"
                                       wire:model.lazy="name" placeholder="Configuracion principal">
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

                            <div class="col-md-6 form-group">
                                <label>Ambiente</label>
                                <div class="custom-control custom-switch mt-2">
                                    <input type="checkbox" class="custom-control-input" id="mpSandbox"
                                           wire:model="sandbox">
                                    <label class="custom-control-label" for="mpSandbox">
                                        {{ $sandbox ? 'Sandbox habilitado' : 'Produccion' }}
                                    </label>
                                </div>
                            </div>

                            <div class="col-md-6 form-group">
                                <label>Estado</label>
                                <div class="custom-control custom-switch mt-2">
                                    <input type="checkbox" class="custom-control-input" id="mpActive"
                                           wire:model="active">
                                    <label class="custom-control-label" for="mpActive">
                                        {{ $active ? 'Configuracion activa' : 'Configuracion inactiva' }}
                                    </label>
                                </div>
                            </div>

                            <div class="col-md-12 mt-2">
                                <button type="button" class="btn btn-primary btn-rounded" wire:click="save" wire:loading.attr="disabled">
                                    <span wire:loading wire:target="save">
                                        <span class="spinner-border spinner-border-sm mr-1" role="status"></span>
                                        Guardando...
                                    </span>
                                    <span wire:loading.remove wire:target="save">
                                        <i class="fas fa-save mr-1"></i> Guardar credenciales
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
    .mp-status-card {
        align-items: center;
        background: #f5f6fb;
        border: 1px solid #e2e6f0;
        border-radius: 8px;
        display: flex;
        gap: 14px;
        padding: 18px;
    }

    .mp-status-icon {
        align-items: center;
        background: #3B3F5C;
        border-radius: 8px;
        color: #fff;
        display: flex;
        font-size: 24px;
        height: 54px;
        justify-content: center;
        width: 54px;
    }

    .mp-status-card small,
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
