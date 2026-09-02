{{-- Modal: Configuración del Sitio --}}
<div class="modal fade" id="configModal" tabindex="-1" role="dialog" aria-labelledby="configModalLabel" aria-hidden="true" wire:ignore.self
     data-backdrop="static" data-keyboard="false">
    <div class="modal-dialog modal-xl" role="document">
        <div class="modal-content">

            <div class="modal-header bg-success text-white">
                <h5 class="modal-title" id="configModalLabel">
                    <i class="fas fa-cog mr-2"></i>
                    {{ $configId ? 'Editar Configuración' : 'Nueva Configuración del Sitio' }}
                </h5>
            </div>

            <div class="modal-body">

                {{-- Tabs del formulario --}}
                <ul class="nav nav-tabs config-form-tabs mb-3" id="configFormTabs">
                    <li class="nav-item">
                        <a class="nav-link {{ $configActiveTab === 'datos' ? 'active' : '' }}"
                           href="#cfgTabDatos"
                           wire:click.prevent="setConfigActiveTab('datos')">
                            <i class="fas fa-info-circle mr-1"></i> Datos Generales
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link {{ $configActiveTab === 'horarios' ? 'active' : '' }}"
                           href="#cfgTabHorarios"
                           wire:click.prevent="setConfigActiveTab('horarios')">
                            <i class="fas fa-clock mr-1"></i> Horarios de Atención
                        </a>
                    </li>
                </ul>

                <div class="tab-content">

                    {{-- ─── Tab: Datos Generales ─── --}}
                    <div class="tab-pane fade {{ $configActiveTab === 'datos' ? 'show active' : '' }}" id="cfgTabDatos">
                        <div class="row">

                            {{-- Nombre --}}
                            <div class="col-md-6 form-group">
                                <label class="cfg-label">Nombre de la configuración <span class="text-danger">*</span></label>
                                <input type="text" class="form-control @error('configNombre') is-invalid @enderror"
                                       wire:model.lazy="configNombre"
                                       placeholder="Ej: Configuración principal">
                                @error('configNombre')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            {{-- Logo --}}
                            <div class="col-md-6 form-group">
                                <label class="cfg-label">Logo del sitio</label>
                                <input type="file" class="form-control-file @error('configLogo') is-invalid @enderror"
                                       wire:model="configLogo" accept="image/*">
                                @error('configLogo')
                                    <div class="invalid-feedback d-block">{{ $message }}</div>
                                @enderror
                                @if ($configLogoActual && ! $configLogo)
                                    <div class="mt-2">
                                        <img src="{{ asset('storage/' . $configLogoActual) }}"
                                             alt="Logo actual" class="cfg-logo-preview">
                                        <small class="text-muted d-block">Logo actual</small>
                                    </div>
                                @endif
                                @if ($configLogo)
                                    <div class="mt-2">
                                        <img src="{{ $configLogo->temporaryUrl() }}"
                                             alt="Preview" class="cfg-logo-preview">
                                        <small class="text-success d-block">Vista previa del nuevo logo</small>
                                    </div>
                                @endif
                            </div>

                            {{-- Datos de contacto --}}
                            <div class="col-12">
                                <h6 class="cfg-section-title"><i class="fas fa-address-card mr-1"></i> Datos de Contacto</h6>
                                <hr class="mt-1 mb-3">
                            </div>

                            <div class="col-md-12 form-group">
                                <label class="cfg-label">Dirección</label>
                                <input type="text" class="form-control"
                                       wire:model.lazy="configDireccion"
                                       placeholder="Ej: Av. Siempre Viva 742, Springfield">
                            </div>

                            <div class="col-md-6 form-group">
                                <label class="cfg-label">Correo electrónico</label>
                                <input type="email" class="form-control @error('configCorreo') is-invalid @enderror"
                                       wire:model.lazy="configCorreo"
                                       placeholder="contacto@tuempresa.com">
                                @error('configCorreo')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-md-6 form-group">
                                <label class="cfg-label">Teléfono</label>
                                <input type="text" class="form-control"
                                       wire:model.lazy="configTelefono"
                                       placeholder="Ej: +54 9 11 1234-5678">
                            </div>

                            {{-- Redes sociales --}}
                            <div class="col-12 mt-1">
                                <h6 class="cfg-section-title"><i class="fas fa-share-alt mr-1"></i> Redes Sociales</h6>
                                <hr class="mt-1 mb-3">
                            </div>

                            <div class="col-md-4 form-group">
                                <label class="cfg-label">
                                    <i class="fab fa-facebook text-primary mr-1"></i> Facebook
                                </label>
                                <input type="url" class="form-control @error('configFacebook') is-invalid @enderror"
                                       wire:model.lazy="configFacebook"
                                       placeholder="https://facebook.com/tupagina">
                                @error('configFacebook')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-md-4 form-group">
                                <label class="cfg-label">
                                    <i class="fab fa-instagram text-danger mr-1"></i> Instagram
                                </label>
                                <input type="url" class="form-control @error('configInstagram') is-invalid @enderror"
                                       wire:model.lazy="configInstagram"
                                       placeholder="https://instagram.com/tuusuario">
                                @error('configInstagram')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-md-4 form-group">
                                <label class="cfg-label">
                                    <i class="fab fa-whatsapp text-success mr-1"></i> WhatsApp
                                </label>
                                <input type="text" class="form-control"
                                       wire:model.lazy="configWhatsapp"
                                       placeholder="Ej: 5491112345678">
                                <small class="text-muted">Número con código de país, sin + ni espacios.</small>
                            </div>

                        </div>
                    </div>

                    {{-- ─── Tab: Horarios ─── --}}
                    <div class="tab-pane fade {{ $configActiveTab === 'horarios' ? 'show active' : '' }}" id="cfgTabHorarios">
                        <p class="text-muted small mb-3">
                            Configure el horario de atención para cada día de la semana.
                        </p>
                        <div class="table-responsive">
                            <table class="table table-sm horario-table">
                                <thead>
                                    <tr>
                                        <th style="width:130px">Día</th>
                                        <th style="width:100px" class="text-center">Abierto</th>
                                        <th>Apertura</th>
                                        <th>Cierre</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach(['lunes','martes','miercoles','jueves','viernes','sabado','domingo'] as $dia)
                                        <tr>
                                            <td class="align-middle font-weight-bold text-capitalize">{{ $dia }}</td>
                                            <td class="align-middle text-center">
                                                <div class="custom-control custom-switch">
                                                    <input type="checkbox"
                                                           class="custom-control-input"
                                                           id="horario_{{ $dia }}"
                                                           wire:model="configHorarios.{{ $dia }}.abierto">
                                                    <label class="custom-control-label" for="horario_{{ $dia }}"></label>
                                                </div>
                                            </td>
                                            <td class="align-middle">
                                                <input type="time"
                                                       class="form-control form-control-sm"
                                                       wire:model="configHorarios.{{ $dia }}.apertura"
                                                       {{ empty(data_get($configHorarios, $dia . '.abierto', false)) ? 'disabled' : '' }}>
                                            </td>
                                            <td class="align-middle">
                                                <input type="time"
                                                       class="form-control form-control-sm"
                                                       wire:model="configHorarios.{{ $dia }}.cierre"
                                                       {{ empty(data_get($configHorarios, $dia . '.abierto', false)) ? 'disabled' : '' }}>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>

                </div>{{-- /tab-content --}}

            </div>{{-- /modal-body --}}

            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-dismiss="modal">
                    <i class="fas fa-times mr-1"></i> Cancelar
                </button>
                <button type="button" class="btn btn-success" wire:click="saveConfig" wire:loading.attr="disabled">
                    <span wire:loading wire:target="saveConfig">
                        <span class="spinner-border spinner-border-sm mr-1" role="status"></span>
                        Guardando...
                    </span>
                    <span wire:loading.remove wire:target="saveConfig">
                        <i class="fas fa-save mr-1"></i>
                        {{ $configId ? 'Actualizar' : 'Guardar' }}
                    </span>
                </button>
            </div>

        </div>
    </div>
</div>

<style>
    .cfg-label { font-size: .83rem; font-weight: 600; color: #3B3F5C; margin-bottom: 4px; }
    .cfg-section-title { color: #3B3F5C; font-weight: 700; font-size: .9rem; }
    .cfg-logo-preview { max-height: 64px; max-width: 120px; object-fit: contain; border: 1px solid #dee2e6; border-radius: 6px; padding: 3px; }
    .config-form-tabs { border-bottom: 1px solid #dee2e6; }
    .config-form-tabs .nav-link { color: #697086; border: 0; border-bottom: 3px solid transparent; border-radius: 0; }
    .config-form-tabs .nav-link:hover { color: #3B3F5C; border-bottom-color: #c9cddd; }
    .config-form-tabs .nav-link.active { color: #3B3F5C; background: transparent; border: 0; border-bottom: 3px solid #3B3F5C; font-weight: 700; }
    .horario-table thead { background: #f0f2f8; }
    .horario-table td { vertical-align: middle; }
</style>
