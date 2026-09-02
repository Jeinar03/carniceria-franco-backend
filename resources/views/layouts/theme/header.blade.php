@php
    $nonce = app()->bound('nonce') ? app('nonce') : '';
    $user = Auth::user();
    $hasTwoFactorMethod = $user && method_exists($user, 'hasTwoFactorAuthEnabled');
    $hasTwoFactorEnabled = $hasTwoFactorMethod ? $user->hasTwoFactorAuthEnabled() : false;
    $canDisableTwoFactor = \Illuminate\Support\Facades\Route::has('2fa.disable');
    $userImageUrl = null;
    $outOfStockProducts = collect();

    if ($user) {
        $outOfStockProducts = \App\Models\Product::query()
            ->withInventoryStock()
            ->active()
            ->sinStock()
            ->orderBy('nombre')
            ->get();
    }

    if ($user && $user->image) {
        $rawImage = (string) $user->image;

        if (str_starts_with($rawImage, 'data:image')) {
            $userImageUrl = $rawImage;
        } elseif (str_contains($rawImage, 'users/')) {
            $userImageUrl = \Illuminate\Support\Facades\Storage::url($rawImage);
        } else {
            $userImageUrl = asset('storage/users/' . $rawImage);
        }
    }
@endphp

<link href="{{ asset('assets/css/app/header.css') }}" rel="stylesheet" type="text/css" nonce="{{ $nonce }}" />

<div class="header-container fixed-top">
    <header class="header navbar navbar-expand-lg px-2" id="header">
        <div class="container-fluid">
            <div class="d-flex align-items-center w-100">
                <div class="d-flex align-items-center flex-grow-1">
                    <a href="{{ url('home') }}" class="d-flex align-items-center mr-2" aria-label="Ir al inicio">
                        <img src="{{ asset('images/logo.jpeg') }}" alt="Logo" class="header-logo">
                    </a>

                    <a href="javascript:void(0);" class="sidebarCollapse ml-2 mr-2" data-placement="bottom" aria-label="Abrir menu lateral">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="feather feather-list">
                            <line x1="8" y1="6" x2="21" y2="6"></line>
                            <line x1="8" y1="12" x2="21" y2="12"></line>
                            <line x1="8" y1="18" x2="21" y2="18"></line>
                            <line x1="3" y1="6" x2="3" y2="6"></line>
                            <line x1="3" y1="12" x2="3" y2="12"></line>
                            <line x1="3" y1="18" x2="3" y2="18"></line>
                        </svg>
                    </a>

                    <span class="text company-text d-none d-md-inline ml-2">
                        CARNICERIA FRANCO
                    </span>
                </div>

                <div class="ml-auto d-flex align-items-center user-profile-header">
                    <div class="dropdown zero-stock-header mr-3">
                        <button class="btn btn-outline-danger btn-sm zero-stock-trigger" type="button"
                                id="zeroStockDropdown" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false"
                                title="Productos sin stock">
                            <i class="fas fa-box-open"></i>
                            <span class="zero-stock-label ml-1">Sin stock</span>
                            <span class="badge badge-danger ml-1">{{ $outOfStockProducts->count() }}</span>
                        </button>
                        <div class="dropdown-menu dropdown-menu-right zero-stock-dropdown-menu" aria-labelledby="zeroStockDropdown">
                            <div class="zero-stock-dropdown-title">
                                <div>
                                    <strong>Productos sin stock</strong>
                                    <small class="d-block text-muted">Requieren una entrada de inventario</small>
                                </div>
                                <span class="badge badge-danger">{{ $outOfStockProducts->count() }}</span>
                            </div>
                            <div class="zero-stock-scroll">
                                @forelse($outOfStockProducts as $stockProduct)
                                    <a href="{{ url('admin/inventario') }}" class="dropdown-item zero-stock-item">
                                        <span class="zero-stock-icon"><i class="fas fa-cube"></i></span>
                                        <span class="zero-stock-info">
                                            <strong>{{ $stockProduct->nombre }}</strong>
                                            <small>{{ $stockProduct->codigo ?: 'Sin código' }} · {{ ucfirst($stockProduct->unidad_venta) }}</small>
                                        </span>
                                        <span class="badge badge-light">0</span>
                                    </a>
                                @empty
                                    <div class="zero-stock-empty">
                                        <i class="fas fa-check-circle text-success"></i>
                                        <span>Todos los productos tienen existencia.</span>
                                    </div>
                                @endforelse
                            </div>
                            <a href="{{ url('admin/inventario') }}" class="zero-stock-footer">Ir a Inventario</a>
                        </div>
                    </div>

                    @if($userImageUrl)
                        <img class="img-fluid rounded-circle mr-2" src="{{ $userImageUrl }}" alt="avatar">
                    @else
                        <div class="avatar-fallback rounded-circle mr-2">
                            <i class="fas fa-user"></i>
                        </div>
                    @endif

                    <div class="d-flex flex-column align-items-end mr-3">
                        <div class="d-flex align-items-center">
                            <span class="font-weight-bold user-name">{{ $user->name ?? 'Usuario' }}</span>
                            @if($hasTwoFactorEnabled)
                                <span title="2FA habilitada" class="twofa-badge">
                                    <i class="fas fa-check-circle"></i>
                                </span>
                            @endif
                        </div>
                        <span class="text-muted user-role">{{ $user->profile ?? 'Perfil' }}</span>
                    </div>

                    <form action="{{ route('logout') }}" method="POST" class="ml-2 mb-0" id="logoutFormHeader">
                        @csrf
                        <button type="submit" class="btn btn-outline-danger btn-sm d-flex align-items-center btn-logout-tpp" id="btnLogoutTpp">
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="feather feather-log-out mr-1">
                                <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
                                <polyline points="16 17 21 12 16 7"></polyline>
                                <line x1="21" y1="12" x2="9" y2="12"></line>
                            </svg>
                            <span class="btn-logout-text">Cerrar sesion</span>
                            <span class="btn-logout-loader d-none ml-2">
                                <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>
                                Cerrando...
                            </span>
                        </button>
                    </form>
                </div>
            </div>

            <div wire:offline class="alert alert-danger text-center w-100 mt-1 mb-0 py-1">
                <strong>Sin conexion.</strong>
            </div>
        </div>
    </header>
