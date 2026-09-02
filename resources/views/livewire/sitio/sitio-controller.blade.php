<div class="row layout-top-spacing">
    <div class="col-sm-12">
        <div class="widget widget-chart-one sitio-widget">

            {{-- Encabezado --}}
            <div class="widget-heading sitio-heading d-flex align-items-center justify-content-between">
                <h4 class="sitio-title">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24"
                         fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"
                         stroke-linejoin="round" class="mr-2">
                        <circle cx="12" cy="12" r="3"></circle>
                        <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"></path>
                    </svg>
                    Sistema | Sitio Web
                </h4>
            </div>

            <div class="widget-content p-3">

                {{-- Tabs nav --}}
                <ul class="nav nav-tabs sitio-tabs mb-3" role="tablist">
                    <li class="nav-item">
                        <a class="nav-link {{ $activeTab === 'config' ? 'active' : '' }}"
                           wire:click="$set('activeTab', 'config')"
                           href="javascript:void(0)" role="tab">
                            <i class="fas fa-sliders-h mr-1"></i> Configuraciones del Sitio
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link {{ $activeTab === 'alertas' ? 'active' : '' }}"
                           wire:click="$set('activeTab', 'alertas')"
                           href="javascript:void(0)" role="tab">
                            <i class="fas fa-bell mr-1"></i> Alertas Emergentes
                        </a>
                    </li>
                </ul>

                {{-- ═══════════════════════════════════════════════ --}}
                {{-- TAB: Configuraciones --}}
                {{-- ═══════════════════════════════════════════════ --}}
                @if ($activeTab === 'config')
                    <div class="d-flex justify-content-end mb-2">
                        <button class="btn btn-success btn-sm"
                                wire:click="openConfigModal()">
                            <i class="fas fa-plus mr-1"></i> Nueva Configuración
                        </button>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-hover sitio-table">
                            <thead>
                                <tr>
                                    <th>Nombre</th>
                                    <th>Logo</th>
                                    <th>Contacto</th>
                                    <th>Redes</th>
                                    <th class="text-center">Estado</th>
                                    <th class="text-center">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($configs as $config)
                                    <tr>
                                        <td class="align-middle font-weight-bold">{{ $config->nombre }}</td>
                                        <td class="align-middle">
                                            @if ($config->logo)
                                                <img src="{{ asset('storage/' . $config->logo) }}"
                                                     alt="Logo" class="sitio-logo-thumb">
                                            @else
                                                <span class="text-muted small">Sin logo</span>
                                            @endif
                                        </td>
                                        <td class="align-middle small">
                                            @if ($config->direccion)
                                                <div><i class="fas fa-map-marker-alt text-muted mr-1"></i>{{ $config->direccion }}</div>
                                            @endif
                                            @if ($config->correo)
                                                <div><i class="fas fa-envelope text-muted mr-1"></i>{{ $config->correo }}</div>
                                            @endif
                                            @if ($config->telefono)
                                                <div><i class="fas fa-phone text-muted mr-1"></i>{{ $config->telefono }}</div>
                                            @endif
                                        </td>
                                        <td class="align-middle">
                                            @if ($config->facebook_url)
                                                <span class="badge badge-primary badge-sm mr-1">FB</span>
                                            @endif
                                            @if ($config->instagram_url)
                                                <span class="badge badge-danger badge-sm mr-1">IG</span>
                                            @endif
                                            @if ($config->whatsapp)
                                                <span class="badge badge-success badge-sm">WA</span>
                                            @endif
                                        </td>
                                        <td class="align-middle text-center">
                                            @if ($config->activo)
                                                <span class="badge badge-success px-2 py-1">Activa</span>
                                            @else
                                                <span class="badge badge-secondary px-2 py-1">Inactiva</span>
                                            @endif
                                        </td>
                                        <td class="align-middle text-center">
                                            @if (! $config->activo)
                                                <button class="btn btn-xs btn-success mr-1"
                                                        wire:click="activateConfig({{ $config->id }})"
                                                        title="Activar">
                                                    <i class="fas fa-check"></i>
                                                </button>
                                            @endif
                                            <button class="btn btn-xs btn-warning mr-1"
                                                    wire:click="openConfigModal({{ $config->id }})"
                                                    title="Editar">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            @if (! $config->activo)
                                                <button class="btn btn-xs btn-danger"
                                                        wire:click="confirmDeleteConfig({{ $config->id }})"
                                                        title="Eliminar">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="text-center text-muted py-4">
                                            No hay configuraciones registradas.
                                            <br>
                                            <button class="btn btn-success btn-sm mt-2"
                                                    wire:click="openConfigModal()">
                                                <i class="fas fa-plus mr-1"></i> Crear primera configuración
                                            </button>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <div class="d-flex justify-content-end mt-2">
                        {{ $configs->links() }}
                    </div>
                @endif

                {{-- ═══════════════════════════════════════════════ --}}
                {{-- TAB: Alertas Emergentes --}}
                {{-- ═══════════════════════════════════════════════ --}}
                @if ($activeTab === 'alertas')
                    <div class="d-flex justify-content-end mb-2">
                        <button class="btn btn-warning btn-sm"
                                wire:click="openAlertModal()">
                            <i class="fas fa-plus mr-1"></i> Nueva Alerta
                        </button>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-hover sitio-table">
                            <thead>
                                <tr>
                                    <th>Título</th>
                                    <th>Tipo</th>
                                    <th>Imagen</th>
                                    <th>Programación</th>
                                    <th class="text-center">Estado</th>
                                    <th class="text-center">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($alertas as $alerta)
                                    <tr>
                                        <td class="align-middle">
                                            <span class="font-weight-bold">{{ $alerta->titulo }}</span>
                                            @if ($alerta->descripcion)
                                                <br><small class="text-muted">{{ Str::limit($alerta->descripcion, 60) }}</small>
                                            @endif
                                        </td>
                                        <td class="align-middle">
                                            @php
                                                $tipoBadge = [
                                                    'oferta'  => 'warning',
                                                    'alerta'  => 'danger',
                                                    'novedad' => 'info',
                                                    'anuncio' => 'secondary',
                                                ][$alerta->tipo] ?? 'secondary';
                                            @endphp
                                            <span class="badge badge-{{ $tipoBadge }}">
                                                {{ ucfirst($alerta->tipo) }}
                                            </span>
                                        </td>
                                        <td class="align-middle">
                                            @if ($alerta->imagen)
                                                <img src="{{ asset('storage/' . $alerta->imagen) }}"
                                                     alt="Img" class="sitio-alert-thumb">
                                            @else
                                                <span class="text-muted small">—</span>
                                            @endif
                                        </td>
                                        <td class="align-middle small">
                                            @if ($alerta->fecha_inicio)
                                                <div><i class="fas fa-calendar-alt text-muted mr-1"></i>
                                                    {{ $alerta->fecha_inicio->format('d/m/Y H:i') }}
                                                </div>
                                                <div><i class="fas fa-clock text-muted mr-1"></i>
                                                    {{ $alerta->dias_duracion }} día(s)
                                                </div>
                                            @else
                                                <span class="text-muted">Sin programar</span>
                                            @endif
                                        </td>
                                        <td class="align-middle text-center">
                                            @if ($alerta->activo)
                                                <span class="badge badge-success px-2 py-1">Activa</span>
                                            @else
                                                <span class="badge badge-secondary px-2 py-1">Inactiva</span>
                                            @endif
                                        </td>
                                        <td class="align-middle text-center">
                                            <button class="btn btn-xs {{ $alerta->activo ? 'btn-secondary' : 'btn-success' }} mr-1"
                                                    wire:click="toggleAlertActivo({{ $alerta->id }})"
                                                    title="{{ $alerta->activo ? 'Desactivar' : 'Activar' }}">
                                                <i class="fas fa-{{ $alerta->activo ? 'toggle-off' : 'toggle-on' }}"></i>
                                            </button>
                                            <button class="btn btn-xs btn-warning mr-1"
                                                    wire:click="openAlertModal({{ $alerta->id }})"
                                                    title="Editar">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button class="btn btn-xs btn-danger"
                                                    wire:click="confirmDeleteAlert({{ $alerta->id }})"
                                                    title="Eliminar">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="text-center text-muted py-4">
                                            No hay alertas registradas.
                                            <br>
                                            <button class="btn btn-warning btn-sm mt-2"
                                                    wire:click="openAlertModal()">
                                                <i class="fas fa-plus mr-1"></i> Crear primera alerta
                                            </button>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <div class="d-flex justify-content-end mt-2">
                        {{ $alertas->links() }}
                    </div>
                @endif

            </div>{{-- /widget-content --}}
        </div>{{-- /widget --}}
    </div>{{-- /col --}}

    {{-- Modales --}}
    @include('livewire.sitio.config-modal')
    @include('livewire.sitio.form')