</div>

<script src="{{ asset('assets/js/livewire/header-logout.js') }}" nonce="{{ $nonce }}"></script>

@if($hasTwoFactorEnabled && $canDisableTwoFactor)
<div class="modal fade" id="twoFactorStatusModal" tabindex="-1" role="dialog" aria-labelledby="twoFactorStatusModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="twoFactorStatusModalLabel">
                    <i class="fas fa-shield-alt text-success"></i> Autenticacion de Dos Factores
                </h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="text-center mb-3">
                    <i class="fas fa-shield-alt text-success" style="font-size: 28px;"></i>
                    <h6 class="mt-2">2FA esta habilitada</h6>
                    <p class="text-muted">Tu cuenta esta protegida con autenticacion de dos factores.</p>
                </div>

                <div class="alert alert-warning">
                    <strong><i class="fas fa-exclamation-triangle"></i> Advertencia:</strong>
                    Deshabilitar 2FA reducira la seguridad de tu cuenta.
                </div>

                <form action="{{ route('2fa.disable') }}" method="POST" id="disable2faForm">
                    @csrf
                    <div class="form-group">
                        <label for="current_password_modal">Contrasena Actual</label>
                        <input type="password" class="form-control" id="current_password_modal" name="password" required>
                    </div>

                    <div class="form-group mb-0">
                        <label for="disable_code_modal">Codigo de Google Authenticator</label>
                        <input type="text" class="form-control text-center" id="disable_code_modal" name="code"
                               maxlength="6" pattern="[0-9]{6}" inputmode="numeric" placeholder="123456" required>
                        <small class="form-text text-muted">Ingresa el codigo de 6 digitos de tu app Authenticator</small>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                <button type="submit" form="disable2faForm" class="btn btn-danger">
                    <i class="fas fa-shield-alt"></i> Deshabilitar 2FA
                </button>
            </div>
        </div>
    </div>
</div>
@endif