</div>{{-- /row --}}

<style>
    .sitio-widget { border-radius: 8px; overflow: hidden; box-shadow: 0 4px 15px rgba(0,0,0,.08); }
    .sitio-heading { background: #3B3F5C; padding: 14px 20px; }
    .sitio-title { color: #fff; font-size: 1.1rem; font-weight: 600; margin: 0; }
    .sitio-tabs { border-bottom: 1px solid #dee2e6; }
    .sitio-tabs .nav-link { color: #697086; font-weight: 500; border: 0; border-bottom: 3px solid transparent; border-radius: 0; }
    .sitio-tabs .nav-link:hover { color: #3B3F5C; border-bottom-color: #c9cddd; }
    .sitio-tabs .nav-link.active { color: #3B3F5C; background: transparent; border: 0; border-bottom: 3px solid #3B3F5C; font-weight: 700; }
    .sitio-table thead { background: #3B3F5C; color: #fff; }
    .sitio-table thead th { border: none; font-size: .82rem; text-transform: uppercase; letter-spacing: .04em; }
    .sitio-table tbody tr:hover { background: #f8f9ff; }
    .sitio-logo-thumb { width: 48px; height: 48px; object-fit: contain; border-radius: 6px; border: 1px solid #dee2e6; }
    .sitio-alert-thumb { width: 56px; height: 40px; object-fit: cover; border-radius: 4px; }
    .btn-xs { padding: 2px 7px; font-size: .75rem; border-radius: 4px; }
    .swal2-container { z-index: 3000 !important; }
</style>

<script>
    if (!window.__sitioLivewireEventsBound) {
        window.__sitioLivewireEventsBound = true;

        window.addEventListener('sitio-success', e => noty({ text: e.detail[0], type: 'success' }));
        window.addEventListener('sitio-error',   e => noty({ text: e.detail[0], type: 'error' }));

        const livewire = window.livewire || window.Livewire;

        livewire.on('show-config-modal', () => $('#configModal').modal('show'));
        livewire.on('hide-config-modal', () => $('#configModal').modal('hide'));
        livewire.on('show-alert-modal',  () => $('#alertModal').modal('show'));
        livewire.on('hide-alert-modal',  () => $('#alertModal').modal('hide'));

        livewire.on('confirm-delete-config', (id) => {
            Swal.fire({
                title: '¿Eliminar configuración?',
                text: 'Esta acción no se puede deshacer.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#aaa',
                confirmButtonText: 'Sí, eliminar',
                cancelButtonText: 'Cancelar',
                reverseButtons: true,
            }).then(result => {
                if (result.isConfirmed) {
                    livewire.emit('deleteConfigConfirmed', id);
                }
            });
        });

        livewire.on('confirm-delete-alert', (id) => {
            Swal.fire({
                title: '¿Eliminar alerta?',
                text: 'Esta acción no se puede deshacer.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#aaa',
                confirmButtonText: 'Sí, eliminar',
                cancelButtonText: 'Cancelar',
                reverseButtons: true,
            }).then(result => {
                if (result.isConfirmed) {
                    livewire.emit('deleteAlertConfirmed', id);
                }
            });
        });
    }
</script>
